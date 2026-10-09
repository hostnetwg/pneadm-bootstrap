<?php

namespace Tests\Feature;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DebtReminderSmsTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputBufferLevel = ob_get_level();

        config([
            'services.smsapi.enabled' => true,
            'services.smsapi.token' => 'test-token',
            'services.smsapi.from' => '',
            'services.smsapi.test_phones' => ['+48 501 654 274', '+48 510 396 579'],
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: DebtCase}
     */
    private function caseWithOrder(array $orderOverrides = []): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create(array_merge([
            'product_name' => 'Szkolenie SMS',
            'product_price' => 365,
            'order_date' => now()->subDays(10),
            'invoice_number' => '100/9/2026',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'orderer_name' => 'Jan Kowalski',
            'orderer_email' => 'jan@example.test',
            'orderer_phone' => '501111222',
        ], $orderOverrides));

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'amount_gross' => 365,
            'invoice_number' => '100/9/2026',
            'due_date' => now()->subDays(2)->toDateString(),
            'assigned_to_id' => $user->id,
            'opened_at' => now(),
        ]);

        return [$user, $case];
    }

    public function test_collections_show_includes_sms_modal_and_orderer_phone(): void
    {
        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->get(route('accounting.collections.show', $case));

        $response->assertOk();
        $response->assertSee('debtReminderSmsModal', false);
        $response->assertSee('Wyślij SMS', false);
        $response->assertSee('+48 501 111 222', false);
        $response->assertSee('0 SMS-ów', false);
    }

    public function test_real_sms_send_logs_action_and_increments_counter(): void
    {
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [
                    ['id' => 'abc123', 'points' => 0.16, 'number' => '48501111222', 'status' => 'QUEUE'],
                ],
            ], 200),
        ]);

        [$user, $case] = $this->caseWithOrder();

        $response = $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'PNEDU: test SMS przypomnienia FV 100/9/2026',
            'send_target' => 'recipient',
            'test_phone' => '48501654274',
        ]);

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_SMS,
            'outcome' => 'sent',
            'channel' => 'sms',
        ]);
        $this->assertSame(1, $case->fresh()->reminderSmsSentCount());
        $this->assertSame(DebtCase::STATUS_IN_PROGRESS, $case->fresh()->status);

        $show = $this->actingAs($user)->get(route('accounting.collections.show', $case));
        $show->assertOk();
        $show->assertSee('1 SMS', false);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sms.do')
                && $request['to'] === '48501111222'
                && str_contains((string) $request['message'], 'FV 100/9/2026');
        });
    }

    public function test_test_sms_does_not_increase_counter(): void
    {
        Http::fake([
            'api.smsapi.pl/*' => Http::response([
                'count' => 1,
                'list' => [
                    ['id' => 'test1', 'points' => 0.16, 'number' => '48501654274', 'status' => 'QUEUE'],
                ],
            ], 200),
        ]);

        [$user, $case] = $this->caseWithOrder();

        $this->actingAs($user)->post(route('accounting.collections.send-reminder-sms', $case), [
            'template' => 'reminder',
            'body' => 'SMS testowy',
            'send_target' => 'test',
            'test_phone' => '48501654274',
        ])->assertRedirect(route('accounting.collections.show', $case));

        $this->assertSame(0, $case->fresh()->reminderSmsSentCount());
        $this->assertDatabaseHas('debt_case_actions', [
            'debt_case_id' => $case->id,
            'action_type' => DebtCaseAction::TYPE_SMS,
            'outcome' => 'test_sent',
        ]);
        $this->assertSame(DebtCase::STATUS_OPEN, $case->fresh()->status);
    }

    public function test_real_sms_requires_orderer_phone(): void
    {
        Http::fake();
        [$user, $case] = $this->caseWithOrder(['orderer_phone' => null]);

        $response = $this->actingAs($user)->from(route('accounting.collections.show', $case))->post(
            route('accounting.collections.send-reminder-sms', $case),
            [
                'template' => 'reminder',
                'body' => 'SMS',
                'send_target' => 'recipient',
            ]
        );

        $response->assertRedirect(route('accounting.collections.show', $case));
        $response->assertSessionHasErrors('body');
        Http::assertNothingSent();
    }
}
