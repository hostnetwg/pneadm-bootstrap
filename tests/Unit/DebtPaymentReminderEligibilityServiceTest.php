<?php

namespace Tests\Unit;

use App\Models\DebtCase;
use App\Models\DebtCaseAction;
use App\Models\DebtPaymentReminderCampaign;
use App\Models\FormOrder;
use App\Models\User;
use App\Services\Debt\DebtPaymentReminderEligibilityService;
use App\Services\IfirmaInvoicePaymentStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtPaymentReminderEligibilityServiceTest extends TestCase
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

    public function test_qualifies_open_overdue_case_with_real_due_date(): void
    {
        [$case] = $this->makeCase();

        $reason = app(DebtPaymentReminderEligibilityService::class)->exclusionReason(
            $case,
            DebtPaymentReminderCampaign::AUDIENCE_STANDARD,
            true,
            false,
        );

        $this->assertNull($reason);
    }

    public function test_excludes_promised_and_vip_on_standard_tab(): void
    {
        [$promised] = $this->makeCase(['status' => DebtCase::STATUS_PROMISED]);
        [$vip] = $this->makeCase(['manual_vip' => true, 'invoice_number' => '2/9/2026']);

        $service = app(DebtPaymentReminderEligibilityService::class);

        $this->assertSame(
            DebtPaymentReminderEligibilityService::REASON_STATUS,
            $service->exclusionReason($promised, DebtPaymentReminderCampaign::AUDIENCE_STANDARD, true, false),
        );
        $this->assertSame(
            DebtPaymentReminderEligibilityService::REASON_VIP_AUDIENCE,
            $service->exclusionReason($vip, DebtPaymentReminderCampaign::AUDIENCE_STANDARD, true, false),
        );
        $this->assertNull(
            $service->exclusionReason($vip, DebtPaymentReminderCampaign::AUDIENCE_VIP, true, false),
        );
    }

    public function test_excludes_do_not_auto_dun_and_contact_today(): void
    {
        $user = User::factory()->create();
        [$blocked] = $this->makeCase(['do_not_auto_dun' => true, 'invoice_number' => '3/9/2026']);
        [$contacted] = $this->makeCase(['invoice_number' => '4/9/2026']);
        DebtCaseAction::create([
            'debt_case_id' => $contacted->id,
            'user_id' => $user->id,
            'action_type' => DebtCaseAction::TYPE_EMAIL,
            'channel' => 'email',
            'outcome' => 'sent',
            'happened_at' => now(),
            'note' => 'test',
        ]);

        $service = app(DebtPaymentReminderEligibilityService::class);
        $this->assertSame(
            DebtPaymentReminderEligibilityService::REASON_DO_NOT_AUTO_DUN,
            $service->exclusionReason($blocked, DebtPaymentReminderCampaign::AUDIENCE_STANDARD, true, false),
        );
        $this->assertSame(
            DebtPaymentReminderEligibilityService::REASON_CONTACTED_TODAY,
            $service->exclusionReason($contacted->fresh(), DebtPaymentReminderCampaign::AUDIENCE_STANDARD, true, false),
        );
    }

    public function test_excludes_when_due_is_only_yesterday(): void
    {
        [$case] = $this->makeCase([
            'due_date' => '2026-10-18',
            'invoice_due_date' => '2026-10-18',
            'invoice_number' => '5/9/2026',
        ]);

        $reason = app(DebtPaymentReminderEligibilityService::class)->exclusionReason(
            $case,
            DebtPaymentReminderCampaign::AUDIENCE_STANDARD,
            true,
            false,
        );

        $this->assertSame(DebtPaymentReminderEligibilityService::REASON_NOT_OVERDUE_ENOUGH, $reason);
    }

    public function test_excludes_ifirma_partial(): void
    {
        [$case] = $this->makeCase([
            'ifirma_payment_status' => IfirmaInvoicePaymentStatusService::STATUS_PARTIAL,
            'invoice_number' => '6/9/2026',
        ]);

        $reason = app(DebtPaymentReminderEligibilityService::class)->exclusionReason(
            $case,
            DebtPaymentReminderCampaign::AUDIENCE_STANDARD,
            true,
            false,
        );

        $this->assertSame(DebtPaymentReminderEligibilityService::REASON_IFIRMA_PARTIAL, $reason);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: DebtCase, 1: FormOrder}
     */
    private function makeCase(array $overrides = []): array
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie bulk',
            'product_price' => 365,
            'order_date' => '2026-09-01',
            'invoice_number' => $overrides['invoice_number'] ?? '1/9/2026',
            'invoice_due_date' => $overrides['invoice_due_date'] ?? '2026-10-01',
            'orderer_email' => 'zamawiajacy@example.test',
            'orderer_phone' => '500600700',
            'orderer_name' => 'Jan Test',
        ]);

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => $overrides['status'] ?? DebtCase::STATUS_OPEN,
            'invoice_number' => $overrides['invoice_number'] ?? '1/9/2026',
            'amount_gross' => 365,
            'product_name' => 'Szkolenie bulk',
            'due_date' => $overrides['due_date'] ?? '2026-10-01',
            'opened_at' => now()->subDays(20),
            'manual_vip' => $overrides['manual_vip'] ?? false,
            'do_not_auto_dun' => $overrides['do_not_auto_dun'] ?? false,
            'ifirma_payment_status' => $overrides['ifirma_payment_status'] ?? null,
        ]);

        return [$case->fresh(['formOrder']), $order];
    }
}
