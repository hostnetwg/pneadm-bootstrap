<?php

namespace Tests\Feature;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
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
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'orderer_email' => 'sekretariat@example.test',
            'buyer_nip' => '1234567890',
        ]);

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

        $this->actingAs($user)->post(route('accounting.collections.store'), [
            'form_order_id' => $newOrder->id,
        ]);

        $case = DebtCase::firstWhere('form_order_id', $newOrder->id);

        $this->assertTrue($case->isVip());
        $this->assertNotNull($case->vip_reason);
        $this->assertGreaterThanOrEqual(60, $case->relationship_score);
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
                        'Zaplacono' => 365,
                        'Brutto' => 365,
                        'TerminPlatnosci' => now()->subDays(10)->toDateString(),
                    ],
                ]);
        });

        $response = $this->actingAs($user)->post(route('accounting.collections.sync-ifirma', $case));

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $case->refresh();
        $this->assertSame(\App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID, $case->ifirma_payment_status);
        $this->assertNotNull($case->ifirma_synced_at);
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_IFIRMA_SYNC,
            'outcome' => \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID,
            'user_id' => $user->id,
        ]);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('Odśwież status z iFirma', false);
        $show->assertSee('Opłacona', false);
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
        $response->assertSee(route('accounting.collections.show', $newerCase), false);
        $response->assertSee(route('accounting.collections.show', $olderCase), false);
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
}
