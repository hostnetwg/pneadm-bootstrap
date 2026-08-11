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
            ->assertJsonPath('filter_no_participant', false)
            ->assertJsonPath('filter_no_invoice', false)
            ->assertJsonPath('course_id', null);
    }

    public function test_filters_by_course_id_and_no_invoice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Kurs match',
            'product_id' => 543,
            'invoice_number' => null,
            'ifirma_invoice_id' => null,
            'orderer_email' => 'match@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs done',
            'product_id' => 543,
            'invoice_number' => 'FV/1',
            'ifirma_invoice_id' => '123',
            'orderer_email' => 'done@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Kurs other',
            'product_id' => 999,
            'invoice_number' => null,
            'ifirma_invoice_id' => null,
            'orderer_email' => 'other@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Ma ID iFirma bez numeru',
            'product_id' => 543,
            'invoice_number' => null,
            'ifirma_invoice_id' => '999',
            'orderer_email' => 'idonly@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'course_id' => 543,
            'filter_no_invoice' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('filter_no_invoice', true)
            ->assertJsonPath('course_id', 543);
    }

    public function test_legacy_filter_new_maps_to_no_invoice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Bez FV',
            'invoice_number' => null,
            'ifirma_invoice_id' => null,
            'orderer_email' => 'a@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Z FV',
            'invoice_number' => '1/8/2026',
            'ifirma_invoice_id' => null,
            'orderer_email' => 'b@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'filter_new' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_no_invoice', true);
    }

    public function test_filters_orders_with_buyer_nip_invoice_but_without_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'NIP bez KSeF',
            'invoice_number' => '10/8/2026',
            'ksef_number' => null,
            'buyer_nip' => '525-234-56-78',
            'orderer_email' => 'noksef@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'FV bez NIP i bez KSeF',
            'invoice_number' => '12/8/2026',
            'ksef_number' => null,
            'buyer_nip' => null,
            'orderer_email' => 'nonip@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Z KSeF',
            'invoice_number' => '11/8/2026',
            'ksef_number' => '7392137630-20260805-ABCDEF000001-11',
            'buyer_nip' => '5252345678',
            'orderer_email' => 'withksef@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'Bez FV',
            'invoice_number' => null,
            'ksef_number' => null,
            'buyer_nip' => '5252345678',
            'orderer_email' => 'nofv@example.test',
        ]);
        FormOrder::create([
            'product_name' => 'FV zero',
            'invoice_number' => '0',
            'ksef_number' => null,
            'buyer_nip' => '5252345678',
            'orderer_email' => 'zero@example.test',
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'filter_no_ksef' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_no_ksef', true)
            ->assertJsonPath('filter_no_invoice', false);
    }

    public function test_filter_no_participant_excludes_cancelled_without_participants(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Do wprowadzenia',
            'orderer_email' => 'open@example.test',
            'cancelled_at' => null,
        ]);
        FormOrder::create([
            'product_name' => 'Anulowane',
            'orderer_email' => 'cancelled@example.test',
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'filter_no_participant' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_no_participant', true);
    }
}
