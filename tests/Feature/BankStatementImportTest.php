<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
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

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')
                ->andReturn(['status' => 'not_found', 'message' => 'Brak FV w teście accept']);
        });

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

    public function test_index_shows_review_progress_and_coverage_gaps(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-07'));

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_lipiec.csv',
            'file_hash' => hash('sha256', 'gap-review-test'),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'rows_total' => 1,
            'rows_incoming' => 1,
            'rows_matched' => 0,
            'rows_duplicate' => 0,
        ]);

        \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-07-15',
            'amount' => 100,
            'currency' => 'PLN',
            'description' => 'Test wpływ bez decyzji',
            'fingerprint' => hash('sha256', 'gap-review-tx'),
            'is_incoming' => true,
        ]);

        $response = $this->actingAs($user)->get(route('accounting.bank-imports.index'));

        $response->assertOk();
        $response->assertSee('Do przeglądu: 1', false);
        $response->assertSee('Luki w okresach wyciągów', false);
        $response->assertSee('2026-08-01', false);

        \Carbon\Carbon::setTestNow();
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

    public function test_lookup_ifirma_status_works_for_order_without_match(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie ręczny kandydat',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '212/4/2026',
            'ifirma_invoice_id' => '660901',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_nip' => '5250001009',
        ]);

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'amount_gross' => 365,
            'currency' => 'PLN',
            'invoice_number' => '212/4/2026',
            'opened_at' => now()->subDays(5),
            'summary' => 'Sprawa do statusu iFirma',
        ]);

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-08'));

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('660901')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '212/4/2026',
                        'Zaplacono' => 0,
                        'Brutto' => 365,
                        'FakturaId' => 660901,
                        'DataWystawienia' => '2026-04-30',
                        'TerminPlatnosci' => '2026-05-14',
                    ],
                ]);
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
        });

        $response = $this->actingAs($user)->postJson(
            route('accounting.bank-imports.lookup-ifirma-status'),
            ['form_order_id' => $order->id]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_accept_as_paid', false)
            ->assertJsonPath('invoice_number', '212/4/2026')
            ->assertJsonPath('issue_date', '2026-04-30')
            ->assertJsonPath('due_date', '2026-05-14')
            ->assertJsonPath('days_overdue', 86)
            ->assertJsonPath('debt_case.id', $case->id)
            ->assertJsonPath('debt_case.url', route('accounting.collections.show', $case));

        \Carbon\Carbon::setTestNow();
    }

    public function test_accept_reuses_existing_closed_debt_case_instead_of_duplicate(): void
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

        $existing = DebtCase::create([
            'form_order_id' => $order->id,
            'created_by' => $user->id,
            'assigned_to_id' => $user->id,
            'status' => DebtCase::STATUS_CLOSED,
            'priority' => DebtCase::PRIORITY_NORMAL,
            'customer_segment' => DebtCase::SEGMENT_STANDARD,
            'risk_score' => 0,
            'relationship_score' => 0,
            'invoice_number' => $order->invoice_number,
            'amount_gross' => $order->product_price,
            'opened_at' => now()->subDays(5),
            'closed_at' => now()->subDay(),
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

        $accept = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $match])
        );
        $accept->assertRedirect();
        $accept->assertSessionHasNoErrors();

        $match->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertSame($existing->id, $match->debt_case_id);
        $this->assertSame(1, DebtCase::withTrashed()->where('form_order_id', $order->id)->count());
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $existing->id,
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
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
            'note' => \App\Services\DebtCaseAutoCloseService::CLOSURE_REASON,
        ]);
        $this->assertSame(DebtCase::STATUS_CLOSED, DebtCase::find($match->debt_case_id)?->status);
        $accept->assertSessionHas('success', function (string $value): bool {
            return str_contains($value, 'Sprawę zamknięto automatycznie');
        });
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
            // 1) auto-sync przy tworzeniu sprawy, 2–3) rejestracja wpłaty + ponowny sync
            $mock->shouldReceive('getInvoice')
                ->times(3)
                ->with('555001')
                ->andReturn(
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '320/7/2026',
                            'Zaplacono' => 0,
                            'Brutto' => 365,
                            'FakturaId' => 555001,
                            'TerminPlatnosci' => '2026-08-01',
                        ],
                    ],
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
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
        ]);
        $this->assertSame(DebtCase::STATUS_CLOSED, DebtCase::find($match->debt_case_id)?->status);
        $accept->assertSessionHas('success', function (string $value): bool {
            return str_contains($value, 'Sprawę zamknięto automatycznie');
        });
    }

    public function test_plain_local_accept_does_not_auto_close(): void
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
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')
                ->andReturn(['status' => 'not_found', 'message' => 'Brak FV w teście local accept']);
        });

        $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $match])
        )->assertRedirect();

        $match->refresh();
        $case = DebtCase::find($match->debt_case_id);
        $this->assertNotNull($case);
        $this->assertNotSame(DebtCase::STATUS_CLOSED, $case->status);
        $this->assertDatabaseMissing('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
        ]);
    }

    public function test_manual_link_from_bank_import_lookup_and_accept(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie ręczne od przelewu',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '901/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Spółdzielnia Socjalna Razem dla Gminy Kurzętnik',
            'buyer_nip' => '7410001111',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '901/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_manual.csv',
            'file_hash' => str_repeat('f', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $transaction = \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'SPOLDZIELNIA SOCJALNA RAZEM DLA GMINY KURZETNIK SZKOLENIE',
            'account_label' => 'Kurzętnik',
            'fingerprint' => str_repeat('1', 64),
            'is_incoming' => true,
        ]);

        $lookup = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => 'Kurzętnik',
        ]));
        $lookup->assertOk();
        $lookup->assertJsonPath('cases.0.id', $case->id);

        $show = $this->actingAs($user)->get(route('accounting.bank-imports.show', [
            'bankImport' => $import,
            'filter' => 'unlinked',
        ]));
        $show->assertOk();
        $show->assertSee('Powiąż ręcznie ze sprawą lub zamówieniem', false);

        $link = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.link-case', [$import, $transaction]),
            [
                'debt_case_id' => $case->id,
                'filter' => 'unlinked',
            ]
        );
        $link->assertRedirect();
        $link->assertSessionHas('success');

        $match = BankTransactionMatch::first();
        $this->assertNotNull($match);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertSame($case->id, $match->debt_case_id);
        $this->assertContains('manual_case_link', $match->match_reasons);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
        ]);
    }

    public function test_manual_link_from_bank_import_to_order_without_active_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $instructor = \App\Models\Instructor::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.nowak@example.test',
            'is_active' => true,
        ]);

        $courseStart = now()->setTimezone('Europe/Warsaw')->addDays(5)->setTime(10, 0);
        $course = \App\Models\Course::create([
            'title' => 'Szkolenie bez sprawy',
            'description' => 'Test',
            'start_date' => $courseStart,
            'end_date' => $courseStart->copy()->addHours(4),
            'instructor_id' => $instructor->id,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $orderDate = now()->subDays(15);
        $order = FormOrder::create([
            'product_id' => $course->id,
            'product_name' => 'Szkolenie bez sprawy',
            'product_price' => 420,
            'order_date' => $orderDate,
            'invoice_number' => '902/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Firma Testowa Bez Sprawy Sp. z o.o.',
            'buyer_nip' => '5251112223',
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_order_only.csv',
            'file_hash' => str_repeat('2', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $transaction = \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 420,
            'currency' => 'PLN',
            'description' => 'FIRMA TESTOWA BEZ SPRAWY PRZELEW',
            'account_label' => 'Firma Testowa',
            'fingerprint' => str_repeat('3', 64),
            'is_incoming' => true,
        ]);

        $lookup = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => 'Bez Sprawy',
        ]));
        $lookup->assertOk();
        $lookup->assertJsonPath('cases', []);
        $lookup->assertJsonPath('orders.0.id', $order->id);
        $lookup->assertJsonPath('orders.0.order_date', $orderDate->format('Y-m-d'));
        $lookup->assertJsonPath('orders.0.course_date', $courseStart->format('Y-m-d'));

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')
                ->andReturn(['status' => 'not_found', 'message' => 'Brak FV w teście']);
        });

        $link = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.link-case', [$import, $transaction]),
            [
                'form_order_id' => $order->id,
                'filter' => 'unlinked',
            ]
        );
        $link->assertRedirect();
        $link->assertSessionHas('success');

        $match = BankTransactionMatch::first();
        $this->assertNotNull($match);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertSame($order->id, $match->form_order_id);
        $this->assertNotNull($match->debt_case_id);
        $this->assertDatabaseHas('debt_cases', [
            'id' => $match->debt_case_id,
            'form_order_id' => $order->id,
        ]);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
        ]);
    }

    public function test_lookup_cases_matches_recipient_address_case_insensitively(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie Bobowo',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '117/7/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Gmina Bobowo',
            'recipient_name' => 'Publiczna Szkoła Podstawowa w Bobowie',
            'recipient_address' => 'ul. Gimnazjalna 20',
            'recipient_postal_code' => '83-212',
            'recipient_city' => 'Bobowo',
        ]);

        $byStreet = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => 'ul. GIMNAZJALNA 20',
        ]));
        $byStreet->assertOk();
        $byStreet->assertJsonPath('orders.0.id', $order->id);

        $byFragment = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => 'GIMNAZJALNA',
        ]));
        $byFragment->assertOk();
        $byFragment->assertJsonPath('orders.0.id', $order->id);

        $byCity = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => 'bobowo',
        ]));
        $byCity->assertOk();
        $byCity->assertJsonPath('orders.0.id', $order->id);
    }

    public function test_lookup_cases_exact_invoice_does_not_match_fragment(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $exact = FormOrder::create([
            'product_name' => 'Exact FV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '63/6/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $fragment = FormOrder::create([
            'product_name' => 'Fragment FV',
            'product_price' => 365,
            'order_date' => now()->subDays(9),
            'invoice_number' => '263/6/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $partial = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => '63/6/2026',
            'exact' => '0',
        ]));
        $partial->assertOk();
        $partialIds = collect($partial->json('orders'))->pluck('id');
        $this->assertTrue($partialIds->contains($exact->id));
        $this->assertTrue($partialIds->contains($fragment->id));

        $exactLookup = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => '63/6/2026',
            'exact' => '1',
        ]));
        $exactLookup->assertOk();
        $exactLookup->assertJsonCount(1, 'orders');
        $exactLookup->assertJsonPath('orders.0.id', $exact->id);
    }

    public function test_lookup_cases_finds_order_by_numeric_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie po ID',
            'product_price' => 365,
            'order_date' => now()->subDays(3),
            'invoice_number' => '304/6/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Ewelina Kulesza',
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => (string) $order->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $order->id);
    }

    public function test_lookup_cases_finds_order_by_cancelled_invoice_number_in_notes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie po anulowanej FV',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '140/7/2026',
            'notes' => 'Anulowano FV 138/7/2026, wystawiono poprawną 140/7/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Szkoła Podstawowa w Parchowie',
        ]);

        $byNotes = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => '138/7/2026',
        ]));
        $byNotes->assertOk();
        $byNotes->assertJsonPath('orders.0.id', $order->id);

        $exact = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => '138/7/2026',
            'exact' => '1',
        ]));
        $exact->assertOk();
        $exact->assertJsonPath('orders.0.id', $order->id);
    }

    public function test_lookup_cases_finds_order_by_cancelled_invoice_number_in_invoice_notes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie po anulowanej FV w uwagach',
            'product_price' => 365,
            'order_date' => now()->subDays(8),
            'invoice_number' => '150/7/2026',
            'invoice_notes' => 'Anulowano poprzednią FV 138/7/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-cases', [
            'q' => '138/7/2026',
        ]));

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $order->id);
    }

    public function test_lookup_order_preview_returns_order_payload(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $instructor = \App\Models\Instructor::create([
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'email' => 'ewa.test@example.test',
            'is_active' => true,
        ]);

        $courseStart = now()->setTimezone('Europe/Warsaw')->addDays(3)->setTime(11, 0);
        $course = \App\Models\Course::create([
            'title' => 'Podgląd zamówienia',
            'description' => 'Test',
            'start_date' => $courseStart,
            'end_date' => $courseStart->copy()->addHours(3),
            'instructor_id' => $instructor->id,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $order = FormOrder::create([
            'product_id' => $course->id,
            'product_name' => 'Podgląd zamówienia',
            'product_price' => 510,
            'order_date' => now()->subDays(7),
            'invoice_number' => '111/8/2026',
            'buyer_name' => 'Nabywca Podgląd Sp. z o.o.',
            'buyer_nip' => '5259998887',
            'buyer_city' => 'Warszawa',
        ]);

        $response = $this->actingAs($user)->getJson(route('accounting.bank-imports.lookup-order-preview', [
            'form_order_id' => $order->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('order.id', $order->id);
        $response->assertJsonPath('order.invoice', '111/8/2026');
        $response->assertJsonPath('order.buyer_name', 'Nabywca Podgląd Sp. z o.o.');
        $response->assertJsonPath('order.buyer_nip', '5259998887');
        $response->assertJsonPath('order.course_id', $course->id);
        $this->assertStringContainsString($courseStart->format('d.m.Y'), $response->json('order.product'));
    }

    public function test_manual_link_from_bank_import_to_order_registers_ifirma_payment_and_closes_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie opłacone ręcznie',
            'product_price' => 365,
            'order_date' => now()->subDays(2),
            'invoice_number' => '115/7/2026',
            'ifirma_invoice_id' => '777115',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Klient Ręczny Sp. z o.o.',
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_manual_ifirma.csv',
            'file_hash' => str_repeat('7', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $transaction = \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-07-23',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZELEW WEWNĘTRZNY PRZYCHODZĄCY',
            'account_label' => 'Firma Testowa',
            'fingerprint' => str_repeat('8', 64),
            'is_incoming' => true,
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            // 1) auto-sync przy tworzeniu sprawy, 2–3) rejestracja wpłaty + ponowny sync
            $mock->shouldReceive('getInvoice')
                ->times(3)
                ->with('777115')
                ->andReturn(
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '115/7/2026',
                            'Zaplacono' => 0,
                            'Brutto' => 365,
                            'FakturaId' => 777115,
                            'TerminPlatnosci' => '2026-08-01',
                        ],
                    ],
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '115/7/2026',
                            'Zaplacono' => 0,
                            'Brutto' => 365,
                            'FakturaId' => 777115,
                        ],
                    ],
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '115/7/2026',
                            'Zaplacono' => 365,
                            'Brutto' => 365,
                            'FakturaId' => 777115,
                        ],
                    ]
                );
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
            $mock->shouldReceive('registerInvoicePayment')
                ->once()
                ->with('777115', 365.0, '2026-07-23', 'prz_faktura_kraj')
                ->andReturn(['status' => 'success', 'data' => ['response' => ['Kod' => 0]]]);
        });

        $response = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.link-case', [$import, $transaction]),
            [
                'form_order_id' => $order->id,
                'register_ifirma_payment' => '1',
                'filter' => 'unlinked',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', function (string $value): bool {
            return str_contains($value, 'Zarejestrowano wpłatę w iFirma')
                && str_contains($value, 'Sprawę zamknięto automatycznie');
        });

        $match = BankTransactionMatch::first();
        $this->assertNotNull($match);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertSame($order->id, $match->form_order_id);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
        ]);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $match->debt_case_id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
        ]);
        $this->assertSame(DebtCase::STATUS_CLOSED, DebtCase::find($match->debt_case_id)?->status);
    }

    public function test_accepted_bank_match_can_retry_ifirma_payment_registration(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie retry iFirma',
            'product_price' => 365,
            'order_date' => now()->subDays(2),
            'invoice_number' => '116/7/2026',
            'ifirma_invoice_id' => '777116',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_retry_ifirma.csv',
            'file_hash' => str_repeat('9', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $transaction = \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-07-23',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZELEW BEZ FV',
            'fingerprint' => str_repeat('a', 64),
            'is_incoming' => true,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '116/7/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $transaction->id,
            'form_order_id' => $order->id,
            'debt_case_id' => $case->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
            'match_reasons' => ['manual_case_link', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->twice()
                ->with('777116')
                ->andReturn(
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '116/7/2026',
                            'Zaplacono' => 0,
                            'Brutto' => 365,
                            'FakturaId' => 777116,
                        ],
                    ],
                    [
                        'status' => 'success',
                        'data' => [
                            'PelnyNumer' => '116/7/2026',
                            'Zaplacono' => 365,
                            'Brutto' => 365,
                            'FakturaId' => 777116,
                        ],
                    ]
                );
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
            $mock->shouldReceive('registerInvoicePayment')
                ->once()
                ->with('777116', 365.0, '2026-07-23', 'prz_faktura_kraj')
                ->andReturn(['status' => 'success', 'data' => ['response' => ['Kod' => 0]]]);
        });

        $response = $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.register-ifirma-payment', [$import, $match]),
            [
                'filter' => 'accepted',
                'preview' => $transaction->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', function (string $value): bool {
            return str_contains($value, 'Zarejestrowano wpłatę w iFirma')
                && str_contains($value, 'Sprawę zamknięto automatycznie');
        });
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_PAYMENT,
        ]);
        $this->assertSame(DebtCase::STATUS_CLOSED, $case->fresh()->status);
    }

    public function test_user_can_bulk_ignore_paynow_gateway_payouts_without_touching_school_transfers(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('p', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        $payNow = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(3)->toDateString(),
            'amount' => 1500.55,
            'currency' => 'PLN',
            'description' => 'MELEMENTS SPÓŁKA AKCYJNA, /OPF/X///// WYPŁATA ŚRODKÓW NR PON-MWB-BZ7-RAU ZA DZIEŃ 28.04.2026',
            'account_label' => 'MELEMENTS SPÓŁKA AKCYJNA',
            'fingerprint' => str_repeat('q', 64),
            'is_incoming' => true,
        ]);
        $schoolWithoutInvoice = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'SZKOLA PODSTAWOWA NR 1 W KURZETNIKU UL MICKIEWICZA 7 ZA SZKOLENIE',
            'account_label' => 'Szkoła Podstawowa nr 1',
            'fingerprint' => str_repeat('r', 64),
            'is_incoming' => true,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.bank-imports.show', $import));
        $show->assertOk();
        $show->assertSee('Ignoruj wypłaty PayNow', false);
        $show->assertSee('bankImportIgnorePayNowModal', false);

        $response = $this->actingAs($user)->post(route('accounting.bank-imports.ignore-paynow-payouts', $import));
        $response->assertRedirect(route('accounting.bank-imports.show', ['bankImport' => $import, 'filter' => 'paynow']));
        $response->assertSessionHas('success');

        $this->assertTrue(
            $payNow->fresh()->matches()->where('status', BankTransactionMatch::STATUS_IGNORED)->exists()
        );
        $this->assertSame(
            [BankTransactionMatch::REASON_GATEWAY_PAYOUT_PAYNOW],
            $payNow->fresh()->matches()->where('status', BankTransactionMatch::STATUS_IGNORED)->first()->match_reasons
        );
        $this->assertFalse(
            $schoolWithoutInvoice->fresh()->matches()->where('status', BankTransactionMatch::STATUS_IGNORED)->exists()
        );

        $payNowTab = $this->actingAs($user)->get(route('accounting.bank-imports.show', [
            'bankImport' => $import,
            'filter' => 'paynow',
        ]));
        $payNowTab->assertOk();
        $payNowTab->assertSee('PayNow (1)', false);
        $payNowTab->assertSee('MELEMENTS', false);
        $payNowTab->assertDontSee('SZKOLA PODSTAWOWA NR 1', false);

        $ignoredTab = $this->actingAs($user)->get(route('accounting.bank-imports.show', [
            'bankImport' => $import,
            'filter' => 'ignored',
        ]));
        $ignoredTab->assertOk();
        $ignoredTab->assertSee('Ignorowane (0)', false);
        $ignoredTab->assertDontSee('MELEMENTS', false);
    }

    public function test_preview_modal_shows_remaining_high_matches_to_accept(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $orderA = FormOrder::create([
            'product_name' => 'Szkolenie A',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '101/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $orderB = FormOrder::create([
            'product_name' => 'Szkolenie B',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '102/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_queue.csv',
            'file_hash' => str_repeat('u', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        $txA = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'FV 101/8/2026',
            'fingerprint' => str_repeat('v', 64),
            'is_incoming' => true,
        ]);
        $txB = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'FV 102/8/2026',
            'fingerprint' => str_repeat('w', 64),
            'is_incoming' => true,
        ]);

        BankTransactionMatch::create([
            'bank_transaction_id' => $txA->id,
            'form_order_id' => $orderA->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:101/8/2026'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $txB->id,
            'form_order_id' => $orderB->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:102/8/2026'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.bank-imports.show', [
            'bankImport' => $import,
            'filter' => 'high',
        ]));

        $show->assertOk();
        $show->assertSee('Do akceptacji: 2 High', false);
        $show->assertSee('id="bankTxPreviewRemainingBadge"', false);
        $show->assertSee('id="bankTxPreviewPositionMeta"', false);
        $show->assertSee('data-queue-filter-count="2"', false);
        $show->assertSee('data-queue-unmatched="2"', false);
    }

    public function test_accept_split_package_allocates_two_invoices_locally(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $orderA = FormOrder::create([
            'product_name' => 'Szkolenie A',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '357/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $orderB = FormOrder::create([
            'product_name' => 'Szkolenie B',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '511/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('s', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);

        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 730,
            'currency' => 'PLN',
            'description' => 'Faktura Vat nr 357/8/2026, 511/8/2026',
            'fingerprint' => str_repeat('t', 64),
            'is_incoming' => true,
        ]);

        $matchA = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderA->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:357/8/2026', 'multi_invoice_sum_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);
        $matchB = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderB->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:511/8/2026', 'multi_invoice_sum_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);

        $response = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.accept-package', [$import, $tx])
        );
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $matchA->refresh();
        $matchB->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $matchA->status);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $matchB->status);
        $this->assertEquals(365.0, (float) $matchA->allocated_amount);
        $this->assertEquals(365.0, (float) $matchB->allocated_amount);
        $this->assertNotNull($matchA->debt_case_id);
        $this->assertNotNull($matchB->debt_case_id);
        $this->assertNotSame($matchA->debt_case_id, $matchB->debt_case_id);

        $tx->load('matches');
        $this->assertTrue($tx->isFullyAllocated());
        $this->assertEquals(0.0, $tx->remainingAllocatableAmount());
    }

    public function test_accept_split_package_can_register_ifirma_payments(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $orderA = FormOrder::create([
            'product_name' => 'Szkolenie A',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '357/9/2026',
            'ifirma_invoice_id' => '111',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $orderB = FormOrder::create([
            'product_name' => 'Szkolenie B',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '511/9/2026',
            'ifirma_invoice_id' => '222',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('w', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);

        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 730,
            'currency' => 'PLN',
            'description' => 'Faktura Vat nr 357/9/2026, 511/9/2026',
            'fingerprint' => str_repeat('x', 64),
            'is_incoming' => true,
        ]);

        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderA->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:357/9/2026', 'multi_invoice_sum_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderB->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:511/9/2026', 'multi_invoice_sum_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);

        $this->mock(\App\Services\IfirmaInvoicePaymentRegistrationService::class, function ($mock) {
            $mock->shouldReceive('registerFromAcceptedBankMatch')
                ->twice()
                ->andReturn([
                    'success' => true,
                    'message' => 'Zarejestrowano wpłatę w iFirma (test).',
                    'status' => 'oplacone',
                ]);
        });

        $this->partialMock(\App\Services\DebtCaseAutoCloseService::class, function ($mock) {
            $mock->shouldReceive('closeIfFullyPaid')->andReturn(false);
        });

        $response = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.accept-package', [$import, $tx]),
            ['register_ifirma_payment' => '1']
        );
        $response->assertRedirect();
        $response->assertSessionHas('success', function (string $value): bool {
            return str_contains($value, 'Zarejestrowano wpłaty w iFirma: 2/2');
        });

        $this->assertSame(2, $tx->fresh()->acceptedMatches()->count());
    }

    public function test_manual_second_link_uses_remaining_amount(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $orderA = FormOrder::create([
            'product_name' => 'Szkolenie A',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '901/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $orderB = FormOrder::create([
            'product_name' => 'Szkolenie B',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '902/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $caseB = DebtCase::create([
            'form_order_id' => $orderB->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '902/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('u', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);

        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 730,
            'currency' => 'PLN',
            'description' => 'Przelew zbiorczy 901 i 902',
            'fingerprint' => str_repeat('v', 64),
            'is_incoming' => true,
        ]);

        $first = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderA->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_MEDIUM,
            'match_reasons' => ['invoice_number:901/8/2026', 'amount_mismatch'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);

        $this->actingAs($user)->post(
            route('accounting.bank-imports.matches.accept', [$import, $first])
        )->assertRedirect();

        $first->refresh();
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $first->status);
        $this->assertEquals(365.0, (float) $first->allocated_amount);

        $tx->load('matches');
        $this->assertEquals(365.0, $tx->remainingAllocatableAmount());

        $second = $this->actingAs($user)->post(
            route('accounting.bank-imports.transactions.link-case', [$import, $tx]),
            ['debt_case_id' => $caseB->id]
        );
        $second->assertRedirect();
        $second->assertSessionHas('success');

        $tx->load('matches');
        $this->assertCount(2, $tx->acceptedMatches());
        $this->assertTrue($tx->isFullyAllocated());
        $this->assertDatabaseHas('bank_transaction_matches', [
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $caseB->id,
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'allocated_amount' => 365.00,
        ]);
    }

    public function test_show_page_renders_when_package_suggestions_present(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $orderA = FormOrder::create([
            'product_name' => 'Pakiet A',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '357/8/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $orderB = FormOrder::create([
            'product_name' => 'Pakiet B',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '511/8/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'package_show.csv',
            'stored_path' => 'bank-statements/package_show.csv',
            'file_hash' => hash('sha256', 'package-show'),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'period_from' => '2026-08-14',
            'period_to' => '2026-08-16',
            'rows_total' => 1,
            'rows_incoming' => 1,
            'rows_matched' => 1,
            'rows_duplicate' => 0,
        ]);

        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-08-14',
            'amount' => 730,
            'currency' => 'PLN',
            'description' => 'Fa 357/8/2026 Fa 511/8/2026 PRZELEW',
            'fingerprint' => hash('sha256', 'package-show-tx'),
            'is_incoming' => true,
        ]);

        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderA->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:357/8/2026', 'multi_invoice_sum_match', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $orderB->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['invoice_number:511/8/2026', 'multi_invoice_sum_match', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_SUGGESTED,
        ]);

        $this->actingAs($user)
            ->get(route('accounting.bank-imports.show', $import))
            ->assertOk()
            ->assertSee('Pakiet podziału', false);
    }

    public function test_reupload_of_same_file_does_not_create_empty_import(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/bank/mbank_sample.csv'));
        $this->assertNotFalse($fixture);
        $file = UploadedFile::fake()->createWithContent('lista_operacji_dup.csv', $fixture);

        $first = $this->actingAs($user)->post(route('accounting.bank-imports.store'), [
            'csv_file' => $file,
        ]);
        $first->assertRedirect();
        $this->assertSame(1, BankStatementImport::count());
        $importId = BankStatementImport::first()->id;

        $secondFile = UploadedFile::fake()->createWithContent('lista_operacji_dup.csv', $fixture);
        $second = $this->actingAs($user)->from(route('accounting.bank-imports.index'))->post(route('accounting.bank-imports.store'), [
            'csv_file' => $secondFile,
        ]);
        $second->assertRedirect(route('accounting.bank-imports.index'));
        $second->assertSessionHasErrors('csv_file');
        $this->assertSame(1, BankStatementImport::count());
        $this->assertSame($importId, BankStatementImport::first()->id);
    }

    public function test_can_delete_import_without_accepted_links_but_not_with_accepted(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $empty = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'empty.csv',
            'stored_path' => null,
            'file_hash' => hash('sha256', 'empty'),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'rows_total' => 28,
            'rows_incoming' => 0,
            'rows_matched' => 0,
            'rows_duplicate' => 28,
        ]);

        $this->actingAs($user)
            ->delete(route('accounting.bank-imports.destroy', $empty))
            ->assertRedirect(route('accounting.bank-imports.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('bank_statement_imports', ['id' => $empty->id]);

        $order = FormOrder::create([
            'product_name' => 'Usuwanie',
            'product_price' => 100,
            'order_date' => now()->subDays(3),
            'invoice_number' => '9/8/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'amount_gross' => 100,
            'invoice_number' => '9/8/2026',
            'assigned_to_id' => $user->id,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'accepted.csv',
            'file_hash' => hash('sha256', 'accepted'),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'rows_total' => 1,
            'rows_incoming' => 1,
            'rows_matched' => 1,
            'rows_duplicate' => 0,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-08-14',
            'amount' => 100,
            'currency' => 'PLN',
            'description' => 'Fa 9/8/2026',
            'fingerprint' => hash('sha256', 'accepted-tx'),
            'is_incoming' => true,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $order->id,
            'debt_case_id' => $case->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'allocated_amount' => 100,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('accounting.bank-imports.destroy', $import))
            ->assertRedirect(route('accounting.bank-imports.index'))
            ->assertSessionHas('warning');
        $this->assertDatabaseHas('bank_statement_imports', ['id' => $import->id]);
    }

    public function test_two_transfers_can_cover_one_case_but_overpayment_is_blocked(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie składkowe',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '553/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '553/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'partials.csv',
            'file_hash' => hash('sha256', 'partials-cover'),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        $tx1 = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-08-12',
            'amount' => 255.50,
            'currency' => 'PLN',
            'description' => '553/8/2026 pierwsza rata',
            'fingerprint' => hash('sha256', 'partial-tx-1'),
            'is_incoming' => true,
        ]);
        $tx2 = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-08-13',
            'amount' => 109.50,
            'currency' => 'PLN',
            'description' => '553/8/2026 dopłata',
            'fingerprint' => hash('sha256', 'partial-tx-2'),
            'is_incoming' => true,
        ]);
        $txOver = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => '2026-08-14',
            'amount' => 50,
            'currency' => 'PLN',
            'description' => '553/8/2026 nadpłata',
            'fingerprint' => hash('sha256', 'partial-tx-over'),
            'is_incoming' => true,
        ]);

        $service = app(\App\Services\Bank\BankStatementImportService::class);
        $first = $service->manuallyLinkTransactionToDebtCase($tx1, $case, $user->id);
        $this->assertEquals(255.50, (float) $first->allocated_amount);
        $case->refresh();
        $this->assertEquals(109.50, (float) $case->remainingBankAllocatableAmount());

        $second = $service->manuallyLinkTransactionToDebtCase($tx2, $case, $user->id);
        $this->assertEquals(109.50, (float) $second->allocated_amount);
        $case->unsetRelation('bankTransactionMatches');
        $case->refresh();
        $this->assertTrue($case->isFullyCoveredByBankPayments());
        $this->assertEquals(0.0, (float) $case->remainingBankAllocatableAmount());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('w pełni pokryta');
        $service->manuallyLinkTransactionToDebtCase($txOver, $case, $user->id);
    }
}
