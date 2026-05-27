<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\InstructorInvoice;
use App\Models\InstructorInvoiceItem;
use App\Models\User;
use App\Support\InstructorSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorSettlementTest extends TestCase
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

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
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
            route('courses.instructor-settlement.store', $course),
            [
                'invoice_number' => 'FV/TEST/1',
                'ksef_number' => 'KSEF-123',
                'amount_gross' => 1500.50,
                'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
            ]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('instructor_invoices', [
            'instructor_id' => $instructor->id,
            'invoice_number' => 'FV/TEST/1',
            'ksef_number' => 'KSEF-123',
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
        ]);
        $this->assertDatabaseHas('instructor_invoice_items', [
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

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $courseA), [
            'invoice_number' => 'FV/ZB/5',
            'amount_gross' => 800,
        ])->assertOk();

        $invoice = InstructorInvoice::where('invoice_number', 'FV/ZB/5')->first();
        $this->assertNotNull($invoice);

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $courseB), [
            'instructor_invoice_id' => $invoice->id,
            'amount_gross' => 1200,
        ])->assertOk();

        $this->assertEquals(2, InstructorInvoiceItem::where('instructor_invoice_id', $invoice->id)->count());
        $this->assertEquals('800.00', (string) InstructorInvoiceItem::where('course_id', $courseA->id)->value('amount_gross'));
        $this->assertEquals('1200.00', (string) InstructorInvoiceItem::where('course_id', $courseB->id)->value('amount_gross'));
    }

    public function test_mark_invoice_paid(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'invoice_number' => 'FV/PAY/1',
            'amount_gross' => 500,
        ]);

        $invoice = InstructorInvoice::first();
        $this->actingAs($user)->postJson(route('instructor-invoices.mark-paid', $invoice), [
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

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'invoice_number' => 'FV/NOTES/1',
            'amount_gross' => 100,
            'invoice_notes' => 'Notatka testowa do faktury',
        ])->assertOk();

        $this->actingAs($user)
            ->getJson(route('courses.instructor-settlement.show', $course))
            ->assertOk()
            ->assertJsonPath('settlement.invoice.notes', 'Notatka testowa do faktury');
    }

    public function test_course_before_cutoff_is_rejected(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-04-01 10:00:00');

        $this->assertFalse(InstructorSettlement::isCourseInScope($course));

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'invoice_number' => 'FV/OLD/1',
            'amount_gross' => 100,
        ])->assertStatus(422);
    }


    public function test_mandate_settlement_without_invoice_number_generates_reference(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'amount_gross' => 750,
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
        ])->assertOk();

        $invoice = InstructorInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(InstructorInvoice::SETTLEMENT_TYPE_MANDATE, $invoice->settlement_type);
        $this->assertStringStartsWith('UZ/', $invoice->invoice_number);
        $this->assertNull($invoice->ksef_number);
    }

    public function test_mandate_with_custom_contract_number(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-16 10:00:00');

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'invoice_number' => 'UMOWA/2026/42',
            'amount_gross' => 300,
        ])->assertOk();

        $this->assertDatabaseHas('instructor_invoices', [
            'invoice_number' => 'UMOWA/2026/42',
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
        ]);
    }

    public function test_consolidated_mandate_links_multiple_courses(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $courseA = $this->createCourse($instructor, '2026-05-10 10:00:00');
        $courseB = $this->createCourse($instructor, '2026-05-20 10:00:00');

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $courseA), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'invoice_number' => 'UZ/ZB/1',
            'amount_gross' => 400,
        ])->assertOk();

        $invoice = InstructorInvoice::where('invoice_number', 'UZ/ZB/1')->first();
        $this->assertNotNull($invoice);

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $courseB), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'instructor_invoice_id' => $invoice->id,
            'amount_gross' => 600,
        ])->assertOk();

        $this->assertEquals(2, InstructorInvoiceItem::where('instructor_invoice_id', $invoice->id)->count());
    }

    public function test_cannot_attach_mandate_course_to_invoice_header(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-18 10:00:00');

        $invoice = InstructorInvoice::create([
            'instructor_id' => $instructor->id,
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_INVOICE,
            'invoice_number' => 'FV/MIX/1',
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
        ]);

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'instructor_invoice_id' => $invoice->id,
            'amount_gross' => 100,
        ])->assertStatus(422);
    }


    public function test_can_update_payment_status_from_course_modal_without_duplicate_number_error(): void
    {
        $user = $this->superAdmin();
        $instructor = $this->createInstructor();
        $course = $this->createCourse($instructor, '2026-05-15 10:00:00');

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'invoice_number' => 'UZ/2026/1/1',
            'amount_gross' => 1850,
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('courses.instructor-settlement.store', $course), [
            'settlement_type' => InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            'invoice_number' => 'UZ/2026/1/1',
            'amount_gross' => 1850,
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_PAID,
            'paid_at' => '2026-05-27',
        ])->assertOk()->assertJsonPath('settlement.invoice.payment_status', InstructorInvoice::PAYMENT_STATUS_PAID);

        $this->assertDatabaseHas('instructor_invoices', [
            'invoice_number' => 'UZ/2026/1/1',
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_PAID,
        ]);
    }

}
