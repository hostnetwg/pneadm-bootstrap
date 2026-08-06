<?php

namespace Tests\Feature;

use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashDebtCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_restore_debt_case_from_trash(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Przywróć sprawę',
            'product_price' => 120,
            'order_date' => now(),
            'invoice_number' => '12/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        $case->delete();

        $response = $this->actingAs($user)->post(route('trash.restore', [
            'table' => 'debt_cases',
            'id' => $case->id,
        ]));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('debt_cases', ['id' => $case->id]);
    }

    public function test_user_can_force_delete_debt_case_from_trash(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Usuń na zawsze',
            'product_price' => 130,
            'order_date' => now(),
            'invoice_number' => '13/8/2026',
        ]);
        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        $caseId = $case->id;
        $case->delete();

        $response = $this->actingAs($user)->delete(route('trash.force-delete', [
            'table' => 'debt_cases',
            'id' => $caseId,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('debt_cases', ['id' => $caseId]);
    }
}
