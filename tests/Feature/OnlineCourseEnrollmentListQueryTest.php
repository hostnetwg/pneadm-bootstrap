<?php

namespace Tests\Feature;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\User;
use App\Services\OnlineCourseEnrollmentListQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineCourseEnrollmentListQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_search_access_filter_name_sort_and_unlimited_query(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
        $course = OnlineCourse::query()->create([
            'slug' => 'kurs-lista-'.uniqid(),
            'title' => 'Kurs lista',
            'is_active' => true,
            'visible_in_dashboard' => true,
        ]);

        OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'anna.nowak@example.test',
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'access_source' => 'manual',
            'access_expires_at' => null,
        ]);
        OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'bartek.wygasly@example.test',
            'first_name' => 'Bartek',
            'last_name' => 'Wygasły',
            'access_source' => 'publigo_migration',
            'access_expires_at' => Carbon::parse('2024-01-01 12:00:00', 'UTC'),
        ]);
        OnlineCourseEnrollment::query()->create([
            'online_course_id' => $course->id,
            'email' => 'celina.zielinska@example.test',
            'first_name' => 'Celina',
            'last_name' => 'Zielińska',
            'phone' => '501111111',
            'access_source' => 'manual',
            'access_expires_at' => Carbon::parse('2027-01-01 12:00:00', 'UTC'),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        $this->get(route('online-courses.enrollments.index', [
            'online_course' => $course,
            'q' => 'celina',
        ]))
            ->assertOk()
            ->assertSee('celina.zielinska@example.test')
            ->assertDontSee('anna.nowak@example.test')
            ->assertSee('Szukaj')
            ->assertSee('Filtruj');

        $this->get(route('online-courses.enrollments.index', [
            'online_course' => $course,
            'access' => 'expired',
        ]))
            ->assertOk()
            ->assertSee('bartek.wygasly@example.test')
            ->assertDontSee('anna.nowak@example.test')
            ->assertDontSee('celina.zielinska@example.test');

        $page = $this->get(route('online-courses.enrollments.index', [
            'online_course' => $course,
            'sort' => 'name',
            'dir' => 'asc',
        ]));
        $page->assertOk();
        $html = $page->getContent();
        $this->assertTrue(
            strpos($html, 'anna.nowak@example.test') < strpos($html, 'bartek.wygasly@example.test'),
            'Nowak powinien być przed Wygasły przy sortowaniu po nazwisku.'
        );

        $ids = app(OnlineCourseEnrollmentListQuery::class)
            ->filteredQuery($course, [
                'q' => '',
                'access' => 'unlimited',
                'pnedu' => 'all',
                'source' => 'all',
                'certificate' => 'all',
                'sort' => 'email',
                'dir' => 'asc',
            ])
            ->pluck('email')
            ->all();

        $this->assertSame(['anna.nowak@example.test'], $ids);

        $fullNameIds = app(OnlineCourseEnrollmentListQuery::class)
            ->filteredQuery($course, [
                'q' => 'Anna Nowak',
                'access' => 'all',
                'pnedu' => 'all',
                'source' => 'all',
                'certificate' => 'all',
                'sort' => 'email',
                'dir' => 'asc',
            ])
            ->pluck('email')
            ->all();

        $this->assertSame(['anna.nowak@example.test'], $fullNameIds);
    }
}
