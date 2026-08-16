<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Participant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrderPneduResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'level' => 50, 'is_system' => true]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);
    }

    private function createProvisionedOrderWithParticipant(): array
    {
        $courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs reset PNEDU',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $participant = Participant::query()->create([
            'course_id' => $courseId,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.reset@example.test',
            'order' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_id' => $courseId,
            'product_name' => 'Kurs reset PNEDU',
            'orderer_email' => 'jan.reset@example.test',
            'pnedu_provisioned_at' => now(),
            'pnedu_user_existed_before' => false,
            'pnedu_clickmeeting_status' => 'success',
        ]);

        $fop = FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Jan',
            'participant_lastname' => 'Kowalski',
            'participant_email' => 'jan.reset@example.test',
            'participant_id' => $participant->id,
            'is_primary' => true,
        ]);

        return [$order->fresh(), $participant->fresh(), $fop->fresh()];
    }

    public function test_reset_without_removing_participant_clears_only_pnedu_fields(): void
    {
        [$order, $participant] = $this->createProvisionedOrderWithParticipant();

        $response = $this->actingAs($this->admin())->postJson(
            route('form-orders.pnedu.reset', $order->id),
            ['remove_participant' => false]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('removed_participant', false);

        $order->refresh();
        $this->assertNull($order->pnedu_provisioned_at);
        $this->assertNull($order->pnedu_clickmeeting_status);
        $this->assertNotNull(Participant::query()->find($participant->id));
    }

    public function test_reset_with_remove_participant_soft_deletes_participant(): void
    {
        [$order, $participant, $fop] = $this->createProvisionedOrderWithParticipant();

        $response = $this->actingAs($this->admin())->postJson(
            route('form-orders.pnedu.reset', $order->id),
            ['remove_participant' => true]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('removed_participant', true);

        $order->refresh();
        $fop->refresh();
        $this->assertNull($order->pnedu_provisioned_at);
        $this->assertNull($fop->participant_id);
        $this->assertNull(Participant::query()->find($participant->id));
        $this->assertNotNull(Participant::withTrashed()->find($participant->id));
    }

    public function test_reset_specific_participant_leaves_other_intact(): void
    {
        $courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs reset multi',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $p1 = Participant::query()->create([
            'course_id' => $courseId,
            'first_name' => 'Anna',
            'last_name' => 'Pierwsza',
            'email' => 'anna.reset.multi@example.test',
            'order' => 1,
        ]);
        $p2 = Participant::query()->create([
            'course_id' => $courseId,
            'first_name' => 'Bartek',
            'last_name' => 'Drugi',
            'email' => 'bartek.reset.multi@example.test',
            'order' => 2,
        ]);

        $order = FormOrder::query()->create([
            'product_id' => $courseId,
            'product_name' => 'Kurs reset multi',
            'orderer_email' => 'buyer.reset.multi@example.test',
            'pnedu_provisioned_at' => now(),
            'pnedu_user_existed_before' => true,
        ]);

        $fop1 = FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Pierwsza',
            'participant_email' => 'anna.reset.multi@example.test',
            'participant_id' => $p1->id,
            'is_primary' => true,
        ]);
        $fop2 = FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Bartek',
            'participant_lastname' => 'Drugi',
            'participant_email' => 'bartek.reset.multi@example.test',
            'participant_id' => $p2->id,
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->admin())->postJson(
            route('form-orders.pnedu.reset', $order->id),
            [
                'remove_participant' => true,
                'form_order_participant_id' => $fop2->id,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('removed_participant', true)
            ->assertJsonPath('form_order_participant_id', $fop2->id);

        $order->refresh();
        $fop1->refresh();
        $fop2->refresh();

        $this->assertNull($order->pnedu_provisioned_at);
        $this->assertSame($p1->id, $fop1->participant_id);
        $this->assertNotNull(Participant::query()->find($p1->id));
        $this->assertNull($fop2->participant_id);
        $this->assertNull(Participant::query()->find($p2->id));
    }

    public function test_filter_no_participant_includes_order_with_null_pnedu_provisioned_at(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs U B2',
            'description' => 'Test',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Participant::query()->create([
            'course_id' => $courseId,
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'email' => 'ewa.b2@example.test',
            'order' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_id' => $courseId,
            'product_name' => 'Kurs U B2',
            'orderer_email' => 'ewa.b2@example.test',
            'pnedu_provisioned_at' => null,
        ]);

        FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Ewa',
            'participant_lastname' => 'Test',
            'participant_email' => 'ewa.b2@example.test',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('form-orders.navigation-filter-count', [
            'filter_no_participant' => 1,
            'course_id' => $courseId,
        ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('filter_no_participant', true);
    }
}
