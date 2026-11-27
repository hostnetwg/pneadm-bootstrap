<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseInstructorNote;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseInstructorNoteTest extends TestCase
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

    private function actingSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Administrator', 'description' => 'Rola testowa', 'is_system' => true, 'level' => 100],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    private function createCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'title' => 'dr',
            'email' => 'anna.nowak.'.uniqid('', true).'@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Szkolenie z notatką instruktora',
            'description' => 'Test',
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'notatki' => 'Techniczna notatka szkolenia',
        ], $overrides));
    }

    public function test_guest_cannot_save_instructor_note(): void
    {
        $course = $this->createCourse();

        $this->putJson(route('courses.instructor-note.update', $course), [
            'note' => 'Spóźniony start',
        ])->assertUnauthorized();
    }

    public function test_operator_cannot_save_instructor_note(): void
    {
        $user = $this->actingOperator();
        $course = $this->createCourse();

        $this->actingAs($user)
            ->putJson(route('courses.instructor-note.update', $course), [
                'note' => 'Spóźniony start',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('course_instructor_notes', [
            'course_id' => $course->id,
        ]);
    }

    public function test_super_admin_saves_note_on_the_course_without_touching_technical_notes(): void
    {
        $user = $this->actingSuperAdmin();
        $course = $this->createCourse(['title' => 'UNIQ-INSTRUCTOR-NOTE-SAVE']);

        $this->actingAs($user)
            ->putJson(route('courses.instructor-note.update', $course), [
                'note' => "Prosi o materiały\ndzień wcześniej",
            ])
            ->assertOk()
            ->assertJsonPath('note', "Prosi o materiały\ndzień wcześniej");

        $this->assertDatabaseHas('course_instructor_notes', [
            'course_id' => $course->id,
            'body' => "Prosi o materiały\ndzień wcześniej",
        ]);
        $this->assertSame('Techniczna notatka szkolenia', $course->fresh()->notatki);
        $this->assertSame(1, CourseInstructorNote::query()->where('course_id', $course->id)->count());

        $this->actingAs($user)
            ->putJson(route('courses.instructor-note.update', $course), [
                'note' => '   ',
            ])
            ->assertOk()
            ->assertJsonPath('note', null);

        $this->assertDatabaseMissing('course_instructor_notes', [
            'course_id' => $course->id,
        ]);
        $this->assertSame('Techniczna notatka szkolenia', $course->fresh()->notatki);
    }

    public function test_courses_index_shows_link_or_sticky_icon(): void
    {
        $empty = $this->createCourse(['title' => 'UNIQ-INSTRUCTOR-NOTE-EMPTY']);
        $filled = $this->createCourse(['title' => 'UNIQ-INSTRUCTOR-NOTE-FILLED']);
        CourseInstructorNote::query()->create([
            'course_id' => $filled->id,
            'body' => 'Karteczka widoczna na liście',
        ]);

        $hidden = $this->actingAs($this->actingOperator())
            ->get(route('courses.index', [
                'date_filter' => 'upcoming',
                'search' => 'UNIQ-INSTRUCTOR-NOTE',
            ]));

        $hidden->assertOk();
        $hidden->assertDontSee('data-instructor-note-slot="'.$empty->id.'"', false);
        $hidden->assertDontSee('Karteczka widoczna na liście', false);
        $hidden->assertDontSee('instructorNoteModal', false);

        $response = $this->actingAs($this->actingSuperAdmin())
            ->get(route('courses.index', [
                'date_filter' => 'upcoming',
                'search' => 'UNIQ-INSTRUCTOR-NOTE',
            ]));

        $response->assertOk();
        $response->assertSee('data-instructor-note-slot="'.$empty->id.'"', false);
        $response->assertSee('Notatka', false);
        $response->assertSee('bi-sticky-fill', false);
        $response->assertSee('Karteczka widoczna na liście', false);
        $response->assertSee('instructorNoteModal', false);
        $response->assertSee('instructorNoteGenerateBtn', false);
        $response->assertSee('>USUŃ</button>', false);
        $response->assertSee('SZKOLENIE: UNIQ-INSTRUCTOR-NOTE-EMPTY (', false);
        $response->assertDontSee('SZKOLENIE RP: UNIQ-INSTRUCTOR-NOTE-EMPTY', false);
    }
}
