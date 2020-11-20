<?php


namespace Lichi\Grab;


interface Validator
{
    public static function checkPost(\Lichi\Grab\Post\Post $post): bool;
}