<?php
include "vendor/autoload.php";

use Lichi\Grab\Post\Init;
use Lichi\Vk\Sdk\ApiProvider;

//$apiProvider = new ApiProvider(TOKEN);
//$grabber = new Init($apiProvider);
//$postsAfterGrabbing = $grabber->getPostsFor(OWNER_ID, COUNT);

$apiProvider = new ApiProvider("b4e7726db4e7726db4e7726dedb494c41abb4e7b4e7726debaa568e625ccf0b1a0b662a");
$grabber = new Init($apiProvider);
$postsAfterGrabbing = $grabber->getPostsFor(-62247825, 10);