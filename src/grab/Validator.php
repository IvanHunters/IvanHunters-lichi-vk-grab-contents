<?php


namespace Lichi\Grab\Post;

class Validator implements \Lichi\Grab\Validator
{

    /**
     * @param Post $post
     * @return bool
     */
    public static function checkPost(Post $post): bool
    {
        if (count($post->images) == 0) return false;
        if ($post->textPost == "" && count($post->images['maxSizeImageUrl']) == 0) return false;
        if (preg_match("/http?[^ ]+/imu", $post->textPost)) return false;
        if (preg_match("/\[[^|]+|[^]]+]/imu", $post->textPost)) return false;
        if (preg_match("/#[^\s]+/imu", $post->textPost)) return false;

        return true;
    }
}