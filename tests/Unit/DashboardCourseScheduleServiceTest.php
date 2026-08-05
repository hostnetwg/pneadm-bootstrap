<?php

namespace Tests\Unit;

use App\Services\Dashboard\DashboardCourseScheduleService;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
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

    public function test_normalize_course_title_replaces_nbsp_with_regular_spaces(): void
    {
        $service = app(DashboardCourseScheduleService::class);
        $method = new ReflectionMethod(DashboardCourseScheduleService::class, 'normalizeCourseTitle');

        $this->assertSame('A B C', $method->invoke($service, 'A&nbsp;B&nbsp;C'));
        $this->assertSame('A B', $method->invoke($service, "A\u{00A0}B"));
        $this->assertSame('Szkolenie test', $method->invoke($service, '<b>Szkolenie&nbsp;test</b>'));
    }
}
