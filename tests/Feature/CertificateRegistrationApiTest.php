<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Participant;
use App\Services\CertificateRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-cert-reg-api-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pneadm.api_token' => self::API_TOKEN]);
    }

    private function createOpenRegistrationCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Prowadzący',
            'email' => 'jan.prowadzacy@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Webinar testowy',
            'description' => 'Opis',
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'certificate_registration_open' => true,
            'certificate_registration_token' => 'reg-token-abc',
            'certificate_registration_starts_at' => now()->subHour(),
            'certificate_registration_ends_at' => now()->addDay(),
            'next_participant_order' => 1,
        ], $overrides));
    }

    /** @param array<string, mixed> $payload */
    private function postRegister(array $payload)
    {
        return $this->withToken(self::API_TOKEN)
            ->postJson('/api/certificate-registration/register', $payload);
    }

    public function test_register_creates_participant_with_sequential_order(): void
    {
        $course = $this->createOpenRegistrationCourse();

        $first = $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Anna',
            'last_name' => 'Jeden',
            'email' => 'anna1@example.com',
            'rodo_consent' => 1,
        ]);

        $first->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $second = $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Bogdan',
            'last_name' => 'Dwa',
            'email' => 'bogdan2@example.com',
            'rodo_consent' => 1,
        ]);

        $second->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $this->assertDatabaseHas('participants', [
            'course_id' => $course->id,
            'email_normalized' => 'anna1@example.com',
            'order' => 1,
        ]);
        $this->assertDatabaseHas('participants', [
            'course_id' => $course->id,
            'email_normalized' => 'bogdan2@example.com',
            'order' => 2,
        ]);

        $course->refresh();
        $this->assertSame(3, $course->next_participant_order);
    }

    public function test_register_updates_existing_email_instead_of_creating_duplicate(): void
    {
        $course = $this->createOpenRegistrationCourse();

        $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Anna',
            'last_name' => 'Stara',
            'email' => 'anna@example.com',
            'rodo_consent' => 1,
        ])->assertOk();

        $response = $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Anna',
            'last_name' => 'Nowa',
            'email' => 'ANNA@example.com',
            'rodo_consent' => 1,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'updated' => true,
        ]);

        $this->assertSame(1, Participant::where('course_id', $course->id)->count());
        $this->assertDatabaseHas('participants', [
            'course_id' => $course->id,
            'email_normalized' => 'anna@example.com',
            'last_name' => 'Nowa',
        ]);
    }

    public function test_service_assigns_unique_orders_for_multiple_registrations(): void
    {
        $course = $this->createOpenRegistrationCourse(['next_participant_order' => 5]);
        $service = app(CertificateRegistrationService::class);

        for ($i = 1; $i <= 5; $i++) {
            $service->registerOrUpdate($course, [
                'first_name' => 'U',
                'last_name' => (string) $i,
                'email' => "user{$i}@example.com",
                'collect_birth_data' => false,
            ]);
        }

        $orders = Participant::where('course_id', $course->id)
            ->orderBy('order')
            ->pluck('order')
            ->all();

        $this->assertSame([5, 6, 7, 8, 9], $orders);

        $course->refresh();
        $this->assertSame(10, $course->next_participant_order);
    }

    public function test_register_grants_unlimited_access_when_course_post_end_rule_is_unlimited(): void
    {
        $course = $this->createOpenRegistrationCourse([
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
        ]);

        $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Ewa',
            'last_name' => 'Bezterminowa',
            'email' => 'ewa.unlimited@example.com',
            'rodo_consent' => 1,
        ])->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $participant = Participant::query()
            ->where('course_id', $course->id)
            ->where('email_normalized', 'ewa.unlimited@example.com')
            ->first();

        $this->assertNotNull($participant);
        $this->assertNull($participant->access_expires_at);
    }

    public function test_register_updates_access_expires_at_when_existing_participant_had_limited_access(): void
    {
        $course = $this->createOpenRegistrationCourse([
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
        ]);

        Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Waldemar',
            'last_name' => 'Grabowski',
            'email' => 'waldekgr@poczta.fm',
            'email_normalized' => 'waldekgr@poczta.fm',
            'access_expires_at' => $course->end_date->copy()->addMonths(2),
        ]);

        $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Waldemar',
            'last_name' => 'Grabowski',
            'email' => 'waldekgr@poczta.fm',
            'rodo_consent' => 1,
        ])->assertOk()->assertJson(['success' => true, 'updated' => true]);

        $participant = Participant::query()
            ->where('course_id', $course->id)
            ->where('email_normalized', 'waldekgr@poczta.fm')
            ->first();

        $this->assertNotNull($participant);
        $this->assertNull($participant->access_expires_at);
    }

    public function test_register_restores_soft_deleted_participant_with_same_email(): void
    {
        $course = $this->createOpenRegistrationCourse();

        $participant = Participant::create([
            'course_id' => $course->id,
            'order' => 1,
            'first_name' => 'Waldemar',
            'last_name' => 'Stary',
            'email' => 'waldekgr@example.com',
            'email_normalized' => 'waldekgr@example.com',
        ]);
        $participant->delete();

        $response = $this->postRegister([
            'token' => 'reg-token-abc',
            'first_name' => 'Waldemar',
            'last_name' => 'Nowy',
            'email' => 'waldekgr@example.com',
            'rodo_consent' => 1,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'updated' => true,
        ]);

        $this->assertSame(1, Participant::where('course_id', $course->id)->count());
        $this->assertDatabaseHas('participants', [
            'id' => $participant->id,
            'course_id' => $course->id,
            'last_name' => 'Nowy',
            'deleted_at' => null,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postRegisterExtended(array $payload)
    {
        return $this->withToken(self::API_TOKEN)
            ->postJson('/api/certificate-registration/register-extended', $payload);
    }

    public function test_status_by_course_active_when_open_without_date_window(): void
    {
        $course = $this->createOpenRegistrationCourse([
            'certificate_registration_starts_at' => now()->addDay(),
            'certificate_registration_ends_at' => now()->subDay(),
        ]);

        $response = $this->withToken(self::API_TOKEN)
            ->getJson('/api/certificate-registration/status-by-course/'.$course->id);

        $response->assertOk()->assertJson([
            'active' => true,
            'course_title' => 'Webinar testowy',
        ]);
    }

    public function test_register_extended_ignores_registration_date_window(): void
    {
        $course = $this->createOpenRegistrationCourse([
            'certificate_registration_starts_at' => now()->addDay(),
            'certificate_registration_ends_at' => now()->subDay(),
        ]);

        $response = $this->postRegisterExtended([
            'course_id' => $course->id,
            'first_name' => 'Karol',
            'last_name' => 'Abonent',
            'email' => 'karol.abonent@example.com',
            'rodo_consent' => 1,
        ]);

        $response->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $this->assertDatabaseHas('participants', [
            'course_id' => $course->id,
            'email_normalized' => 'karol.abonent@example.com',
        ]);
    }

    public function test_register_extended_rejected_when_registration_closed(): void
    {
        $course = $this->createOpenRegistrationCourse([
            'certificate_registration_open' => false,
        ]);

        $this->postRegisterExtended([
            'course_id' => $course->id,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'email' => 'anna@example.com',
            'rodo_consent' => 1,
        ])->assertStatus(403);
    }
}
