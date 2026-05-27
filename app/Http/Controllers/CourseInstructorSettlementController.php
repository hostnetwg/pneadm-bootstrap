<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorInvoice;
use App\Services\InstructorSettlementService;
use App\Support\InstructorSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseInstructorSettlementController extends Controller
{
    public function __construct(
        private readonly InstructorSettlementService $settlementService
    ) {}

    public function show(Course $course): JsonResponse
    {
        $item = $course->instructorSettlementItem()
            ->with(['instructorInvoice' => fn ($q) => $q->withCount('items')])
            ->first();

        $course->loadMissing('instructor');

        return response()->json([
            'success' => true,
            'in_scope' => InstructorSettlement::isCourseInScope($course),
            'cutoff_date' => InstructorSettlement::cutoffDate()->format('Y-m-d'),
            'default_settlement_type' => $this->settlementService->defaultSettlementTypeForInstructor($course->instructor),
            'settlement' => $item ? $this->formatSettlement($item) : null,
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        try {
            $this->settlementService->assertCourseInScope($course);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $settlementType = $request->input('settlement_type', InstructorInvoice::SETTLEMENT_TYPE_INVOICE);
        $isMandate = $settlementType === InstructorInvoice::SETTLEMENT_TYPE_MANDATE;

        $validator = Validator::make($request->all(), [
            'settlement_type' => ['nullable', Rule::in([
                InstructorInvoice::SETTLEMENT_TYPE_INVOICE,
                InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            ])],
            'instructor_invoice_id' => 'nullable|integer|exists:instructor_invoices,id',
            'invoice_number' => ($isMandate ? 'nullable' : 'required_without:instructor_invoice_id').'|string|max:64',
            'ksef_number' => 'nullable|string|max:128',
            'invoice_date' => 'nullable|date',
            'amount_gross' => 'required|numeric|min:0|max:9999999.99',
            'amount_net' => 'nullable|numeric|min:0|max:9999999.99',
            'payment_status' => ['nullable', Rule::in([InstructorInvoice::PAYMENT_STATUS_UNPAID, InstructorInvoice::PAYMENT_STATUS_PAID])],
            'paid_at' => 'nullable|date',
            'invoice_notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->input('payment_status') === InstructorInvoice::PAYMENT_STATUS_PAID && ! $request->filled('paid_at')) {
            $request->merge(['paid_at' => now()->toDateString()]);
        }

        try {
            $item = $this->settlementService->upsertForCourse($course, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Rozliczenie instruktora zostało zapisane.',
                'settlement' => $this->formatSettlement($item),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zapisać rozliczenia: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Course $course): JsonResponse
    {
        try {
            $this->settlementService->removeForCourse($course);

            return response()->json([
                'success' => true,
                'message' => 'Powiązanie rozliczenia ze szkoleniem zostało usunięte.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroyDocument(Course $course): JsonResponse
    {
        try {
            $this->settlementService->assertCourseInScope($course);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $item = $course->instructorSettlementItem()
            ->with('instructorInvoice')
            ->first();

        if (! $item || ! $item->instructorInvoice) {
            return response()->json([
                'success' => false,
                'message' => 'Brak rozliczenia do usunięcia.',
            ], 404);
        }

        $invoice = $item->instructorInvoice;
        $itemsCount = $invoice->items()->count();

        if ($itemsCount > 1) {
            return response()->json([
                'success' => false,
                'message' => 'To rozliczenie obejmuje wiele szkoleń. Usuń całość w module Księgowość lub odłącz tylko to szkolenie.',
            ], 422);
        }

        $number = $invoice->invoice_number;
        $this->settlementService->deleteInvoice($invoice);

        return response()->json([
            'success' => true,
            'message' => "Rozliczenie {$number} zostało usunięte.",
        ]);
    }

    public function instructorInvoices(Request $request, Instructor $instructor): JsonResponse
    {
        $settlementType = $request->query('settlement_type', InstructorInvoice::SETTLEMENT_TYPE_INVOICE);

        $invoices = InstructorInvoice::query()
            ->where('instructor_id', $instructor->id)
            ->where('settlement_type', $settlementType)
            ->withCount('items')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (InstructorInvoice $invoice) => [
                'id' => $invoice->id,
                'settlement_type' => $invoice->settlement_type,
                'invoice_number' => $invoice->invoice_number,
                'ksef_number' => $invoice->ksef_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'payment_status' => $invoice->payment_status,
                'paid_at' => $invoice->paid_at?->format('Y-m-d'),
                'items_count' => $invoice->items_count,
                'total_amount' => $invoice->totalItemsAmount(),
            ]);

        return response()->json([
            'success' => true,
            'invoices' => $invoices,
        ]);
    }

    public function markPaid(Request $request, InstructorInvoice $instructorInvoice): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'paid_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $paidAt = $request->filled('paid_at')
            ? \Carbon\Carbon::parse($request->input('paid_at'))
            : null;

        $invoice = $this->settlementService->markInvoicePaid($instructorInvoice, $paidAt);

        return response()->json([
            'success' => true,
            'message' => 'Faktura oznaczona jako opłacona.',
            'invoice' => [
                'id' => $invoice->id,
                'payment_status' => $invoice->payment_status,
                'paid_at' => $invoice->paid_at?->format('Y-m-d'),
            ],
        ]);
    }

    private function formatSettlement(\App\Models\InstructorInvoiceItem $item): array
    {
        $invoice = $item->instructorInvoice;

        return [
            'item_id' => $item->id,
            'amount_gross' => (string) $item->amount_gross,
            'amount_net' => $item->amount_net !== null ? (string) $item->amount_net : null,
            'invoice' => [
                'id' => $invoice->id,
                'settlement_type' => $invoice->settlement_type,
                'invoice_number' => $invoice->invoice_number,
                'ksef_number' => $invoice->ksef_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'payment_status' => $invoice->payment_status,
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                'is_paid' => $invoice->isPaid(),
                'notes' => $invoice->notes,
                'items_count' => (int) ($invoice->items_count ?? 0),
            ],
        ];
    }
}
