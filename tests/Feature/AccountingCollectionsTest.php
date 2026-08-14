<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtCaseContact;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_collections_index_shows_invoice_lookup_for_creating_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.index'));

        $response->assertOk();
        $response->assertSee('Znajdź ID zamówienia po numerze faktury / KSeF', false);
        $response->assertSee('id="createInvoiceLookup"', false);
        $response->assertSee('Utwórz sprawę', false);
        $response->assertSee('createInvoiceLookupBtn', false);
        $response->assertSee('Niezamknięte', false);
        $response->assertSee('Pokaż niezamknięte', false);
        $response->assertSee('sort=id', false);
        $response->assertSee('sort=invoice', false);
        $response->assertSee('sort=due_date', false);
        $response->assertSee('bi-arrow-down-up', false);
    }

    public function test_collections_index_sorts_by_invoice_year_month_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $cases = [];
        foreach ([
            '5/7/2026',
            '260/7/2026',
            '10/1/2025',
            '1/8/2026',
        ] as $invoice) {
            $order = FormOrder::create([
                'product_name' => 'Sort FV '.$invoice,
                'product_price' => 100,
                'order_date' => now()->subDays(5),
                'invoice_number' => $invoice,
            ]);
            $cases[$invoice] = DebtCase::create([
                'form_order_id' => $order->id,
                'status' => DebtCase::STATUS_OPEN,
                'invoice_number' => $invoice,
                'opened_at' => now(),
            ]);
        }

        $asc = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'active',
            'sort' => 'invoice',
            'dir' => 'asc',
        ]));
        $asc->assertOk();
        $ascBody = $asc->getContent();
        $pos2025 = strpos($ascBody, 'FV: 10/1/2025');
        $posJuly5 = strpos($ascBody, 'FV: 5/7/2026');
        $posJuly260 = strpos($ascBody, 'FV: 260/7/2026');
        $posAug = strpos($ascBody, 'FV: 1/8/2026');
        $this->assertNotFalse($pos2025);
        $this->assertNotFalse($posJuly5);
        $this->assertNotFalse($posJuly260);
        $this->assertNotFalse($posAug);
        $this->assertTrue($pos2025 < $posJuly5);
        $this->assertTrue($posJuly5 < $posJuly260);
        $this->assertTrue($posJuly260 < $posAug);

        $desc = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'active',
            'sort' => 'invoice',
            'dir' => 'desc',
        ]));
        $descBody = $desc->getContent();
        $this->assertTrue(strpos($descBody, 'FV: 1/8/2026') < strpos($descBody, 'FV: 10/1/2025'));
    }

    public function test_collections_index_sorts_by_due_date_and_case_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $earlyOrder = FormOrder::create([
            'product_name' => 'Wcześniejszy termin',
            'product_price' => 100,
            'order_date' => now()->subDays(20),
            'invoice_number' => '11/8/2026',
        ]);
        $early = DebtCase::create([
            'form_order_id' => $earlyOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '11/8/2026',
            'due_date' => '2026-07-01',
            'opened_at' => now(),
        ]);

        $lateOrder = FormOrder::create([
            'product_name' => 'Późniejszy termin',
            'product_price' => 100,
            'order_date' => now()->subDays(10),
            'invoice_number' => '12/8/2026',
        ]);
        $late = DebtCase::create([
            'form_order_id' => $lateOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '12/8/2026',
            'due_date' => '2026-08-01',
            'opened_at' => now(),
        ]);

        $byDue = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'active',
            'sort' => 'due_date',
            'dir' => 'asc',
        ]));
        $byDue->assertOk();
        $dueBody = $byDue->getContent();
        $this->assertTrue(
            strpos($dueBody, 'FV: 11/8/2026') < strpos($dueBody, 'FV: 12/8/2026')
        );

        $byIdDesc = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'active',
            'sort' => 'id',
            'dir' => 'desc',
        ]));
        $byIdDesc->assertOk();
        $idBody = $byIdDesc->getContent();
        $this->assertTrue($late->id > $early->id);
        $this->assertTrue(
            strpos($idBody, 'FV: 12/8/2026') < strpos($idBody, 'FV: 11/8/2026')
        );
    }

    public function test_collections_index_defaults_to_active_and_hides_closed(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $openOrder = FormOrder::create([
            'product_name' => 'Otwarta',
            'product_price' => 100,
            'order_date' => now()->subDays(5),
            'invoice_number' => '100/8/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $openOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '100/8/2026',
            'opened_at' => now(),
        ]);

        $closedOrder = FormOrder::create([
            'product_name' => 'Zamknięta',
            'product_price' => 200,
            'order_date' => now()->subDays(10),
            'invoice_number' => '200/8/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $closedOrder->id,
            'status' => DebtCase::STATUS_CLOSED,
            'invoice_number' => '200/8/2026',
            'opened_at' => now()->subDays(8),
            'closed_at' => now()->subDay(),
        ]);

        $default = $this->actingAs($user)->get(route('accounting.collections.index'));
        $default->assertOk();
        $default->assertSee('100/8/2026');
        $default->assertDontSee('200/8/2026');

        $activeCard = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'active',
        ]));
        $activeCard->assertOk();
        $activeCard->assertSee('100/8/2026');
        $activeCard->assertDontSee('200/8/2026');

        $all = $this->actingAs($user)->get(route('accounting.collections.index', [
            'status' => 'all',
        ]));
        $all->assertOk();
        $all->assertSee('100/8/2026');
        $all->assertSee('200/8/2026');
    }

    public function test_user_can_create_debt_case_from_form_order(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie testowe',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '43/7/2026',
            'ifirma_invoice_id' => '998877',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'orderer_email' => 'sekretariat@example.test',
            'buyer_nip' => '1234567890',
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('998877')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '43/7/2026',
                        'Zaplacono' => 0,
                        'Brutto' => 365,
                        'DataWystawienia' => '2026-07-01',
                        'TerminPlatnosci' => '2026-07-15',
                    ],
                ]);
        });

        $response = $this->actingAs($user)->post(route('accounting.collections.store'), [
            'form_order_id' => $order->id,
            'summary' => 'Pierwsza sprawa do weryfikacji.',
        ]);

        $case = DebtCase::first();

        $response->assertRedirect(route('accounting.collections.show', $case));
        $this->assertDatabaseHas('debt_cases', [
            'form_order_id' => $order->id,
            'invoice_number' => '43/7/2026',
            'status' => DebtCase::STATUS_OPEN,
            'created_by' => $user->id,
            'assigned_to_id' => $user->id,
        ]);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_CASE_OPENED,
            'user_id' => $user->id,
        ]);
        $case->refresh();
        $this->assertSame(\App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE, $case->ifirma_payment_status);
        $this->assertNotNull($case->ifirma_synced_at);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_SYNC,
            'outcome' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE,
        ]);
    }

    public function test_user_cannot_create_debt_case_without_invoice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Bez FV',
            'product_price' => 100,
            'order_date' => now(),
            'invoice_number' => null,
            'ifirma_invoice_id' => null,
        ]);

        $response = $this->actingAs($user)->from(route('form-orders.show', $order))->post(route('accounting.collections.store'), [
            'form_order_id' => $order->id,
        ]);

        $response->assertRedirect(route('form-orders.show', $order));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('debt_cases', 0);
    }

    public function test_user_can_soft_delete_mistaken_debt_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Pomyłka',
            'product_price' => 200,
            'order_date' => now(),
            'invoice_number' => '99/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('accounting.collections.destroy', $case), [
            'reason' => 'Utworzono przez pomyłkę',
        ]);

        $response->assertRedirect(route('accounting.collections.index'));
        $this->assertSoftDeleted('debt_cases', ['id' => $case->id]);
    }

    public function test_user_cannot_soft_delete_case_with_accepted_bank_match(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Z przelewem',
            'product_price' => 200,
            'order_date' => now(),
            'invoice_number' => '100/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $import = \App\Models\BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_soft_delete.csv',
            'file_hash' => str_repeat('s', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = \App\Models\BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 200,
            'currency' => 'PLN',
            'description' => 'FV 100/8/2026',
            'account_label' => 'Szkoła',
            'fingerprint' => str_repeat('t', 64),
            'is_incoming' => true,
        ]);
        \App\Models\BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $case->id,
            'form_order_id' => $order->id,
            'status' => \App\Models\BankTransactionMatch::STATUS_ACCEPTED,
            'confidence' => \App\Models\BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link'],
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('accounting.collections.destroy', $case));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted('debt_cases', ['id' => $case->id]);
    }

    public function test_user_can_add_payment_promise_action(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie z FV',
            'product_price' => 500,
            'order_date' => now()->subDays(25),
            'invoice_number' => '44/7/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('accounting.collections.actions.store', $case), [
            'action_type' => DebtCaseAction::TYPE_PAYMENT_PROMISE,
            'promised_payment_at' => now()->addDays(3)->toDateString(),
            'next_action_at' => now()->addDays(4)->format('Y-m-d H:i'),
            'note' => 'Klient obiecał płatność.',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $case->refresh();

        $this->assertSame(DebtCase::STATUS_PROMISED, $case->status);
        $this->assertSame($user->id, $case->assigned_to_id);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_PAYMENT_PROMISE,
            'note' => 'Klient obiecał płatność.',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_delete_debt_case_contact(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie kontakt',
            'product_price' => 300,
            'order_date' => now()->subDays(10),
            'invoice_number' => '55/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        $contact = DebtCaseContact::create([
            'debt_case_id' => $case->id,
            'created_by' => $user->id,
            'contact_type' => DebtCaseContact::TYPE_EMAIL,
            'value' => 'kontakt@example.test',
            'source' => 'ręcznie',
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('case-contact-delete-btn', false);
        $show->assertSee('kontakt@example.test', false);

        $response = $this->actingAs($user)->delete(route('accounting.collections.contacts.destroy', [$case, $contact]));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('debt_case_contacts', ['id' => $contact->id]);
        $this->assertSame($user->id, $case->fresh()->assigned_to_id);
    }

    public function test_collections_filter_finds_case_by_linked_form_order_invoice_number(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $matchingOrder = FormOrder::create([
            'product_name' => 'Szkolenie szukane po FV',
            'product_price' => 500,
            'order_date' => now()->subDays(20),
            'invoice_number' => '349/6/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $matchingOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $otherOrder = FormOrder::create([
            'product_name' => 'Szkolenie niepasujące',
            'product_price' => 500,
            'order_date' => now()->subDays(20),
            'invoice_number' => '350/6/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $otherOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.index', [
            'search' => '349/6/2026',
            'status' => '',
            'segment' => '',
        ]));

        $response->assertOk();
        $response->assertSee('349/6/2026');
        $response->assertDontSee('350/6/2026');
    }

    public function test_collections_filter_finds_case_by_form_order_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $matchingOrder = FormOrder::create([
            'product_name' => 'Szkolenie ID search',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '111/8/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $matchingOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $otherOrder = FormOrder::create([
            'product_name' => 'Szkolenie inne',
            'product_price' => 500,
            'order_date' => now()->subDays(20),
            'invoice_number' => '222/8/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $otherOrder->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.index', [
            'search' => (string) $matchingOrder->id,
            'status' => 'active',
            'segment' => '',
        ]));

        $response->assertOk();
        $response->assertSee('111/8/2026');
        $response->assertDontSee('222/8/2026');
    }

    public function test_settings_update_records_admin_user_in_history(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin Windykacja',
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie audyt',
            'product_price' => 400,
            'order_date' => now()->subDays(20),
            'invoice_number' => '10/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'priority' => DebtCase::PRIORITY_NORMAL,
            'customer_segment' => DebtCase::SEGMENT_STANDARD,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('accounting.collections.update', $case), [
            'status' => DebtCase::STATUS_IN_PROGRESS,
            'priority' => DebtCase::PRIORITY_HIGH,
            'customer_segment' => DebtCase::SEGMENT_STANDARD,
            'summary' => 'Przejęte do obsługi.',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $case->refresh();

        $this->assertSame($user->id, $case->assigned_to_id);
        $this->assertSame(DebtCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_STATUS_UPDATE,
            'user_id' => $user->id,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Admin Windykacja');
        $show->assertSee('Zmiana ustawień');
    }

    public function test_loyal_customer_is_marked_as_vip_segment_on_case_create(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $order = FormOrder::create([
                'product_name' => 'Historyczne szkolenie '.$i,
                'product_price' => 600,
                'order_date' => now()->subMonths($i),
                'invoice_number' => "{$i}/1/2026",
                'orderer_email' => 'vip@example.test',
                'buyer_nip' => '1234567890',
            ]);

            FormOrderParticipant::create([
                'form_order_id' => $order->id,
                'participant_firstname' => 'Anna',
                'participant_lastname' => 'VIP',
                'participant_email' => 'vip@example.test',
                'is_primary' => true,
            ]);
        }

        $newOrder = FormOrder::create([
            'product_name' => 'Nowe szkolenie',
            'product_price' => 700,
            'order_date' => now()->subDays(25),
            'invoice_number' => '99/7/2026',
            'invoice_payment_delay' => 14,
            'orderer_email' => 'vip@example.test',
            'buyer_nip' => '1234567890',
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('findSalesInvoiceByPelnyNumer')
                ->andReturn(['status' => 'not_found', 'message' => 'Brak FV w teście VIP']);
        });

        $this->actingAs($user)->post(route('accounting.collections.store'), [
            'form_order_id' => $newOrder->id,
        ]);

        $case = DebtCase::firstWhere('form_order_id', $newOrder->id);

        $this->assertTrue($case->isVip());
        $this->assertNotNull($case->vip_reason);
        $this->assertGreaterThanOrEqual(60, $case->relationship_score);
    }

    public function test_collections_show_uses_current_profile_for_vip_alert_reason(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie po zmianie reguł VIP',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '77/8/2026',
            'buyer_nip' => '9999999999',
            'recipient_nip' => '1111111111',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'customer_segment' => DebtCase::SEGMENT_VIP,
            'relationship_score' => 80,
            'vip_reason' => '13 powiązanych zamówień, łączna wartość 4 152,00 zł',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertDontSee('VIP / lojalny klient', false);
        $response->assertDontSee('Powód: 13 powiązanych zamówień', false);
        $response->assertSee('Łącznie: 1 zamówień', false);
    }

    public function test_collections_show_explains_related_order_link_reasons(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie bieżące',
            'product_price' => 400,
            'order_date' => now()->subDays(10),
            'invoice_number' => '101/8/2026',
            'buyer_nip' => '9999999999',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'sekretariat@example.test',
        ]);
        $relatedOrder = FormOrder::create([
            'product_name' => 'Szkolenie powiązane',
            'product_price' => 500,
            'order_date' => now()->subDays(40),
            'invoice_number' => '50/7/2026',
            'buyer_nip' => '8888888888',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'inna@example.test',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        $relatedCase = DebtCase::create([
            'form_order_id' => $relatedOrder->id,
            'status' => DebtCase::STATUS_IN_PROGRESS,
            'opened_at' => now()->subDays(5),
        ]);
        DebtCase::create([
            'form_order_id' => FormOrder::create([
                'product_name' => 'Szkolenie zamknięte powiązane',
                'product_price' => 300,
                'order_date' => now()->subDays(90),
                'invoice_number' => '10/5/2026',
                'buyer_nip' => '7777777777',
                'recipient_nip' => '1111111111',
            ])->id,
            'status' => DebtCase::STATUS_CLOSED,
            'opened_at' => now()->subDays(80),
            'closed_at' => now()->subDays(60),
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertSee('Historia powiązanych zamówień', false);
        $response->assertSee('Identyfikacja:', false);
        $response->assertSee('NIP odbiorcy: 1111111111', false);
        $response->assertSee('ta sprawa', false);
        $response->assertSee('To zamówienie należy do obecnie otwartej karty sprawy.', false);
        $response->assertSee('Powiązane, bo ma ten sam NIP odbiorcy', false);
        $response->assertSee('Szkolenie powiązane', false);
        $response->assertSee('#'.$relatedCase->id.' · '.$relatedCase->statusLabel(), false);
        $response->assertSee('Inna niezamknięta sprawa windykacyjna dla tego zamówienia.', false);
        $response->assertSee(route('accounting.collections.show', $relatedCase), false);
        $response->assertSee('Zamknięte', false);
    }

    public function test_user_can_sync_ifirma_payment_status_on_debt_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie sync',
            'product_price' => 365,
            'order_date' => now()->subDays(30),
            'invoice_number' => '50/7/2026',
            'ifirma_invoice_id' => '123456',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '50/7/2026',
            'invoice_date' => '2026-06-30',
            'due_date' => '2026-07-14',
            'opened_at' => now(),
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('123456')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '50/7/2026',
                        'Zaplacono' => 0,
                        'Brutto' => 365,
                        'DataWystawienia' => '2026-07-13',
                        'TerminPlatnosci' => '2026-07-27',
                    ],
                ]);
        });

        $response = $this->actingAs($user)->post(route('accounting.collections.sync-ifirma', $case));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $case->refresh();
        $order->refresh();
        $this->assertSame(\App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE, $case->ifirma_payment_status);
        $this->assertSame('2026-07-13', $case->invoice_date?->toDateString());
        $this->assertSame('2026-07-27', $case->due_date?->toDateString());
        $this->assertSame('2026-07-13', $order->invoice_issue_date?->toDateString());
        $this->assertSame('2026-07-27', $order->invoice_due_date?->toDateString());
        $this->assertNotNull($case->ifirma_synced_at);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_SYNC,
            'outcome' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE,
            'user_id' => $user->id,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Odśwież status z iFirma', false);
        $show->assertSee('Przeterminowana', false);
        $show->assertSee('Wystawiono:', false);
        $show->assertSee('13.07.2026', false);
        $show->assertSee('Termin płatności:', false);
        $show->assertSee('27.07.2026', false);
    }

    public function test_sync_ifirma_paid_auto_closes_open_debt_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie sync paid',
            'product_price' => 365,
            'order_date' => now()->subDays(30),
            'invoice_number' => '51/7/2026',
            'ifirma_invoice_id' => '654321',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '51/7/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $this->partialMock(\App\Services\IfirmaApiService::class, function ($mock) {
            $mock->shouldReceive('getInvoice')
                ->once()
                ->with('654321')
                ->andReturn([
                    'status' => 'success',
                    'data' => [
                        'PelnyNumer' => '51/7/2026',
                        'Zaplacono' => 365,
                        'Brutto' => 365,
                        'DataWystawienia' => '2026-07-01',
                        'TerminPlatnosci' => '2026-07-15',
                    ],
                ]);
            $mock->shouldReceive('unwrapInvoicePayload')->andReturnUsing(fn ($d) => is_array($d) ? $d : []);
        });

        $response = $this->actingAs($user)->post(route('accounting.collections.sync-ifirma', $case));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');
        $this->assertStringContainsString(
            'Sprawę zamknięto automatycznie',
            (string) session('success')
        );

        $case->refresh();
        $this->assertSame(DebtCase::STATUS_CLOSED, $case->status);
        $this->assertSame(\App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID, $case->ifirma_payment_status);
        $this->assertNotNull($case->closed_at);
        $this->assertSame(
            \App\Services\DebtCaseAutoCloseService::CLOSURE_REASON_IFIRMA_SYNC,
            $case->closure_reason
        );
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_CLOSE,
            'note' => \App\Services\DebtCaseAutoCloseService::CLOSURE_REASON_IFIRMA_SYNC,
        ]);
    }

    public function test_user_can_manually_link_unlinked_bank_transfer_from_case(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie ręczne',
            'product_price' => 365,
            'order_date' => now()->subDays(30),
            'invoice_number' => '77/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '77/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('a', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $transaction = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'SPECJALNY OSRODEK KURZETNIK PRZELEW ZA SZKOLENIE',
            'account_label' => 'Specjalny Ośrodek w Kurzętniku',
            'counterparty_account' => '00112233445566778899001122',
            'fingerprint' => str_repeat('b', 64),
            'is_incoming' => true,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Szukaj przelewu', false);
        $show->assertSee('bank_unlinked_only', false);
        $show->assertSee('case-fill-bank-search', false);
        $show->assertSee('bankTransferSearchClearBtn', false);
        $show->assertDontSee('SPECJALNY OSRODEK KURZETNIK', false);

        $search = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => 'KURZETNIK',
            'bank_amount' => '365.00',
        ]));
        $search->assertOk();
        $search->assertJsonPath('candidates.0.id', $transaction->id);
        $search->assertJsonFragment(['description_short' => 'SPECJALNY OSRODEK KURZETNIK PRZELEW ZA SZKOLENIE']);

        $response = $this->actingAs($user)->post(route('accounting.collections.bank-transactions.link', [$case, $transaction]));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $match = BankTransactionMatch::first();
        $this->assertNotNull($match);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $match->status);
        $this->assertSame($transaction->id, $match->bank_transaction_id);
        $this->assertSame($case->id, $match->debt_case_id);
        $this->assertContains('manual_case_link', $match->match_reasons);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_BANK_MATCH,
            'user_id' => $user->id,
        ]);
        $this->assertNotSame(DebtCase::STATUS_CLOSED, $case->fresh()->status);
    }

    public function test_bank_transfer_search_defaults_to_after_order_date(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie filtr daty',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '88/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '88/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('c', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(20)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZELEW PRZED ZAMOWIENIEM ALPHA',
            'account_label' => 'Przed',
            'fingerprint' => str_repeat('d', 64),
            'is_incoming' => true,
        ]);
        BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZELEW PO ZAMOWIENIU BETA',
            'account_label' => 'Po',
            'fingerprint' => str_repeat('e', 64),
            'is_incoming' => true,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('bank_after_order', false);
        $show->assertDontSee('PRZELEW PO ZAMOWIENIU BETA', false);
        $show->assertDontSee('PRZELEW PRZED ZAMOWIENIEM ALPHA', false);

        $withDateFilter = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => 'PRZELEW',
            'bank_amount' => '365.00',
            'bank_after_order' => '1',
        ]));
        $withDateFilter->assertOk();
        $withDateFilter->assertJsonCount(1, 'candidates');
        $withDateFilter->assertJsonFragment(['description_short' => 'PRZELEW PO ZAMOWIENIU BETA']);

        $withoutDateFilter = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => 'PRZELEW',
            'bank_amount' => '365.00',
            'bank_after_order' => '0',
        ]));
        $withoutDateFilter->assertOk();
        $withoutDateFilter->assertJsonCount(2, 'candidates');
        $withoutDateFilter->assertJsonFragment(['description_short' => 'PRZELEW PO ZAMOWIENIU BETA']);
        $withoutDateFilter->assertJsonFragment(['description_short' => 'PRZELEW PRZED ZAMOWIENIEM ALPHA']);
    }

    public function test_bank_transfer_search_requires_query_and_does_not_run_on_show(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie bez auto-search',
            'product_price' => 200,
            'order_date' => now()->subDays(5),
            'invoice_number' => '99/8/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '99/8/2026',
            'amount_gross' => 200,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('f', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 200,
            'currency' => 'PLN',
            'description' => 'WPLYW BEZ WYSZUKIWANIA XYZ',
            'account_label' => 'Test',
            'fingerprint' => str_repeat('g', 64),
            'is_incoming' => true,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Brak wyników — wykonaj wyszukiwanie.', false);
        $show->assertDontSee('WPLYW BEZ WYSZUKIWANIA XYZ', false);
        $show->assertSee('id="caseBankPaymentsCollapse"', false);
        $show->assertSee('id="caseBankPaymentsHeader"', false);
        $show->assertSee('aria-expanded="false"', false);
        $show->assertSee('class="collapse"', false);

        $tooShort = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => 'X',
        ]));
        $tooShort->assertStatus(422);
    }

    public function test_bank_transfer_search_can_include_already_linked_transfers(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie linked search',
            'product_price' => 250,
            'order_date' => now()->subDays(8),
            'invoice_number' => '77/8/2026',
            'ksef_number' => '7392137630-20260802-ABCDEF000077-11',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '77/8/2026',
            'ksef_number' => '7392137630-20260802-ABCDEF000077-11',
            'amount_gross' => 250,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('h', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        $linked = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 250,
            'currency' => 'PLN',
            'description' => 'FV 77/8/2026 JUZ PRZYPISANY',
            'account_label' => 'Linked',
            'fingerprint' => str_repeat('i', 64),
            'is_incoming' => true,
        ]);
        $free = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 250,
            'currency' => 'PLN',
            'description' => 'FV 77/8/2026 WOLNY WPLYW',
            'account_label' => 'Free',
            'fingerprint' => str_repeat('j', 64),
            'is_incoming' => true,
        ]);

        BankTransactionMatch::create([
            'bank_transaction_id' => $linked->id,
            'debt_case_id' => $case->id,
            'form_order_id' => $order->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('data-bank-search-text="77/8/2026"', false);
        $show->assertSee('data-bank-search-text="7392137630-20260802-ABCDEF000077-11"', false);
        $show->assertSee('Szukaj tylko w nieprzypisanych', false);

        $unlinkedOnly = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => '77/8/2026',
            'bank_unlinked_only' => '1',
            'bank_after_order' => '0',
        ]));
        $unlinkedOnly->assertOk();
        $unlinkedOnly->assertJsonCount(1, 'candidates');
        $unlinkedOnly->assertJsonPath('candidates.0.id', $free->id);
        $unlinkedOnly->assertJsonPath('candidates.0.is_linkable', true);

        $allTransfers = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => '77/8/2026',
            'bank_unlinked_only' => '0',
            'bank_after_order' => '0',
        ]));
        $allTransfers->assertOk();
        $allTransfers->assertJsonCount(2, 'candidates');

        $ids = collect($allTransfers->json('candidates'))->pluck('id');
        $this->assertTrue($ids->contains($linked->id));
        $this->assertTrue($ids->contains($free->id));

        $linkedPayload = collect($allTransfers->json('candidates'))->firstWhere('id', $linked->id);
        $this->assertFalse($linkedPayload['is_linkable']);
        $this->assertSame(BankTransactionMatch::STATUS_ACCEPTED, $linkedPayload['link_status']);
    }

    public function test_bank_transfer_exact_search_does_not_match_invoice_number_fragment(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie exact FV',
            'product_price' => 365,
            'order_date' => now()->subDays(40),
            'invoice_number' => '63/6/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '63/6/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('k', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 2,
            'rows_incoming' => 2,
        ]);

        $wrongInvoice = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(2)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'OPLATA ZA FAKTURA VAT 263/6/2026 SZKOLENIE',
            'account_label' => 'Inna szkola',
            'fingerprint' => str_repeat('l', 64),
            'is_incoming' => true,
        ]);
        $exactInvoice = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDay()->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'OPLATA ZA FAKTURA VAT 63/6/2026 SZKOLENIE',
            'account_label' => 'Wlasciwa szkola',
            'fingerprint' => str_repeat('m', 64),
            'is_incoming' => true,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('bank_search_exact', false);
        $show->assertSee('Szukaj dokładnie wpisanego numeru (bez dopasowania fragmentu)', false);

        $partial = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => '63/6/2026',
            'bank_search_exact' => '0',
            'bank_after_order' => '0',
        ]));
        $partial->assertOk();
        $partialIds = collect($partial->json('candidates'))->pluck('id');
        $this->assertTrue($partialIds->contains($wrongInvoice->id));
        $this->assertTrue($partialIds->contains($exactInvoice->id));

        $exact = $this->actingAs($user)->getJson(route('accounting.collections.bank-transactions.search', [
            'debtCase' => $case,
            'bank_search' => '63/6/2026',
            'bank_search_exact' => '1',
            'bank_after_order' => '0',
        ]));
        $exact->assertOk();
        $exact->assertJsonCount(1, 'candidates');
        $exact->assertJsonPath('candidates.0.id', $exactInvoice->id);
    }

    public function test_collections_show_overdue_label_depends_on_ifirma_status_and_payment_date(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $makeCase = function (array $caseAttrs, array $orderAttrs = []) {
            $order = FormOrder::create(array_merge([
                'product_name' => 'Termin test',
                'product_price' => 365,
                'order_date' => now()->subDays(40),
                'invoice_number' => 'due-'.uniqid(),
                'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
                'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            ], $orderAttrs));

            return DebtCase::create(array_merge([
                'form_order_id' => $order->id,
                'status' => DebtCase::STATUS_OPEN,
                'invoice_number' => $order->invoice_number,
                'amount_gross' => 365,
                'due_date' => now()->subDays(10)->toDateString(),
                'opened_at' => now(),
            ], $caseAttrs));
        };

        $overdueCase = $makeCase([
            'ifirma_payment_status' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE,
        ]);
        $overdueShow = $this->actingAs($user)->get(route('accounting.collections.show', $overdueCase));
        $overdueShow->assertOk();
        $overdueShow->assertSee('text-danger">(10 dni po terminie)', false);

        $paidWithoutPaymentDate = $makeCase([
            'ifirma_payment_status' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID,
            'status' => DebtCase::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
        $paidNoDateShow = $this->actingAs($user)->get(route('accounting.collections.show', $paidWithoutPaymentDate));
        $paidNoDateShow->assertOk();
        $paidNoDateShow->assertDontSee('po terminie', false);

        $paidLate = $makeCase([
            'ifirma_payment_status' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID,
            'status' => DebtCase::STATUS_CLOSED,
            'closed_at' => now(),
            'due_date' => now()->subDays(20)->toDateString(),
        ]);
        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('n', 64),
            'source' => 'mbank',
            'status' => 'parsed',
            'rows_total' => 1,
            'rows_incoming' => 1,
        ]);
        $tx = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'operation_date' => now()->subDays(5)->toDateString(),
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'WPLATA PO TERMINIE',
            'account_label' => 'Klient',
            'fingerprint' => str_repeat('o', 64),
            'is_incoming' => true,
        ]);
        BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'debt_case_id' => $paidLate->id,
            'form_order_id' => $paidLate->form_order_id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['manual_case_link'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $paidLateShow = $this->actingAs($user)->get(route('accounting.collections.show', $paidLate));
        $paidLateShow->assertOk();
        $paidLateShow->assertSee('text-muted fw-normal">(15 dni po terminie)', false);
        $paidLateShow->assertDontSee('text-danger">(15 dni po terminie)', false);

        $unpaidPastDue = $makeCase([
            'ifirma_payment_status' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_UNPAID,
        ]);
        $unpaidShow = $this->actingAs($user)->get(route('accounting.collections.show', $unpaidPastDue));
        $unpaidShow->assertOk();
        $unpaidShow->assertDontSee('po terminie', false);
    }

    public function test_collections_show_has_previous_and_next_case_links(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $older = FormOrder::create([
            'product_name' => 'Starsza',
            'product_price' => 100,
            'order_date' => now()->subDays(40),
            'invoice_number' => '1/nav/2026',
        ]);
        $middle = FormOrder::create([
            'product_name' => 'Środkowa',
            'product_price' => 200,
            'order_date' => now()->subDays(30),
            'invoice_number' => '2/nav/2026',
        ]);
        $newer = FormOrder::create([
            'product_name' => 'Nowsza',
            'product_price' => 300,
            'order_date' => now()->subDays(20),
            'invoice_number' => '3/nav/2026',
        ]);

        $olderCase = DebtCase::create(['form_order_id' => $older->id, 'status' => DebtCase::STATUS_OPEN, 'opened_at' => now()]);
        $middleCase = DebtCase::create(['form_order_id' => $middle->id, 'status' => DebtCase::STATUS_OPEN, 'opened_at' => now()]);
        $newerCase = DebtCase::create(['form_order_id' => $newer->id, 'status' => DebtCase::STATUS_OPEN, 'opened_at' => now()]);

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $middleCase));

        $response->assertOk();
        $response->assertSee('Poprzednia', false);
        $response->assertSee('Następna', false);
        $response->assertSee('case_nav_active_only', false);
        $response->assertSee('tylko niezamknięte', false);
        $response->assertSee(route('accounting.collections.show', $newerCase), false);
        $response->assertSee(route('accounting.collections.show', $olderCase), false);
        $response->assertSee('data-prev-active-url="'.route('accounting.collections.show', $newerCase).'"', false);
        $response->assertSee('data-next-active-url="'.route('accounting.collections.show', $olderCase).'"', false);
    }

    public function test_collections_show_nav_skips_closed_cases_in_active_urls(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $oldest = FormOrder::create([
            'product_name' => 'Najstarsza open',
            'product_price' => 100,
            'order_date' => now()->subDays(50),
            'invoice_number' => '10/nav/2026',
        ]);
        $closed = FormOrder::create([
            'product_name' => 'Zamknięta pomiędzy',
            'product_price' => 150,
            'order_date' => now()->subDays(40),
            'invoice_number' => '11/nav/2026',
        ]);
        $current = FormOrder::create([
            'product_name' => 'Bieżąca open',
            'product_price' => 200,
            'order_date' => now()->subDays(30),
            'invoice_number' => '12/nav/2026',
        ]);
        $newestClosed = FormOrder::create([
            'product_name' => 'Nowsza zamknięta',
            'product_price' => 250,
            'order_date' => now()->subDays(20),
            'invoice_number' => '13/nav/2026',
        ]);
        $newestOpen = FormOrder::create([
            'product_name' => 'Najnowsza open',
            'product_price' => 300,
            'order_date' => now()->subDays(10),
            'invoice_number' => '14/nav/2026',
        ]);

        $oldestCase = DebtCase::create(['form_order_id' => $oldest->id, 'status' => DebtCase::STATUS_OPEN, 'opened_at' => now()]);
        $closedBetween = DebtCase::create(['form_order_id' => $closed->id, 'status' => DebtCase::STATUS_CLOSED, 'opened_at' => now(), 'closed_at' => now()]);
        $currentCase = DebtCase::create(['form_order_id' => $current->id, 'status' => DebtCase::STATUS_OPEN, 'opened_at' => now()]);
        $newerClosed = DebtCase::create(['form_order_id' => $newestClosed->id, 'status' => DebtCase::STATUS_CLOSED, 'opened_at' => now(), 'closed_at' => now()]);
        $newestOpenCase = DebtCase::create(['form_order_id' => $newestOpen->id, 'status' => DebtCase::STATUS_IN_PROGRESS, 'opened_at' => now()]);

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $currentCase));

        $response->assertOk();
        $response->assertSee('data-prev-active-url="'.route('accounting.collections.show', $newestOpenCase).'"', false);
        $response->assertSee('data-next-active-url="'.route('accounting.collections.show', $oldestCase).'"', false);
        $response->assertSee('data-prev-all-url="'.route('accounting.collections.show', $newerClosed).'"', false);
        $response->assertSee('data-next-all-url="'.route('accounting.collections.show', $closedBetween).'"', false);
        $response->assertSee('accounting_collections_nav_active_only_v1', false);
        $response->assertSee('accounting_collections_bank_filters_v1', false);
    }

    public function test_collections_show_displays_course_with_link_date_and_instructor(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $instructor = \App\Models\Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.test',
            'is_active' => true,
        ]);

        $start = now()->setTimezone('Europe/Warsaw')->setTime(9, 0);
        $course = \App\Models\Course::create([
            'title' => 'Windykacja Testowe Szkolenie',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(4),
            'instructor_id' => $instructor->id,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $order = FormOrder::create([
            'product_id' => $course->id,
            'product_name' => 'Windykacja Testowe Szkolenie',
            'product_price' => 365,
            'order_date' => now()->subDays(20),
            'invoice_number' => '88/7/2026',
        ]);

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '88/7/2026',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertSee('Szkolenie', false);
        $response->assertSee('Windykacja Testowe Szkolenie', false);
        $response->assertSee(route('courses.show', $course->id), false);
        $response->assertSee('Jan Kowalski', false);
        $response->assertSee($course->start_date->timezone(config('app.timezone'))->format('d.m.Y'), false);
    }

    public function test_collections_can_register_ifirma_payment_for_accepted_bank_match(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Szkolenie iFirma retry',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '208/8/2026',
            'ifirma_invoice_id' => '208001',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '208/8/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $import = BankStatementImport::create([
            'uploaded_by' => $user->id,
            'original_filename' => 'lista_operacji_test.csv',
            'file_hash' => str_repeat('y', 64),
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
            'description' => 'Przelew zbiorczy FV 208',
            'fingerprint' => str_repeat('z', 64),
            'is_incoming' => true,
        ]);
        $match = BankTransactionMatch::create([
            'bank_transaction_id' => $tx->id,
            'form_order_id' => $order->id,
            'debt_case_id' => $case->id,
            'confidence' => BankTransactionMatch::CONFIDENCE_HIGH,
            'match_reasons' => ['split_allocation', 'amount_match'],
            'status' => BankTransactionMatch::STATUS_ACCEPTED,
            'allocated_amount' => 365,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Wpłata iFirma', false);
        $show->assertSee('365,00', false);
        $show->assertSee('z przelewu 730,00', false);

        $this->mock(\App\Services\IfirmaInvoicePaymentRegistrationService::class, function ($mock) {
            $mock->shouldReceive('registerFromAcceptedBankMatch')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Zarejestrowano wpłatę w iFirma (test).',
                    'status' => 'oplacone',
                ]);
        });
        $this->partialMock(\App\Services\DebtCaseAutoCloseService::class, function ($mock) {
            $mock->shouldReceive('closeIfFullyPaid')->once()->andReturn(false);
        });

        $response = $this->actingAs($user)->post(
            route('accounting.collections.bank-matches.register-ifirma', [$case, $match])
        );
        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');
    }
}
