<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\TrainerInvoice;
use App\Models\TrainerInvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\TrainerInvoicePeriodFilter;
use Tests\TestCase;

class TrainerInvoicesModuleTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name, int $level = 1): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => $name, 'level' => $level, 'is_system' => true]
        );
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role_id' => $this->role('super_admin', 100)->id,
            'is_active' => 1,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => $this->role('admin', 50)->id,
            'is_active' => 1,
        ]);
    }

    private function seedInvoiceWithCourse(): array
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.test',
            'is_active' => true,
        ]);
        $course = Course::create([
            'title' => 'Szkolenie A',
            'description' => 'T',
            'start_date' => '2026-05-10 10:00:00',
            'end_date' => '2026-05-10 12:00:00',
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
        $invoice = TrainerInvoice::create([
            'instructor_id' => $instructor->id,
            'invoice_number' => 'FV/MOD/1',
            'invoice_date' => '2026-05-10',
            'notes' => 'Notatka modułu',
            'payment_status' => TrainerInvoice::PAYMENT_STATUS_UNPAID,
        ]);
        $item = TrainerInvoiceItem::create([
            'trainer_invoice_id' => $invoice->id,
            'course_id' => $course->id,
            'amount_gross' => 999.99,
        ]);

        return compact('instructor', 'course', 'invoice', 'item');
    }

    /** @return array<string, string> */
    private function defaultListQuery(): array
    {
        return TrainerInvoicePeriodFilter::defaultQueryParams();
    }

    public function test_admin_cannot_access_trainer_invoices_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('accounting.trainer-invoices.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_index_and_show(): void
    {
        $data = $this->seedInvoiceWithCourse();
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('accounting.trainer-invoices.index', $this->defaultListQuery()))
            ->assertOk()
            ->assertSee('FV/MOD/1');

        $this->actingAs($user)
            ->get(route('accounting.trainer-invoices.show', $data['invoice']))
            ->assertOk()
            ->assertSee('Notatka modułu')
            ->assertSee('Szkolenie A');
    }

    public function test_super_admin_can_delete_whole_invoice(): void
    {
        $data = $this->seedInvoiceWithCourse();
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->delete(route('accounting.trainer-invoices.destroy', array_merge(['trainerInvoice' => $data['invoice']], $this->defaultListQuery())))
            ->assertRedirect(route('accounting.trainer-invoices.index', $this->defaultListQuery()));

        $this->assertDatabaseMissing('trainer_invoices', ['id' => $data['invoice']->id]);
        $this->assertDatabaseMissing('trainer_invoice_items', ['id' => $data['item']->id]);
    }

    public function test_super_admin_can_delete_single_item(): void
    {
        $data = $this->seedInvoiceWithCourse();
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->delete(route('accounting.trainer-invoices.items.destroy', array_merge(['trainerInvoice' => $data['invoice'], 'item' => $data['item']], $this->defaultListQuery())))
            ->assertRedirect(route('accounting.trainer-invoices.show', array_merge(['trainerInvoice' => $data['invoice']], $this->defaultListQuery())));

        $this->assertDatabaseMissing('trainer_invoice_items', ['id' => $data['item']->id]);
        $this->assertDatabaseHas('trainer_invoices', ['id' => $data['invoice']->id]);
    }
}
