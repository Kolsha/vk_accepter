<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;

class PollAddVote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * [
     * 'owner_id',
     * 'poll_id',
     * 'answer_ids',
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
     * @throws \VK\Exceptions\Api\VKApiPollsAccessException
     * @throws \VK\Exceptions\Api\VKApiPollsAnswerIdException
     * @throws \VK\Exceptions\Api\VKApiPollsPollIdException
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public function handle(\VK\Client\VKApiClient $vk)
    {
        $poll_vote_request = Arr::except($this->payload, ['access_token']);
        $vk->polls()->addVote($this->payload['access_token'], $poll_vote_request);
    }
}
