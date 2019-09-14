<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserRule extends Model
{

    protected $dateFormat = 'Y-m-d H:i:sO';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'process_published_posts' => true,
        'chatbot_enabled' => true,
    ];

    /**
     * @param int $group_id
     * @param int|array $users_id
     * @return UserRule|null
     */
    public static function getFirstMatchByUserId(int $group_id, $users_id)
    {
        if (!is_array($users_id)) {
            $users_id = [$users_id];
        }


        $rules = self::where('group_id', $group_id)->whereIn('user_id', $users_id)->get();

        foreach ($users_id as $user_id) {
            if (empty($user_id)) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule->user_id == $user_id) {
                    return $rule;
                }
            }
        }

        return null;
    }
}
