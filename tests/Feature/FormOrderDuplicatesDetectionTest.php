<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrderDuplicatesDetectionTest extends TestCase
{
    use RefreshDatabase;

    private int $courseId;

    private string $email = 'dup@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs duplikatów',
            'description' => 'Test',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(7),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(array $overrides = []): FormOrder
    {
        $order = FormOrder::create(array_merge([
            'product_id' => $this->courseId,
            'product_name' => 'Zamówienie test',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => $this->email,
        ], $overrides));

        FormOrderParticipant::create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Jan',
            'participant_lastname' => 'Test',
            'participant_email' => $this->email,
            'is_primary' => true,
        ]);

        return $order->fresh();
    }

    public function test_two_active_orders_form_duplicate_group(): void
    {
        $a = $this->createOrder();
        $b = $this->createOrder();

        $groups = FormOrder::duplicates()->get();

        $this->assertCount(1, $groups);
        $this->assertSame(2, (int) $groups->first()->duplicate_count);
        $this->assertEqualsCanonicalizing(
            [(string) $a->id, (string) $b->id],
            explode(',', $groups->first()->order_ids)
        );
    }

    public function test_active_plus_completed_without_invoice_is_not_duplicate(): void
    {
        $active = $this->createOrder();
        $this->createOrder([
            'status_completed' => 1,
            'invoice_number' => null,
        ]);

        $this->assertCount(0, FormOrder::duplicates()->get());
        $this->assertSame(0, FormOrder::findDuplicatesFor($active->id)->count());
    }

    public function test_invoice_plus_cancelled_is_not_duplicate(): void
    {
        $withInvoice = $this->createOrder([
            'invoice_number' => 'FV/1/2026',
            'status_completed' => 1,
        ]);
        $this->createOrder([
            'cancelled_at' => now(),
            'cancelled_reason' => 'duplicate',
            'status_completed' => 1,
        ]);

        $this->assertCount(0, FormOrder::duplicates()->get());
        $this->assertTrue($withInvoice->participatesInDuplicateDetection());
        $this->assertSame(0, FormOrder::findDuplicatesFor($withInvoice->id)->count());
    }

    public function test_two_cancelled_orders_do_not_form_duplicate_group(): void
    {
        $this->createOrder([
            'cancelled_at' => now(),
            'cancelled_reason' => 'duplicate',
            'status_completed' => 1,
        ]);
        $this->createOrder([
            'cancelled_at' => now(),
            'cancelled_reason' => 'duplicate',
            'status_completed' => 1,
        ]);

        $this->assertCount(0, FormOrder::duplicates()->get());
    }

    public function test_two_orders_with_invoice_still_form_duplicate_group(): void
    {
        $a = $this->createOrder([
            'invoice_number' => 'FV/A/2026',
            'status_completed' => 1,
        ]);
        $b = $this->createOrder([
            'invoice_number' => 'FV/B/2026',
            'status_completed' => 1,
        ]);

        $groups = FormOrder::duplicates()->get();

        $this->assertCount(1, $groups);
        $this->assertSame(2, (int) $groups->first()->duplicate_count);
        $this->assertEqualsCanonicalizing(
            [(string) $a->id, (string) $b->id],
            explode(',', $groups->first()->order_ids)
        );
    }

    public function test_completed_without_invoice_does_not_participate(): void
    {
        $order = $this->createOrder([
            'status_completed' => 1,
            'invoice_number' => null,
        ]);

        $this->assertFalse($order->participatesInDuplicateDetection());
        $this->assertSame(0, FormOrder::findDuplicatesFor($order->id)->count());
    }
}
