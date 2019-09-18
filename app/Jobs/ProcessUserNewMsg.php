<?php

namespace App\Jobs;

use App\Post;
use App\UserRule;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Group;

// TODO: text in lang files

class ProcessUserNewMsg implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $group;
    protected $msg;
    protected $vk;
    protected $user_rules;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;


    /**
     * Create a new job instance.
     *
     * @param Group $group
     * @param Object $msg
     */
    public function __construct(Group $group, $msg)
    {
        $this->group = $group;
        $this->msg = (array)$msg;
    }

    /**
     * Execute the job.
     *
     * @param \VK\Client\VKApiClient $vk
     * @return void
     * @throws \Exception
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {
        $this->vk = $vk;


        $command = '';
        if (!empty($this->msg) && !empty($this->msg['payload'])) {
            $this->msg['payload'] = json_decode($this->msg['payload'], true);
            $command = $this->msg['payload']['cmd'];
        }

        $answer = [
            'group_id' => $this->group->vk_group_id,
            'user_id' => $this->msg['from_id'],
            'access_token' => $this->group->vk_user_access_token
        ];


        $this->user_rules = UserRule::getFirstMatchByUserId($this->group->vk_group_id, $this->msg['from_id']);
        if (empty($this->user_rules)) {
            $this->user_rules = UserRule::create([
                'group_id' => $this->group->vk_group_id,
                'user_id' => $this->msg['from_id']]);

        }

        if (empty($this->user_rules->chatbot_enabled)) {
            if (!empty($command)) {
                $this->user_rules->chatbot_enabled = true;
                $command = 'show_posts';
            } else {
                return;
            }
        }

        if (empty($command)) {
            $command = 'show_posts';
        }

        $command_result = [
            'message' => 'if u see this message then something wrong'
        ];
        switch ($command) {
            case 'show_posts':
                $command_result = $this->show_posts();
                break;

            case 'confirm_delete_post':
                $command_result = $this->confirm_delete_post();
                break;

            case 'delete_post':
                $command_result = $this->delete_post();
                break;

            case 'disable_chatbot':
                $command_result = $this->disable_chatbot();
                break;


            case 'enable_chatbot':
                $command_result = $this->enable_chatbot();
                break;

            default:
                throw new \Exception('invalid command: ' . $command);
        }

        $answer = array_merge($answer, $command_result);

        SendMessageFromGroup::dispatch($answer);

        $this->user_rules->save();
    }

    private function show_posts()
    {
        $per_line = 2;
        $free_line = 3;
        $page = 0;
        if (isset($this->msg['payload']['page'])) {
            $page = intval($this->msg['payload']['page']);
        }

        $posts = Post::where(
            [
                ['group_id', '=', $this->group->vk_group_id],
                ['user_id', '=', $this->msg['from_id']],


            ]
        )
            ->whereIn(


                'post_type', ['to_update', 'updated']


            )
            ->orderBy('post_id', 'desc')
            ->simplePaginate($per_line * $free_line, ['*'], 'page', $page); // todo orderby time/id

        $my_posts_btn = [['cmd' => 'show_posts', 'page' => 1], 'Мои посты', 'green'];
        $disable_chatbot_btn = [['cmd' => 'disable_chatbot'], 'Отключить бота', 'red'];

        $first_row = [
            $my_posts_btn,
            $disable_chatbot_btn
        ];

        if (!$posts->onFirstPage()) {
            $first_row[] = [['cmd' => 'show_posts', 'page' => $posts->currentPage() - 1], '< туда', 'blue'];
        }

        if ($posts->hasMorePages()) {
            $first_row[] = [['cmd' => 'show_posts', 'page' => $posts->currentPage() + 1], 'сюда >', 'blue'];
        }

        $buttons = [$first_row];

        $row = [];
        $i = 0;
        foreach ($posts->items() as $post) {
            $pobj = json_decode($post->object);

            $label = strval($i + 1);

            if (!empty($pobj->text)) {
                $label = substr($pobj->text, 0, 40); // 40 comes from vk api exception
            }

            $btn = [
                [
                    'cmd' => 'confirm_delete_post',
                    'post_id' => $post->post_id,
                    'page' => $posts->currentPage(),
                ],
                $label,
                'white'
            ];

            $row[] = $btn;

            $i++;
            if (($i % $per_line) == 0) {
                $buttons[] = $row;
                $row = [];
            }


        }

        if (($i % $per_line) != 0) {
            $buttons[] = $row;
        }


        $keyboard = generate_keyboard($buttons);


        return [

            'message' => 'Ваши посты',
            'keyboard' => $keyboard,
        ];


    }

    private function confirm_delete_post()
    {
        $post = Post::where(
            [
                ['group_id', '=', $this->group->vk_group_id],
                ['user_id', '=', $this->msg['from_id']],
                ['post_id', '=', $this->msg['payload']['post_id']]


            ]
        )->firstOrFail();

        $yes_payload = [
            'cmd' => 'delete_post',
            'post_id' => $this->msg['payload']['post_id'],
            'page' => $this->msg['payload']['page'],
        ];

        $no_payload = [
            'cmd' => 'show_posts',
            'page' => $this->msg['payload']['page'],
        ];

        $dialog = generate_dialog('Вы действительно хотите удалить пост?', $yes_payload, $no_payload, 'Удалить пост?');

        $dialog['attachment'] = $post->attachment;

        return $dialog;


    }

    private function delete_post()
    {

        $post = Post::where(
            [
                ['group_id', '=', $this->group->vk_group_id],
                ['user_id', '=', $this->msg['from_id']],
                ['post_id', '=', $this->msg['payload']['post_id']]


            ]
        )->firstOrFail();


        DeletePost::withChain([
            new UpdatePostDBStatus($post, 'deleted')

        ])
            ->dispatch(
                [
                    'owner_id' => '-' . $this->group->vk_group_id,
                    'post_id' => $post->post_id,
                    'access_token' => $this->group->vk_user_access_token
                ]
            );

        $answer = $this->show_posts();

        $answer['message'] = 'Пост будет скоро удален!';

        return $answer;
    }

    private function disable_chatbot()
    {
        $this->user_rules->chatbot_enabled = false;

        $my_posts_btn = [['cmd' => 'enable_chatbot'], 'Включить бота', 'green'];
        $first_row = [
            $my_posts_btn
        ];
        $buttons = [$first_row];

        $keyboard = generate_keyboard($buttons);


        return [

            'message' => 'Бот отключен',
            'keyboard' => $keyboard,
        ];
    }

    private function enable_chatbot()
    {
        $this->user_rules->chatbot_enabled = false;

        $answer = $this->show_posts();

        $answer['message'] = 'Бот включен!';

        return $answer;
    }
}
