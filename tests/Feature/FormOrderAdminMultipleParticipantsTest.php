<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\User;
use App\Services\FormOrderAdminParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormOrderAdminMultipleParticipantsTest extends TestCase
{
    use RefreshDatabase;

    private int $courseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->courseId = (int) DB::table('courses')->insertGetId([
            'title' => 'Kurs wielu uczestników',
            'description' => 'Test',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(8),
            'is_paid' => 1,
            'type' => 'online',
            'category' => 'open',
            'is_active' => 1,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_create_form_has_add_participant_button(): void
    {
        $this->actingAsAdmin()
            ->get(route('form-orders.create'))
            ->assertOk()
            ->assertSee('UCZESTNICY', false)
            ->assertSee('Dodaj kolejnego uczestnika', false)
            ->assertSee('name="participants[0][first_name]"', false);
    }

    public function test_store_saves_multiple_participants(): void
    {
        $this->actingAsAdmin()
            ->post(route('form-orders.store'), $this->validPayload([
                'participants' => [
                    ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test'],
                    ['first_name' => 'Bartek', 'last_name' => 'Nowak', 'email' => 'bartek@example.test'],
                ],
            ]))
            ->assertRedirect();

        $order = FormOrder::query()->latest('id')->first();
        $this->assertNotNull($order);

        $participants = $order->participants()->orderByDesc('is_primary')->orderBy('id')->get();
        $this->assertCount(2, $participants);
        $this->assertTrue((bool) $participants[0]->is_primary);
        $this->assertSame('anna@example.test', $participants[0]->participant_email);
        $this->assertSame('bartek@example.test', $participants[1]->participant_email);
        $this->assertFalse((bool) $participants[1]->is_primary);
    }

    public function test_store_rejects_duplicate_emails_on_the_same_order(): void
    {
        $this->actingAsAdmin()
            ->from(route('form-orders.create'))
            ->post(route('form-orders.store'), $this->validPayload([
                'participants' => [
                    ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'ta-sama@example.test'],
                    ['first_name' => 'Ewa', 'last_name' => 'Nowak', 'email' => 'ta-sama@example.test'],
                ],
            ]))
            ->assertRedirect(route('form-orders.create'))
            ->assertSessionHasErrors('participants.1.email');
    }

    public function test_edit_form_shows_all_participants(): void
    {
        $order = $this->createOrderWithParticipants([
            ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test', 'is_primary' => true],
            ['first_name' => 'Bartek', 'last_name' => 'Nowak', 'email' => 'bartek@example.test', 'is_primary' => false],
        ]);

        $this->actingAsAdmin()
            ->get(route('form-orders.edit', $order->id))
            ->assertOk()
            ->assertSee('anna@example.test', false)
            ->assertSee('bartek@example.test', false)
            ->assertSee('name="participants[1][email]"', false);
    }

    public function test_update_replaces_participant_list(): void
    {
        $order = $this->createOrderWithParticipants([
            ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test', 'is_primary' => true],
            ['first_name' => 'Bartek', 'last_name' => 'Nowak', 'email' => 'bartek@example.test', 'is_primary' => false],
        ]);

        $response = $this->actingAsAdmin()
            ->put(route('form-orders.update', $order->id), array_merge($this->validPayload([
                'participants' => [
                    ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test'],
                    ['first_name' => 'Celina', 'last_name' => 'Lis', 'email' => 'celina@example.test'],
                ],
            ]), [
                'from_edit_page' => '1',
                'course_id' => $this->courseId,
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString('/form-orders/'.$order->id, (string) $response->headers->get('Location'));
        $response->assertSessionHas('success');

        $emails = $order->participants()->orderByDesc('is_primary')->orderBy('id')->pluck('participant_email')->all();
        $this->assertSame(['anna@example.test', 'celina@example.test'], $emails);
        $this->assertNull(
            FormOrderParticipant::withTrashed()
                ->where('form_order_id', $order->id)
                ->where('participant_email', 'bartek@example.test')
                ->whereNull('deleted_at')
                ->first()
        );
    }

    public function test_clone_prefills_all_participants(): void
    {
        $source = $this->createOrderWithParticipants([
            ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test', 'is_primary' => true],
            ['first_name' => 'Bartek', 'last_name' => 'Nowak', 'email' => 'bartek@example.test', 'is_primary' => false],
        ]);

        $this->actingAsAdmin()
            ->get(route('form-orders.create', ['clone_from' => $source->id]))
            ->assertOk()
            ->assertSee('anna@example.test', false)
            ->assertSee('bartek@example.test', false);
    }

    public function test_sync_keeps_participant_id_when_email_matches(): void
    {
        $order = $this->createOrderWithParticipants([
            ['first_name' => 'Anna', 'last_name' => 'Kowalska', 'email' => 'anna@example.test', 'is_primary' => true],
        ]);
        $row = $order->participants()->first();
        $originalId = (int) $row->id;

        app(FormOrderAdminParticipantService::class)->sync($order, [
            ['first_name' => 'Anna', 'last_name' => 'Kowalska-Nowak', 'email' => 'anna@example.test'],
        ]);

        $fresh = $order->participants()->first();
        $this->assertSame($originalId, (int) $fresh->id);
        $this->assertSame('Kowalska-Nowak', $fresh->participant_lastname);
    }

    private function actingAsAdmin()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        return $this->actingAs($user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'course_id' => $this->courseId,
            'product_price' => 318,
            'orderer_name' => 'Szkoła Testowa',
            'orderer_phone' => '501654274',
            'orderer_email' => 'zamawiajacy@example.test',
            'buyer_name' => 'Szkoła Testowa',
            'buyer_address' => 'Testowa 1',
            'buyer_postal_code' => '00-001',
            'buyer_city' => 'Warszawa',
            'buyer_nip' => '1234567890',
            'participants' => [
                ['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test'],
            ],
        ], $overrides);
    }

    /**
     * @param  list<array{first_name: string, last_name: string, email: string, is_primary: bool}>  $rows
     */
    private function createOrderWithParticipants(array $rows): FormOrder
    {
        $order = FormOrder::create([
            'ident' => FormOrder::generateIdent(),
            'product_id' => $this->courseId,
            'product_name' => 'Kurs wielu uczestników',
            'product_price' => 318,
            'orderer_name' => 'Szkoła Testowa',
            'orderer_phone' => '501654274',
            'orderer_email' => 'zamawiajacy@example.test',
            'buyer_name' => 'Szkoła Testowa',
            'buyer_address' => 'Testowa 1',
            'buyer_postal_code' => '00-001',
            'buyer_city' => 'Warszawa',
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        foreach ($rows as $row) {
            FormOrderParticipant::create([
                'form_order_id' => $order->id,
                'participant_firstname' => $row['first_name'],
                'participant_lastname' => $row['last_name'],
                'participant_email' => $row['email'],
                'is_primary' => $row['is_primary'],
            ]);
        }

        return $order->fresh();
    }
}
