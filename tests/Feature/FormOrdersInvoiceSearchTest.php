<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrdersInvoiceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_search_matches_classic_invoice_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $match = FormOrder::create([
            'product_name' => 'Kurs A',
            'invoice_number' => '43/7/2026',
            'orderer_email' => 'a@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs B',
            'invoice_number' => '99/1/2025',
            'orderer_email' => 'b@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => '43/7/2026',
            'invoice_search_exact' => 1,
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('43/7/2026');
        $response->assertSee((string) $match->id);
        $response->assertDontSee('99/1/2025');
    }

    public function test_invoice_search_exact_does_not_match_fragment(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Kurs pełny',
            'invoice_number' => '43/7/2026',
            'orderer_email' => 'full@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => '43/7',
            'invoice_search_exact' => 1,
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('z 0 rekordów');
        $response->assertDontSee('Kurs pełny');
    }

    public function test_invoice_search_partial_matches_fragment(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $match = FormOrder::create([
            'product_name' => 'Kurs fragment',
            'invoice_number' => '43/7/2026',
            'orderer_email' => 'frag@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => '43/7',
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee((string) $match->id);
        $response->assertSee('43/7/2026');
    }

    public function test_invoice_search_matches_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $match = FormOrder::create([
            'product_name' => 'Kurs KSeF',
            'invoice_number' => '278/7/2026',
            'ksef_number' => '7392137630-20260724-67937DC00009-11',
            'ksef_status' => 'sent',
            'orderer_email' => 'ksef@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs inny',
            'invoice_number' => '1/1/2026',
            'ksef_number' => '1111111111-20260101-AAAAAAAAAAAA-00',
            'ksef_status' => 'sent',
            'orderer_email' => 'other@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => '67937DC00009',
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('7392137630-20260724-67937DC00009-11');
        $response->assertSee((string) $match->id);
        $response->assertDontSee('1111111111-20260101-AAAAAAAAAAAA-00');
    }

    public function test_invoice_search_exact_matches_full_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $full = '7392137630-20260724-67937DC00009-11';
        $match = FormOrder::create([
            'product_name' => 'Kurs KSeF exact',
            'invoice_number' => '278/7/2026',
            'ksef_number' => $full,
            'ksef_status' => 'sent',
            'orderer_email' => 'ksef-exact@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => $full,
            'invoice_search_exact' => 1,
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee((string) $match->id);
        $response->assertSee($full);
    }

    public function test_invoice_search_finds_processed_order_even_with_hostile_handling_filter(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $processed = FormOrder::create([
            'product_name' => 'Kurs przetworzony FV',
            'invoice_number' => '80/8/2026',
            'orderer_email' => 'processed-fv@example.test',
            'status_completed' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Kurs bez FV w kolejce',
            'invoice_number' => null,
            'orderer_email' => 'handling@example.test',
            'status_completed' => 0,
        ]);

        // Celowo: filter=handling + brak quick=all — wyszukiwarka FV i tak musi znaleźć przetworzone.
        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'invoice_search' => '80/8/2026',
            'invoice_search_exact' => 1,
            'filter' => 'handling',
            'quick' => 'handling',
        ]));

        $response->assertOk();
        $response->assertSee((string) $processed->id);
        $response->assertSee('80/8/2026');
        $response->assertSee('Kurs przetworzony FV');
        $response->assertDontSee('Kurs bez FV w kolejce');
    }

    public function test_general_search_also_matches_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $match = FormOrder::create([
            'product_name' => 'Kurs ogólny',
            'invoice_number' => '10/2/2026',
            'ksef_number' => '7392137630-20260210-ABCDEF000001-22',
            'ksef_status' => 'sent',
            'orderer_email' => 'gen@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'search' => 'ABCDEF000001',
            'quick' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee((string) $match->id);
        $response->assertSee('7392137630-20260210-ABCDEF000001-22');
    }
}
