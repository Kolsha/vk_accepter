<?php

namespace App\Jobs;

use App\Group;
use App\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ScanPostToDelete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 200;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        /* original query

SELECT p.*
FROM posts p
JOIN groups g ON g.vk_group_id = p.group_id
WHERE g.enabled = TRUE
AND p.post_type = 'to_delete'
AND (now() AT TIME ZONE 'utc') > ((p.updated_at AT TIME ZONE 'utc') + g.delete_timeout_sec * interval '1 second')

        */

        $posts = Post::join('groups', 'groups.vk_group_id', '=', 'posts.group_id')
            ->whereRaw('groups.enabled = TRUE')
            ->whereRaw("posts.post_type = 'to_delete'")
            ->whereRaw("(now() AT TIME ZONE 'utc') > ((posts.updated_at AT TIME ZONE 'utc') + groups.delete_timeout_sec * interval '1 second')")
            ->limit(50)
            ->get();

        foreach ($posts as $post) {

            DeletePost::dispatch(
                [
                    'owner_id' => '-' . $post->group->vk_group_id,
                    'post_id' => $post->post_id,
                    'access_token' => $post->group->vk_user_access_token
                ]
            );

            UpdatePostDBStatus::dispatchNow(Post::find($post->id), 'deleted');
        }


    }
}
