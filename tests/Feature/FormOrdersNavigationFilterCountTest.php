<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrdersNavigationFilterCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_total_count_without_filters(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Kurs A',
            'orderer_email' => 'a@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs B',
            'orderer_email' => 'b@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs C',
            'orderer_email' => 'c@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count'));

        $response->assertOk()
            ->assertJsonPath('count', 3)
            ->assertJsonPath('filter_new', false)
            ->assertJsonPath('course_id', null);
    }

    public function test_filters_by_course_id_and_unprocessed(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Kurs match',
            'product_id' => 543,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'match@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs done',
            'product_id' => 543,
            'invoice_number' => 'FV/1',
            'status_completed' => 1,
            'orderer_email' => 'done@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs other',
            'product_id' => 999,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'other@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'course_id' => 543,
            'filter_new' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_new', true)
            ->assertJsonPath('course_id', 543);
    }
}
