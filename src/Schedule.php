<?php


namespace Lichi\Grab;


interface Schedule
{
    public function __construct(array $schedule);
    public function getUnixFor($dayOffset, $scheduleOffset): int;

}