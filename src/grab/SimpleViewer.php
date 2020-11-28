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
    public $selectGroupId;
    public int $offsetTimePost;

    public function __construct(Client $redis, MysqliProvider $db, ApiProvider $apiProvider, Schedule $schedule, string $viewTemplate)
    {
        $this->db = $db;
        $this->redis = $redis;
        $this->apiProvider = $apiProvider;
        $this->schedule = $schedule;

        $this->preloader($viewTemplate);
    }

    public function preloader(string $viewTemplate): void
    {
        $this->selectGroup = isset($_GET['select'])? $_GET['select'] : die('Укажите Select');
        $groupInfo = $this->getInfoForGroup();
        $this->checkReset();
        $this->getSourceForGroup($groupInfo);
        if(isset($_POST['act']))
        {
            $this->actionsHandler($_POST['act']);
        }
        $this->viewTemplate($viewTemplate);

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
        }

        /** @var int $groupId **/
        $groupId = $groupInfo['group_id'];

        return $this->db->select("groups_content_list", ['for_group'=>$groupId], true);
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
                $postInfo['text'] = $_POST['text'] == "none" ? "" : $_POST['text'];

                $imagesData = json_decode($postInfo['images'], true);
                $imageLinks = $imagesData['maxSizeImageUrl'];

                if (time() < $lastPostTimeForGroup) {
                    $this->schedule->changeLastTime($lastPostTimeForGroup);
                }

                $scheduleOffsetTime = $this->schedule->optimalIndexScheduleOffset;
                if($scheduleOffsetTime === -1){
                    $offsetTimePost = $this->schedule->getUnixFor(1, 0);
                }else{
                    $offsetTimePost = $this->schedule->getUnixFor(0, $scheduleOffsetTime);
                }

                $this->offsetTimePost = $offsetTimePost;

                foreach ($imageLinks as $imageLink) {
                    $hashName = rand(1, 1000) . microtime(true) . rand(1, 1000) . ".jpg";
                    $this->apiProvider->photos->downloadFromUrl($imageLink, $hashName);
                    try{
                        $attachmentData[] = $this->apiProvider->photos->uploadOnWall($hashName, [
                            'group_id' => $this->selectGroupId
                        ]);
                        sleep(1);
                    } catch (RuntimeException $exception) {
                        sleep(2);
                        $attachmentData[] = $this->apiProvider->photos->uploadOnWall($hashName, [
                            'group_id' => $this->selectGroupId
                        ]);
                    }
                }

                sleep(1);
                $error = false;
                try {
                    $this->apiProvider->wall->post(
                        $this->selectGroupId * (-1),
                        $offsetTimePost,
                        $postInfo['textPost'],
                        $attachmentData,
                        [
                            "from_group" => 1
                        ]
                    );
                } catch (RuntimeException $exception) {
                    $error = true;
                    $this->db->update("find_contents", ["status" => 0], ["id" => $postInfo['id']]);
                    echo($exception->getMessage());
                }
                if (!$error){
                    echo "Запланировано на " . date("d.m.Y H:i", $this->offsetTimePost);
                    $this->redis->set($this->selectGroupId, $offsetTimePost);
                    $this->db->update("find_contents", ["status" => 1], ["id" => $postInfo['id']]);
                }
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
        $order_by                       = @$_POST['order_by'];
        $order_by_type                  = @$_POST['order_by_type'];


        $_SESSION[$this->selectGroupId.'_order_by'] = @$_POST['order_by'];
        $_SESSION[$this->selectGroupId.'_order_by_type'] = @$_POST['order_by_type'];
        $_SESSION[$this->selectGroupId.'_with'] = $_POST['with'];
        $_SESSION[$this->selectGroupId.'_text'] = ($_POST['text'])? $_POST['text']: "";
        $_SESSION[$this->selectGroupId.'_ownerId'] = $_POST['ownerId'];

        $text = "SELECT f1.*, f2.avg " .
            "FROM `find_contents` f1 " .
            "INNER JOIN " .
            "(SELECT ownerId, avg(likes) as avg " .
            "FROM `find_contents` " .
            "GROUP BY ownerId) f2 " .
            "on f1.ownerId = f2.ownerId " .
            "WHERE " .
            "f1.likes > f2.avg and f1.status = 0 and f1.forGroup = '{$this->selectGroupId}'";
        if(isset($_POST['text']))
            $text .= " and f1.textPost like '%".$_POST['text']."%'";
        if(isset($_POST['ownerId']))
            $text .= " and f1.ownerId like '".$_POST['ownerId']."'";

        if(isset($_POST['order_by']))
            $text .= " ORDER BY {$order_by} {$order_by_type} limit 10";
        $_SESSION[$this->selectGroup.'_last_request'] = $text;
    }

    public function check_place(string $place, string $value, bool $flag = false): void
    {
        if(isset($_SESSION[$this->selectGroupId."_".$place]))
        {
            if((is_array($_SESSION[$this->selectGroupId."_".$place]) && in_array($value, $_SESSION[$this->selectGroupId."_".$place])) || $_SESSION[$this->selectGroupId."_".$place] == $value)
            {
                if($flag)
                {
                    echo 'selected';
                }else{
                    echo 'checked';
                }
            }
        }

    }

    private function viewTemplate(string $viewTemplate)
    {
        if(!isset($_SESSION[$this->selectGroupId.'_last_request']))
        {
            $req = "SELECT f1.*, f2.avg FROM `find_contents` f1 INNER JOIN (SELECT ownerId, avg(likes) as avg FROM `find_contents` GROUP BY ownerId) f2 on f1.ownerId = f2.ownerId WHERE f1.likes > f2.avg and f1.status = 0 and f1.forGroup = '{$this->selectGroupId}' ORDER BY f1.unixTime DESC, f1.views DESC, f1.likes DESC limit 10";
            $notPublish = $this->db->exq($req, true)[0];
        }else{
            $notPublish = $this->db->exq($_SESSION[$this->selectGroupId . '_last_request'], true)[0];
        }
        $countNotResponse = $notPublish->num_rows;

        $lastPostTimeForGroup = $this->redis->get($this->selectGroupId);
        if (time() < $lastPostTimeForGroup) {
            $this->schedule->changeLastTime($lastPostTimeForGroup);
        }else{
            $this->schedule->changeLastTime(time());
        }

        $scheduleOffsetTime = $this->schedule->optimalIndexScheduleOffset;
        if($scheduleOffsetTime === -1){
            $offsetTimePost = $this->schedule->getUnixFor(1, 0);
        }else{
            $offsetTimePost = $this->schedule->getUnixFor(0, $scheduleOffsetTime);
        }
        $next_p = date("d-m-Y H:i", $offsetTimePost);

        include $viewTemplate;
    }
}
