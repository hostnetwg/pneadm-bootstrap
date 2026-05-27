<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\TrainerInvoice;
use App\Models\TrainerInvoiceItem;
use App\Models\User;
use App\Support\TrainerSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name, int $level = 1): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            [
                'display_name' => ucfirst(str_replace('_', ' ', $name)),
                'level' => $level,
                'is_system' => true,
            ]
        );
    }

    private function userWithRole(string $roleName): User
    {
        $role = $this->role($roleName, $roleName === 'super_admin' ? 100 : 50);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => 1,
        ]);
    }

    private function superAdmin(): User
    {
        return $this->userWithRole('super_admin');
    }

    private function createInstructor(): Instructor
    {
        return Instructor::create([
            'first_name' => 'Anna',
            'last_name' => 'Testowa',
            'email' => 'anna.test@example.test',
            'is_active' => true,
        ]);
    }

    private function createCourse(Instructor $instructor, string $startDate): Course
    {
        return Course::create([
            'title' => 'Szkolenie testowe',
            'description' => 'Test',
            'start_date' => $startDate,
            'end_date' => $startDate,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
    }

    public function test_non_super_admin_cannot_manage_settlement(): void
    {
        $user = $this->userWithRole('admin');
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $course), [
            'invoice_number' => 'FV/FORBIDDEN',
            'amount_gross' => 100,
        ])->assertForbidden();
    }

    public function test_single_invoice_settlement_for_course(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $response = $this->actingAs($user)->postJson(
            route('courses.trainer-settlement.store', $course),
            [
                'invoice_number' => 'FV/TEST/1',
                'ksef_number' => 'KSEF-123',
                'amount_gross' => 1500.50,
                'payment_status' => TrainerInvoice::PAYMENT_STATUS_UNPAID,
            ]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('trainer_invoices', [
            'instructor_id' => $instructor->id,
            'invoice_number' => 'FV/TEST/1',
            'ksef_number' => 'KSEF-123',
            'payment_status' => TrainerInvoice::PAYMENT_STATUS_UNPAID,
        ]);
        $this->assertDatabaseHas('trainer_invoice_items', [
            'course_id' => $course->id,
            'amount_gross' => '1500.50',
        ]);
    }

    public function test_consolidated_invoice_links_multiple_courses(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $courseA = $this->createCourse($instructor, '2026-05-10 10:00:00');
        $courseB = $this->createCourse($instructor, '2026-05-20 10:00:00');

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $courseA), [
            'invoice_number' => 'FV/ZB/5',
            'amount_gross' => 800,
        ])->assertOk();

        $invoice = TrainerInvoice::where('invoice_number', 'FV/ZB/5')->first();
        $this->assertNotNull($invoice);

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $courseB), [
            'trainer_invoice_id' => $invoice->id,
            'amount_gross' => 1200,
        ])->assertOk();

        $this->assertEquals(2, TrainerInvoiceItem::where('trainer_invoice_id', $invoice->id)->count());
        $this->assertEquals('800.00', (string) TrainerInvoiceItem::where('course_id', $courseA->id)->value('amount_gross'));
        $this->assertEquals('1200.00', (string) TrainerInvoiceItem::where('course_id', $courseB->id)->value('amount_gross'));
    }

    public function test_mark_invoice_paid(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $course), [
            'invoice_number' => 'FV/PAY/1',
            'amount_gross' => 500,
        ]);

        $invoice = TrainerInvoice::first();
        $this->actingAs($user)->postJson(route('trainer-invoices.mark-paid', $invoice), [
            'paid_at' => '2026-06-01',
        ])->assertOk();

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertEquals('2026-06-01', $invoice->paid_at->format('Y-m-d'));
    }


    public function test_show_includes_invoice_notes(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $course), [
            'invoice_number' => 'FV/NOTES/1',
            'amount_gross' => 100,
            'invoice_notes' => 'Notatka testowa do faktury',
        ])->assertOk();

        $this->actingAs($user)
            ->getJson(route('courses.trainer-settlement.show', $course))
            ->assertOk()
            ->assertJsonPath('settlement.invoice.notes', 'Notatka testowa do faktury');
    }

    public function test_course_before_cutoff_is_rejected(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-04-01 10:00:00');

        $this->assertFalse(TrainerSettlement::isCourseInScope($course));

        $this->actingAs($user)->postJson(route('courses.trainer-settlement.store', $course), [
            'invoice_number' => 'FV/OLD/1',
            'amount_gross' => 100,
        ])->assertStatus(422);
    }
}
