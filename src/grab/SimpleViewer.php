<?php


namespace Lichi\Grab\Post;

use Lichi\Vk\Sdk\ApiProvider;
use Predis\Client;
use RuntimeException;


class SimpleViewer
{
    public MysqliProvider $db;
    public Client $redis;
    public string $selectGroup;
    public array $sources;
    public string $selectGroupName;
    public ApiProvider $apiProvider;
    public Schedule $schedule;
    /**
     * @var int|mixed
     */
    private $selectGroupId;

    public function __construct(Client $redis, MysqliProvider $db, ApiProvider $apiProvider, Schedule $schedule)
    {
        $this->db = $db;
        $this->redis = $redis;
        $this->apiProvider = $apiProvider;
        $this->schedule = $schedule;

        $this->preloader();
    }

    public function preloader(): void
    {
        $this->selectGroup = isset($_GET['select'])? $_GET['select'] : die('Укажите Select');
        $groupInfo = $this->getInfoForGroup();
        $this->checkReset();
        $this->getSourceForGroup($groupInfo);
        if(isset($_GET['act']))
        {
            $this->actionsHandler($_GET['act']);
        }

    }

    public function checkReset(): void
    {
        if (isset($_GET['reset']))
        {
            $this->redis->set($this->selectGroupId, 0);
            die('таймер для сообщества сброшен');
        }
    }
    private function getInfoForGroup(): array
    {
        $groupInfo = $this->db->select("groups", ['id' => $this->selectGroup]);

        if($groupInfo['count_rows'] == 0){
            die("Такой группы не найдено!");
        }else{
            $this->selectGroupName = $groupInfo['name'];
            $this->selectGroupId = $groupInfo['group_id'];
            echo "Выбрана группа: <b>{$this->selectGroupName}</b><br><br>";
        }

        /** @var int $groupId **/
        $groupId = $groupInfo['group_id'];

        return $this->db->select("groups_content_list", ['for_group'=>$groupId]);
    }

    private function getSourceForGroup(array $groupInfoFromDb): void
    {
        while($row = $groupInfoFromDb[0]->fetch_assoc())
        {
            $this->sources[$row['group_id']] = $row['name'];
        }
    }

    private function actionsHandler(string $act)
    {
        switch ($act) {
            case 'post':
                $this->handlePost();
                break;
            case 'cancel':
                $this->handleCancel();
                break;
            case 'search':
                $this->handleSearch();
                break;
        }
    }

    private function handlePost()
    {
        $attachmentData = [];
        if(isset($_POST['id'])) {
            $lastPostTimeForGroup = $this->redis->get($this->selectGroupId);
            $rowId = $_POST['id'];
            $postInfo = $this->db->select("find_contents", [
                'id' => $rowId,
                'status' => 0
            ]);

            if ($postInfo['count_rows'] != 0) {
                $this->db->update("find_contents", ["status" => 1], ["id" => $postInfo['id']]);
                $postInfo['text'] = $_POST['text'] == "none" ? "" : $_POST['text'];

                $imagesData = json_decode($postInfo['images'], true);
                $imageLinks = explode(",", $imagesData['maxSizeImageUrl']);

                foreach ($imageLinks as $imageLink) {
                    $hashName = rand(1, 1000) . microtime(true) . rand(1, 1000);
                    $this->apiProvider->photos->downloadFromUrl($imageLink, $hashName);
                    $attachmentData[] = $this->apiProvider->photos->uploadOnWall($hashName, [
                        'group_id' => $this->selectGroupId
                    ]);
                }
                if (time() > $lastPostTimeForGroup) {
                    $scheduleOffsetTime = $this->schedule->optimalIndexScheduleOffset;
                    $offsetTimePost = $this->schedule->getUnixFor(0, $scheduleOffsetTime);
                } else {
                    $this->schedule->changeLastTime($lastPostTimeForGroup);
                    $scheduleOffsetTime = $this->schedule->optimalIndexScheduleOffset;
                    $offsetTimePost = $this->schedule->getUnixFor(0, $scheduleOffsetTime);
                }
                try {
                    $this->apiProvider->wall->post(
                        $this->selectGroupId,
                        $offsetTimePost,
                        $postInfo['textPost'],
                        $attachmentData,
                        [
                            "from_group" => 1
                        ]
                    );
                } catch (RuntimeException $exception) {
                    $this->db->update("find_contents", ["status" => 0], ["id" => $postInfo['id']]);
                    die($exception->getMessage());
                }
                $this->redis->set($this->selectGroupId, $offsetTimePost);
            } else {
                echo("Not row");
                die();
            }
        }
    }

    private function handleCancel(): void
    {
        $id = $_POST['id'];
        $this->db->update("find_contents", ["status" => 3], ["id" => $id]);
    }

    private function handleSearch(): void
    {
        $order_by                       = $_POST['order_by'];
        $order_by_type                  = $_POST['order_by_type'];


        $_SESSION[$this->selectGroupId.'_order_by'] = $_POST['order_by'];
        $_SESSION[$this->selectGroupId.'_order_by_type'] = $_POST['order_by_type'];
        $_SESSION[$this->selectGroupId.'_with'] = $_POST['with'];
        $_SESSION[$this->selectGroupId.'_text'] = ($_POST['text'])? $_POST['text']: "";
        $_SESSION[$this->selectGroupId.'_owner_id'] = $_POST['owner_id'];


        $text = "SELECT f1.*, f2.avg " .
                "FROM `find_contents` f1 " .
                "INNER JOIN " .
                    "(SELECT owner_id, avg(likes) as avg " .
                        "FROM `find_contents` " .
                        "GROUP BY owner_id) f2 " .
                "on f1.owner_id = f2.owner_id " .
                "WHERE " .
                "f1.likes > f2.avg and f1.status = 0 and f1.for_group = '{$this->selectGroup}'";

        if(isset($_POST['text']))
            $text .= " and f1.text like '%".$_POST['text']."%'";
        if(isset($_POST['owner_id']))
            $text .= " and f1.owner_id like '".$_POST['owner_id']."'";

        if(isset($_POST['with']) && in_array("image",$_POST['with']))
            $text .= " and f1.attach like '%http%'";
        if(isset($_POST['with']) && in_array("audio",$_POST['with']))
            $text .= " and f1.attach like '%audio%'";
        if(isset($_POST['with']) && in_array("video",$_POST['with']))
            $text .= " and f1.attach like '%video%'";
        if(isset($_POST['order_by']))
            $text .= " ORDER BY {$order_by} {$order_by_type} limit 3";

        $_SESSION[$this->selectGroup.'_last_request'] = $text;
    }
}