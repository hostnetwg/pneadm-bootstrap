<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrdersArchivalFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_archival_filter_includes_online_order_linked_by_product_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $archivedCourseId = DB::table('courses')->insertGetId([
            'title' => 'Archiwalny kurs (nowe powiązanie)',
            'description' => 'Test',
            'start_date' => now()->subDays(14),
            'end_date' => now()->subDays(1),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activeCourseId = DB::table('courses')->insertGetId([
            'title' => 'Aktywny kurs (nie archiwalny)',
            'description' => 'Test',
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(7),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $archivalOrder = FormOrder::create([
            'product_id' => $archivedCourseId,
            'product_name' => 'Zamówienie archiwalne online',
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_PAID,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'archival@example.test',
        ]);

        $nonArchivalOrder = FormOrder::create([
            'product_id' => $activeCourseId,
            'product_name' => 'Zamówienie niearchiwalne',
            'payment_mode' => FormOrder::PAYMENT_MODE_ONLINE_GATEWAY,
            'payment_status' => FormOrder::PAYMENT_STATUS_PAID,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'non-archival@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', ['filter' => 'archival']));

        $response->assertOk();
        $response->assertSee('ID: #'.$archivalOrder->id, false);
        $response->assertDontSee('ID: #'.$nonArchivalOrder->id, false);
        $response->assertViewHas('archivalCount', 1);
    }

    public function test_archival_filter_keeps_legacy_linking_by_publigo_product_id_and_id_old(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        DB::table('courses')->insert([
            'id_old' => 987654,
            'source_id_old' => 'certgen_Publigo',
            'title' => 'Archiwalny kurs legacy',
            'description' => 'Test',
            'start_date' => now()->subDays(20),
            'end_date' => now()->subDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyOrder = FormOrder::create([
            'publigo_product_id' => 987654,
            'product_name' => 'Zamówienie legacy',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'invoice_number' => null,
            'status_completed' => 0,
            'orderer_email' => 'legacy@example.test',
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', ['filter' => 'archival']));

        $response->assertOk();
        $response->assertSee('ID: #'.$legacyOrder->id, false);
        $response->assertViewHas('archivalCount', 1);
    }
}

