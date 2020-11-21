<?php


namespace Lichi\Grab\Post;


use Lichi\Vk\Sdk\ApiProvider;

class Init implements \Lichi\Grab\Init
{

    private ApiProvider $provider;

    /**
     * Init constructor.
     * @param ApiProvider $apiProvider
     */
    public function __construct(ApiProvider $apiProvider)
    {
        $this->provider = $apiProvider;
    }

    /**
     * @param int $ownerId
     * @param int $count
     * @return array
     */
    public function getPostsFor(int $ownerId, int $count): array
    {
        $post = [];
        $offset = $this->getStartPost($ownerId, $count);

        $postsInfo = $this->provider->wall->get($ownerId, $count, $offset);
        foreach ($postsInfo['items'] as $postInfo) {
            $postObject = new Post($postInfo);
            if (Validator::checkPost($postObject)) {
                $post[] = $postObject;
            }else{
                $a = 10;
            }
        }
        return $post;
    }

    /**
     *
     * @param int $ownerId
     * @param int $count
     * @return int
     */
    private function getStartPost(int $ownerId, int $count): int
    {
        $configInfo = file_get_contents("checkingData.json");
        if(!$configInfo)
        {
            $configInfo = "[]";
        }
        $configArray = json_decode($configInfo, true);
        if (!isset($configArray[$ownerId]))
        {
            $countPost = $this->provider->wall->getCountPostsFor($ownerId);
            $configArray[$ownerId] = $countPost - ((int) ($countPost / 3) * 2) - $count;
        }else{
            $configArray[$ownerId]-=$count;
        }
        file_put_contents("checkingData.json", json_encode($configArray));

        return $configArray[$ownerId];
    }
}