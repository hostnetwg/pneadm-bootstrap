<?php

namespace Tests\Unit;

use App\Services\FormOrderOperationalStatusService;
use Tests\TestCase;

class FormOrderOperationalStatusServiceSqlTest extends TestCase
{
    public function test_participant_provisioned_sql_includes_participant_id_and_email_fallback(): void
    {
        $service = new FormOrderOperationalStatusService;
        $sql = $service->participantProvisionedExistsSql('fop', 'fo.product_id');

        $this->assertStringContainsString('fop.participant_id', $sql);
        $this->assertStringContainsString('email_normalized', $sql);
        $this->assertStringContainsString('participants p_enr', $sql);
    }

    public function test_resolve_course_id_sql_uses_product_id_and_legacy_id_old(): void
    {
        $service = new FormOrderOperationalStatusService;
        $sql = $service->resolveCourseIdSql('form_orders');

        $this->assertStringContainsString('form_orders.product_id', $sql);
        $this->assertStringContainsString('NULLIF', $sql);
        $this->assertStringContainsString('publigo_product_id', $sql);
        $this->assertStringContainsString('id_old', $sql);
    }

    public function test_scope_needs_operational_handling_sql_checks_invoice_and_provisioning(): void
    {
        $service = new FormOrderOperationalStatusService;
        $query = \App\Models\FormOrder::query();
        $service->scopeNeedsOperationalHandling($query);
        $sql = $query->toSql();

        $this->assertStringContainsString('cancelled_at', $sql);
        $this->assertStringContainsString('invoice_number', $sql);
        $this->assertStringContainsString('form_order_participants', $sql);
    }
}
