<?php


namespace Lichi\Grab\Post;
use RuntimeException;

class Schedule implements \Lichi\Grab\Schedule
{
    private array $schedules;
    private int $lastTime;
    public int $optimalIndexScheduleOffset;

    /**
     * Schedule constructor.
     * @param int $lastTime
     * @param array $schedule
     */
    public function __construct(array $schedule, int $lastTime = 0)
    {
        $countOfSchedule = count($schedule);
        if($countOfSchedule == 0)
        {
            throw new RuntimeException("Schedule is empty");
        }
        if($countOfSchedule > 25)
        {
            throw new RuntimeException("Too more elements in schedule");
        }
        $this->sortTime($schedule);
        $this->schedules = $schedule;
        if(!$lastTime){
            $lastTime = time();
        }
        $this->lastTime = $lastTime;
        $this->optimalIndexScheduleOffset = $this->getOptimalIndexScheduleOffset();
    }

    public function changeLastTime($lastTime)
    {
        $this->lastTime = $lastTime;
    }

    public function changeSchedule($newSchedule) {
        $this->schedules = $newSchedule;
    }


    private function sortTime(&$schedule): void
    {
        $sortDate = [];
        foreach ($schedule as $scheduleInfo){
            $unixDate = strtotime($scheduleInfo);
            if($unixDate) {
                $sortDate[$scheduleInfo] = strtotime($scheduleInfo);
            } else {
                throw new RuntimeException(sprintf("Bad date: %s in array", $scheduleInfo));
            }
        }
        asort($sortDate);
        $sortDate = array_unique($sortDate);
        $schedule = array_keys($sortDate);
    }

    private function getOptimalIndexScheduleOffset(): int
    {
        date_default_timezone_set("Europe/Volgograd");
        foreach ($this->schedules as $id=>$schedule){
            $unixTime = $this->getUnixFor(0, $id);
            if($this->lastTime < $unixTime)
                return $id;
        }
        return -1;
    }

    /**
     * @param $dayOffset
     * @param $scheduleOffset
     * @return int
     */
    public function getUnixFor($dayOffset, $scheduleOffset): int
    {
        date_default_timezone_set("Europe/Volgograd");
        if($scheduleOffset > count($this->schedules) - 1)
        {
            throw new RuntimeException("Offset schedule not found");
        }

        return strtotime("+{$dayOffset} day " . $this->schedules[$scheduleOffset] . ":00", $this->lastTime);
    }
}