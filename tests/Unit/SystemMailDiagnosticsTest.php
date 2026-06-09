<?php

namespace Tests\Unit;

use App\Services\Mail\SystemMailDiagnostics;
use Tests\TestCase;

class SystemMailDiagnosticsTest extends TestCase
{
    public function test_current_config_detects_log_transport_as_not_real_delivery(): void
    {
        config([
            'mail.system.mailer' => 'log',
            'mail.mailers.log.transport' => 'log',
        ]);

        $cfg = SystemMailDiagnostics::currentConfig();

        $this->assertSame('log', $cfg['mailer']);
        $this->assertSame('log', $cfg['transport']);
        $this->assertFalse($cfg['real_delivery']);
        $this->assertNotNull($cfg['warning']);
    }

    public function test_current_config_detects_ses_as_real_delivery(): void
    {
        config([
            'mail.system.mailer' => 'ses',
            'mail.mailers.ses.transport' => 'ses',
        ]);

        $cfg = SystemMailDiagnostics::currentConfig();

        $this->assertTrue($cfg['real_delivery']);
        $this->assertNull($cfg['warning']);
    }

    public function test_delivery_meta_from_log_reads_nested_delivery_key(): void
    {
        $meta = [
            'delivery' => [
                'mailer' => 'ses',
                'transport' => 'ses',
                'real_delivery' => true,
            ],
        ];

        $delivery = SystemMailDiagnostics::deliveryMetaFromLog($meta);

        $this->assertSame('ses', $delivery['mailer']);
        $this->assertTrue($delivery['real_delivery']);
    }
}
