<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormOrdersDateRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_range_filter_limits_orders_by_order_date_in_app_timezone(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $tz = config('app.timezone', 'Europe/Warsaw');

        $inRangeOrder = FormOrder::create([
            'product_name' => 'Zamówienie w zakresie',
            'order_date' => Carbon::parse('2026-06-15 12:00:00', $tz)->utc(),
            'orderer_email' => 'in-range@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $inRangeOrder->id,
            'participant_firstname' => 'In',
            'participant_lastname' => 'Range',
            'participant_email' => 'in-range@example.test',
            'is_primary' => true,
        ]);

        $outOfRangeOrder = FormOrder::create([
            'product_name' => 'Zamówienie poza zakresem',
            'order_date' => Carbon::parse('2026-06-20 12:00:00', $tz)->utc(),
            'orderer_email' => 'out-of-range@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $outOfRangeOrder->id,
            'participant_firstname' => 'Out',
            'participant_lastname' => 'Range',
            'participant_email' => 'out-of-range@example.test',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'date_from' => '2026-06-10',
            'date_to' => '2026-06-16',
        ]));

        $response->assertOk();
        $response->assertSee('ID: #'.$inRangeOrder->id, false);
        $response->assertDontSee('ID: #'.$outOfRangeOrder->id, false);
    }

    public function test_date_from_only_includes_orders_on_or_after_that_day(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $tz = config('app.timezone', 'Europe/Warsaw');

        $olderOrder = FormOrder::create([
            'product_name' => 'Starsze zamówienie',
            'order_date' => Carbon::parse('2026-06-01 10:00:00', $tz)->utc(),
            'orderer_email' => 'older@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $olderOrder->id,
            'participant_firstname' => 'Old',
            'participant_lastname' => 'Er',
            'participant_email' => 'older@example.test',
            'is_primary' => true,
        ]);

        $newerOrder = FormOrder::create([
            'product_name' => 'Nowsze zamówienie',
            'order_date' => Carbon::parse('2026-06-10 10:00:00', $tz)->utc(),
            'orderer_email' => 'newer@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $newerOrder->id,
            'participant_firstname' => 'New',
            'participant_lastname' => 'Er',
            'participant_email' => 'newer@example.test',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'date_from' => '2026-06-10',
        ]));

        $response->assertOk();
        $response->assertSee('ID: #'.$newerOrder->id, false);
        $response->assertDontSee('ID: #'.$olderOrder->id, false);
    }

    public function test_invalid_date_range_shows_error_and_does_not_filter(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $tz = config('app.timezone', 'Europe/Warsaw');

        $firstOrder = FormOrder::create([
            'product_name' => 'Pierwsze zamówienie',
            'order_date' => Carbon::parse('2026-06-01 10:00:00', $tz)->utc(),
            'orderer_email' => 'first@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $firstOrder->id,
            'participant_firstname' => 'First',
            'participant_lastname' => 'Order',
            'participant_email' => 'first@example.test',
            'is_primary' => true,
        ]);

        $secondOrder = FormOrder::create([
            'product_name' => 'Drugie zamówienie',
            'order_date' => Carbon::parse('2026-06-20 10:00:00', $tz)->utc(),
            'orderer_email' => 'second@example.test',
        ]);
        FormOrderParticipant::create([
            'form_order_id' => $secondOrder->id,
            'participant_firstname' => 'Second',
            'participant_lastname' => 'Order',
            'participant_email' => 'second@example.test',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->get(route('form-orders.index', [
            'date_from' => '2026-06-20',
            'date_to' => '2026-06-01',
        ]));

        $response->assertOk();
        $response->assertSee('Data „od” nie może być późniejsza niż data „do”.', false);
        $response->assertSee('ID: #'.$firstOrder->id, false);
        $response->assertSee('ID: #'.$secondOrder->id, false);
    }
}
