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

    public function test_filters_orders_with_invoice_but_without_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Bez KSeF',
            'invoice_number' => '10/8/2026',
            'ksef_number' => null,
            'orderer_email' => 'noksef@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Z KSeF',
            'invoice_number' => '11/8/2026',
            'ksef_number' => '7392137630-20260805-ABCDEF000001-11',
            'orderer_email' => 'withksef@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Bez FV',
            'invoice_number' => null,
            'ksef_number' => null,
            'orderer_email' => 'nofv@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'FV zero',
            'invoice_number' => '0',
            'ksef_number' => null,
            'orderer_email' => 'zero@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'filter_no_ksef' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_no_ksef', true)
            ->assertJsonPath('filter_new', false);
    }
}
