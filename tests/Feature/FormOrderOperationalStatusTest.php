<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\FormOrderOperationalStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrderOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createCourse(array $overrides = []): int
    {
        return (int) DB::table('courses')->insertGetId(array_merge([
            'title' => 'Kurs testowy',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createOrderWithParticipant(int $courseId, array $orderOverrides = [], array $participantOverrides = []): FormOrder
    {
        $order = FormOrder::create(array_merge([
            'product_id' => $courseId,
            'product_name' => 'Szkolenie test',
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'orderer@example.test',
        ], $orderOverrides));

        FormOrderParticipant::create(array_merge([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Jan',
            'participant_lastname' => 'Kowalski',
            'participant_email' => 'jan@example.test',
            'is_primary' => true,
        ], $participantOverrides));

        return $order->fresh(['participants']);
    }

    public function test_order_without_invoice_and_without_participant_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertTrue($service->needsAttention($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_PROVISIONING, $service->evaluate($order)['status']);
        $this->assertTrue(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_order_with_invoice_but_without_participant_still_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['invoice_number' => 'FV/1/2026']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertTrue($service->needsAttention($order));
        $this->assertContains($service->evaluate($order)['status'], [
            FormOrderOperationalStatusService::STATUS_NEEDS_PROVISIONING,
            FormOrderOperationalStatusService::STATUS_INCONSISTENT,
        ]);
    }

    public function test_order_without_invoice_with_participant_needs_invoice(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsAttention($order));
        $this->assertTrue($service->needsOperationalHandling($order));
        $this->assertFalse($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_NEEDS_INVOICE, $service->evaluate($order)['status']);
        $this->assertTrue(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertFalse(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_order_with_invoice_and_participant_is_processed(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['invoice_number' => 'FV/1/2026']);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsAttention($order));
        $this->assertFalse($service->needsOperationalHandling($order));
        $this->assertTrue($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_PROCESSED, $service->evaluate($order)['status']);
        $this->assertFalse(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_order_with_participant_and_invoice_exempt_is_processed_without_invoice(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, [
            'invoice_exempt_at' => now(),
            'invoice_exempt_reason' => 'Bezpłatny dostęp',
        ]);

        $participant = Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        FormOrderParticipant::where('form_order_id', $order->id)->update(['participant_id' => $participant->id]);
        $order = $order->fresh(['participants']);

        $service = app(FormOrderOperationalStatusService::class);

        $this->assertFalse($service->needsOperationalHandling($order));
        $this->assertTrue($service->isProcessed($order));
        $this->assertSame(FormOrderOperationalStatusService::STATUS_PROCESSED, $service->evaluate($order)['status']);
        $this->assertFalse(FormOrder::needsHandling()->whereKey($order->id)->exists());
        $this->assertTrue(FormOrder::processed()->whereKey($order->id)->exists());
    }

    public function test_status_completed_without_participant_still_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, ['status_completed' => 1]);

        $this->assertTrue(app(FormOrderOperationalStatusService::class)->needsAttention($order->fresh(['participants'])));
    }

    public function test_cancelled_order_is_not_in_new_scope(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId, [
            'cancelled_at' => now(),
            'cancelled_reason' => 'test',
        ]);

        $this->assertFalse(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_partially_provisioned_multi_participant_order_needs_attention(): void
    {
        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        FormOrderParticipant::create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Nowak',
            'participant_email' => 'anna@example.test',
            'is_primary' => false,
        ]);

        Participant::create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'order' => 1,
        ]);

        $order = $order->fresh(['participants']);
        $status = app(FormOrderOperationalStatusService::class)->evaluate($order);

        $this->assertSame(FormOrderOperationalStatusService::STATUS_PARTIALLY_PROCESSED, $status['status']);
        $this->assertSame(2, $status['expected_count']);
        $this->assertSame(1, $status['provisioned_count']);
        $this->assertTrue(FormOrder::new()->whereKey($order->id)->exists());
    }

    public function test_cancel_order_endpoint_sets_cancelled_at(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $courseId = $this->createCourse();
        $order = $this->createOrderWithParticipant($courseId);

        $response = $this->actingAs($user)->postJson(route('form-orders.cancel', $order->id), [
            'reason' => 'rezygnacja',
        ]);

        $response->assertOk();
        $order->refresh();
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('rezygnacja', $order->cancelled_reason);
    }
}
