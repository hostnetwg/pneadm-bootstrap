<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use Tests\TestCase;

class FormOrderDashboardMetricsScopeTest extends TestCase
{
    public function test_included_in_dashboard_metrics_excludes_cancelled_orders(): void
    {
        $sql = FormOrder::includedInDashboardMetrics()->toSql();

        $this->assertStringContainsString('cancelled_at', $sql);
        $this->assertStringContainsString('invoice_number', $sql);
    }
}
