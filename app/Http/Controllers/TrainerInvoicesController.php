<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\TrainerInvoice;
use App\Models\TrainerInvoiceItem;
use App\Services\TrainerSettlementService;
use App\Support\TrainerInvoicePeriodFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrainerInvoicesController extends Controller
{
    public function __construct(
        private readonly TrainerSettlementService $settlementService
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
    private function routeParams(TrainerInvoice $trainerInvoice, array $listFilters = []): array
    {
        return array_merge(['trainerInvoice' => $trainerInvoice], $listFilters);
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (TrainerInvoicePeriodFilter::shouldApplyDefaultPeriod($request)) {
            return redirect()->route(
                'accounting.trainer-invoices.index',
                TrainerInvoicePeriodFilter::defaultQueryParams()
            );
        }

        $periodRange = TrainerInvoicePeriodFilter::resolve($request);
        $filters = $this->listFiltersForUrl($request, $periodRange);

        $query = TrainerInvoice::query();
        $this->applyListFilters($query, $request, $periodRange);

        $filteredTotalGross = (float) TrainerInvoiceItem::query()
            ->whereIn('trainer_invoice_id', (clone $query)->select('trainer_invoices.id'))
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

        return view('accounting.trainer-invoices.index', [
            'invoices' => $invoices,
            'instructors' => $instructors,
            'filters' => $filters,
            'periodOptions' => TrainerInvoicePeriodFilter::periodOptions(),
            'filteredTotalGross' => $filteredTotalGross,
            'filteredInvoicesCount' => $filteredInvoicesCount,
            'periodLabel' => TrainerInvoicePeriodFilter::periodOptions()[$periodRange['period']] ?? '',
        ]);
    }

    public function show(Request $request, TrainerInvoice $trainerInvoice): View
    {
        $periodRange = TrainerInvoicePeriodFilter::resolve($request);

        $trainerInvoice->load([
            'instructor:id,first_name,last_name,title,email',
            'items' => fn ($q) => $q->with(['course:id,title,start_date,end_date,instructor_id']),
        ]);

        return view('accounting.trainer-invoices.show', [
            'invoice' => $trainerInvoice,
            'listFilters' => $this->listFiltersForUrl($request, $periodRange),
        ]);
    }

    public function update(Request $request, TrainerInvoice $trainerInvoice): RedirectResponse
    {
        $periodRange = TrainerInvoicePeriodFilter::resolve($request);

        $validated = $request->validate([
            'invoice_number' => 'required|string|max:64',
            'ksef_number' => 'nullable|string|max:128',
            'invoice_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'payment_status' => ['required', Rule::in([TrainerInvoice::PAYMENT_STATUS_UNPAID, TrainerInvoice::PAYMENT_STATUS_PAID])],
            'paid_at' => 'nullable|date',
        ]);

        if ($validated['payment_status'] === TrainerInvoice::PAYMENT_STATUS_PAID && empty($validated['paid_at'])) {
            $validated['paid_at'] = now()->toDateString();
        }

        try {
            $this->settlementService->updateInvoice($trainerInvoice, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('accounting.trainer-invoices.show', $this->routeParams($trainerInvoice, $this->listFiltersForUrl($request, $periodRange)))
            ->with('success', 'Faktura została zaktualizowana.');
    }

    public function destroy(Request $request, TrainerInvoice $trainerInvoice): RedirectResponse
    {
        $periodRange = TrainerInvoicePeriodFilter::resolve($request);
        $number = $trainerInvoice->invoice_number;
        $this->settlementService->deleteInvoice($trainerInvoice);

        return redirect()
            ->route('accounting.trainer-invoices.index', $this->listFiltersForUrl($request, $periodRange))
            ->with('success', "Faktura {$number} i wszystkie powiązane pozycje zostały usunięte.");
    }

    public function destroyItem(Request $request, TrainerInvoice $trainerInvoice, TrainerInvoiceItem $item): RedirectResponse
    {
        $periodRange = TrainerInvoicePeriodFilter::resolve($request);

        if ((int) $item->trainer_invoice_id !== (int) $trainerInvoice->id) {
            abort(404);
        }

        $this->settlementService->deleteItem($item);

        return redirect()
            ->route('accounting.trainer-invoices.show', $this->routeParams($trainerInvoice, $this->listFiltersForUrl($request, $periodRange)))
            ->with('success', 'Pozycja szkolenia została usunięta z faktury.');
    }

    public function markPaid(Request $request, TrainerInvoice $trainerInvoice): RedirectResponse
    {
        $validated = $request->validate(['paid_at' => 'nullable|date']);
        $paidAt = ! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : null;
        $this->settlementService->markInvoicePaid($trainerInvoice, $paidAt);

        return back()->with('success', 'Faktura oznaczona jako opłacona.');
    }

    public function markUnpaid(TrainerInvoice $trainerInvoice): RedirectResponse
    {
        $this->settlementService->markInvoiceUnpaid($trainerInvoice);

        return back()->with('success', 'Faktura oznaczona jako nieopłacona.');
    }
}
