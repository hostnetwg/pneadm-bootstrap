<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDebtorsLookupKsefTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_finds_order_by_partial_ksef_number(): void
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
            'product_price' => 100,
        ]);
        FormOrder::create([
            'product_name' => 'Kurs inny',
            'invoice_number' => '1/1/2026',
            'ksef_number' => '1111111111-20260101-AAAAAAAAAAAA-00',
            'ksef_status' => 'sent',
            'orderer_email' => 'other@example.test',
            'product_price' => 50,
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.debtors.lookup', [
            'q' => '67937DC00009',
            'match_mode' => 'partial',
        ]));

        $response->assertOk();
        $response->assertJsonPath('selected.id', $match->id);
        $response->assertJsonPath('selected.ksef_number', '7392137630-20260724-67937DC00009-11');
        $response->assertJsonPath('selected.invoice_number', '278/7/2026');
        $this->assertCount(1, $response->json('matches'));
    }

    public function test_lookup_exact_match_on_full_ksef_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $full = '7392137630-20260724-67937DC00009-11';
        $match = FormOrder::create([
            'product_name' => 'Kurs exact KSeF',
            'invoice_number' => '278/7/2026',
            'ksef_number' => $full,
            'ksef_status' => 'sent',
            'orderer_email' => 'exact@example.test',
            'product_price' => 100,
        ]);

        $miss = $this->actingAs($user)->getJson(route('accounting.debtors.lookup', [
            'q' => '67937DC00009',
            'match_mode' => 'exact',
        ]));
        $miss->assertOk();
        $miss->assertJsonPath('selected', null);

        $hit = $this->actingAs($user)->getJson(route('accounting.debtors.lookup', [
            'q' => $full,
            'match_mode' => 'exact',
        ]));
        $hit->assertOk();
        $hit->assertJsonPath('selected.id', $match->id);
        $hit->assertJsonPath('selected.ksef_number', $full);
    }

    public function test_lookup_still_finds_by_classic_invoice_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $match = FormOrder::create([
            'product_name' => 'Kurs FV',
            'invoice_number' => '43/7/2026',
            'orderer_email' => 'fv@example.test',
            'product_price' => 200,
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.debtors.lookup', [
            'q' => '43/7/2026',
            'match_mode' => 'exact',
        ]));

        $response->assertOk();
        $response->assertJsonPath('selected.id', $match->id);
        $response->assertJsonPath('selected.invoice_number', '43/7/2026');
        $response->assertJsonPath('selected.active_debt_case', null);
    }

    public function test_lookup_includes_active_debt_case_when_present(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Kurs z sprawą',
            'invoice_number' => '349/6/2026',
            'orderer_email' => 'case@example.test',
            'product_price' => 300,
        ]);

        $case = \App\Models\DebtCase::create([
            'form_order_id' => $order->id,
            'status' => \App\Models\DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.debtors.lookup', [
            'q' => '349/6/2026',
            'match_mode' => 'exact',
        ]));

        $response->assertOk();
        $response->assertJsonPath('selected.id', $order->id);
        $response->assertJsonPath('selected.active_debt_case.id', $case->id);
        $response->assertJsonPath('selected.active_debt_case.status', 'open');
        $response->assertJsonPath('matches.0.active_debt_case.id', $case->id);
    }
}
