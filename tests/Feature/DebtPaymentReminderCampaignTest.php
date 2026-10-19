<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\DebtCase;
use App\Models\DebtPaymentReminderCampaign;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\Debt\DebtPaymentReminderGateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtPaymentReminderCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_gate_blocks_when_pending_review_or_gaps(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => 1]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista.csv',
            'file_hash' => str_repeat('a', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-19',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);

        BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-10-18',
            'amount' => 100,
            'currency' => 'PLN',
            'description' => 'WPLYW DO PRZEGLADU',
            'account_label' => 'Test',
            'fingerprint' => str_repeat('b', 64),
            'is_incoming' => true,
        ]);

        $gate = app(DebtPaymentReminderGateService::class)->evaluate();
        $this->assertFalse($gate['allowed']);
        $this->assertSame(1, $gate['pending_review_count']);

        $this->actingAs($user)
            ->get(route('accounting.bank-imports.index'))
            ->assertOk()
            ->assertSee('Przypomnienia o zaległych płatnościach', false)
            ->assertSee('Rozpatrz wszystkie wpływy', false);
    }

    public function test_wizard_and_start_campaign_when_gate_open(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => 1]);

        BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista.csv',
            'file_hash' => str_repeat('c', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-19',
            'rows_total' => 0,
            'rows_incoming' => 0,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 200,
            'order_date' => '2026-09-01',
            'invoice_number' => '10/9/2026',
            'invoice_due_date' => '2026-10-01',
            'orderer_email' => 'szkola@example.test',
            'orderer_phone' => '501502503',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '10/9/2026',
            'amount_gross' => 200,
            'product_name' => 'Szkolenie',
            'due_date' => '2026-10-01',
            'opened_at' => now()->subDays(10),
        ]);

        $gate = app(DebtPaymentReminderGateService::class)->evaluate();
        $this->assertTrue($gate['allowed']);

        $this->actingAs($user)
            ->get(route('accounting.bank-imports.payment-reminders.index'))
            ->assertOk()
            ->assertSee('Standardowe', false)
            ->assertSee('#'.$case->id, false);

        $response = $this->actingAs($user)->post(route('accounting.bank-imports.payment-reminders.store'), [
            'audience' => 'standard',
            'selected_ids' => [$case->id],
            'send_email' => '1',
            'email_template' => 'reminder',
            'sms_template' => 'reminder',
            'check_ifirma' => '0',
            'attach_ifirma_pdf' => '0',
        ]);

        $campaign = DebtPaymentReminderCampaign::first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('accounting.bank-imports.payment-reminders.show', $campaign));
        $this->assertSame(1, $campaign->items()->count());
        $this->assertSame(DebtPaymentReminderCampaign::STATUS_PROCESSING, $campaign->status);
    }
}
