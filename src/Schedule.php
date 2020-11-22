<?php


namespace Lichi\Grab;


interface Schedule
{
    public function __construct(array $schedule, int $lastTime = 0);
    public function getUnixFor($dayOffset, $scheduleOffset): int;

}