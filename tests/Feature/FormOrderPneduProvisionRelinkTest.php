<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Participant;
use App\Models\PneduUser;
use App\Services\FormOrderPneduProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormOrderPneduProvisionRelinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_relinks_existing_course_participant_after_status_reset(): void
    {
        if (! Schema::connection('pnedu')->hasTable('users')) {
            $this->markTestSkipped('Brak tabeli users w połączeniu pnedu — pomijam test provision.');
        }

        Notification::fake();

        $courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs relink',
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

        $email = 'robert.relink@example.test';

        $existing = Participant::query()->create([
            'course_id' => $courseId,
            'first_name' => 'Robert',
            'last_name' => 'Wróbel',
            'email' => $email,
            'order' => 1,
        ]);

        $order = FormOrder::query()->create([
            'product_id' => $courseId,
            'product_name' => 'Kurs relink',
            'status_completed' => 0,
            'orderer_email' => $email,
            'pnedu_provisioned_at' => null,
        ]);

        FormOrderParticipant::query()->create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Robert',
            'participant_lastname' => 'Wróbel',
            'participant_email' => $email,
            'is_primary' => true,
            'participant_id' => null,
        ]);

        PneduUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Robert',
                'last_name' => 'Wróbel',
                'email_unique_slot' => PneduUser::buildEmailUniqueSlot($email, null),
                'password' => Hash::make('secret-password-123'),
                'email_verified_at' => now(),
            ]
        );

        $result = app(FormOrderPneduProvisionService::class)->provision($order->id, false);

        $this->assertTrue($result['success'] ?? false, $result['error'] ?? json_encode($result));
        $this->assertTrue($result['reused_participant'] ?? false);
        $this->assertSame(1, Participant::query()->where('course_id', $courseId)->whereRaw('LOWER(TRIM(email)) = ?', [$email])->count());

        $order->refresh();
        $this->assertNotNull($order->pnedu_provisioned_at);

        $primary = FormOrderParticipant::query()->where('form_order_id', $order->id)->where('is_primary', true)->first();
        $this->assertSame($existing->id, (int) $primary->participant_id);
    }
}
