<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorInvoice;
use App\Models\InstructorInvoiceItem;
use App\Support\InstructorSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorSettlementService
{
    public function assertCourseInScope(Course $course): void
    {
        if (! InstructorSettlement::isCourseInScope($course)) {
            throw ValidationException::withMessages([
                'course' => 'Rozliczenie instruktora jest dostępne tylko dla szkoleń od '.InstructorSettlement::cutoffDate()->format('d.m.Y').'.',
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
    public function upsertForCourse(Course $course, array $data): InstructorInvoiceItem
    {
        $this->assertCourseInScope($course);
        $course->loadMissing('instructor');

        return DB::transaction(function () use ($course, $data) {
            $invoice = $this->resolveInvoice($course, $data);
            $this->applyPaymentStatus($invoice, $data);

            $item = InstructorInvoiceItem::query()->updateOrCreate(
                [
                    'instructor_invoice_id' => $invoice->id,
                    'course_id' => $course->id,
                ],
                [
                    'amount_gross' => $data['amount_gross'],
                    'amount_net' => $data['amount_net'] ?? null,
                    'notes' => $data['item_notes'] ?? $data['notes'] ?? null,
                ]
            );

            return $item->load('instructorInvoice');
        });
    }

    public function removeForCourse(Course $course): void
    {
        $this->assertCourseInScope($course);

        InstructorInvoiceItem::query()
            ->where('course_id', $course->id)
            ->delete();
    }

    public function markInvoicePaid(InstructorInvoice $invoice, ?Carbon $paidAt = null): InstructorInvoice
    {
        $invoice->update([
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_PAID,
            'paid_at' => $paidAt ?? now(),
        ]);

        return $invoice->fresh();
    }

    public function markInvoiceUnpaid(InstructorInvoice $invoice): InstructorInvoice
    {
        $invoice->update([
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
            'paid_at' => null,
        ]);

        return $invoice->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(InstructorInvoice $invoice, array $data): InstructorInvoice
    {
        $settlementType = $this->normalizeSettlementType(
            (string) ($data['settlement_type'] ?? $invoice->settlement_type)
        );

        $providedNumber = array_key_exists('invoice_number', $data)
            ? trim((string) $data['invoice_number'])
            : (string) $invoice->invoice_number;

        if ($providedNumber === '' && $settlementType === InstructorInvoice::SETTLEMENT_TYPE_MANDATE) {
            $providedNumber = (string) $invoice->invoice_number;
        }

        $invoiceNumber = $this->resolveDocumentNumber(
            $settlementType,
            $providedNumber,
            (int) $invoice->instructor_id
        );

        $this->assertUniqueDocumentNumber(
            (int) $invoice->instructor_id,
            $invoiceNumber,
            $invoice->id
        );

        $invoice->update([
            'settlement_type' => $settlementType,
            'invoice_number' => $invoiceNumber,
            'ksef_number' => $settlementType === InstructorInvoice::SETTLEMENT_TYPE_INVOICE
                ? ($data['ksef_number'] ?? null)
                : null,
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

    public function deleteInvoice(InstructorInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    public function deleteItem(InstructorInvoiceItem $item): void
    {
        $item->delete();
    }

    public function defaultSettlementTypeForInstructor(?Instructor $instructor): string
    {
        $type = $instructor?->default_settlement_type;

        if ($type && in_array($type, [
            InstructorInvoice::SETTLEMENT_TYPE_INVOICE,
            InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
        ], true)) {
            return $type;
        }

        return InstructorInvoice::SETTLEMENT_TYPE_INVOICE;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveInvoice(Course $course, array $data): InstructorInvoice
    {
        $settlementType = $this->normalizeSettlementType(
            (string) ($data['settlement_type'] ?? $this->defaultSettlementTypeForInstructor($course->instructor))
        );

        if (! empty($data['instructor_invoice_id'])) {
            $invoice = InstructorInvoice::query()->findOrFail($data['instructor_invoice_id']);

            if ((int) $invoice->instructor_id !== (int) $course->instructor_id) {
                throw ValidationException::withMessages([
                    'instructor_invoice_id' => 'Wybrane rozliczenie należy do innego instruktora.',
                ]);
            }

            if ($invoice->settlement_type !== $settlementType) {
                throw ValidationException::withMessages([
                    'instructor_invoice_id' => 'Nie można przypiąć do rozliczenia innego typu (faktura vs umowa zlecenie).',
                ]);
            }

            $fill = [
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            ];

            if ($settlementType === InstructorInvoice::SETTLEMENT_TYPE_INVOICE) {
                $fill['ksef_number'] = $data['ksef_number'] ?? $invoice->ksef_number;
            }

            $invoice->fill($fill);

            if (array_key_exists('invoice_notes', $data)) {
                $invoice->notes = $data['invoice_notes'];
            }

            $invoice->save();

            return $invoice;
        }

        $invoiceNumber = $this->resolveDocumentNumber(
            $settlementType,
            trim((string) ($data['invoice_number'] ?? '')),
            (int) $course->instructor_id
        );

        $existingInvoice = InstructorInvoice::query()
            ->where('instructor_id', $course->instructor_id)
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if ($existingInvoice !== null) {
            if ($existingInvoice->settlement_type !== $settlementType) {
                throw ValidationException::withMessages([
                    'invoice_number' => 'Ten numer dokumentu jest już użyty u tego instruktora jako inny typ rozliczenia.',
                ]);
            }

            $existingInvoice->update([
                'ksef_number' => $settlementType === InstructorInvoice::SETTLEMENT_TYPE_INVOICE
                    ? ($data['ksef_number'] ?? $existingInvoice->ksef_number)
                    : null,
                'invoice_date' => $data['invoice_date'] ?? $existingInvoice->invoice_date,
                'notes' => array_key_exists('invoice_notes', $data)
                    ? $data['invoice_notes']
                    : $existingInvoice->notes,
            ]);

            return $existingInvoice;
        }

        $this->assertUniqueDocumentNumber((int) $course->instructor_id, $invoiceNumber);

        return InstructorInvoice::query()->create([
            'instructor_id' => $course->instructor_id,
            'invoice_number' => $invoiceNumber,
            'settlement_type' => $settlementType,
            'ksef_number' => $settlementType === InstructorInvoice::SETTLEMENT_TYPE_INVOICE
                ? ($data['ksef_number'] ?? null)
                : null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'notes' => $data['invoice_notes'] ?? null,
        ]);
    }

    private function normalizeSettlementType(string $type): string
    {
        if ($type === InstructorInvoice::SETTLEMENT_TYPE_MANDATE) {
            return InstructorInvoice::SETTLEMENT_TYPE_MANDATE;
        }

        return InstructorInvoice::SETTLEMENT_TYPE_INVOICE;
    }

    private function resolveDocumentNumber(string $settlementType, string $number, int $instructorId): string
    {
        if ($number !== '') {
            return $number;
        }

        if ($settlementType === InstructorInvoice::SETTLEMENT_TYPE_MANDATE) {
            return $this->generateMandateReference($instructorId);
        }

        throw ValidationException::withMessages([
            'invoice_number' => 'Numer faktury jest wymagany.',
        ]);
    }

    private function generateMandateReference(int $instructorId): string
    {
        $year = now()->year;
        $prefix = "UZ/{$year}/{$instructorId}/";

        $lastNumber = InstructorInvoice::query()
            ->where('instructor_id', $instructorId)
            ->where('settlement_type', InstructorInvoice::SETTLEMENT_TYPE_MANDATE)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $sequence = 1;
        if (is_string($lastNumber) && preg_match('/\/(\d+)$/', $lastNumber, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.$sequence;
    }

    private function assertUniqueDocumentNumber(int $instructorId, string $invoiceNumber, ?int $exceptId = null): void
    {
        $query = InstructorInvoice::query()
            ->where('instructor_id', $instructorId)
            ->where('invoice_number', $invoiceNumber);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Ten numer dokumentu jest już użyty u tego instruktora.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyPaymentStatus(InstructorInvoice $invoice, array $data): void
    {
        if (! isset($data['payment_status'])) {
            return;
        }

        if ($data['payment_status'] === InstructorInvoice::PAYMENT_STATUS_PAID) {
            $paidAt = ! empty($data['paid_at'])
                ? Carbon::parse($data['paid_at'])
                : ($invoice->paid_at ?? now());

            $invoice->update([
                'payment_status' => InstructorInvoice::PAYMENT_STATUS_PAID,
                'paid_at' => $paidAt,
            ]);

            return;
        }

        $invoice->update([
            'payment_status' => InstructorInvoice::PAYMENT_STATUS_UNPAID,
            'paid_at' => null,
        ]);
    }
}

