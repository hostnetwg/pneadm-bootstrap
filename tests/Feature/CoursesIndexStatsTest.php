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
}
