<?php

namespace App\Services;

use App\Models\Course;
use App\Models\TrainerInvoice;
use App\Models\TrainerInvoiceItem;
use App\Support\TrainerSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainerSettlementService
{
    public function assertCourseInScope(Course $course): void
    {
        if (! TrainerSettlement::isCourseInScope($course)) {
            throw ValidationException::withMessages([
                'course' => 'Rozliczenie trenera jest dostępne tylko dla szkoleń od '.TrainerSettlement::cutoffDate()->format('d.m.Y').'.',
            ]);
        }

        if (! $course->instructor_id) {
            throw ValidationException::withMessages([
                'instructor_id' => 'Szkolenie musi mieć przypisanego instruktora.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForCourse(Course $course, array $data): TrainerInvoiceItem
    {
        $this->assertCourseInScope($course);
        $course->loadMissing('instructor');

        return DB::transaction(function () use ($course, $data) {
            $invoice = $this->resolveInvoice($course, $data);
            $this->applyPaymentStatus($invoice, $data);

            $item = TrainerInvoiceItem::query()->updateOrCreate(
                [
                    'trainer_invoice_id' => $invoice->id,
                    'course_id' => $course->id,
                ],
                [
                    'amount_gross' => $data['amount_gross'],
                    'amount_net' => $data['amount_net'] ?? null,
                    'notes' => $data['item_notes'] ?? $data['notes'] ?? null,
                ]
            );

            return $item->load('trainerInvoice');
        });
    }

    public function removeForCourse(Course $course): void
    {
        $this->assertCourseInScope($course);

        TrainerInvoiceItem::query()
            ->where('course_id', $course->id)
            ->delete();
    }

    public function markInvoicePaid(TrainerInvoice $invoice, ?Carbon $paidAt = null): TrainerInvoice
    {
        $invoice->update([
            'payment_status' => TrainerInvoice::PAYMENT_STATUS_PAID,
            'paid_at' => $paidAt ?? now(),
        ]);

        return $invoice->fresh();
    }

    public function markInvoiceUnpaid(TrainerInvoice $invoice): TrainerInvoice
    {
        $invoice->update([
            'payment_status' => TrainerInvoice::PAYMENT_STATUS_UNPAID,
            'paid_at' => null,
        ]);

        return $invoice->fresh();
    }


    /**
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(TrainerInvoice $invoice, array $data): TrainerInvoice
    {
        $invoiceNumber = trim((string) ($data['invoice_number'] ?? $invoice->invoice_number));
        if ($invoiceNumber === '') {
            throw ValidationException::withMessages([
                'invoice_number' => 'Numer faktury jest wymagany.',
            ]);
        }

        $duplicate = TrainerInvoice::query()
            ->where('instructor_id', $invoice->instructor_id)
            ->where('invoice_number', $invoiceNumber)
            ->where('id', '!=', $invoice->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Ten numer faktury jest już użyty u tego trenera.',
            ]);
        }

        $invoice->update([
            'invoice_number' => $invoiceNumber,
            'ksef_number' => $data['ksef_number'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (isset($data['payment_status'])) {
            $this->applyPaymentStatus($invoice, [
                'payment_status' => $data['payment_status'],
                'paid_at' => $data['paid_at'] ?? null,
            ]);
        }

        return $invoice->fresh();
    }

    public function deleteInvoice(TrainerInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    public function deleteItem(TrainerInvoiceItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveInvoice(Course $course, array $data): TrainerInvoice
    {
        if (! empty($data['trainer_invoice_id'])) {
            $invoice = TrainerInvoice::query()->findOrFail($data['trainer_invoice_id']);

            if ((int) $invoice->instructor_id !== (int) $course->instructor_id) {
                throw ValidationException::withMessages([
                    'trainer_invoice_id' => 'Wybrana faktura należy do innego instruktora.',
                ]);
            }

            $invoice->fill([
                'ksef_number' => $data['ksef_number'] ?? $invoice->ksef_number,
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            ]);
            if (array_key_exists('invoice_notes', $data)) {
                $invoice->notes = $data['invoice_notes'];
            }
            $invoice->save();

            return $invoice;
        }

        $invoiceNumber = trim((string) ($data['invoice_number'] ?? ''));
        if ($invoiceNumber === '') {
            throw ValidationException::withMessages([
                'invoice_number' => 'Numer faktury jest wymagany.',
            ]);
        }

        return TrainerInvoice::query()->updateOrCreate(
            [
                'instructor_id' => $course->instructor_id,
                'invoice_number' => $invoiceNumber,
            ],
            [
                'ksef_number' => $data['ksef_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'notes' => $data['invoice_notes'] ?? null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyPaymentStatus(TrainerInvoice $invoice, array $data): void
    {
        if (! isset($data['payment_status'])) {
            return;
        }

        if ($data['payment_status'] === TrainerInvoice::PAYMENT_STATUS_PAID) {
            $paidAt = ! empty($data['paid_at'])
                ? Carbon::parse($data['paid_at'])
                : ($invoice->paid_at ?? now());

            $invoice->update([
                'payment_status' => TrainerInvoice::PAYMENT_STATUS_PAID,
                'paid_at' => $paidAt,
            ]);

            return;
        }

        $invoice->update([
            'payment_status' => TrainerInvoice::PAYMENT_STATUS_UNPAID,
            'paid_at' => null,
        ]);
    }
}
