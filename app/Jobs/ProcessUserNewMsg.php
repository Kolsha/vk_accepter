<?php

namespace App\Jobs;

use App\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Group;

class ProcessUserNewMsg implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $group;
    protected $msg;
    protected $vk;
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
        // TODO: check if is needed
        $this->vk = $vk;
        $command = 'show_posts';
        if (!empty($this->msg) && !empty($this->msg['payload'])) {
            $this->msg['payload'] = json_decode($this->msg['payload'], true);
            $command = $this->msg['payload']['cmd'];
        }

        $answer = [
            'group_id' => $this->group->vk_group_id,
            'user_id' => $this->msg['from_id'],
            'access_token' => $this->group->vk_user_access_token
        ];

        switch ($command) {
            case 'show_posts':
                $answer = array_merge($answer, $this->show_posts());
                break;

            case 'confirm_delete_post':
                $answer = array_merge($answer, $this->confirm_delete_post());
                break;

            case 'delete_post':
                $answer = array_merge($answer, $this->delete_post());
                break;
            default:
                throw new \Exception('invalid command: ' . $command);
        }

        SendMessageFromGroup::dispatch($answer);
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
        $first_row = [
            $my_posts_btn
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

        $dialog = generate_dialog('Вы действительно хотите удалить пост?', $yes_payload, $no_payload);

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
}
