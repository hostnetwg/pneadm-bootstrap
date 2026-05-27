<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\TrainerInvoice;
use App\Services\TrainerSettlementService;
use App\Support\TrainerSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseTrainerSettlementController extends Controller
{
    public function __construct(
        private readonly TrainerSettlementService $settlementService
    ) {}

    public function show(Course $course): JsonResponse
    {
        $item = $course->trainerSettlementItem()
            ->with('trainerInvoice')
            ->first();

        return response()->json([
            'success' => true,
            'in_scope' => TrainerSettlement::isCourseInScope($course),
            'cutoff_date' => TrainerSettlement::cutoffDate()->format('Y-m-d'),
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

        $validator = Validator::make($request->all(), [
            'trainer_invoice_id' => 'nullable|integer|exists:trainer_invoices,id',
            'invoice_number' => 'required_without:trainer_invoice_id|string|max:64',
            'ksef_number' => 'nullable|string|max:128',
            'invoice_date' => 'nullable|date',
            'amount_gross' => 'required|numeric|min:0|max:9999999.99',
            'amount_net' => 'nullable|numeric|min:0|max:9999999.99',
            'payment_status' => ['nullable', Rule::in([TrainerInvoice::PAYMENT_STATUS_UNPAID, TrainerInvoice::PAYMENT_STATUS_PAID])],
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

        if ($request->input('payment_status') === TrainerInvoice::PAYMENT_STATUS_PAID && ! $request->filled('paid_at')) {
            $request->merge(['paid_at' => now()->toDateString()]);
        }

        try {
            $item = $this->settlementService->upsertForCourse($course, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Rozliczenie trenera zostało zapisane.',
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

    public function instructorInvoices(Instructor $instructor): JsonResponse
    {
        $invoices = TrainerInvoice::query()
            ->where('instructor_id', $instructor->id)
            ->withCount('items')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (TrainerInvoice $invoice) => [
                'id' => $invoice->id,
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

    public function markPaid(Request $request, TrainerInvoice $trainerInvoice): JsonResponse
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

        $invoice = $this->settlementService->markInvoicePaid($trainerInvoice, $paidAt);

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

    private function formatSettlement(\App\Models\TrainerInvoiceItem $item): array
    {
        $invoice = $item->trainerInvoice;

        return [
            'item_id' => $item->id,
            'amount_gross' => (string) $item->amount_gross,
            'amount_net' => $item->amount_net !== null ? (string) $item->amount_net : null,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'ksef_number' => $invoice->ksef_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'payment_status' => $invoice->payment_status,
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                'is_paid' => $invoice->isPaid(),
                'notes' => $invoice->notes,
            ],
        ];
    }
}
