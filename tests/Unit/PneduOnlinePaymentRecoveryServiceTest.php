<?php

namespace Tests\Unit;

use App\Services\PneduOnlinePaymentRecoveryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PneduOnlinePaymentRecoveryServiceTest extends TestCase
{
    public function test_posts_to_pnedu_internal_recovery_endpoint(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu-app',
            'services.pnedu.internal_api_token' => 'secret-token',
        ]);

        Http::fake([
            'http://pnedu-app/api/internal/form-orders/42/send-online-payment-recovery' => Http::response([
                'success' => true,
                'emails' => ['buyer@example.test'],
                'sent_at' => '2026-08-22T12:00:00+00:00',
            ], 200),
        ]);

        $result = app(PneduOnlinePaymentRecoveryService::class)->sendRecoveryEmail(42, true);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://pnedu-app/api/internal/form-orders/42/send-online-payment-recovery'
                && $request['allow_resend'] === true
                && $request->hasHeader('Authorization', 'Bearer secret-token');
        });
    }

    public function test_gets_pnedu_internal_recovery_preview(): void
    {
        config([
            'services.pnedu.internal_url' => 'http://pnedu-app',
            'services.pnedu.internal_api_token' => 'secret-token',
        ]);

        Http::fake([
            'http://pnedu-app/api/internal/form-orders/42/preview-online-payment-recovery' => Http::response([
                'success' => true,
                'to' => 'buyer@example.test',
                'subject' => 'Przypomnienie o płatności',
                'body_html' => '<p>Test</p>',
            ], 200),
        ]);

        $result = app(PneduOnlinePaymentRecoveryService::class)->previewRecoveryEmail(42);

        $this->assertTrue($result['success']);
        $this->assertSame('buyer@example.test', $result['to']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'http://pnedu-app/api/internal/form-orders/42/preview-online-payment-recovery');
    }
}
