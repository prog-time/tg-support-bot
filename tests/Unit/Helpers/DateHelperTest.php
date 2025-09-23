<?php

namespace Tests\Unit\Helpers;

use App\Helpers\DateHelper;
use Carbon\Carbon;
use Tests\TestCase;

class DateHelperTest extends TestCase
{
    public function test_returns_false_if_interval_is_not_exceeded(): void
    {
        $date1 = Carbon::now();
        $date2 = $date1->copy()->addSeconds(5); // разница 5 секунд
        $seconds = 10;

        $this->assertFalse(DateHelper::isIntervalExceeded($date1, $date2, $seconds));
    }

    public function test_returns_true_if_interval_is_exceeded(): void
    {
        $date1 = Carbon::now();
        $date2 = $date1->copy()->addSeconds(15); // разница 15 секунд
        $seconds = 10;

        $this->assertTrue(DateHelper::isIntervalExceeded($date1, $date2, $seconds));
    }

    public function test_returns_false_if_interval_is_equal_to_threshold(): void
    {
        $date1 = Carbon::now();
        $date2 = $date1->copy()->addSeconds(10); // разница 10 секунд
        $seconds = 10;

        $this->assertFalse(DateHelper::isIntervalExceeded($date1, $date2, $seconds));
    }
}
