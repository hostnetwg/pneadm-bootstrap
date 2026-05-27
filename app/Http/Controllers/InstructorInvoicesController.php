<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\InstructorInvoice;
use App\Models\InstructorInvoiceItem;
use App\Services\InstructorSettlementService;
use App\Support\InstructorInvoicePeriodFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstructorInvoicesController extends Controller
{
    public function __construct(
        private readonly InstructorSettlementService $settlementService
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function listFiltersForUrl(Request $request, array $periodRange): array
    {
        return array_merge(
            $request->only([
                'instructor_id',
                'payment_status',
                'settlement_type',
                'search',
            ]),
            [
                'period' => $periodRange['period'],
                'date_from' => $periodRange['date_from'] ?? '',
                'date_to' => $periodRange['date_to'] ?? '',
            ]
        );
    }

    /**
     * @param  array{period: string, date_from: string|null, date_to: string|null}  $periodRange
     */
    private function applyListFilters(Builder $query, Request $request, array $periodRange): Builder
    {
        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->integer('instructor_id'));
        }

        if ($request->filled('payment_status') && in_array($request->payment_status, ['paid', 'unpaid'], true)) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('settlement_type') && in_array($request->settlement_type, [
            InstructorInvoice::SETTLEMENT_TYPE_INVOICE,
            InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
        ], true)) {
            $query->where('settlement_type', $request->settlement_type);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('ksef_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($periodRange['date_from'])) {
            $query->whereDate('invoice_date', '>=', $periodRange['date_from']);
        }

        if (! empty($periodRange['date_to'])) {
            $query->whereDate('invoice_date', '<=', $periodRange['date_to']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $listFilters
     * @return array<string, mixed>
     */
    private function routeParams(InstructorInvoice $instructorInvoice, array $listFilters = []): array
    {
        return array_merge(['instructorInvoice' => $instructorInvoice], $listFilters);
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (InstructorInvoicePeriodFilter::shouldApplyDefaultPeriod($request)) {
            return redirect()->route(
                'accounting.instructor-invoices.index',
                InstructorInvoicePeriodFilter::defaultQueryParams()
            );
        }

        $periodRange = InstructorInvoicePeriodFilter::resolve($request);
        $filters = $this->listFiltersForUrl($request, $periodRange);

        $query = InstructorInvoice::query();
        $this->applyListFilters($query, $request, $periodRange);

        $filteredTotalGross = (float) InstructorInvoiceItem::query()
            ->whereIn('instructor_invoice_id', (clone $query)->select('instructor_invoices.id'))
            ->sum('amount_gross');

        $filteredInvoicesCount = (clone $query)->count();

        $invoices = (clone $query)
            ->with('instructor:id,first_name,last_name,title')
            ->withCount('items')
            ->withSum('items as items_total_gross', 'amount_gross')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $instructors = Instructor::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'title']);

        return view('accounting.instructor-invoices.index', [
            'invoices' => $invoices,
            'instructors' => $instructors,
            'filters' => $filters,
            'periodOptions' => InstructorInvoicePeriodFilter::periodOptions(),
            'filteredTotalGross' => $filteredTotalGross,
            'filteredInvoicesCount' => $filteredInvoicesCount,
            'periodLabel' => InstructorInvoicePeriodFilter::periodOptions()[$periodRange['period']] ?? '',
            'settlementTypeOptions' => InstructorInvoice::settlementTypeOptions(),
        ]);
    }

    public function show(Request $request, InstructorInvoice $instructorInvoice): View
    {
        $periodRange = InstructorInvoicePeriodFilter::resolve($request);

        $instructorInvoice->load([
            'instructor:id,first_name,last_name,title,email',
            'items' => fn ($q) => $q->with(['course:id,title,start_date,end_date,instructor_id']),
        ]);

        return view('accounting.instructor-invoices.show', [
            'invoice' => $instructorInvoice,
            'listFilters' => $this->listFiltersForUrl($request, $periodRange),
        ]);
    }

    public function update(Request $request, InstructorInvoice $instructorInvoice): RedirectResponse
    {
        $periodRange = InstructorInvoicePeriodFilter::resolve($request);

        $validated = $request->validate([
            'settlement_type' => ['required', Rule::in([
                InstructorInvoice::SETTLEMENT_TYPE_INVOICE,
                InstructorInvoice::SETTLEMENT_TYPE_MANDATE,
            ])],
            'invoice_number' => 'nullable|string|max:64',
            'ksef_number' => 'nullable|string|max:128',
            'invoice_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'payment_status' => ['required', Rule::in([InstructorInvoice::PAYMENT_STATUS_UNPAID, InstructorInvoice::PAYMENT_STATUS_PAID])],
            'paid_at' => 'nullable|date',
        ]);

        if ($validated['payment_status'] === InstructorInvoice::PAYMENT_STATUS_PAID && empty($validated['paid_at'])) {
            $validated['paid_at'] = now()->toDateString();
        }

        try {
            $this->settlementService->updateInvoice($instructorInvoice, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('accounting.instructor-invoices.show', $this->routeParams($instructorInvoice, $this->listFiltersForUrl($request, $periodRange)))
            ->with('success', 'Rozliczenie zostało zaktualizowane.');
    }

    public function destroy(Request $request, InstructorInvoice $instructorInvoice): RedirectResponse
    {
        $periodRange = InstructorInvoicePeriodFilter::resolve($request);
        $number = $instructorInvoice->invoice_number;
        $this->settlementService->deleteInvoice($instructorInvoice);

        return redirect()
            ->route('accounting.instructor-invoices.index', $this->listFiltersForUrl($request, $periodRange))
            ->with('success', "Faktura {$number} i wszystkie powiązane pozycje zostały usunięte.");
    }

    public function destroyItem(Request $request, InstructorInvoice $instructorInvoice, InstructorInvoiceItem $item): RedirectResponse
    {
        $periodRange = InstructorInvoicePeriodFilter::resolve($request);

        if ((int) $item->instructor_invoice_id !== (int) $instructorInvoice->id) {
            abort(404);
        }

        $this->settlementService->deleteItem($item);

        return redirect()
            ->route('accounting.instructor-invoices.show', $this->routeParams($instructorInvoice, $this->listFiltersForUrl($request, $periodRange)))
            ->with('success', 'Pozycja szkolenia została usunięta z faktury.');
    }

    public function markPaid(Request $request, InstructorInvoice $instructorInvoice): RedirectResponse
    {
        $validated = $request->validate(['paid_at' => 'nullable|date']);
        $paidAt = ! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : null;
        $this->settlementService->markInvoicePaid($instructorInvoice, $paidAt);

        return back()->with('success', 'Rozliczenie oznaczone jako opłacone.');
    }

    public function markUnpaid(InstructorInvoice $instructorInvoice): RedirectResponse
    {
        $this->settlementService->markInvoiceUnpaid($instructorInvoice);

        return back()->with('success', 'Rozliczenie oznaczone jako nieopłacone.');
    }
}
