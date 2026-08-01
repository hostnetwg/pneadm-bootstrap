<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputBufferLevel = ob_get_level();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    public function test_upload_creates_suggestions_and_accept_creates_action(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie CSV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '320/7/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);

        $file = UploadedFile::fake()->createWithContent('lista_operacji_test.csv', $fixture);

        $response = $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ]);

        $import = BankStatementImport::first();
        $this->assertNotNull($import);
        $response->assertRedirect(route('accounting.bank-imports.show', $import));

        $this->assertGreaterThan(0, $import->rows_incoming);

        $match = BankTransactionMatch::query()
            ->where('form_order_id', $order->id)
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->where('confidence', BankTransactionMatch::CONFIDENCE_HIGH)
            ->first();

        $this->assertNotNull($match, 'Expected high-confidence suggestion for FV 320/7/2026');

        $accept = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $match])
        );
        $accept->assertRedirect();

        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertNotNull($match->debt_case_id);

        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
            'user_id' => $user->id,
        ]);

        $case = DebtCase::find($match->debt_case_id);
        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Wpłaty z wyciągu', false);
        $show->assertSee('365,00', false);
    }

    public function test_index_page_requires_auth_and_renders(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('accounting.bank-imports.index'))
            ->assertOk()
            ->assertSee('Import wyciągu mBank', false);
    }

    public function test_show_page_has_unlinked_filter_for_transactions_without_suggestions(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        FormOrder::create([
            'product_name' => 'Szkolenie CSV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '320/7/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);
        $file = UploadedFile::fake()->createWithContent('lista_operacji_test.csv', $fixture);

        $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ])->assertRedirect();

        $import = BankStatementImport::first();

        $this->actingAs($user)
            ->get(route('accounting.bank-imports.show', ['bankImport' => $import, 'filter' => 'unlinked']))
            ->assertOk()
            ->assertSee('Bez powiązania', false)
            ->assertSee('Brak sugestii', false);
    }

    public function test_user_can_check_ifirma_status_for_bank_match_without_accepting(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie CSV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '320/7/2026',
            'ifirma_invoice_id' => '555001',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);
        $file = UploadedFile::fake()->createWithContent('lista_operacji_test.csv', $fixture);

        $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ])->assertRedirect();

        $import = BankStatementImport::first();
        $match = BankTransactionMatch::query()
            ->where('form_order_id', $order->id)
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->first();
        $this->assertNotNull($match);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('555001')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '320/7/2026',
                        'Zaplacono' => 365,
                        'Brutto' => 365,
                        'FakturaId' => 555001,
                    ],
                ]);
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
        });

        $response = $this->actingAs($user)->postJson(
            route('accounting.bank-imports.matches.ifirma-status', [$import, $match])
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID)
            ->assertJsonPath('can_accept_as_paid', true);

        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_SUGGESTED, $match->status);
        $this->assertDatabaseMissing('debt_case_actions', [
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
        ]);
    }

    public function test_accept_as_already_paid_in_ifirma_is_local_only(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie CSV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '320/7/2026',
            'ifirma_invoice_id' => '555001',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);
        $file = UploadedFile::fake()->createWithContent('lista_operacji_test.csv', $fixture);

        $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ])->assertRedirect();

        $import = BankStatementImport::first();
        $match = BankTransactionMatch::query()
            ->where('form_order_id', $order->id)
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->first();
        $this->assertNotNull($match);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldNotReceive('registerInvoicePayment');
        });

        $accept = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $match]),
            ['ifirma_already_paid' => '1']
        );

        $accept->assertRedirect();
        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
        ]);
        $this->assertDatabaseMissing('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
        ]);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'note' => 'Zaakceptowano wpłatę z wyciągu mBank: 365,00 PLN z dnia 2026-07-31 (FV 320/7/2026). Transakcja #'.$match->bank_transaction_id.'. iFirma przed akceptacją wskazywała fakturę jako opłaconą — nie rejestrowano nowej wpłaty w iFirma.',
        ]);
    }

    public function test_accept_with_ifirma_registration_flag_calls_payment_api(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie CSV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '320/7/2026',
            'ifirma_invoice_id' => '555001',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);
        $file = UploadedFile::fake()->createWithContent('lista_operacji_test.csv', $fixture);

        $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ])->assertRedirect();

        $import = BankStatementImport::first();
        $match = BankTransactionMatch::query()
            ->where('form_order_id', $order->id)
            ->where('status', BankTransactionMatch::STATUS_SUGGESTED)
            ->first();
        $this->assertNotNull($match);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->twice()
                ->with('555001')
                ->andReturn(
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '320/7/2026',
                            'Zaplacono' => 0,
                            'Brutto' => 365,
                            'FakturaId' => 555001,
                        ],
                    ],
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '320/7/2026',
                            'Zaplacono' => 365,
                            'Brutto' => 365,
                            'FakturaId' => 555001,
                        ],
                    ]
                );
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
            $mock->shouldReceive('registerInvoicePayment')
                ->once()
                ->andReturn(['status' => 'success', 'data' => ['response' => ['Kod' => 0]]]);
        });

        $accept = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $match]),
            ['register_ifirma_payment' => '1']
        );
        $accept->assertRedirect();
        $accept->assertSessionHas('success');

        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
        ]);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_SYNC,
        ]);
    }
}
