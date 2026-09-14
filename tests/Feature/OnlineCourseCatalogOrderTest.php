<?php

namespace Tests\Feature;

use App\Models\OnlineCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineCourseCatalogOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reorder_courses_for_public_catalog(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_active' => true]);
        $first = OnlineCourse::query()->create([
            'slug' => 'kurs-alfa',
            'title' => 'Alfa',
            'is_active' => true,
            'visible_in_dashboard' => true,
            'catalog_sort_order' => 0,
        ]);
        $second = OnlineCourse::query()->create([
            'slug' => 'kurs-beta',
            'title' => 'Beta',
            'is_active' => true,
            'visible_in_dashboard' => true,
            'catalog_sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('online-courses.index'))
            ->assertOk()
            ->assertSee('Kolejność na /kursy')
            ->assertSee('data-course-id="'.$first->id.'"', false)
            ->assertSee('data-course-id="'.$second->id.'"', false);

        $this->actingAs($admin)
            ->postJson(route('online-courses.reorder'), [
                'order' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Kolejność kursów na /kursy zapisana.']);

        $this->assertSame(0, $second->fresh()->catalog_sort_order);
        $this->assertSame(1, $first->fresh()->catalog_sort_order);
    }

    public function test_search_hides_reorder_and_rejects_partial_order(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_active' => true]);
        $first = OnlineCourse::query()->create([
            'slug' => 'kurs-alfa',
            'title' => 'Alfa',
            'is_active' => true,
            'visible_in_dashboard' => true,
            'catalog_sort_order' => 0,
        ]);
        OnlineCourse::query()->create([
            'slug' => 'kurs-beta',
            'title' => 'Beta',
            'is_active' => true,
            'visible_in_dashboard' => true,
            'catalog_sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('online-courses.index', ['q' => 'Alfa']))
            ->assertOk()
            ->assertSee('wyczyść wyszukiwanie')
            ->assertDontSee('online-courses-sortable');

        $this->actingAs($admin)
            ->postJson(route('online-courses.reorder'), [
                'order' => [$first->id],
            ])
            ->assertStatus(422);
    }
}
