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
    } else
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


// TODO : move helpers
