<?php

namespace Tests\Unit;

use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\IfirmaApiService;
use App\Services\IfirmaInvoicePaymentRegistrationService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IfirmaInvoicePaymentRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_from_bank_match_posts_payment_and_syncs_status(): void
    {
        $user = User::factory()->create();
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '320/7/2026',
            'ifirma_invoice_id' => '999001',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '320/7/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);
        $import = \App\Models\BankStatementImport::create([
            'original_filename' => 'test.csv',
            'stored_path' => 'bank/test.csv',
            'file_hash' => hash('sha256', 'test-fp-reg-1'),
            'status' => 'completed',
            'uploaded_by' => $user->id,
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-07-20',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'F-ra 320/7/2026',
            'is_incoming' => true,
            'fingerprint' => 'fp-reg-1',
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $order->id,
            'debt_case_id' => $case->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:320/7/2026', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getInvoice')
            ->twice()
            ->with('999001')
            ->andReturn(
                [
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '320/7/2026',
                        'Zaplacono' => 0,
                        'Brutto' => 365,
                        'FakturaId' => 999001,
                        'TerminPlatnosci' => now()->addDays(7)->toDateString(),
                    ],
                ],
                [
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '320/7/2026',
                        'Zaplacono' => 365,
                        'Brutto' => 365,
                        'FakturaId' => 999001,
                        'TerminPlatnosci' => now()->addDays(7)->toDateString(),
                    ],
                ]
            );
        $api->shouldReceive('registerInvoicePayment')
            ->once()
            ->with('999001', 365.0, '2026-07-20', 'prz_faktura_kraj')
            ->andReturn(['status' => 'success', 'data' => ['response' => ['Kod' => 0]]]);
        $api->shouldReceive('unwrapInvoicePayload')
            ->andReturnUsing(fn ($data) => is_array($data) ? $data : []);

        $statusService = new IfirmaInvoicePaymentStatusService($api);
        $service = new IfirmaInvoicePaymentRegistrationService($api, $statusService);

        $result = $service->registerFromAcceptedBankMatch($match, $user);

        $this->assertTrue($result['success']);
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_PAID, $result['status'] ?? null);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
            'user_id' => $user->id,
        ]);
        $case->refresh();
        $this->assertSame(IfirmaInvoicePaymentStatusService::STATUS_PAID, $case->ifirma_payment_status);
    }

    public function test_register_refuses_amount_mismatch(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '321/7/2026',
            'ifirma_invoice_id' => '999002',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '321/7/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);
        $import = \App\Models\BankStatementImport::create([
            'original_filename' => 'test.csv',
            'stored_path' => 'bank/test2.csv',
            'file_hash' => hash('sha256', 'test-fp-reg-2'),
            'status' => 'completed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-07-20',
            'amount' => 730,
            'currency' => 'PLN',
            'description' => 'F-ra 321/7/2026',
            'is_incoming' => true,
            'fingerprint' => 'fp-reg-2',
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $order->id,
            'debt_case_id' => $case->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_MEDIUM,
            'match_reasons' => ['amount_mismatch'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
        ]);

        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldNotReceive('registerInvoicePayment');
        $statusService = new IfirmaInvoicePaymentStatusService($api);
        $service = new IfirmaInvoicePaymentRegistrationService($api, $statusService);

        $result = $service->registerFromAcceptedBankMatch($match);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('zgodnej kwocie', $result['message']);
    }
}
