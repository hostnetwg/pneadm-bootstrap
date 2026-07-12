<?php

namespace Tests\Unit;

use App\Services\Dashboard\DashboardCourseScheduleService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardCourseScheduleServiceTest extends TestCase
{
    public function test_build_for_range_returns_empty_when_courses_table_missing(): void
    {
        if (Schema::hasTable('courses')) {
            $this->markTestSkipped('Tabela courses istnieje — test tylko dla środowiska bez migracji.');
        }

        $service = app(DashboardCourseScheduleService::class);

        $this->assertSame([], $service->buildForRange(
            now('Europe/Warsaw')->startOfDay(),
            now('Europe/Warsaw')->startOfDay(),
            'Europe/Warsaw',
            'day',
        ));
    }
}
