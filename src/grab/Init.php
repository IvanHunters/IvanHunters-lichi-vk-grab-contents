<?php


namespace Lichi\Grab\Post;


use Lichi\Vk\Sdk\ApiProvider;
use RuntimeException;

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

            $calcCountForOffset = $countPost - (int) (($countPost / 3) /3);
            if ($calcCountForOffset < $count)
            {
                $calcCountForOffset = $countPost;
            }else{
                $calcCountForOffset -= $count;
            }

            $configArray[$ownerId]['offset'] = $calcCountForOffset;
            $configArray[$ownerId]['start_offset'] = $countPost;
        }else{
            $countPost = $this->provider->wall->getCountPostsFor($ownerId);
            if($countPost != $configArray[$ownerId]['start_offset'])
            {
                $startOffset = $configArray[$ownerId]['start_offset'];
                $diffOffset = $countPost - $startOffset;
                $sumOldOffsetAndDiff = $configArray[$ownerId]['offset'] + $diffOffset;
                $configArray[$ownerId]['offset'] = ($configArray[$ownerId]['offset'] + $diffOffset)  - $count;
                $configArray[$ownerId]['start_offset'] = $countPost;
            }else{
                $configArray[$ownerId]['offset'] -= $count;
            }
        }
        if($configArray[$ownerId]['offset'] < 0)
            throw new RuntimeException("Group [{$ownerId}] was cleaner");
        if($configArray) {
            file_put_contents("checkingData.json", json_encode($configArray));
        }

        return $configArray[$ownerId]['offset'];
    }
}