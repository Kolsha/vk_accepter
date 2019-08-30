<?php

namespace App\Jobs;

use http\Exception\InvalidArgumentException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;

class PostComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * [
     * 'type' => post|topic,
     * 'group_id',
     * 'id',
     * 'message',
     * 'access_token'
     * ]
     */
    protected $payload;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @param \VK\Client\VKApiClient $vk
     * @return void
     * @throws \VK\Exceptions\Api\VKApiWallAccessAddReplyException
     * @throws \VK\Exceptions\Api\VKApiWallAccessRepliesException
     * @throws \VK\Exceptions\Api\VKApiWallLinksForbiddenException
     * @throws \VK\Exceptions\Api\VKApiWallReplyOwnerFloodException
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {
        //

        if (empty($this->payload['message'])) {
            return;
        }

        $comment_request = Arr::only($this->payload, ['message']);

        $comment_request[$this->payload['type'] . '_id'] = $this->payload['id'];


        switch ($this->payload['type']) {
            case 'post':

                $comment_request['owner_id'] = '-' . $this->payload['group_id'];
                $comment_request['from_group'] = $this->payload['group_id'];

                $vk->wall()->createComment($this->payload['access_token'], $comment_request);
                break;


            case 'topic':

                $comment_request['group_id'] = $this->payload['group_id'];
                $comment_request['from_group'] = 1;

                $vk->board()->createComment($this->payload['access_token'], $comment_request);
                break;

            default:
                throw new InvalidArgumentException('Comment type ' . $this->payload['type'] .
                    ' unsupported');
        }


    }
}
