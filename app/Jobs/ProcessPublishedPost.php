<?php

namespace App\Jobs;

use App\Post;
use App\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Intervention\Image\Facades\Image;
use VK\TransportClient\Curl\CurlHttpClient;

class ProcessPublishedPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    private $group;


    private const NEXT_TASK_DELAY_MIN = 15;
    private const NEXT_TASK_DELAY_MAX = 60;

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
     * @throws \VK\TransportClient\TransportRequestException
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {


        $pobj = json_decode($this->post->object);

        $this->group =& $this->post->group;

        $access_token = $this->group->vk_user_access_token;


        $post_request = array(
            'owner_id' => '-' . $this->group->vk_group_id,
            'message' => $pobj->text,
            'attachments' => array(),
            'signed' => ((isset($pobj->signer_id)) ? 1 : 0),
            'post_id' => $pobj->id,

        );

        $has_poll = false;
        $url = null;
        $photos = array();
        $urls = array();

        $post_watermark_filename = public_path('watermarks/' . $this->group->post_watermark_filename);
        $need_update_photos = $this->group->post_flag_watermark &&
            file_exists($post_watermark_filename);


//        dd($need_update_photos);
        if (isset($pobj->attachments)) {
            foreach ($pobj->attachments as $att) {
                switch ($att->type) {
                    case 'link':
                        $url = $att->link->url;
                        break;
                    default:

                        $has_poll = $has_poll || ($att->type == 'poll');

                        $att_string = $att->type . $att->{$att->type}->owner_id . '_' . $att->{$att->type}->id;


                        if (isset($att->{$att->type}->access_key)) {
                            $att_string .= '_' . $att->{$att->type}->access_key;
                        }

                        if ($need_update_photos) {

                            $photo_url = get_photo_url($att->photo);
                            if (!empty($photo_url)) {
                                $photos[] = [
                                    'url' => $photo_url,
                                    'text' => $att->photo->text,
                                    'att' => $att_string
                                ];

                                continue;
                            }
                        }

                        $post_request['attachments'][] = $att_string;
                }
            }
        }

        { // Urls block
            if ($this->group->post_flag_upd_url && !empty($post_request['message'])) {


                if (preg_match_all(
                    '/((https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?)/i',
                    $post_request['message'],
                    $urls)) {

                    $urls = $urls[0];

                } else {
                    $urls = array();
                }

                if (!empty($url)) {
                    $urls[] = $url;
                }

                foreach ($urls as $key => $val) {
                    $url = $this->updateUrl($val, $this->group->update_url_mask);
                    $post_request['message'] = str_replace(
                        $val,
                        $url,
                        $post_request['message']);
                }


            }

            if (!empty($url)) {
                $post_request['attachments'][] = $url;
            }
            unset($urls);// Urls block
        }



        { //photos block
            $http_client = new CurlHttpClient(10);

            foreach ($photos as $k => $v) {

                $tmp_name = storage_path($k . '.jpg');

                $tmp_data = $http_client->get($v['url'], [])->getBody();
                if (empty($tmp_data)) {
                    $post_request['attachments'][] = $v['att'];
                    continue;
                }

                if (file_put_contents($tmp_name, $tmp_data)) {

                    $img = Image::make($tmp_name);


                    $watermark = Image::make($post_watermark_filename)->widen(
                        intval($img->width() * 0.4)
                    )->opacity(70);

                    $img->insert($watermark, 'bottom-right', 5, 5);

                    $img->save();

                    if (empty($v['text'])) {
                        $v['text'] = $this->group->title;
                    }

                    $v['text'] = '@club' . $this->group->vk_group_id . '(' . $v['text'] . ')';
                    $tmp_att = $this->uploadWallPhoto($vk, $this->group, $tmp_name, $v['text']);
                    if (!empty($tmp_att)) {
                        $post_request['attachments'][] = $tmp_att;
                    } else {
                        $post_request['attachments'][] = $v['att'];
                    }
                    @unlink($tmp_name);
                }
            }
            unset($http_client);
            unset($watermark);
            unset($photos); //photos block
        }


        $poll_vars = $this->group->poll_vars;
        $poll_vars = explode('|', $poll_vars);

        $need_add_poll = $this->group->post_flag_poll && !$has_poll
            && !empty($this->group->poll_title) && !empty($poll_vars);

        $poll = null;
        if ($need_add_poll) {

            $poll = $this->newPoll($vk, $this->group,
                $this->group->poll_title, $poll_vars);
            if (!empty($poll)) {
                $poll_att = 'poll' . $poll['owner_id'] . '_' . $poll['id'];
                array_unshift($post_request['attachments'], $poll_att);
            }
        }


        $post_request['attachments'] = array_slice($post_request['attachments'], 0, 10);
        $post_request['attachments'] = implode(',', $post_request['attachments']);


        if ($this->group->post_flag_add_text) {
            $post_request['message'] .= PHP_EOL . mask_text($this->group->post_add_text);
        }


        $vk->wall()->edit($access_token, $post_request);

        $this->post->post_type = 'updated';
        $this->post->save();

        $need_comment = $this->group->post_flag_comment && !empty($this->group->comment_text_mask)
            && isset($pobj->signer_id);

        if ($need_comment) {

            $user = $vk->users()->get($access_token, ['user_ids' => $pobj->signer_id]);
            $user = $user[array_key_first($user)];

            $comment_text = replace_user_mention($this->group->comment_text_mask, $user);

            PostComment::dispatch(
                [
                    'type' => 'post',
                    'group_id' => $this->group->vk_group_id,
                    'id' => $this->post->post_id,
                    'message' => $comment_text,
                    'access_token' => $access_token
                ]
            )->delay(now()->addSeconds(rand(self::NEXT_TASK_DELAY_MIN, self::NEXT_TASK_DELAY_MAX)));
        }

        if (!empty($poll)) {

            $poll_req = [
                'poll_id' => $poll['id'],
                'owner_id' => $poll['owner_id'],
                'access_token' => $access_token,
                'answer_ids' => Arr::random($poll['answers'])['id']
            ];

            PollAddVote::dispatch($poll_req)
                ->delay(now()->addSeconds(rand(self::NEXT_TASK_DELAY_MIN, self::NEXT_TASK_DELAY_MAX)));

        }


    }

    /**
     * @param \VK\Client\VKApiClient $vk
     * @param Group $group
     * @param string $title
     * @param array $answers
     * @param int $is_anonymous
     * @return mixed|null
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    private function newPoll(\VK\Client\VKApiClient $vk, Group $group,
                             $title, $answers = ['yes', 'no'], $is_anonymous = 1)
    {
        $poll_request = [
            'question' => $title,
            'add_answers' => json_encode($answers),
            'owner_id' => '-' . $group->vk_group_id,
            'is_anonymous' => $is_anonymous
        ];


        $poll_res = $vk->polls()->create(
            $this->group->vk_user_access_token,
            $poll_request
        );

        if (!empty($poll_res['id'])) {
            return $poll_res;
        }

        return null;
    }

    /**
     * @param \VK\Client\VKApiClient $vk
     * @param Group $group
     * @param string $file_path
     * @param string $caption
     * @return string|null
     * @throws \VK\Exceptions\Api\VKApiParamAlbumIdException
     * @throws \VK\Exceptions\Api\VKApiParamHashException
     * @throws \VK\Exceptions\Api\VKApiParamServerException
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    private function uploadWallPhoto(\VK\Client\VKApiClient $vk, Group $group, string $file_path, string $caption = '')
    {


        $res = $vk->photos()->getWallUploadServer(
            $this->group->vk_user_access_token,
            [
                'group_id' => $group->vk_group_id
            ]
        );


        if (empty($res['upload_url'])) {
            return null;
        }

        $upload = $vk->getRequest()->upload($res['upload_url'], 'photo', $file_path);


        if (empty($upload['photo']) || $upload['photo'] == '[]') {
            return null;
        }

        if (!empty($caption)) {
            $caption = substr($caption, 0, 2048);
        }

        $photo_save = array(
            'group_id' => $group->vk_group_id,
            'photo' => $upload['photo'],
            'server' => $upload['server'],
            'hash' => $upload['hash'],
            'caption' => $caption
        );

        $save_res = $vk->photos()->saveWallPhoto(
            $this->group->vk_user_access_token,
            $photo_save
        );
        $save_res = $save_res[array_key_first($save_res)];

        if (!empty($save_res['id'])) {
            return 'photo' . $save_res['owner_id'] . '_' . $save_res['id'];
        }


        return null;
    }


    /**
     * @param string $url
     * @param string $mask
     * @return string
     */
    private function updateUrl($url, $mask)
    {
        // TODO
        return $mask;
    }
}
