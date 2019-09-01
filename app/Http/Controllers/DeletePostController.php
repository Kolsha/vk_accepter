<?php

namespace App\Http\Controllers;

use App\Group;
use App\Jobs\DeletePost;
use App\Post;

class DeletePostController extends Controller
{
    public function showConfirmation($group_id, $post_id)
    {
        return view('post.to_delete', [
            'group_id' => $group_id,
            'post_id' => $post_id]);
    }

    public function confirmed($group_id, $post_id)
    {
        $group = Group::where('vk_group_id', $group_id)->firstOrFail();
        DeletePost::dispatch(
            [
                'owner_id' => '-' . $group_id,
                'post_id' => $post_id,
                'access_token' => $group->vk_user_access_token
            ]
        );

        $post = Post::where('group_id', $group_id)->where('post_id', $post_id)->firstOrFail();

        $post->post_type = 'deleted';
        $post->save();

        return view('post.to_delete', [
            'group_id' => $group_id,
            'post_id' => $post_id,
            'deleted' => true]);
    }
}
