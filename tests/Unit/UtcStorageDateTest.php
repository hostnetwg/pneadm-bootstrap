<?php

namespace Tests\Unit;

use App\Support\UtcStorageDate;
use Carbon\Carbon;
use Tests\TestCase;

class UtcStorageDateTest extends TestCase
{
    public function test_day_range_for_warsaw_summer_time(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);

        $from = Carbon::parse('2026-07-04', 'Europe/Warsaw')->startOfDay();
        $to = Carbon::parse('2026-07-04', 'Europe/Warsaw')->endOfDay();

        [$fromUtc, $toUtc] = UtcStorageDate::utcRangeForLocalDays($from, $to);

        $this->assertSame('2026-07-03 22:00:00', $fromUtc);
        $this->assertSame('2026-07-04 21:59:59', $toUtc);
    }
}
