<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Check if difference between two dates exceeds given interval.
     *
     * @param Carbon $date1
     * @param Carbon $date2
     * @param int    $seconds
     *
     * @return bool
     */
    public static function isIntervalExceeded(Carbon $date1, Carbon $date2, int $seconds): bool
    {
        $diffInSeconds = (int)$date1->diffInSeconds($date2);
        return $diffInSeconds > $seconds;
    }
}
