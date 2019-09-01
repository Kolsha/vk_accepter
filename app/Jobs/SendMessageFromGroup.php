<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;

class SendMessageFromGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * [
     * 'group_id',
     * 'user_id',
     * 'message',
     * 'access_token'
     * 'attachment' - optional
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
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {
        $msg_from_group = $vk->messages()->isMessagesFromGroupAllowed(
            $this->payload['access_token'],
            Arr::only($this->payload, ['group_id', 'user_id'])
        );

        if (empty($msg_from_group['is_allowed'])) {
            return;

        }

        $vk->messages()->send(
            $this->payload['access_token'],
            Arr::except($this->payload, ['access_token'])
        );

    }
}
