<?php

namespace App\Observers;

use App\Group;

class GroupObserver
{
    /**
     * Handle the group "created" event.
     *
     * @param  \App\Group $group
     * @return void
     */
    public function created(Group $group)
    {
        //TODO: job check access token
    }

    /**
     * Handle the group "updating" event.
     *
     * @param  \App\Group $group
     * @return void
     */
    public function updating(Group $group)
    {

        // https://stackoverflow.com/questions/48793257/laravel-check-with-observer-if-column-was-changed-on-update
        if ($group->isDirty('vk_user_access_token')) {
            //TODO: job check access token
        }
    }

    /**
     * Handle the group "deleted" event.
     *
     * @param  \App\Group $group
     * @return void
     */
    public function deleted(Group $group)
    {
        //
    }

    /**
     * Handle the group "restored" event.
     *
     * @param  \App\Group $group
     * @return void
     */
    public function restored(Group $group)
    {
        //
    }

    /**
     * Handle the group "force deleted" event.
     *
     * @param  \App\Group $group
     * @return void
     */
    public function forceDeleted(Group $group)
    {
        //
    }
}
