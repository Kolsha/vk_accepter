<?php

namespace App\Http\Controllers;

use App\Group;
use App\Jobs\ProcessPublishedPost;
use App\Jobs\ProcessUserJoined;
use App\Post;
use App\Jobs\ProcessSuggestedPost;


use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Http\Request;


class VKCallBackController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
//        dd($request);
        $event = json_decode($request->getContent());
        if (empty($event)) {
            throw new BadRequestHttpException('empty event');
        }
        $group = Group::where('vk_group_id', $event->group_id)->firstOrFail();

        if (!$group->enabled) {
            throw new BadRequestHttpException('group is disabled');
        }


        $group->vk_secret = preg_replace('/\s+/', '', $group->vk_secret);
        if (!empty($group->vk_secret) &&
            !empty($event->vk_secret) &&
            $group->vk_secret !== $event->secret) {
            throw new BadRequestHttpException('verification failed, secret mismatched');
        }

        switch ($event->type) {
            case 'confirmation':
                return $group->vk_confirmation;

            case 'wall_post_new':
                $post_type = Post::postTypeMapper($event->object->post_type);
                if (empty($post_type)) {
                    break;
                }


                $post = Post::updateOrCreate(
                    ['group_id' => $event->group_id, 'post_id' => $event->object->id],
                    [
                        'post_type' => $post_type,
                        'object' => json_encode($event->object)
                    ]
                );
//                if ($post->post_type != $post_type) {
//
//                    return response('not ok'); // TODO
//                }

                switch ($post_type) {
                    case 'suggested':

                        ProcessSuggestedPost::dispatch($post); // TODO:
                        break;
                    case 'to_update':

                        ProcessPublishedPost::dispatch($post); // TODO:
                        break;
                    default:
                        break;
                }


                break;


            case 'group_join':
                ProcessUserJoined::dispatch($event->object->user_id);//todo
                break;

            default:
                throw new BadRequestHttpException('unhandled event type, write admin');
        }

        return response('ok');
    }


}
