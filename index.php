<?php
include "vendor/autoload.php";

use Lichi\Grab\Post\Init;
use Lichi\Vk\Sdk\ApiProvider;

$apiProvider = new ApiProvider(TOKEN);
$grabber = new Init($apiProvider);
$postsAfterGrabbing = $grabber->getPostsFor(OWNER_ID, COUNT);