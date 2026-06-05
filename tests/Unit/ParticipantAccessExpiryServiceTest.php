<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\Instructor;
use App\Models\PaymentDisplayOption;
use App\Services\ParticipantAccessExpiryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantAccessExpiryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantAccessExpiryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ParticipantAccessExpiryService::class);
    }

    private function createCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Szkolenie testowe',
            'description' => 'Opis',
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'is_paid' => false,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
            'next_participant_order' => 1,
        ], $overrides));
    }

    public function test_default_expires_at_from_course_end_is_null_when_course_unlimited(): void
    {
        $course = $this->createCourse([
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
        ]);

        $this->assertNull($this->service->defaultExpiresAtFromCourseEnd($course));
    }

    public function test_form_order_provisioning_without_variant_uses_course_unlimited(): void
    {
        $course = $this->createCourse([
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
        ]);

        $expiresAt = $this->service->resolveAccessExpiresAtForFormOrderProvisioning(
            null,
            $course,
            now(),
            now()->subHours(3),
            false
        );

        $this->assertNull($expiresAt);
    }

    public function test_variant_unlimited_overrides_course_duration(): void
    {
        $course = $this->createCourse([
            'post_end_access_duration_value' => 6,
            'post_end_access_duration_unit' => 'months',
            'post_end_access_rule' => Course::POST_END_RULE_DURATION,
        ]);

        $variant = CoursePriceVariant::create([
            'course_id' => $course->id,
            'name' => 'Wariant',
            'price' => 100,
            'is_active' => true,
            'access_type' => '3',
            'access_duration_value' => 1,
            'access_duration_unit' => 'months',
            'post_end_access_rule' => CoursePriceVariant::POST_END_RULE_UNLIMITED,
        ]);

        $expiresAt = $this->service->expiresAtForPostEndPurchase(
            $variant,
            $course,
            $course->end_date->copy()
        );

        $this->assertNull($expiresAt);
    }

    public function test_variant_duration_overrides_course_unlimited(): void
    {
        $course = $this->createCourse([
            'post_end_access_rule' => Course::POST_END_RULE_UNLIMITED,
        ]);

        $variant = CoursePriceVariant::create([
            'course_id' => $course->id,
            'name' => 'Wariant',
            'price' => 100,
            'is_active' => true,
            'access_type' => '3',
            'access_duration_value' => 1,
            'access_duration_unit' => 'months',
            'post_end_access_rule' => CoursePriceVariant::POST_END_RULE_DURATION,
            'post_end_access_duration_value' => 3,
            'post_end_access_duration_unit' => 'months',
        ]);

        $baseDate = $course->end_date->copy();
        $expiresAt = $this->service->expiresAtForPostEndPurchase($variant, $course, $baseDate);

        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->equalTo($baseDate->copy()->addMonths(3)));
    }

    public function test_course_duration_used_when_variant_inherits(): void
    {
        PaymentDisplayOption::query()->updateOrInsert(['id' => 1], [
            'show_pay_publigo' => true,
            'show_pay_online' => true,
            'show_deferred_order' => true,
            'show_order_form' => true,
            'show_order_form_alt' => true,
            'order_form_auto_fill_test_data' => false,
            'default_post_end_access_duration_value' => 2,
            'default_post_end_access_duration_unit' => 'months',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $course = $this->createCourse([
            'post_end_access_duration_value' => 5,
            'post_end_access_duration_unit' => 'weeks',
            'post_end_access_rule' => Course::POST_END_RULE_DURATION,
        ]);

        $baseDate = Carbon::parse('2026-01-15 12:00:00');
        $expiresAt = $this->service->expiresAtForPostEndPurchase(null, $course, $baseDate);

        $this->assertTrue($expiresAt->equalTo($baseDate->copy()->addWeeks(5)));
    }
}
