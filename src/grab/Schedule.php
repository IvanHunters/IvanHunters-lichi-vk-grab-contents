<?php


namespace Lichi\Grab\Post;
use RuntimeException;

class Schedule implements \Lichi\Grab\Schedule
{
    private array $schedule;
    private int $lastTime;

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
        $this->schedule = $schedule;
        if(!$lastTime){
            $lastTime = time();
        }
        $this->lastTime = $lastTime;
    }

    /**
     * @param $dayOffset
     * @param $scheduleOffset
     * @return int
     */
    public function getUnixFor($dayOffset, $scheduleOffset): int
    {
        date_default_timezone_set("Europe/Volgograd");
        if($scheduleOffset > count($this->schedule) - 1)
        {
            throw new RuntimeException("Offset schedule not found");
        }

        return strtotime("+{$dayOffset} day " . $this->schedule[$scheduleOffset] . ":00", $this->lastTime);
    }
}