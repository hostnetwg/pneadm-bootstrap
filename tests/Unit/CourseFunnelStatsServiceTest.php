<?php

namespace Tests\Unit;

use App\Services\CourseFunnelStatsService;
use Tests\TestCase;

class CourseFunnelStatsServiceTest extends TestCase
{
    public function test_operational_submitted_excludes_completed_without_invoice(): void
    {
        $service = new CourseFunnelStatsService;
        $sql = $service->operationalSubmittedOrderSql('fo.invoice_number', 'fo.status_completed');

        $this->assertStringContainsString('fo.status_completed', $sql);
        $this->assertStringContainsString('fo.invoice_number', $sql);
        $this->assertStringStartsWith('NOT (', $sql);
    }

    public function test_invoice_present_sql_matches_scope_with_invoice(): void
    {
        $service = new CourseFunnelStatsService;
        $sql = $service->invoicePresentSql('invoice_number');

        $this->assertSame("(invoice_number IS NOT NULL AND invoice_number != '' AND invoice_number != '0')", $sql);
    }
}
