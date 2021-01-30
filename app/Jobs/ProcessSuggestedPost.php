<?php

namespace App\Jobs;

use App\Post;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class ProcessSuggestedPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    private $group;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param Post $post
     */
    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    /**
     * Execute the job.
     *
     * @param \VK\Client\VKApiClient $vk
     * @return void
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {

        // TODO: https://laravel.com/docs/5.8/eloquent-mutators#array-and-json-casting
        $pobj = json_decode($this->post->object);

        $this->group =& $this->post->group;


        $access_token = $this->group->vk_user_access_token;

        $this->post->user_id = $pobj->from_id;

        $code = user_is_member_code(
            $pobj->from_id, // suggest: from_id, posted : signer_id
            $this->group->vk_group_id
        );

        /** @var array $exec_res
         * user => array (
         *      id,
         *      first_name,
         *      last_name,
         *      ?deactivated
         * ),
         * is_member => [0,1]
         * )
         */
        $exec_res = $vk->getRequest()->post('execute', $access_token,
            [
                'code' => $code
            ]);


        if (!$this->group->allow_with_empty_text &&
            empty($pobj->text)) {

            $this->markToDelete();
            return;
        }


        if (!empty($this->group->post_ban_keys) &&
            preg_match(
                '#(' . $this->group->post_ban_keys . ')#siu',
                $pobj->text,
                $ban_res) != false) {

            $this->sendNotification(__('notification.banned'), $exec_res['user']);
            $this->markToDelete();
            return;
        }

        if (!$this->group->allow_from_not_alive &&
            isset($exec_res['user']['deactivated'])) {

            $this->markToDelete();
            return;
        }

        if (!$this->group->allow_from_not_member && empty($exec_res['is_member'])) {


            $this->sendNotification(__('notification.not_subscribed'), $exec_res['user']);

            $this->markToDelete();
            return;
        }


        $post_request = array(
            'owner_id' => '-' . $this->group->vk_group_id,
            'from_group' => 1,
            'message' => $pobj->text,
            'attachments' => array(),
            'signed' => 1,
            'post_id' => $pobj->id,

        );
        $is_youla_ad = false;
        if (isset($pobj->attachments)) {
            foreach ($pobj->attachments as $att) {
                switch ($att->type) {
                    case 'link':
                        $url = $att->link->url;
                        $post_request['attachments'][] = $url;
                        $is_youla_ad = preg_match('/youla.ru/i', $url);
                        break;
                    default:

                        $att_string = $att->type . $att->{$att->type}->owner_id . '_' . $att->{$att->type}->id;

                        if (isset($att->{$att->type}->access_key)) {
                            $att_string .= '_' . $att->{$att->type}->access_key;
                        }

                        $post_request['attachments'][] = $att_string;
                }
            }
        }

        if ($is_youla_ad && ((rand() % 50) != 37)) {
            $this->markToDelete();
            return;
        }

        $post_request['attachments'] = array_slice($post_request['attachments'], 0, 10);
        $post_request['attachments'] = implode(',', $post_request['attachments']);

        if (preg_match(
                __('anon.regexp_pattern'),
                $pobj->text,
                $anon_res) != false) {
            $post_request['signed'] = ($anon_res[0] == __('anon.true_option')) ? 0 : 1;

            $post_request['message'] = str_replace($anon_res[0], '', $post_request['message']);

        }


        $post_res = $vk->wall()->post($access_token, $post_request);


        $this->post->post_type = 'to_update';
        $this->post->post_id = $post_res['post_id'];
        $this->post->save();


    }


    private function markToDelete()
    {
        $this->post->post_type = 'to_delete';
        $this->post->save();
    }

    private function sendNotification($text, $user)
    {

        //TODO: maybe private msg

        if (!$this->group->use_notification || empty($this->group->notification_topic)) {
            return;
        }

        $text = replace_user_mention($text, $user);

        $notification = [
            'type' => 'topic',
            'group_id' => $this->group->vk_group_id,
            'id' => $this->group->notification_topic,
            'message' => $text,
            'access_token' => $this->group->vk_user_access_token
        ];

        PostComment::dispatch($notification);
    }


}
