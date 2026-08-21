<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrderPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function actingOperator(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    public function test_authenticated_user_can_download_order_pdf(): void
    {
        $user = $this->actingOperator();
        $order = FormOrder::create([
            'product_name' => 'Test szkolenie PDF',
            'product_price' => 365,
            'buyer_name' => 'Szkoła Test',
            'buyer_address' => 'ul. Testowa 1',
            'buyer_postal_code' => '00-001',
            'buyer_city' => 'Warszawa',
            'orderer_email' => 'dyrektor@example.test',
            'ident' => 'pdf-test-ident-8713',
            'order_date' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.pdf', $order->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment; filename=zamowienie-pdf-test-ident-8713.pdf',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_guest_cannot_download_order_pdf(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Test',
            'product_price' => 100,
            'ident' => 'guest-pdf-ident',
            'order_date' => now(),
        ]);

        $this->get(route('form-orders.pdf', $order->id))
            ->assertRedirect();
    }
}
