<?php
/**
 * Created by PhpStorm.
 * User: kolsha
 * Date: 2019-08-27
 * Time: 14:22
 */


/**
 * Mask text like a
 * 'Test square brackets [ab|cd|ee]<br/>
 * Test figure brackets {ab|cd|ee}<br/>
 * Test interval (1-1000)<br/>'
 * @param $text
 * @return string|string[]|null
 */
function mask_text($text)
{
    if (preg_match('/^(.*?)\{([^\{\}\[\]]+)\}(.*?)$/isU', $text, $matches)) {
        $p = explode('|', $matches[2]);
        return mask_text($matches[1] . $p[array_rand($p)] . $matches[3]);
    } elseif (preg_match('/^(.*?)\[\+(.*)\+([^\[\]\{\}]+)\](.*?)$/isU', $text, $matches)) {
        $p = explode('|', $matches[3]);
        shuffle($p);
        $p = implode($matches[2], $p);
        return mask_text($matches[1] . $p . $matches[4]);
    } elseif (preg_match('/^(.*)\[([^\[\]\+\{\}]+)\](.*)$/isU', $text, $matches)) {
        $p = explode('|', $matches[2]);
        shuffle($p);
        $p = implode('', $p);
        return mask_text($matches[1] . $p . $matches[3]);
    } elseif (preg_match('/^(.*)\((\d{1,})\-(\d{1,})\)(.*)$/isU', $text, $matches)) {
        $p = rand((int)$matches[2], (int)$matches[3]);
        return mask_text($matches[1] . $p . $matches[4]);
    }

    return preg_replace('/ {2,}/', ' ', $text);
}

/**
 * @param string $text
 * @param VKUser $user
 * @return mixed
 */
function replace_user_mention($text, $user)
{
    $text = mask_text($text);

    return str_replace(
        array(
            'domain',
            'first_name',
            'last_name'
        )
        ,
        array(
            'id' . $user['id'],
            $user['first_name'],
            $user['last_name']
        ),
        $text);
}


function user_is_member_code($user_id, $group_id)
{
    $code = <<<'CODE'
var user_id = %d;
var group_id = %d;

var users = API.users.get({
    "user_ids": user_id
});

var is_member = API.groups.isMember({
    "group_id": group_id,
    "user_id": user_id
});

return {
    "user": users[0],
    "is_member": is_member
};

CODE;

    return sprintf(
        $code,
        $user_id,
        $group_id
    );
}

function user_is_manager_code($user_id, $group_id)
{
    $code = <<<'CODE'
var user_id = %d;
var group_id = %d;

var users = API.groups.getMembers({
    "group_id": group_id,
    "filter": "managers"
}).items@.id;

return {"is_manager": (users.indexOf( user_id ) != -1)};

CODE;

    return sprintf(
        $code,
        $user_id,
        $group_id
    );
}

function share_post_code($group_id, $post_id)
{
    $code = <<<'CODE'
var user_id = API.account.getProfileInfo().id;

return API.messages.send({
"user_id": user_id,
"attachment": "wall-%d_%d",
random_id: %d
});

CODE;

    return sprintf(
        $code,
        $group_id,
        $post_id,
        $post_id
    );
}


function get_photo_url($photo)
{
    if (empty($photo)) {
        return null;
    }


    $max_area = 0;
    $url = null;

    foreach ($photo->sizes as $size) {
        $area = $size->width * $size->height;
        if ($area < $max_area) {
            continue;
        }
        $max_area = $area;
        $url = $size->url;

    }


    return $url;
}


/**
 * @param string $color
 * @return string
 */
function replace_button_color($color)
{
    $map = [
        'red' => 'negative',
        'green' => 'positive',
        'blue' => 'primary',
        'white' => 'default',
    ];

    return (array_key_exists($color, $map)) ? $map[$color] : end($map);
}


/**
 * @param $keys
 * [
 * [ [row_btn], [row_btn] ..],
 * [ [row_btn], [row_btn] ..],
 * ]
 * row_btn = [ payload, title, color]
 * @param bool $one_time
 * @param bool $inline
 * @return false|string
 */
function generate_keyboard($keys, $one_time = false, $inline = false)
{
    $encode_option = JSON_UNESCAPED_LINE_TERMINATORS |
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_IGNORE;
    $buttons = [];
    $i = 0;
    foreach ($keys as $button_str) {
        $j = 0;
        foreach ($button_str as $button) {

            $buttons[$i][$j]['action']['type'] = 'text';
            if (count($button) > 3) {
                $buttons[$i][$j]['action']['type'] = $button[3];
            }

            if (!empty($button[0])) {
                $buttons[$i][$j]['action']['payload'] = json_encode($button[0], $encode_option);
            }

            $buttons[$i][$j]['action']['label'] = $button[1];

            $buttons[$i][$j]['color'] = replace_button_color($button[2]);

            $j++;
        }
        $i++;
    }
    $buttons = [
        'one_time' => $one_time,
        'buttons' => $buttons,
        'inline' => $inline
    ];

    return json_encode($buttons, $encode_option);

}

function generate_dialog($title, $yes_payload, $no_payload = null, $button_title = '?')
{
    $buttons = [
        [[null, $button_title, 'blue']],

        [
            [$yes_payload, 'Да', 'red'],
            [$no_payload, 'Нет', 'green']
        ]

    ];

    $result = [
        'message' => $title,
        'keyboard' => generate_keyboard($buttons)
    ];

    return $result;
}


// TODO : move helpers
