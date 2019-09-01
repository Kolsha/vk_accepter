<?php

namespace App\Jobs;

use App\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessUserJoined implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected $user_id;
    protected $group_id;


    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param $user_id
     * @param $group_id
     */
    public function __construct($user_id, $group_id)
    {
        $this->user_id = $user_id;
        $this->group_id = $group_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $posts = Post::where('post_type', 'to_delete')
            ->where('group_id', $this->group_id)
            ->where('user_id', $this->user_id)
            ->get();

        foreach ($posts as $post) {
            ProcessSuggestedPost::dispatchNow($post);
        }
    }
}
