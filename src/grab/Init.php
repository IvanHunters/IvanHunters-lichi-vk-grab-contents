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
                $post[$postObject->postId] = $postObject;
            }
        }
        return $post;
    }

    private function getDataFromFile(): array
    {
        if(!file_exists("checkingData.json")){
            $configInfo = "[]";
        } else {
            $configInfo = file_get_contents("checkingData.json");
        }
        return json_decode($configInfo, true);
    }

    private function getFirstDataFromSource(int $ownerId, array $configArray, int $count): array
    {
        $countPost = $this->provider->wall->getCountPostsFor($ownerId);

        if($countPost < $count) {
            throw new RuntimeException("Small posts in group");
        }

        $calcCountForOffset = $countPost - (int) (($countPost / 3) / 3);
        if ($calcCountForOffset < $count)
        {
            $calcCountForOffset = $countPost - $count;
        }else{
            $calcCountForOffset -= $count;
        }

        $configArray[$ownerId]['offset'] = $calcCountForOffset;
        $configArray[$ownerId]['start_offset'] = $countPost;

        return $configArray;
    }

    private function getLastDataFromSource(int $ownerId, array $configArray, int $count): array
    {
        $countPost = $this->provider->wall->getCountPostsFor($ownerId);
        if($countPost != $configArray[$ownerId]['start_offset'])
        {
            $startOffset = $configArray[$ownerId]['start_offset'];
            $diffOffset = $countPost - $startOffset;
            $sumOldOffsetAndDiff = $configArray[$ownerId]['offset'] + $diffOffset;
            $configArray[$ownerId]['offset'] = $sumOldOffsetAndDiff  - $count;
            $configArray[$ownerId]['start_offset'] = $countPost;
        }else{
            $configArray[$ownerId]['offset'] -= $count;
        }

        return $configArray;
    }

    /**
     *
     * @param int $ownerId
     * @param int $count
     * @return int
     */
    private function getStartPost(int $ownerId, int $count): int
    {
        $configArray = $this->getDataFromFile();
        if (!isset($configArray[$ownerId]))
        {
            $configArray = $this->getFirstDataFromSource($ownerId, $configArray, $count);
        }else{
            $configArray = $this->getLastDataFromSource($ownerId, $configArray, $count);
        }
        if($configArray[$ownerId]['offset'] < 0)
            throw new RuntimeException("Group [{$ownerId}] was cleaner");
        if($configArray) {
            file_put_contents("checkingData.json", json_encode($configArray));
        }
        file_put_contents("checkingData.json", json_encode($configArray));
        return $configArray[$ownerId]['offset'] + $count;
    }
}