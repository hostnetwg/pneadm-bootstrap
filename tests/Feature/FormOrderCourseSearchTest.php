<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrderCourseSearchTest extends TestCase
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

    public function test_create_page_exposes_course_search_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('form-orders.create'))
            ->assertOk()
            ->assertSee('Wybierz szkolenie', false)
            ->assertSee('id="course_id"', false)
            ->assertSee('Pokaż również archiwalne', false);
    }

    public function test_course_search_returns_upcoming_paid_trainings_not_catalog_products(): void
    {
        $user = User::factory()->create();
        $start = now()->addDays(3);
        $upcoming = Course::query()->create([
            'title' => 'Nadchodzące FORM',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
        $archivedStart = now()->subDays(20);
        $archived = Course::query()->create([
            'title' => 'Archiwalne FORM',
            'description' => 'Test',
            'start_date' => $archivedStart,
            'end_date' => $archivedStart->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $this->actingAs($user)
            ->getJson(route('form-orders.courses.search'))
            ->assertOk()
            ->assertJsonFragment(['id' => $upcoming->id, 'title_text' => 'Nadchodzące FORM'])
            ->assertJsonMissing(['id' => $archived->id]);

        $this->actingAs($user)
            ->getJson(route('form-orders.courses.search', ['include_archived' => '1']))
            ->assertOk()
            ->assertJsonFragment(['id' => $archived->id]);
    }
}
