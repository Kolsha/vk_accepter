<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Group;

class ProcessAdminMsg implements ShouldQueue
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

    private static function getPostId($msg, $group_id)
    {
        $post_id = null;
        if (preg_match('/wall-(\d+)_(\d+)/', $msg['text'], $matches)) {
            if ($group_id == $matches[1])
                $post_id = $matches[2];
        }

        foreach ($msg['attachments'] as $att) {
            $needed_type = 'wall';
            if ($att->type != $needed_type)
                continue;

            $wall = $att->{$needed_type};
            if ($group_id != $wall->from->id)
                continue;
            $post_id = $wall->id;
        }

        return $post_id;
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
        $access_token = $this->group->vk_user_access_token;


        $command = '';
        if (!empty($this->msg) && !empty($this->msg['payload'])) {
            $this->msg['payload'] = json_decode($this->msg['payload'], true);
            $command = $this->msg['payload']['cmd'];
        }


        if (empty($command)) {
            $post_id = self::getPostId($this->msg, $this->group->vk_group_id);
            if (!empty($post_id)) {
                $this->deleteAndBan($post_id);
                return;
            }
            return;
        }

        $command_result = [
            'message' => 'if u see this message then something wrong'
        ];


        switch ($command) {
            case 'restore':
                $command_result = $this->restorePost($this->msg['payload']);
                break;
            case 'unban':
                $command_result = $this->unbanUser($this->msg['payload']);
                break;
            case 'cancel':
                $this->restorePost($this->msg['payload']);
                $this->unbanUser($this->msg['payload']);

                $command_result = [
                    'message' => 'Все действия отменены.'
                ];
                break;
            default:
                throw new \Exception('invalid command: ' . $command);
        }

        $answer = [
            'v' => '5.124',
            'group_id' => $this->group->vk_group_id,
            'user_id' => $this->msg['from_id'],
            'access_token' => $access_token,

        ];
        $answer = array_merge($answer, $command_result);
        SendMessageFromGroup::dispatch($answer);
    }


    private function getUserId($post_id)
    {
        $access_token = $this->group->vk_user_access_token;
        $post_res = $this->vk->wall()->getById(
            $access_token,
            [
                'posts' => "-{$this->group->vk_group_id}_{$post_id}"
            ]
        );
        if (count($post_res) < 1)
            return null;

        $post = array_shift($post_res);

        $type2field = ['post' => 'signer_id', 'suggest' => 'from_id'];

        if (array_key_exists($post['post_type'], $type2field)) {
            $field = $type2field[$post['post_type']];

            if (array_key_exists($field, $post))
                return $post[$field];
        }

        return null;

    }

    private function banUser($user_id)
    {
        $access_token = $this->group->vk_user_access_token;
        $this->vk->groups()->ban($access_token,
            [
                'group_id' => $this->group->vk_group_id,
                'owner_id' => $user_id,
                'end_date' => strtotime('+1 week'),
                'reason' => 1,
                'comment' => 'Реклама платная. Пишите в сообщения сообщества.',
                'comment_visible' => 1,
            ]
        );
    }

    private function unbanUser($payload)
    {
        $user_id = $payload['user_id'];
        $access_token = $this->group->vk_user_access_token;


        try {
            $this->vk->groups()->unban($access_token,
                [
                    'group_id' => $this->group->vk_group_id,
                    'owner_id' => $user_id,
                ]
            );
        } catch (\Exception $e) {
            // in most cases post already deleted
        }
        return ['message' => 'Разбанен'];
    }

    private function restorePost($payload)
    {
        $post_id = $payload['post_id'];
        $access_token = $this->group->vk_user_access_token;
        try {
            $this->vk->wall()->restore($access_token,
                [
                    'owner_id' => '-' . $this->group->vk_group_id,
                    'post_id' => $post_id,
                ]
            );
        } catch (\Exception $e) {
            // in most cases post already deleted
        }
        return ['message' => 'Восстановлен'];
    }

    private function deleteAndBan($post_id)
    {
        $access_token = $this->group->vk_user_access_token;
        $user_id = $this->getUserId($post_id);

        DeletePost::dispatch(
            [
                'owner_id' => '-' . $this->group->vk_group_id,
                'post_id' => $post_id,
                'access_token' => $access_token
            ]
        );

        $buttons = [
            [
                [
                    ['cmd' => 'cancel', 'post_id' => $post_id, 'user_id' => $user_id],
                    'Отмена', 'green'
                ],
                [
                    ['cmd' => 'restore', 'post_id' => $post_id],
                    'Восстановить', 'blue'
                ],
                [
                    ['cmd' => 'unban', 'user_id' => $user_id],
                    'Разбанить', 'white'
                ],
            ]
        ];

        $keyboard = generate_keyboard($buttons, false, true);

        $answer = [
            'v' => '5.124',
            'group_id' => $this->group->vk_group_id,
            'user_id' => $this->msg['from_id'],
            'access_token' => $access_token,
            'message' => "Пост({$post_id}) удален, [id{$user_id}|пользователь] забанен.",
            'keyboard' => $keyboard,

        ];
        $this->banUser($user_id);
        SendMessageFromGroup::dispatch($answer);
    }
}
