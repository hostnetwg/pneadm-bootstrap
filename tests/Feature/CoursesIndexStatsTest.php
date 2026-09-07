<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursesIndexStatsTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    private function actingOperator(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    private function createCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'title' => 'mgr',
            'email' => 'jan.kowalski.'.uniqid('', true).'@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Szkolenie testowe',
            'description' => 'Test',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(4),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ], $overrides));
    }

    public function test_index_stats_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('courses.index-stats'));
        $this->assertStringContainsString('/courses/index-stats', route('courses.index-stats'));
    }

    public function test_index_stats_returns_html_payload_for_course_ids(): void
    {
        $user = $this->actingOperator();
        $course = $this->createCourse();

        $response = $this->actingAs($user)
            ->getJson(route('courses.index-stats', ['ids' => [$course->id]]));

        $response->assertOk();
        $response->assertJsonStructure([
            'funnel_stats_days',
            'courses' => [
                (string) $course->id => [
                    'operational_html',
                    'funnel_html',
                    'billing_html',
                ],
            ],
        ]);

        $payload = $response->json('courses.'.$course->id);
        $this->assertStringContainsString('U 0', $payload['operational_html']);
        $this->assertStringContainsString('FV 0', $payload['operational_html']);
        $this->assertStringContainsString('bi-megaphone-fill', $payload['funnel_html']);
        $this->assertSame('', $payload['billing_html']);
    }

    public function test_index_stats_includes_billing_placeholder_for_closed_paid_course(): void
    {
        $user = $this->actingOperator();
        $course = $this->createCourse([
            'category' => 'closed',
            'is_paid' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('courses.index-stats', ['ids' => [$course->id]]));

        $response->assertOk();
        $html = $response->json('courses.'.$course->id.'.billing_html');
        $this->assertStringContainsString('Brak zamówienia', $html);
    }

    public function test_guest_cannot_access_index_stats(): void
    {
        $this->getJson(route('courses.index-stats', ['ids' => [1]]))
            ->assertUnauthorized();
    }

    public function test_inactive_course_row_uses_inactive_class(): void
    {
        $user = $this->actingOperator();
        $this->createCourse([
            'title' => 'UNIQ-INACTIVE-ROW-STYLE',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('courses.index', [
                'date_filter' => 'upcoming',
                'search' => 'UNIQ-INACTIVE-ROW-STYLE',
            ]));

        $response->assertOk();
        $response->assertSee('UNIQ-INACTIVE-ROW-STYLE');
        $response->assertSee('<tr class="course-row-inactive">', false);
        $response->assertSee('Nieaktywne');
    }

    public function test_active_upcoming_course_row_does_not_use_inactive_class(): void
    {
        $user = $this->actingOperator();
        $this->createCourse([
            'title' => 'UNIQ-ACTIVE-ROW-STYLE',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('courses.index', [
                'date_filter' => 'upcoming',
                'search' => 'UNIQ-ACTIVE-ROW-STYLE',
            ]));

        $response->assertOk();
        $response->assertSee('UNIQ-ACTIVE-ROW-STYLE');
        $response->assertDontSee('<tr class="course-row-inactive">', false);
        $response->assertSee('Aktywne');
    }

    public function test_ended_active_course_keeps_table_secondary_and_not_inactive_class(): void
    {
        $user = $this->actingOperator();
        $this->createCourse([
            'title' => 'UNIQ-ENDED-ACTIVE-ROW',
            'is_active' => true,
            'start_date' => now()->subDays(7),
            'end_date' => now()->subDays(6),
        ]);

        $response = $this->actingAs($user)
            ->get(route('courses.index', [
                'date_filter' => 'past',
                'search' => 'UNIQ-ENDED-ACTIVE-ROW',
            ]));

        $response->assertOk();
        $response->assertSee('UNIQ-ENDED-ACTIVE-ROW');
        $response->assertSee('<tr class="table-secondary text-muted">', false);
        $response->assertDontSee('<tr class="course-row-inactive">', false);
    }

    public function test_ended_inactive_course_prefers_inactive_row_class(): void
    {
        $user = $this->actingOperator();
        $this->createCourse([
            'title' => 'UNIQ-ENDED-INACTIVE-ROW',
            'is_active' => false,
            'start_date' => now()->subDays(7),
            'end_date' => now()->subDays(6),
        ]);

        $response = $this->actingAs($user)
            ->get(route('courses.index', [
                'date_filter' => 'past',
                'search' => 'UNIQ-ENDED-INACTIVE-ROW',
            ]));

        $response->assertOk();
        $response->assertSee('UNIQ-ENDED-INACTIVE-ROW');
        $response->assertSee('<tr class="course-row-inactive">', false);
        $response->assertDontSee('<tr class="table-secondary text-muted">', false);
    }
}
