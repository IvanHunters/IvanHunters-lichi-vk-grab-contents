<?php


namespace Lichi\Grab\Post;
use RuntimeException;

class Schedule implements \Lichi\Grab\Schedule
{
    private array $schedule;

    /**
     * Schedule constructor.
     * @param array $schedule
     */
    public function __construct(array $schedule)
    {
        $this->schedule = $schedule;
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

        return strtotime("+{$dayOffset} day " . $this->schedule[$scheduleOffset] . ":00", time());
    }
}