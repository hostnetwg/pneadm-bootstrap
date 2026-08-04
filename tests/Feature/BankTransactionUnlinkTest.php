<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\IfirmaApiService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BankTransactionUnlinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_can_unlink_accepted_bank_match_and_reopen_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie unlink',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '88/8/2026',
            'ifirma_invoice_id' => '999001',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_CLOSED,
            'invoice_number' => '88/8/2026',
            'amount_gross' => 365,
            'opened_at' => now()->subDays(5),
            'closed_at' => now()->subDay(),
            'closure_reason' => 'Zamknięto automatycznie — FV opłacona w iFirma po akceptacji przelewu z wyciągu.',
            'ifirma_payment_status' => IfirmaInvoicePaymentStatusService::STATUS_PAID,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_unlink.csv',
            'file_hash' => str_repeat('u', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'FV 88/8/2026 UNLINK TEST',
            'account_label' => 'Szkoła',
            'fingerprint' => str_repeat('v', 64),
            'is_incoming' => true,
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $case->id,
            'form_order_id' => $order->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now()->subDay(),
        ]);

        $this->mock(IfirmaApiService::class, function ($mock) {
            $invoiceRow = [
                'Kod' => 0,
                'PelnyNumer' => '88/8/2026',
                'Zaplacono' => 365,
                'Brutto' => 365,
                'DataWystawienia' => '2026-07-20',
            ];
            $mock->shouldReceive('getInvoice')
                ->andReturn([
                    'status' => 'success',
                    'data' => ['response' => $invoiceRow],
                ]);
            $mock->shouldReceive('unwrapInvoicePayload')
                ->andReturn($invoiceRow);
            $mock->shouldReceive('deleteInvoicePayment')
                ->once()
                ->andReturn([
                    'status' => 'error',
                    'status_code' => 405,
                    'message' => 'Method Not Allowed',
                ]);
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')->zeroOrMoreTimes()->andReturn([
                'status' => 'error',
                'message' => 'skip',
            ]);
        });

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Cofnij', false);
        $show->assertSee('bankPaymentUnlinkModal', false);

        $response = $this->actingAs($user)->post(
            route('accounting.collections.bank-matches.unlink', [$case, $match])
        );

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        $match->refresh();
        $case->refresh();

        $this->assertSame(BankTransactionMatch::STATUS_REJECTED, $match->status);
        $this->assertNull($match->accepted_at);
        $this->assertSame(DebtCase::STATUS_OPEN, $case->status);
        $this->assertNull($case->closed_at);
        $this->assertTrue(
            $case->actions()->where('action_type', DebtCaseAction::TYPE_BANK_UNMATCH)->exists()
        );
    }

    public function test_bank_import_can_unlink_accepted_match(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie import unlink',
            'product_price' => 200,
            'order_date' => now()->subDays(10),
            'invoice_number' => '89/8/2026',
            'ifirma_invoice_id' => '999002',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '89/8/2026',
            'amount_gross' => 200,
            'opened_at' => now()->subDays(5),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_import_unlink.csv',
            'file_hash' => str_repeat('w', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 200,
            'currency' => 'PLN',
            'description' => 'FV 89/8/2026 IMPORT UNLINK',
            'account_label' => 'Szkoła',
            'fingerprint' => str_repeat('x', 64),
            'is_incoming' => true,
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $case->id,
            'form_order_id' => $order->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_MEDIUM,
            'match_reasons' => ['invoice_number', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now()->subDay(),
        ]);

        $this->mock(IfirmaApiService::class, function ($mock) {
            $invoiceRow = [
                'Kod' => 0,
                'PelnyNumer' => '89/8/2026',
                'Zaplacono' => 0,
                'Brutto' => 200,
                'DataWystawienia' => '2026-07-20',
            ];
            $mock->shouldReceive('getInvoice')
                ->andReturn([
                    'status' => 'success',
                    'data' => ['response' => $invoiceRow],
                ]);
            $mock->shouldReceive('unwrapInvoicePayload')
                ->andReturn($invoiceRow);
            $mock->shouldReceive('deleteInvoicePayment')->never();
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')->zeroOrMoreTimes()->andReturn([
                'status' => 'error',
                'message' => 'skip',
            ]);
        });

        $show = $this->actingAs($user)->get(route('accounting.bank-imports.show', [
            'bankImport' => $import,
            'filter' => 'matched',
        ]));
        $show->assertOk();
        $show->assertSee('Cofnij przypisanie', false);

        $response = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.unlink', [$import, $match]),
            ['filter' => 'matched']
        );
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_REJECTED, $match->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
