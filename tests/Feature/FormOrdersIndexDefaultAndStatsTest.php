<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrdersIndexDefaultAndStatsTest extends TestCase
{
    use RefreshDatabase;

    private function actingOperator(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    public function test_index_without_quick_defaults_to_handling_queue(): void
    {
        $user = $this->actingOperator();

        $response = $this->actingAs($user)->get(route('form-orders.index'));

        $response->assertOk();
        $response->assertSee('Szybki filtr: Do obsługi — aktywne szkolenia', false);
        $response->assertSee('Ładowanie podsumowania', false);
        $response->assertSee('form-orders\/index-stats', false);
    }

    public function test_index_with_quick_all_shows_all_orders_filter(): void
    {
        $user = $this->actingOperator();

        $response = $this->actingAs($user)->get(route('form-orders.index', ['quick' => 'all']));

        $response->assertOk();
        $response->assertSee('Szybki filtr: Wszystkie zamówienia', false);
    }

    public function test_index_stats_endpoint_returns_json_payload(): void
    {
        $user = $this->actingOperator();

        $response = $this->actingAs($user)
            ->getJson(route('form-orders.index-stats'));

        $response->assertOk();
        $response->assertJsonStructure([
            'handling_count',
            'processed_count',
            'archival_count',
            'cancelled_count',
            'total_duplicate_groups',
            'urgent_duplicates',
            'stats' => [
                'total',
                'handling',
                'handling_backlog',
                'yesterday',
                'today',
                'archival',
                'sales_value',
                'avg_price',
            ],
        ]);
    }

    public function test_guest_cannot_access_index_stats(): void
    {
        $this->get(route('form-orders.index-stats'))
            ->assertRedirect();
    }
}
