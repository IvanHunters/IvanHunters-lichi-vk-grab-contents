<?php


namespace Lichi\Grab;


use Lichi\Vk\Sdk\ApiProvider;

interface Init
{
    /**
     * Init constructor.
     * @param ApiProvider $apiProvider
     */
    public function __construct(ApiProvider $apiProvider);

    /**
     * @param int $ownerId
     * @param int $count
     * @return array
     */
    public function getPostsFor(int $ownerId, int $count): array;
}