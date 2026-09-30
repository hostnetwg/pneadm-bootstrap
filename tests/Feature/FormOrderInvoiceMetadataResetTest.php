<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrderInvoiceMetadataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_invoice_number_from_show_clears_ifirma_and_ksef_linkage(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::query()->create([
            'ident' => '260930-RESET',
            'product_name' => 'Szkolenie',
            'orderer_email' => 'reset@example.test',
            'invoice_number' => '577/9/2026',
            'invoice_issue_date' => '2026-09-20',
            'invoice_due_date' => '2026-10-04',
            'ifirma_invoice_id' => '111222333',
            'ksef_number' => '7392137630-20260920-OLD000000001-11',
            'ksef_sent_at' => now(),
            'ksef_status' => 'sent',
            'ksef_error' => 'stary błąd',
            'ksef_email_pending' => true,
            'notes' => 'Numer korekty: 160/2026 dla 577/9/2026',
        ]);

        $this->actingAs($user)
            ->put(route('form-orders.update', $order->id), [
                'from_show_page' => '1',
                'invoice_number' => '',
                'notes' => 'Numer korekty: 160/2026 dla 577/9/2026',
            ])
            ->assertRedirect(route('form-orders.show', $order->id))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertNull($order->invoice_number);
        $this->assertNull($order->invoice_issue_date);
        $this->assertNull($order->invoice_due_date);
        $this->assertNull($order->ifirma_invoice_id);
        $this->assertNull($order->ksef_number);
        $this->assertNull($order->ksef_sent_at);
        $this->assertNull($order->ksef_status);
        $this->assertNull($order->ksef_error);
        $this->assertFalse((bool) $order->ksef_email_pending);
        $this->assertSame('Numer korekty: 160/2026 dla 577/9/2026', $order->notes);
    }

    public function test_saving_existing_invoice_number_does_not_clear_linkage(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_name' => 'Szkolenie',
            'orderer_email' => 'keep@example.test',
            'invoice_number' => '342/9/2026',
            'ifirma_invoice_id' => '444555666',
            'ksef_number' => '7392137630-20260921-KEEP00000001-22',
            'ksef_status' => 'sent',
        ]);

        $this->actingAs($user)
            ->put(route('form-orders.update', $order->id), [
                'from_show_page' => '1',
                'invoice_number' => '342/9/2026',
                'notes' => 'Bez zmian',
            ])
            ->assertRedirect(route('form-orders.show', $order->id));

        $order->refresh();

        $this->assertSame('444555666', $order->ifirma_invoice_id);
        $this->assertSame('7392137630-20260921-KEEP00000001-22', $order->ksef_number);
        $this->assertSame('sent', $order->ksef_status);
    }
}
