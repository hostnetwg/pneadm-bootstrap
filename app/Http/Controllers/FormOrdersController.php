<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use App\Services\FormOrderAccessExtensionService;
use App\Services\FormOrderPneduProvisionService;
use App\Services\IfirmaApiService;
use App\Services\PubligoApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class FormOrdersController extends Controller
{
    /**
     * Wyświetla listę zamówień z tabeli form_orders (baza pneadm)
     */
    public function index(Request $request)
    {
        // Liczba rekordów na stronę (domyślnie 50)
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search', '');
        $orderIdFilter = trim((string) $request->get('order_id', ''));
        $courseIdFilter = trim((string) $request->get('course_id', ''));
        // Status przetwarzania — dwa źródła:
        //  - 'quick'  => szybki, NIEZALEŻNY filtr z górnych przycisków (działa samodzielnie).
        //                Dozwolone: '' | new | processed | archival (archival = po terminie + bez faktury).
        //  - 'filter' => opcja "Przetwarzanie" w formularzu (łączy się z resztą pól formularza).
        //                Dozwolone: '' | new | processed (BEZ archival — archival to osobny checkbox).
        $quickFilter = $request->get('quick', '');
        $quickFilter = in_array($quickFilter, ['new', 'processed', 'archival'], true) ? $quickFilter : '';
        $filter = $request->get('filter', '');
        $filter = in_array($filter, ['new', 'processed'], true) ? $filter : '';

        // Osobny checkbox formularza: "Tylko archiwalne" = minęła data zakończenia szkolenia.
        // Łączy się (AND) z listą "Przetwarzanie" i pozostałymi filtrami — daje więcej kombinacji.
        $archivalOnly = $request->boolean('archival');

        $orderIdForExact = (ctype_digit($orderIdFilter) && $orderIdFilter !== '' && (int) $orderIdFilter > 0)
            ? (int) $orderIdFilter
            : null;

        $courseIdForPanel = (ctype_digit($courseIdFilter) && $courseIdFilter !== '' && (int) $courseIdFilter > 0)
            ? (int) $courseIdFilter
            : null;

        // Budujemy zapytanie używając modelu Eloquent
        $query = FormOrder::query();

        // Stosujemy filtr przetwarzania niezależnie z przycisków (quick) i z formularza (filter).
        $this->applyProcessingFilter($query, $quickFilter);
        $this->applyProcessingFilter($query, $filter);

        // Checkbox "Tylko archiwalne" z formularza — dokłada warunek po terminie szkolenia.
        if ($archivalOnly) {
            $this->applyArchivalScope($query);
        }

        // Konkretne ID zamówienia (priorytet nad polem „Wyszukaj”) — dokładnie jeden rekord lub brak
        if ($orderIdForExact !== null) {
            $query->whereKey($orderIdForExact);
        } elseif (! empty($search)) {
            // Wyszukiwanie jeśli podano frazę (m.in. uczestnicy z form_order_participants)
            $searchTrimmed = trim($search);
            $searchAsCourseId = (ctype_digit($searchTrimmed) && $searchTrimmed !== '') ? (int) $searchTrimmed : null;

            $query->where(function ($q) use ($search, $searchAsCourseId) {
                $q->whereHas('primaryParticipant', function ($pq) use ($search) {
                    $pq->where('participant_firstname', 'LIKE', "%{$search}%")
                        ->orWhere('participant_lastname', 'LIKE', "%{$search}%")
                        ->orWhere('participant_email', 'LIKE', "%{$search}%");
                })
                    ->orWhere('orderer_email', 'LIKE', "%{$search}%")
                    ->orWhere('product_name', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhere('id', 'LIKE', "%{$search}%")
                    ->orWhere('publigo_product_id', 'LIKE', "%{$search}%")
                    ->when($searchAsCourseId !== null, function ($sub) use ($searchAsCourseId) {
                        return $sub->orWhere('product_id', $searchAsCourseId);
                    })
                    // Wyszukiwanie po danych nabywcy
                    ->orWhere('buyer_name', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_address', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_postal_code', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_city', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_nip', 'LIKE', "%{$search}%")
                    // Wyszukiwanie po danych odbiorcy
                    ->orWhere('recipient_name', 'LIKE', "%{$search}%")
                    ->orWhere('recipient_address', 'LIKE', "%{$search}%")
                    ->orWhere('recipient_postal_code', 'LIKE', "%{$search}%")
                    ->orWhere('recipient_city', 'LIKE', "%{$search}%")
                    ->orWhere('recipient_nip', 'LIKE', "%{$search}%")
                    ->orWhere('fb_source', 'LIKE', "%{$search}%")
                    ->orWhereHas('marketingCampaign', function ($mq) use ($search) {
                        $mq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('campaign_code', 'LIKE', "%{$search}%");
                    });
            });
        }

        // ID szkolenia w panelu (courses.id): form_orders.product_id lub publigo_product_id = courses.id_old
        if ($courseIdForPanel !== null) {
            $ot = (new FormOrder)->getTable();
            $query->where(function ($q) use ($courseIdForPanel, $ot) {
                $q->where($ot.'.product_id', $courseIdForPanel)
                    ->orWhereExists(function ($sub) use ($courseIdForPanel, $ot) {
                        $sub->select(DB::raw(1))
                            ->from('courses')
                            ->where('courses.id', $courseIdForPanel)
                            ->whereNotNull('courses.id_old')
                            ->where('courses.id_old', '!=', '')
                            ->whereColumn('courses.id_old', $ot.'.publigo_product_id');
                    });
            });
        }

        $settlementRaw = (string) $request->get('settlement', '');
        $settlementFilter = in_array($settlementRaw, ['deferred', 'online'], true) ? $settlementRaw : '';
        $formOrdersTable = (new FormOrder)->getTable();
        if ($settlementFilter === 'deferred') {
            $query->where(function ($q) use ($formOrdersTable) {
                $q->where($formOrdersTable.'.payment_mode', FormOrder::PAYMENT_MODE_DEFERRED_INVOICE)
                    ->orWhereNull($formOrdersTable.'.payment_mode');
            });
        } elseif ($settlementFilter === 'online') {
            $query->where($formOrdersTable.'.payment_mode', FormOrder::PAYMENT_MODE_ONLINE_GATEWAY);
        }

        /*
         * Podfiltr statusu płatności online — wyłącznie przy settlement=online.
         * Źródło: online_payment_orders.status (odpowiedź bramki), spójnie z /online-payment-orders.
         * - in_progress: pending + created (jeszcze możliwa dokończenie wpłaty)
         * - paid: opłacone
         * - cancelled_or_failed: anulowane lub błąd (zamknięte bez sukcesu)
         */
        $opoStatusRaw = (string) $request->get('opo_status', '');
        $allowedOpo = ['in_progress', 'paid', 'cancelled_or_failed'];
        $opoStatusFilter = ($settlementFilter === 'online' && in_array($opoStatusRaw, $allowedOpo, true))
            ? $opoStatusRaw
            : '';
        if ($opoStatusFilter === 'in_progress') {
            $query->whereHas('onlinePaymentOrders', function ($q) {
                $q->whereIn('status', [
                    OnlinePaymentOrder::STATUS_PENDING,
                    OnlinePaymentOrder::STATUS_CREATED,
                ]);
            });
        } elseif ($opoStatusFilter === 'paid') {
            $query->whereHas('onlinePaymentOrders', function ($q) {
                $q->where('status', OnlinePaymentOrder::STATUS_PAID);
            });
        } elseif ($opoStatusFilter === 'cancelled_or_failed') {
            $query->whereHas('onlinePaymentOrders', function ($q) {
                $q->whereIn('status', [
                    OnlinePaymentOrder::STATUS_CANCELLED,
                    OnlinePaymentOrder::STATUS_FAILED,
                ]);
            });
        }

        $placementFilterRaw = (string) $request->get('placement', '');
        $allowedPlacementFilters = ['dashboard_sidebar', 'other'];
        $placementFilter = in_array($placementFilterRaw, $allowedPlacementFilters, true) ? $placementFilterRaw : '';
        if ($placementFilter !== '' && Schema::hasColumn($formOrdersTable, 'conversion_placement')) {
            if ($placementFilter === 'dashboard_sidebar') {
                $query->where($formOrdersTable.'.conversion_placement', FormOrder::CONVERSION_PLACEMENT_DASHBOARD_SIDEBAR);
            } elseif ($placementFilter === 'other') {
                $query->where(function ($q) use ($formOrdersTable) {
                    $q->whereNull($formOrdersTable.'.conversion_placement')
                        ->orWhere($formOrdersTable.'.conversion_placement', '')
                        ->orWhere($formOrdersTable.'.conversion_placement', '!=', FormOrder::CONVERSION_PLACEMENT_DASHBOARD_SIDEBAR);
                });
            }
        }

        // Pobieramy dane z paginacją lub wszystkie rekordy (primaryParticipant – dane uczestnika z form_order_participants)
        if ($perPage === 'all') {
            $zamowienia = $query->with(['marketingCampaign.sourceType', 'primaryParticipant', 'onlinePaymentOrders', 'course.instructor'])->orderByDesc('id')->get();
            // Tworzymy własny obiekt paginacji dla wszystkich rekordów
            $zamowienia = new \Illuminate\Pagination\LengthAwarePaginator(
                $zamowienia,
                $zamowienia->count(),
                $zamowienia->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $zamowienia = $query->with(['marketingCampaign.sourceType', 'primaryParticipant', 'onlinePaymentOrders', 'course.instructor'])->orderByDesc('id')->paginate($perPage);
        }

        // Pobierz informacje o duplikatach dla wyświetlanych zamówień
        $duplicateInfo = [];
        $duplicateGroups = FormOrder::duplicates()->get();
        foreach ($duplicateGroups as $group) {
            $orderIds = explode(',', $group->order_ids);
            foreach ($orderIds as $orderId) {
                $duplicateInfo[$orderId] = [
                    'count' => $group->duplicate_count,
                    'is_duplicate' => true,
                    'group_email' => $group->participant_email,
                    'group_product_id' => $group->duplicate_course_key,
                ];
            }
        }

        $totalDuplicateGroupsCount = $duplicateGroups->count();
        $urgentDuplicatesCount = $this->countUrgentDuplicateGroups($duplicateGroups);

        // Policz archiwalne (skrót przycisku) = nieprzetworzone (bez faktury i niezakończone)
        // ORAZ minęła data zakończenia szkolenia — spójnie z applyProcessingFilter('archival').
        $archivalCount = FormOrder::new()
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('courses')
                    ->where('courses.end_date', '<', \Carbon\Carbon::now())
                    ->where(function ($match) {
                        $match->whereColumn('courses.id', 'form_orders.product_id')
                            ->orWhere(function ($legacy) {
                                $legacy->where('courses.source_id_old', '=', 'certgen_Publigo')
                                    ->whereNotNull('courses.id_old')
                                    ->where('courses.id_old', '!=', '')
                                    ->whereColumn('courses.id_old', 'form_orders.publigo_product_id');
                            });
                    });
            })
            ->count();

        // Policz wszystkie nieprzetworzone zamówienia (bez numeru faktury i nie ukończone)
        // To jest dokładnie to samo co pokazuje filtr "new"
        $newCount = FormOrder::new()->count();

        // Policz przetworzone zamówienia = z numerem faktury LUB oznaczone jako zakończone
        $processedCount = FormOrder::processed()->count();

        // Statystyki do wyświetlenia
        // `order_date` jest zapisywane w bazie jako UTC, ale UI pokazuje dzień wg strefy aplikacji
        // (np. 00:39/01:00 w lokalnym czasie powinno liczyć się jako "dziś").
        // Dlatego liczymy granice "wczoraj/dziś" w czasie lokalnym, a dopiero potem porównujemy do UTC.
        $tz = config('app.timezone', 'Europe/Warsaw');

        $todayLocal = Carbon::today($tz);
        $yesterdayLocal = Carbon::yesterday($tz);

        $todayStartUtc = $todayLocal->copy()->startOfDay()->utc();
        $tomorrowStartUtc = $todayLocal->copy()->addDay()->startOfDay()->utc(); // koniec wyłączny
        $yesterdayStartUtc = $yesterdayLocal->copy()->startOfDay()->utc();

        $stats = [
            'total' => FormOrder::count(),
            'new' => $newCount,
            'yesterday' => FormOrder::where('order_date', '>=', $yesterdayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $todayStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'today' => FormOrder::where('order_date', '>=', $todayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $tomorrowStartUtc->format('Y-m-d H:i:s'))
                ->count(),
            'archival' => $archivalCount,
            'sales_value' => FormOrder::withInvoice()->sum('product_price'),
            'avg_price' => FormOrder::withInvoice()->avg('product_price') ?: 0,
        ];

        if (Schema::hasColumn((new FormOrder)->getTable(), 'conversion_placement')) {
            $sidebarPlacement = FormOrder::CONVERSION_PLACEMENT_DASHBOARD_SIDEBAR;
            $stats['dashboard_sidebar_total'] = FormOrder::where('conversion_placement', $sidebarPlacement)->count();
            $stats['dashboard_sidebar_invoiced'] = FormOrder::withInvoice()
                ->where('conversion_placement', $sidebarPlacement)
                ->count();
            $stats['dashboard_sidebar_sales'] = FormOrder::withInvoice()
                ->where('conversion_placement', $sidebarPlacement)
                ->sum('product_price');
            $stats['other_placement_total'] = FormOrder::where(function ($q) use ($sidebarPlacement) {
                $q->whereNull('conversion_placement')
                    ->orWhere('conversion_placement', '')
                    ->orWhere('conversion_placement', '!=', $sidebarPlacement);
            })->count();
            $stats['other_placement_invoiced'] = FormOrder::withInvoice()
                ->where(function ($q) use ($sidebarPlacement) {
                    $q->whereNull('conversion_placement')
                        ->orWhere('conversion_placement', '')
                        ->orWhere('conversion_placement', '!=', $sidebarPlacement);
                })
                ->count();
        }

        return view('form-orders.index', compact('zamowienia', 'perPage', 'search', 'orderIdFilter', 'courseIdFilter', 'quickFilter', 'filter', 'archivalOnly', 'settlementFilter', 'opoStatusFilter', 'placementFilter', 'duplicateInfo', 'urgentDuplicatesCount', 'totalDuplicateGroupsCount', 'stats', 'newCount', 'processedCount', 'archivalCount'));
    }

    /**
     * Dokłada do zapytania warunek statusu przetwarzania zamówienia.
     * Wartości: '' (brak filtra) | 'new' (bez faktury i niezakończone)
     * | 'processed' (z fakturą LUB oznaczone jako zakończone)
     * | 'archival' (nieprzetworzone ORAZ po terminie — odpowiednik formularza:
     *   "Nieprzetworzone" + zaznaczony checkbox "Archiwalne").
     */
    private function applyProcessingFilter($query, string $value): void
    {
        if ($value === 'new') {
            $query->new();
        } elseif ($value === 'processed') {
            // Przetworzone = ma numer faktury LUB oznaczone jako zakończone.
            $query->processed();
        } elseif ($value === 'archival') {
            // Skrót przycisku = dokładnie to samo co formularz: Nieprzetworzone + Archiwalne.
            $query->new();
            $this->applyArchivalScope($query);
        }
    }

    /**
     * Dokłada do zapytania wyłącznie warunek "archiwalne" = minęła data i godzina
     * zakończenia szkolenia (courses.end_date < teraz). Używane przez checkbox
     * formularza oraz jako część skrótu przycisku "Archiwalne". Nie nakłada warunku
     * faktury — dzięki temu łączy się dowolnie z filtrem przetwarzania.
     */
    private function applyArchivalScope($query): void
    {
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('courses')
                ->where('courses.end_date', '<', Carbon::now())
                ->where(function ($match) {
                    $match->whereColumn('courses.id', 'form_orders.product_id')
                        ->orWhere(function ($legacy) {
                            $legacy->where('courses.source_id_old', '=', 'certgen_Publigo')
                                ->whereNotNull('courses.id_old')
                                ->where('courses.id_old', '!=', '')
                                ->whereColumn('courses.id_old', 'form_orders.publigo_product_id');
                        });
                });
        });
    }

    /**
     * Endpoint AJAX do wyszukiwania kursów dla selecta szkolenia.
     * Zwraca listę kursów filtrowanych do źródła Publigo (spójność z create/store).
     */
    public function searchCourses(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 30);
        $limit = max(1, min($limit, 100));
        $includeArchived = filter_var($request->input('include_archived', false), FILTER_VALIDATE_BOOLEAN);

        // Filtr archiwalnych kursów ignorujemy gdy admin coś wpisał — wyszukiwanie zawsze
        // przeszukuje pełen zbiór (inaczej szukanie po tytule/ID kursu z przeszłości
        // dawałoby pustą listę i frustrację).
        $applyArchivedFilter = ! $includeArchived && $q === '';

        $query = \App\Models\Course::query()
            ->with([
                'instructor:id,title,first_name,last_name',
                'priceVariants' => function ($q) {
                    $q->orderByDesc('is_active')->orderBy('id');
                },
            ])
            // Form-orders dotyczą płatnych szkoleń (zarówno nowych pneadm,
            // jak i historycznych z Publigo). Nie filtrujemy po source_id_old,
            // żeby były widoczne także nadchodzące kursy bez powiązania z Publigo.
            ->where('is_paid', 1)
            ->select('id', 'id_old', 'title', 'start_date', 'end_date', 'instructor_id');

        if ($q !== '') {
            $query->whereMatchesAdminSelectSearch($q);
        }

        $now = now();

        if ($applyArchivedFilter) {
            // Tylko upcoming (start w przyszłości) + ongoing (start w przeszłości,
            // end jeszcze nie minął). Brak start_date traktujemy jako bez kategorii.
            $query->where(function ($w) use ($now) {
                $w->where('start_date', '>=', $now)
                    ->orWhere(function ($w2) use ($now) {
                        $w2->whereNotNull('end_date')
                            ->where('start_date', '<=', $now)
                            ->where('end_date', '>=', $now);
                    });
            });
        }

        // Sortowanie: nadchodzące kursy najpierw (start_date >= dziś), potem najnowsze archiwalne,
        // na końcu te bez daty.
        $today = $now->format('Y-m-d');
        $courses = $query
            ->orderByRaw('start_date IS NULL')
            ->orderByRaw('CASE WHEN start_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByRaw('CASE WHEN start_date >= ? THEN start_date END ASC', [$today])
            ->orderByRaw('CASE WHEN start_date <  ? THEN start_date END DESC', [$today])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $courses->map(function ($c) {
                $variants = $c->priceVariants ?? collect();
                $defaultVariant = $variants->firstWhere('is_active', true);
                if (! $defaultVariant && $variants->count() === 1) {
                    $defaultVariant = $variants->first();
                }

                $tz = config('app.timezone');

                return [
                    'value' => (string) $c->id,
                    'id' => (int) $c->id,
                    'id_hash' => '#'.$c->id,
                    'id_old' => (string) ($c->id_old ?? ''),
                    'title_text' => trim(strip_tags((string) $c->title)),
                    'title_html' => (string) $c->title,
                    'start_date' => $c->start_date ? $c->start_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
                    'end_date' => $c->end_date ? $c->end_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
                    'status' => $c->getLifecycleStatus(),
                    'instructor' => $c->instructor
                        ? trim(($c->instructor->title ? $c->instructor->title.' ' : '').$c->instructor->first_name.' '.$c->instructor->last_name)
                        : '',
                    'default_price' => $defaultVariant
                        ? number_format((float) $defaultVariant->getCurrentPrice(), 2, '.', '')
                        : null,
                    'default_variant_name' => $defaultVariant?->name,
                    'default_variant_active' => $defaultVariant ? (bool) $defaultVariant->is_active : null,
                ];
            })->values(),
        ]);
    }

    /**
     * Wyświetla formularz tworzenia nowego zamówienia.
     */
    public function create(Request $request)
    {
        // Pobierz kursy z Publigo (source_id_old = 'certgen_Publigo')
        $courses = \App\Models\Course::where('source_id_old', 'certgen_Publigo')
            ->orderBy('start_date', 'desc')
            ->get();

        $prefill = [];
        $cloneSourceId = null;
        $cloneWarning = null;

        $cloneFrom = (int) $request->integer('clone_from');
        if ($cloneFrom > 0) {
            $sourceOrder = FormOrder::with('primaryParticipant')->find($cloneFrom);

            if (! $sourceOrder) {
                $cloneWarning = 'Nie znaleziono zamówienia do skopiowania.';
            } else {
                $cloneSourceId = $sourceOrder->id;
                $prefill = [
                    'course_id' => $sourceOrder->product_id,
                    'product_price' => $sourceOrder->product_price,
                    'participant_firstname' => $sourceOrder->primaryParticipant?->participant_firstname,
                    'participant_lastname' => $sourceOrder->primaryParticipant?->participant_lastname,
                    'participant_email' => $sourceOrder->primaryParticipant?->participant_email,
                    'orderer_name' => $sourceOrder->orderer_name,
                    'orderer_phone' => $sourceOrder->orderer_phone,
                    'orderer_email' => $sourceOrder->orderer_email,
                    'buyer_name' => $sourceOrder->buyer_name,
                    'buyer_address' => $sourceOrder->buyer_address,
                    'buyer_postal_code' => $sourceOrder->buyer_postal_code,
                    'buyer_city' => $sourceOrder->buyer_city,
                    'buyer_nip' => $sourceOrder->buyer_nip,
                    'recipient_name' => $sourceOrder->recipient_name,
                    'recipient_address' => $sourceOrder->recipient_address,
                    'recipient_postal_code' => $sourceOrder->recipient_postal_code,
                    'recipient_city' => $sourceOrder->recipient_city,
                    'recipient_nip' => $sourceOrder->recipient_nip,
                    'invoice_notes' => $sourceOrder->invoice_notes,
                    'invoice_payment_delay' => $sourceOrder->invoice_payment_delay,
                    'notes' => $sourceOrder->notes,
                ];
            }
        }

        $courseIdFromQuery = (int) $request->integer('course_id');
        if ($courseIdFromQuery > 0 && empty($prefill['course_id'])) {
            $prefill['course_id'] = $courseIdFromQuery;
        }

        $selectedCourse = null;
        if (! empty($prefill['course_id'])) {
            $selectedCourse = \App\Models\Course::with(['priceVariants' => function ($q) {
                $q->where('is_active', true)->orderBy('id');
            }])->find((int) $prefill['course_id']);
            if ($selectedCourse && empty($prefill['product_price'])) {
                $variant = $selectedCourse->priceVariants->first();
                if ($variant && method_exists($variant, 'getCurrentPrice')) {
                    $prefill['product_price'] = $variant->getCurrentPrice();
                }
            }
        }

        return view('form-orders.create', compact('courses', 'prefill', 'cloneSourceId', 'cloneWarning', 'selectedCourse'));
    }

    /**
     * Zapisuje nowe zamówienie w bazie danych.
     */
    public function store(Request $request)
    {
        try {
            // Walidacja danych
            $request->validate([
                // Dane produktu/szkolenia
                'course_id' => 'required|exists:courses,id',
                'product_name' => 'nullable|string|max:255',
                'product_price' => 'nullable|numeric|min:0',
                'product_description' => 'nullable|string',

                // Dane uczestnika
                'participant_firstname' => 'required|string|max:100',
                'participant_lastname' => 'required|string|max:100',
                'participant_email' => 'required|email|max:255',

                // Dane zamawiającego
                'orderer_name' => 'required|string|max:255',
                'orderer_phone' => 'required|string|max:20',
                'orderer_email' => 'required|email|max:255',

                // Dane nabywcy
                'buyer_name' => 'required|string|max:255',
                'buyer_address' => 'required|string|max:255',
                'buyer_postal_code' => 'required|string|max:10',
                'buyer_city' => 'required|string|max:100',
                'buyer_nip' => 'nullable|string|max:20',

                // Dane odbiorcy
                'recipient_name' => 'nullable|string|max:255',
                'recipient_address' => 'nullable|string|max:255',
                'recipient_postal_code' => 'nullable|string|max:10',
                'recipient_city' => 'nullable|string|max:100',
                'recipient_nip' => 'nullable|string|max:20',

                // Metadane KSeF Podmiot3 (ETAP 1) — patrz docs/KSEF_FORM_ORDERS.md
                'ksef_entity_source' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ENTITY_SOURCES),
                'ksef_additional_entity_role' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ROLES),
                'ksef_additional_entity_id_type' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ID_TYPES),
                'ksef_additional_entity_identifier' => 'nullable|string|max:50',
                'ksef_admin_note' => 'nullable|string',

                // Dane Publigo (opcjonalne)
                'publigo_product_id' => 'nullable|integer',
                'publigo_price_id' => 'nullable|integer',

                // Uwagi do faktury
                'invoice_notes' => 'nullable|string',
                'invoice_payment_delay' => 'nullable|integer|min:0|max:31',

                // Notatki
                'notes' => 'nullable|string',
            ]);

            // Pobierz dane kursu
            $course = \App\Models\Course::findOrFail($request->course_id);

            // Ustaw dane Publigo na podstawie kursu
            $publigoProductId = $course->id_old; // ID produktu z Publigo
            $publigoPriceId = 1; // Domyślny price_id

            // Tworzenie nowego zamówienia
            $formOrder = FormOrder::create([
                'ident' => FormOrder::generateIdent(),
                'order_date' => now('UTC'),
                'product_id' => $course->id, // ID kursu z bazy
                'product_name' => $course->title,
                'product_price' => $request->product_price ?? 0, // Można ustawić ręcznie
                'product_description' => $course->description,
                'orderer_name' => $request->orderer_name,
                'orderer_phone' => $request->orderer_phone,
                'orderer_email' => $request->orderer_email,
                'buyer_name' => $request->buyer_name,
                'buyer_address' => $request->buyer_address,
                'buyer_postal_code' => $request->buyer_postal_code,
                'buyer_city' => $request->buyer_city,
                'buyer_nip' => $request->buyer_nip,
                'recipient_name' => $request->recipient_name,
                'recipient_address' => $request->recipient_address,
                'recipient_postal_code' => $request->recipient_postal_code,
                'recipient_city' => $request->recipient_city,
                'recipient_nip' => $request->recipient_nip,
                'ksef_entity_source' => $request->input('ksef_entity_source', FormOrder::KSEF_ENTITY_SOURCE_NONE),
                'ksef_additional_entity_role' => $request->input('ksef_additional_entity_role') ?: null,
                'ksef_additional_entity_id_type' => $request->input('ksef_additional_entity_id_type') ?: null,
                'ksef_additional_entity_identifier' => $request->input('ksef_additional_entity_identifier') ?: null,
                'ksef_admin_note' => $request->input('ksef_admin_note') ?: null,
                'publigo_product_id' => $publigoProductId,
                'publigo_price_id' => $publigoPriceId,
                'invoice_notes' => $request->invoice_notes,
                'invoice_payment_delay' => $request->invoice_payment_delay,
                'notes' => $request->notes,
                'submission_source' => FormOrder::SUBMISSION_SOURCE_PNEADM_MANUAL,
                'ip_address' => $request->ip(),
            ]);

            // Tworzenie uczestnika w tabeli form_order_participants
            \App\Models\FormOrderParticipant::create([
                'form_order_id' => $formOrder->id,
                'participant_firstname' => $request->participant_firstname,
                'participant_lastname' => $request->participant_lastname,
                'participant_email' => $request->participant_email,
                'is_primary' => true,
            ]);

            return redirect()->route('form-orders.show', $formOrder->id)
                ->with('success', 'Zamówienie zostało pomyślnie utworzone.');

        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas tworzenia zamówienia: '.$e->getMessage());
        }
    }

    /**
     * Wyświetla szczegóły zamówienia.
     */
    public function show(Request $request, $id)
    {
        $zamowienie = FormOrder::with(['marketingCampaign.sourceType', 'primaryParticipant', 'onlinePaymentOrders', 'course.instructor', 'coursePriceVariant'])->find($id);

        if (! $zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        // Sprawdzamy czy mamy filtrować tylko niewprowadzone zamówienia
        $filterNew = $request->has('filter_new') && $request->input('filter_new') == '1';

        // Sprawdzamy czy mamy filtrować po ID szkolenia
        $courseId = $request->input('course_id');

        // Pobieramy poprzednie i następne zamówienie
        if ($filterNew || $courseId) {
            // Filtrujemy zamówienia
            $prevQuery = FormOrder::where('id', '<', $id);
            $nextQuery = FormOrder::where('id', '>', $id);

            // Dodajemy filtr dla niewprowadzonych zamówień
            if ($filterNew) {
                $prevQuery->where(function ($q) {
                    $q->whereNull('invoice_number')
                        ->orWhere('invoice_number', '')
                        ->orWhere('invoice_number', '0');
                })->where(function ($q) {
                    $q->where('status_completed', '!=', 1)
                        ->orWhereNull('status_completed');
                });

                $nextQuery->where(function ($q) {
                    $q->whereNull('invoice_number')
                        ->orWhere('invoice_number', '')
                        ->orWhere('invoice_number', '0');
                })->where(function ($q) {
                    $q->where('status_completed', '!=', 1)
                        ->orWhereNull('status_completed');
                });
            }

            // Filtr po courses.id (form_orders.product_id), nie po Publigo (id_old)
            if ($courseId !== null && $courseId !== '') {
                $prevQuery->where('product_id', (int) $courseId);
                $nextQuery->where('product_id', (int) $courseId);
            }

            $prevOrder = $prevQuery->orderByDesc('id')->first();
            $nextOrder = $nextQuery->orderBy('id')->first();
        } else {
            // Standardowe pobieranie wszystkich zamówień
            $prevOrder = FormOrder::where('id', '<', $id)
                ->orderByDesc('id')
                ->first();

            $nextOrder = FormOrder::where('id', '>', $id)
                ->orderBy('id')
                ->first();
        }

        $duplicateSiblingsCount = FormOrder::findDuplicatesFor($id)->count();

        $zamowienie->ensureIdent();
        $pneduOrderFormEditUrl = null;
        if ($zamowienie->product_id) {
            $courseForLink = $zamowienie->course ?? \App\Models\Course::find($zamowienie->product_id);
            if ($courseForLink) {
                $pneduOrderFormEditUrl = \App\Services\CourseFormOrderBillingService::editOrderFormUrl(
                    $courseForLink,
                    (string) $zamowienie->ident
                );
            }
        }

        return view('form-orders.show', compact('zamowienie', 'prevOrder', 'nextOrder', 'filterNew', 'duplicateSiblingsCount', 'pneduOrderFormEditUrl'));
    }

    /**
     * Aktualizuje zamówienie.
     */
    public function update(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return redirect()->route('form-orders.index')->with('error', 'Zamówienie nie zostało znalezione.');
            }

            // Analityka (ADR-005): ręczna edycja numeru faktury → invoice_path_type=manual.
            \App\Services\Analytics\InvoiceAnalyticsTracker::hintSource(
                \App\Services\Analytics\InvoiceAnalyticsTracker::PATH_MANUAL
            );

            // Sprawdzamy, skąd użytkownik przyszedł
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            $isFromEditPage = $request->has('from_edit_page') && $request->input('from_edit_page') == '1';

            // Jeśli przychodzi z pełnej strony edycji, aktualizuj wszystkie pola
            if ($isFromEditPage) {
                // Walidacja metadanych KSeF Podmiot3 — ETAP 1.
                // Uwaga: nie czyścimy automatycznie role / id_type / identifier
                // kiedy ksef_entity_source = 'none' — admin może świadomie trzymać
                // wartości (np. jst_recipient) do czasu ETAPU 2. Mapowanie i tak je ignoruje.
                $request->validate([
                    'course_id' => 'nullable|integer|exists:courses,id',
                    'ksef_entity_source' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ENTITY_SOURCES),
                    'ksef_additional_entity_role' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ROLES),
                    'ksef_additional_entity_id_type' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ID_TYPES),
                    'ksef_additional_entity_identifier' => 'nullable|string|max:50',
                    'ksef_admin_note' => 'nullable|string',
                ]);

                // Jeżeli zmieniono szkolenie, przepnij powiązane pola produktu (id/nazwa/publigo).
                $newCourseId = (int) $request->input('course_id');
                if ($newCourseId > 0 && $newCourseId !== (int) $zamowienie->product_id) {
                    $newCourse = \App\Models\Course::find((int) $newCourseId);
                    if ($newCourse) {
                        $zamowienie->product_id = (int) $newCourse->id;
                        $zamowienie->product_name = (string) $newCourse->title;
                        $zamowienie->product_description = $newCourse->description;
                        $zamowienie->publigo_product_id = $newCourse->id_old ? (int) $newCourse->id_old : null;
                        if (empty($zamowienie->publigo_price_id)) {
                            $zamowienie->publigo_price_id = 1;
                        }
                    }
                }

                $zamowienie->fill([
                    // product_name nadpisujemy tylko jeśli nie nastąpiła zmiana kursu — w przeciwnym razie wartość ustawiona powyżej.
                    'product_name' => $zamowienie->product_name,
                    'product_price' => $request->input('product_price'),
                    'orderer_name' => $request->input('orderer_name'),
                    'orderer_phone' => $request->input('orderer_phone'),
                    'orderer_email' => $request->input('orderer_email'),
                    'buyer_name' => $request->input('buyer_name'),
                    'buyer_nip' => $request->input('buyer_nip'),
                    'buyer_address' => $request->input('buyer_address'),
                    'buyer_postal_code' => $request->input('buyer_postal_code'),
                    'buyer_city' => $request->input('buyer_city'),
                    'recipient_name' => $request->input('recipient_name'),
                    'recipient_address' => $request->input('recipient_address'),
                    'recipient_postal_code' => $request->input('recipient_postal_code'),
                    'recipient_city' => $request->input('recipient_city'),
                    'recipient_nip' => $request->input('recipient_nip'),
                    'ksef_entity_source' => $request->input('ksef_entity_source', FormOrder::KSEF_ENTITY_SOURCE_NONE),
                    'ksef_additional_entity_role' => $request->input('ksef_additional_entity_role') ?: null,
                    'ksef_additional_entity_id_type' => $request->input('ksef_additional_entity_id_type') ?: null,
                    'ksef_additional_entity_identifier' => $request->input('ksef_additional_entity_identifier') ?: null,
                    'ksef_admin_note' => $request->input('ksef_admin_note') ?: null,
                    'invoice_number' => $request->input('invoice_number'),
                    'invoice_payment_delay' => $request->input('invoice_payment_delay'),
                    'invoice_notes' => $request->input('invoice_notes'),
                    'notes' => $request->input('notes'),
                    'status_completed' => $request->has('status_completed') ? 1 : 0,
                ]);
            } else {
                // Aktualizacja podstawowych danych (z listy lub show)
                $zamowienie->invoice_number = $request->input('invoice_number');
                $zamowienie->notes = $request->input('notes');
                $zamowienie->status_completed = $request->has('status_completed') ? 1 : 0;
            }

            $zamowienie->updated_manually_at = now();
            $zamowienie->save();

            // Aktualizuj lub utwórz głównego uczestnika w form_order_participants (bez zapisu do form_orders)
            if ($isFromEditPage) {
                $participant = \App\Models\FormOrderParticipant::where('form_order_id', $id)
                    ->where('is_primary', true)
                    ->first();

                $participantData = [
                    'participant_firstname' => $request->input('participant_firstname'),
                    'participant_lastname' => $request->input('participant_lastname'),
                    'participant_email' => $request->input('participant_email'),
                ];

                if ($participant) {
                    $participant->update($participantData);
                } else {
                    \App\Models\FormOrderParticipant::create(array_merge($participantData, [
                        'form_order_id' => $id,
                        'is_primary' => true,
                    ]));
                }
            }

            // Jeśli nie ma ukrytego pola, sprawdzamy referer (fallback)
            if (! $isFromShowPage && ! $isFromEditPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/'.$id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }

            // Przekierowanie w zależności od źródła
            if ($isFromEditPage) {
                // Zachowujemy parametry filtrów przy przekierowaniu z formularza edycji
                $redirectParams = [];
                if ($request->has('filter_new')) {
                    $redirectParams['filter_new'] = $request->input('filter_new');
                }
                if ($request->has('course_id')) {
                    $redirectParams['course_id'] = $request->input('course_id');
                }

                return redirect()->route('form-orders.show', array_merge(['id' => $id], $redirectParams))->with('success', 'Zamówienie zostało zaktualizowane.');
            } elseif ($isFromShowPage) {
                // Zachowujemy parametry filtrów przy przekierowaniu
                $redirectParams = [];
                if ($request->has('filter_new')) {
                    $redirectParams['filter_new'] = $request->input('filter_new');
                }
                if ($request->has('course_id')) {
                    $redirectParams['course_id'] = $request->input('course_id');
                }

                return redirect()->route('form-orders.show', array_merge(['id' => $id], $redirectParams))->with('success', 'Zamówienie zostało zaktualizowane.');
            } else {
                // Wracamy do listy z zachowaniem parametrów
                $redirectParams = [];
                if ($request->has('per_page')) {
                    $redirectParams['per_page'] = $request->input('per_page');
                }
                if ($request->has('search')) {
                    $redirectParams['search'] = $request->input('search');
                }
                if ($request->has('filter')) {
                    $redirectParams['filter'] = $request->input('filter');
                }
                if ($request->has('page')) {
                    $redirectParams['page'] = $request->input('page');
                }
                if ($request->filled('order_id')) {
                    $redirectParams['order_id'] = $request->input('order_id');
                }
                if ($request->filled('course_id')) {
                    $redirectParams['course_id'] = $request->input('course_id');
                }

                return redirect()->route('form-orders.index', $redirectParams)->with('success', 'Zamówienie zostało zaktualizowane.');
            }
        } catch (Exception $e) {
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            $isFromEditPage = $request->has('from_edit_page') && $request->input('from_edit_page') == '1';

            if (! $isFromShowPage && ! $isFromEditPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/'.$id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }

            if ($isFromEditPage) {
                // Zachowujemy parametry filtrów przy przekierowaniu z błędem
                $redirectParams = [];
                if ($request->has('filter_new')) {
                    $redirectParams['filter_new'] = $request->input('filter_new');
                }
                if ($request->has('course_id')) {
                    $redirectParams['course_id'] = $request->input('course_id');
                }

                return redirect()->route('form-orders.edit', array_merge(['id' => $id], $redirectParams))->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia: '.$e->getMessage());
            } elseif ($isFromShowPage) {
                return redirect()->route('form-orders.show', $id)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            } else {
                $redirectParams = [];
                if ($request->has('per_page')) {
                    $redirectParams['per_page'] = $request->input('per_page');
                }
                if ($request->has('search')) {
                    $redirectParams['search'] = $request->input('search');
                }
                if ($request->has('filter')) {
                    $redirectParams['filter'] = $request->input('filter');
                }
                if ($request->has('page')) {
                    $redirectParams['page'] = $request->input('page');
                }
                if ($request->filled('order_id')) {
                    $redirectParams['order_id'] = $request->input('order_id');
                }
                if ($request->filled('course_id')) {
                    $redirectParams['course_id'] = $request->input('course_id');
                }

                return redirect()->route('form-orders.index', $redirectParams)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            }
        }
    }

    /**
     * Tworzy zamówienie w Publigo API na podstawie zamówienia z bazy
     */
    public function createPubligoOrder(Request $request, $id)
    {
        try {
            // Blokada wiersza: zapobiega równoległemu podwójnemu wysłaniu (wyścig → mylący błąd + duplikat w Publigo)
            return DB::connection('mysql')->transaction(function () use ($id) {
                $zamowienie = FormOrder::with('primaryParticipant')->lockForUpdate()->find($id);

                if (! $zamowienie) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Zamówienie nie zostało znalezione.',
                    ], 404);
                }

                // Sprawdzenie czy zamówienie ma dane Publigo
                if (empty($zamowienie->publigo_product_id) || empty($zamowienie->publigo_price_id)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Brak danych produktu Publigo. Zamówienie nie może być przesłane do Publigo.',
                    ], 400);
                }

                // Sprawdzenie czy zamówienie już zostało wysłane do Publigo (po lock — aktualny stan)
                if ($zamowienie->publigo_sent == 1) {
                    return response()->json([
                        'success' => false,
                        'error' => 'To zamówienie zostało już wysłane do Publigo.',
                        'sent_at' => $zamowienie->publigo_sent_at ? $zamowienie->publigo_sent_at->format('d.m.Y H:i') : 'Nieznana data',
                    ], 400);
                }

                // Sprawdzenie czy ma wszystkie wymagane dane (uczestnik z form_order_participants)
                $missingFields = [];
                if (empty(trim($zamowienie->display_participant_email ?? ''))) {
                    $missingFields[] = 'participant_email';
                }
                if (empty(trim($zamowienie->display_participant_name ?? ''))) {
                    $missingFields[] = 'participant_name';
                }
                foreach (['buyer_address', 'buyer_postal_code', 'buyer_city'] as $field) {
                    if (empty($zamowienie->$field)) {
                        $missingFields[] = $field;
                    }
                }

                if (! empty($missingFields)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Brak wymaganych danych: '.implode(', ', $missingFields),
                    ], 400);
                }

                // Przygotowanie obiektu zgodnego z oczekiwaniami PubligoApiService
                // Musimy zmapować pola z nowego formatu na stary
                // Używamy danych NABYWCY zamiast ODBIORCY dla adresu wysyłkowego
                $orderDataForService = (object) [
                    'id' => $zamowienie->id, // Dodanie brakującego pola ID
                    'konto_email' => $zamowienie->display_participant_email,
                    'konto_imie_nazwisko' => $zamowienie->display_participant_name,
                    'odb_nazwa' => $zamowienie->buyer_name, // Używamy nazwy nabywcy
                    'odb_adres' => $zamowienie->buyer_address, // Używamy adresu nabywcy
                    'odb_kod' => $zamowienie->buyer_postal_code, // Używamy kodu pocztowego nabywcy
                    'odb_poczta' => $zamowienie->buyer_city, // Używamy miasta nabywcy
                    'idProdPubligo' => $zamowienie->publigo_product_id,
                    'price_idProdPubligo' => $zamowienie->publigo_price_id,
                ];

                // Przygotowanie i wysłanie zamówienia do Publigo
                $publigoService = new PubligoApiService;
                $orderData = $publigoService->prepareOrderData($orderDataForService);
                $result = $publigoService->createOrder($orderData);

                // Zwrócenie odpowiedzi
                if ($result['success']) {
                    // Aktualizacja statusu zamówienia po udanym wysłaniu
                    $zamowienie->publigo_sent = 1;
                    $zamowienie->publigo_sent_at = now();
                    if (! $zamowienie->save()) {
                        Log::error('FormOrdersController: publigo_sent — save() zwróciło false', ['form_order_id' => $zamowienie->id]);

                        return response()->json([
                            'success' => false,
                            'error' => 'Publigo przyjęło zamówienie, ale nie udało się zapisać statusu w bazie. Skontaktuj się z administratorem.',
                            'order_data' => $orderData,
                            'publigo_response' => $result['response'] ?? null,
                        ], 500);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => $result['message'],
                        'order_data' => $orderData,
                        'publigo_response' => $result['response'],
                        'sent_at' => now()->format('d.m.Y H:i'),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'order_data' => $orderData,
                    'publigo_response' => $result['response'],
                    'http_code' => $result['http_code'],
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dodaje uczestnika w pneadm, konto (lub powiązanie) w pnedu.users oraz wysyła e-mail do uczestnika.
     */
    public function provisionPneduAccess(Request $request, int $id)
    {
        $result = app(FormOrderPneduProvisionService::class)->provision(
            $id,
            $request->boolean('add_participant_to_sendy')
        );

        $http = (int) ($result['http_code'] ?? 500);
        unset($result['http_code']);

        return response()->json($result, $http);
    }

    public function extendPneduAccess(Request $request, int $id)
    {
        $result = app(FormOrderAccessExtensionService::class)->extendByOrder($id);

        $http = (int) ($result['http_code'] ?? 500);
        unset($result['http_code']);

        return response()->json($result, $http);
    }

    /**
     * Resetuje status Publigo dla zamówienia (tylko dla administratorów)
     */
    public function resetPubligoStatus(Request $request, $id)
    {
        // Sprawdzenie uprawnień - tylko admin i super_admin
        if (! auth()->user()->hasRole('admin') && ! auth()->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Brak uprawnień do resetowania statusu Publigo.',
            ], 403);
        }

        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // Resetowanie statusu Publigo
            $zamowienie->publigo_sent = 0;
            $zamowienie->publigo_sent_at = null;
            $updated = $zamowienie->save();

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status Publigo został zresetowany. Zamówienie może być ponownie wysłane.',
                    'reset_at' => now()->format('d.m.Y H:i'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie udało się zresetować statusu Publigo.',
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas resetowania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resetuje status PNEDU dla zamówienia (tylko dla administratorów)
     */
    public function resetPneduStatus(Request $request, $id)
    {
        if (! auth()->user()->hasRole('admin') && ! auth()->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Brak uprawnień do resetowania statusu PNEDU.',
            ], 403);
        }

        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            $zamowienie->pnedu_provisioned_at = null;
            $zamowienie->pnedu_user_existed_before = null;
            if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_status')) {
                $zamowienie->pnedu_clickmeeting_status = null;
            }
            if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_synced_at')) {
                $zamowienie->pnedu_clickmeeting_synced_at = null;
            }
            if (Schema::connection('mysql')->hasColumn('form_orders', 'pnedu_clickmeeting_message')) {
                $zamowienie->pnedu_clickmeeting_message = null;
            }
            $updated = $zamowienie->save();

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status PNEDU został zresetowany. Zamówienie może być ponownie dodane do PNEDU.',
                    'reset_at' => now()->format('d.m.Y H:i'),
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Nie udało się zresetować statusu PNEDU.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas resetowania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wystawia fakturę pro forma w iFirma.pl na podstawie zamówienia
     */
    public function createIfirmaProForma(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.',
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.',
                ], 400);
            }

            // Przygotowanie uwag do faktury
            // Sprawdź, czy użytkownik przesłał niestandardowe uwagi (edytowane w formularzu)
            // JavaScript zawsze wyśle to pole (nawet jako pusty string), więc sprawdzamy czy jest w request
            if ($request->has('custom_remarks')) {
                // Pole tekstowe było edytowane - użyj dokładnie tego co jest w polu
                // Jeśli użytkownik wyczyścił pole (pusty string), użyj pustego stringa (nie generuj danych odbiorcy)
                $uwagi = trim($request->input('custom_remarks', ''));
            } else {
                // Pole nie było przesłane w request - generuj automatycznie dane odbiorcy
                $recipientData = [];
                if (! empty($zamowienie->recipient_name)) {
                    $recipientData[] = $zamowienie->recipient_name;
                }
                if (! empty($zamowienie->recipient_address)) {
                    $recipientData[] = $zamowienie->recipient_address;
                }
                if (! empty($zamowienie->recipient_postal_code) && ! empty($zamowienie->recipient_city)) {
                    $recipientData[] = $zamowienie->recipient_postal_code.' '.$zamowienie->recipient_city;
                }
                if (! empty($zamowienie->recipient_nip)) {
                    $recipientData[] = 'NIP: '.preg_replace('/[^0-9]/', '', $zamowienie->recipient_nip);
                }

                $uwagi = "ODBIORCA:\n";
                if (! empty($recipientData)) {
                    $uwagi .= implode("\n", $recipientData);
                }
            }

            // ZAWSZE na końcu dodaj identyfikator zamówienia
            // Dzięki temu każda faktura pro-forma będzie miała powiązanie z zamówieniem
            if (! empty(trim($uwagi))) {
                $uwagi .= "\npnedu.pl #{$zamowienie->id}";
            } else {
                $uwagi = "pnedu.pl #{$zamowienie->id}";
            }

            // Przygotowanie danych kontrahenta (PRO-FORMA) — wspólny builder (ETAP 3).
            // Pro forma NIE dokleja OdbiorcaNaFakturze (publiczna dokumentacja iFirma
            // nie potwierdza obsługi tego pola dla fakturaproformakraj; pro forma nie
            // podlega też KSeF). Patrz docs/KSEF_FORM_ORDERS.md — sekcja „ETAP 3”.
            $kontrahent = (new \App\Services\IfirmaKontrahentBuilder)->buildForProForma($zamowienie);

            // Przygotowanie pozycji faktury
            // Zgodnie z dokumentacją API iFirma - pozycja powinna zawierać:
            // - NazwaPelna (wymagane) - nazwa produktu/usługi
            // - Ilosc (wymagane) - ilość jako float
            // - CenaJednostkowa (wymagane) - cena jednostkowa netto
            // - Jednostka (opcjonalne) - jednostka miary
            // - StawkaVat (wymagane jeśli podatnik VAT) - stawka VAT jako float (0.23 dla 23%)
            // - TypStawkiVat (wymagane) - typ stawki: 'PRC' dla procent, 'ZW' dla zwolnionego
            // Przygotowanie pozycji faktury pro forma
            // Zgodnie z dokumentacją API iFirma - próba z różnymi wariantami nazw pól
            $pozycja = [
                'NazwaPelna' => $this->ifirmaNazwaPelnaFromRequest($request, (string) $zamowienie->product_name),
                'Ilosc' => 1.0,
                'CenaJednostkowa' => round((float) $zamowienie->product_price, 2),
                'Jednostka' => 'sztuk',
                'StawkaVat' => 0.23,
                'TypStawkiVat' => 'PRC',
            ];

            // Jeśli konto jest zwolnione z VAT (nievatowiec) – dostosuj stawkę
            if (config('services.ifirma.vat_exempt')) {
                // Zgodnie z dokumentacją dla zwolnienia: TypStawkiVat = 'ZW', usuń/wyzeruj StawkaVat i dodaj podstawę prawną
                unset($pozycja['StawkaVat']);
                $pozycja['TypStawkiVat'] = 'ZW';
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
            }

            // Przygotowanie danych faktury pro forma dla iFirma
            // Zgodnie z dokumentacją API: https://api.ifirma.pl/wystawianie-faktury-proforma/
            // Wymagane pola dla PRO FORMA (NIE są takie same jak dla zwykłej faktury!):
            // - LiczOd (NET/BRT) - TAK
            // - TypFakturyKrajowej (SPRZ/BUD/ZAL) - TAK
            // - DataWystawienia - TAK
            // - SposobZaplaty - TAK (wartości: GTK, POB, PRZ, KAR, PZA, CZK, KOM, BAR, DOT, PAL, ALG, P24, TPA, ELE)
            // - RodzajPodpisuOdbiorcy (OUP/UPO/BPO/BWO) - TAK
            // UWAGA: DataSprzedazy NIE jest używana w fakturach pro forma (tylko w zwykłych fakturach)!

            $invoiceData = [
                'LiczOd' => 'NET',
                'TypFakturyKrajowej' => 'SPRZ', // SPRZ = krajowa sprzedaż
                'DataWystawienia' => now()->format('Y-m-d'),
                // DataSprzedazy - NIE używamy dla pro forma (powoduje błąd walidacji)
                'SposobZaplaty' => 'PRZ', // PRZ = przelew
                'RodzajPodpisuOdbiorcy' => 'BWO', // brak podpisu odbiorcy i wystawcy
                'NumerZamowienia' => (string) $zamowienie->id,
                'Kontrahent' => $kontrahent,
                'Pozycje' => [$pozycja],
            ];

            // Termin płatności pro forma wg trybu rozliczenia (online opłacone vs odroczona vs domyślnie)
            $this->applyIfirmaProFormaPaymentTerms($invoiceData, $zamowienie);

            // Uwagi - dodajemy tylko jeśli są (nie pusty string)
            if (! empty(trim($uwagi ?? ''))) {
                $invoiceData['Uwagi'] = trim($uwagi);
            }

            // Numer konta bankowego - dodajemy tylko jeśli jest skonfigurowany
            $bankAccount = config('services.ifirma.bank_account', '');
            if (! empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            // Logowanie danych przed wysłaniem do API
            Log::info('iFirma Pro Forma Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'invoice_data_json' => json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);

            // Wystawienie faktury pro forma przez API iFirma
            $ifirmaService = new IfirmaApiService;
            $result = $ifirmaService->createProFormaInvoice($invoiceData);

            // Logowanie pełnej odpowiedzi dla debugowania
            Log::info('iFirma Pro Forma Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
                'full_response' => $result,
            ]);

            // Zwrócenie odpowiedzi
            if ($result['status'] === 'success') {
                // Pobierz Identyfikator z odpowiedzi
                $invoiceId = null;
                if (isset($result['data']['response']['Identyfikator'])) {
                    $invoiceId = $result['data']['response']['Identyfikator'];
                } elseif (isset($result['data']['Identyfikator'])) {
                    $invoiceId = $result['data']['Identyfikator'];
                }

                // Pobierz pełny numer faktury (np. "1/11/2025/ProForma") zamiast samego ID
                $invoiceNumber = null;
                $fullInvoiceData = null;

                if (! empty($invoiceId)) {
                    try {
                        // Pobierz szczegóły faktury z iFirma, aby uzyskać PelnyNumer
                        $invoiceDetails = $ifirmaService->getProFormaInvoice($invoiceId);

                        if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                            $fullInvoiceData = $invoiceDetails['data'];

                            // Pełny numer faktury jest w polu "PelnyNumer"
                            if (isset($fullInvoiceData['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                            } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                            }
                        }

                        Log::info('iFirma Pro Forma - szczegóły pobrane', [
                            'invoice_id' => $invoiceId,
                            'invoice_number' => $invoiceNumber,
                            'details' => $fullInvoiceData,
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                            'invoice_id' => $invoiceId,
                            'error' => $e->getMessage(),
                        ]);
                        // Jeśli nie udało się pobrać, użyj Identyfikatora jako fallback
                        $invoiceNumber = $invoiceId;
                    }
                }

                // Aktualizacja numeru PRO-FORMA w polu notes (Notatki)
                // PRO-FORMA zapisuje się w notes, nie w invoice_number!
                if (! empty($invoiceNumber)) {
                    // Dodaj numer PRO-FORMA na początku (na górze), spychając poprzednie wpisy w dół
                    $existingNotes = ! empty($zamowienie->notes) ? trim($zamowienie->notes) : '';
                    $proFormaNote = "PRO-FORMA: {$invoiceNumber}";

                    // Sprawdź czy numer PRO-FORMA już nie jest w notatkach
                    if (strpos($zamowienie->notes ?? '', $invoiceNumber) === false) {
                        // Nowy wpis na górze, poprzednie wpisy poniżej
                        $zamowienie->notes = $existingNotes
                            ? "{$proFormaNote}\n{$existingNotes}"
                            : $proFormaNote;
                        $zamowienie->save();
                    }
                }

                // Wysyłka e-mailem (jeśli zaznaczono checkbox)
                $sendEmail = $request->input('send_email', false);
                $emailsSent = [];
                $emailErrors = [];

                if ($sendEmail && ! empty($invoiceId)) {
                    // Zbierz unikalne adresy e-mail (małe litery, bez duplikatów)
                    $emails = [];

                    // E-mail zamawiającego
                    if (! empty($zamowienie->orderer_email)) {
                        $emails[] = strtolower(trim($zamowienie->orderer_email));
                    }

                    // E-mail uczestnika (jeśli różny od zamawiającego)
                    if (! empty(trim($zamowienie->display_participant_email ?? ''))) {
                        $participantEmail = strtolower(trim($zamowienie->display_participant_email));
                        if (! in_array($participantEmail, $emails)) {
                            $emails[] = $participantEmail;
                        }
                    }

                    // Wysyłka do wszystkich adresów
                    foreach ($emails as $email) {
                        try {
                            $sendResult = $ifirmaService->sendProFormaByEmail(
                                $invoiceId,
                                $email,
                                $invoiceNumber,
                                $zamowienie->id
                            );

                            if ($sendResult['status'] === 'success') {
                                $emailsSent[] = $email;
                                Log::info('Faktura pro forma wysłana e-mailem', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                ]);
                            } else {
                                $emailErrors[] = [
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ];
                                Log::warning('Błąd wysyłki faktury pro forma', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ]);
                            }
                        } catch (Exception $e) {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $e->getMessage(),
                            ];
                            Log::error('Exception podczas wysyłki faktury pro forma', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Przygotuj wiadomość dla użytkownika
                $message = 'Faktura pro forma została pomyślnie wystawiona w iFirma.pl';
                if (! empty($emailsSent)) {
                    $message .= ' i wysłana na: '.implode(', ', $emailsSent);
                }
                if (! empty($emailErrors)) {
                    $message .= ' (Błędy wysyłki: '.count($emailErrors).')';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['data'] ?? $result['raw_response'] ?? null,
                    'emails_sent' => $emailsSent,
                    'email_errors' => $emailErrors,
                    'created_at' => now()->format('d.m.Y H:i'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury pro forma',
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['raw_response'] ?? null,
                    'status_code' => $result['status_code'] ?? null,
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sprawdza czy faktura już istnieje w bazie danych (przed wystawieniem)
     */
    public function checkInvoiceStatus($id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // Sprawdź pole invoice_number w bazie danych (nie w formularzu!)
            $hasInvoice = $zamowienie->has_invoice;
            $invoiceNumber = $zamowienie->invoice_number;

            return response()->json([
                'success' => true,
                'has_invoice' => $hasInvoice,
                'invoice_number' => $invoiceNumber ?: null,
                'order_id' => $zamowienie->id,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas sprawdzania statusu faktury: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wystawia fakturę krajową (nie pro-forma) w iFirma.pl na podstawie zamówienia
     */
    public function createIfirmaInvoice(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // ZAWSZE sprawdzamy bazę danych (nie formularz!)
            $hasInvoice = $zamowienie->has_invoice;
            $existingInvoiceNumber = $zamowienie->invoice_number;
            $force = $request->input('force', false);

            // Jeśli faktura już istnieje w bazie i nie ma parametru force, zwróć błąd
            if ($hasInvoice && ! $force) {
                // Logowanie próby ponownego wystawienia faktury
                \App\Models\ActivityLog::logCustom(
                    'Próba ponownego wystawienia faktury',
                    "Próba wystawienia faktury dla zamówienia #{$zamowienie->id}, które ma już fakturę: {$existingInvoiceNumber}",
                    [
                        'model_type' => FormOrder::class,
                        'model_id' => $zamowienie->id,
                        'model_name' => "Zamówienie #{$zamowienie->id}",
                        'old_values' => ['invoice_number' => $existingInvoiceNumber],
                    ]
                );

                return response()->json([
                    'success' => false,
                    'error' => 'Faktura dla tego zamówienia została już wystawiona.',
                    'existing_invoice_number' => $existingInvoiceNumber,
                    'message' => 'Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.',
                ], 409); // 409 Conflict
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.',
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.',
                ], 400);
            }

            // Przygotowanie uwag do faktury
            // Sprawdź, czy użytkownik przesłał niestandardowe uwagi (edytowane w formularzu)
            // JavaScript zawsze wyśle to pole (nawet jako pusty string), więc sprawdzamy czy jest w request
            if ($request->has('custom_remarks')) {
                // Pole tekstowe było edytowane - użyj dokładnie tego co jest w polu
                // Jeśli użytkownik wyczyścił pole (pusty string), użyj pustego stringa (nie generuj danych odbiorcy)
                $uwagi = trim($request->input('custom_remarks', ''));
            } else {
                // Pole nie było przesłane w request - generuj automatycznie dane odbiorcy
                $recipientData = [];
                if (! empty($zamowienie->recipient_name)) {
                    $recipientData[] = $zamowienie->recipient_name;
                }
                if (! empty($zamowienie->recipient_address)) {
                    $recipientData[] = $zamowienie->recipient_address;
                }
                if (! empty($zamowienie->recipient_postal_code) && ! empty($zamowienie->recipient_city)) {
                    $recipientData[] = $zamowienie->recipient_postal_code.' '.$zamowienie->recipient_city;
                }
                if (! empty($zamowienie->recipient_nip)) {
                    $recipientData[] = 'NIP: '.preg_replace('/[^0-9]/', '', $zamowienie->recipient_nip);
                }

                $uwagi = "ODBIORCA:\n";
                if (! empty($recipientData)) {
                    $uwagi .= implode("\n", $recipientData);
                }
            }

            // ZAWSZE na końcu dodaj identyfikator zamówienia
            if (! empty(trim($uwagi))) {
                $uwagi .= "\npnedu.pl #{$zamowienie->id}";
            } else {
                $uwagi = "pnedu.pl #{$zamowienie->id}";
            }

            // Przygotowanie danych kontrahenta (FAKTURA KRAJOWA) — ETAP 3.
            // Przycisk „Wystaw Fakturę iFirma”: zawsze faktura BEZ Podmiotu3 (`podmiot3_mode=ignore`),
            // nawet gdy w zamówieniu są metadane KSeF / niekompletne recipient_* — mapper nie jest wołany.
            try {
                $kontrahent = (new \App\Services\IfirmaKontrahentBuilder)
                    ->buildForInvoice($zamowienie, [
                        'podmiot3_mode' => \App\Services\IfirmaKontrahentBuilder::PODMIOT3_MODE_IGNORE,
                    ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } catch (\App\Services\IfirmaKontrahentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }

            // Sprawdzenie, czy konto jest na RYCZAŁCIE
            $isLumpSum = config('services.ifirma.is_lump_sum', false);
            $vatExempt = config('services.ifirma.vat_exempt', false);

            // Przygotowanie pozycji faktury krajowej
            // OBSERWACJA: W formularzu iFirma.pl (UI) NIE MA pola dla StawkaRyczaltu w pozycji faktury
            // To sugeruje, że dla nievatowców na ryczałcie StawkaRyczaltu jest automatycznie
            // pobierane z konfiguracji konta i NIE powinno być podawane explicite w pozycji!
            // Zgodnie z dokumentacją API iFirma dla nievatowców:
            // https://api.ifirma.pl/wystawianie-faktury-sprzedazy-krajowej-dla-nievatowca/

            // Przygotowanie pozycji faktury
            // WAŻNE: Na podstawie działającego kodu PHP kolejność pól jest kluczowa!
            // Kolejność z działającego kodu:
            // 1. PodstawaPrawna (jeśli VAT exempt)
            // 2. StawkaVat (null jeśli VAT exempt)
            // 3. Ilosc
            // 4. CenaJednostkowa
            // 5. NazwaPelna
            // 6. Jednostka
            // 7. TypStawkiVat (na końcu!)

            $cenaJednostkowa = (float) round((float) $zamowienie->product_price, 2);

            $pozycja = [];

            // Dla zwolnionych z VAT: NAJPIERW PodstawaPrawna, POTEM StawkaVat = null
            if ($vatExempt) {
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
                $pozycja['StawkaVat'] = null; // EXPLICITE null (nie brak pola!)
            } else {
                $pozycja['StawkaVat'] = 0.23;
                if ($isLumpSum) {
                    $pozycja['StawkaRyczaltu'] = (float) config('services.ifirma.lump_sum_rate', 0.085);
                }
            }

            // Pozostałe pola w dokładnej kolejności jak w działającym kodzie
            $pozycja['Ilosc'] = (float) 1.0;
            $pozycja['CenaJednostkowa'] = $cenaJednostkowa;
            $pozycja['NazwaPelna'] = $this->ifirmaNazwaPelnaFromRequest($request, (string) $zamowienie->product_name);
            $pozycja['Jednostka'] = 'sztuk';

            // TypStawkiVat NA KOŃCU!
            $pozycja['TypStawkiVat'] = $vatExempt ? 'ZW' : 'PRC';

            // Przygotowanie danych faktury krajowej dla iFirma
            // Zgodnie z dokumentacją: https://api.ifirma.pl/wystawianie-faktury-krajowej/
            // RÓŻNICE vs PRO-FORMA:
            // - Endpoint: fakturakraj.json (nie fakturaproformakraj.json)
            // - DataSprzedazy jest WYMAGANA (w pro-forma jej nie ma)
            // - BRAK pola TypFakturyKrajowej (to pole jest TYLKO dla pro-forma!)
            // - RodzajPodpisuOdbiorcy może być opcjonalne

            // Przygotowanie danych faktury - DOKŁADNA KOLEJNOŚĆ z działającego kodu PHP!
            // WAŻNE: Na podstawie działającego kodu:
            // - LiczOd: 'BRT' dla nievatowców (nie 'NET'!)
            // - RodzajPodpisuOdbiorcy: 'BPO' (nie 'BWO'!)
            // - Kolejność pól jest ważna!

            $invoiceData = [
                'Zaplacono' => 0.00, // PIERWSZA - dokładnie 0.00
                'ZaplaconoNaDokumencie' => 0.00, // DRUGA - dokładnie 0.00
                'LiczOd' => 'BRT', // TRZECIA - BRT dla nievatowców!
                'NumerKontaBankowego' => null,
                'DataWystawienia' => now()->format('Y-m-d'),
                'MiejsceWystawienia' => 'Bieżuń',
                'DataSprzedazy' => now()->format('Y-m-d'),
                'FormatDatySprzedazy' => 'DZN',
                'SposobZaplaty' => 'PRZ',
                'RodzajPodpisuOdbiorcy' => 'BPO', // WAŻNE: BPO, nie BWO!
                'WidocznyNumerBdo' => false,
                'Numer' => null,
                'Pozycje' => [$pozycja],
                'Kontrahent' => $kontrahent,
            ];

            $this->applyIfirmaPaymentSettlementToInvoiceData($invoiceData, $zamowienie);

            // Uwagi
            if (! empty(trim($uwagi))) {
                $invoiceData['Uwagi'] = trim($uwagi);
            }

            // Numer konta bankowego
            $bankAccount = config('services.ifirma.bank_account', '');
            if (! empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            $paymentDelay = ! empty($zamowienie->invoice_payment_delay) ? (int) $zamowienie->invoice_payment_delay : 14;

            Log::info('iFirma Invoice Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'payment_delay_days' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie) ? null : $paymentDelay,
                'ifirma_paid_invoice' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie),
            ]);

            // Wystawienie faktury przez API iFirma
            $ifirmaService = new IfirmaApiService;
            $result = $ifirmaService->createInvoice($invoiceData);

            Log::info('iFirma Invoice Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
                'full_response' => $result,
            ]);

            if ($result['status'] === 'success') {
                // Pobierz Identyfikator z odpowiedzi
                $invoiceId = null;
                if (isset($result['data']['response']['Identyfikator'])) {
                    $invoiceId = $result['data']['response']['Identyfikator'];
                } elseif (isset($result['data']['Identyfikator'])) {
                    $invoiceId = $result['data']['Identyfikator'];
                }

                // Pobierz pełny numer faktury
                $invoiceNumber = null;
                $fullInvoiceData = null;

                if (! empty($invoiceId)) {
                    try {
                        $invoiceDetails = $ifirmaService->getInvoice($invoiceId);

                        if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                            $fullInvoiceData = $invoiceDetails['data'];

                            if (isset($fullInvoiceData['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                            } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                            }
                        }

                        Log::info('iFirma Invoice - szczegóły pobrane', [
                            'invoice_id' => $invoiceId,
                            'invoice_number' => $invoiceNumber,
                            'details' => $fullInvoiceData,
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                            'invoice_id' => $invoiceId,
                            'error' => $e->getMessage(),
                        ]);
                        $invoiceNumber = $invoiceId;
                    }
                }

                // Aktualizacja numeru faktury w zamówieniu (pole invoice_number)
                // Faktura krajowa zapisuje się w invoice_number (nie w invoice_notes!)
                if (! empty($invoiceNumber)) {
                    $oldInvoiceNumber = $zamowienie->invoice_number;

                    // Aktualizuj numer faktury (nadpisz jeśli force=true lub jeśli było puste)
                    if (empty($oldInvoiceNumber) || $force) {
                        // Analityka (ADR-005): numer ustawiony przez iFirma → invoice_path_type=ifirma.
                        \App\Services\Analytics\InvoiceAnalyticsTracker::hintSource(
                            \App\Services\Analytics\InvoiceAnalyticsTracker::PATH_IFIRMA
                        );
                        $zamowienie->invoice_number = $invoiceNumber;
                        $zamowienie->save();

                        // Logowanie operacji wystawienia faktury
                        $logDescription = $force && ! empty($oldInvoiceNumber)
                            ? "Wystawiono nową fakturę {$invoiceNumber} dla zamówienia #{$zamowienie->id} (nadpisano poprzednią fakturę: {$oldInvoiceNumber})"
                            : "Wystawiono fakturę {$invoiceNumber} dla zamówienia #{$zamowienie->id}";

                        \App\Models\ActivityLog::logCustom(
                            'Wystawienie faktury iFirma',
                            $logDescription,
                            [
                                'model_type' => FormOrder::class,
                                'model_id' => $zamowienie->id,
                                'model_name' => "Zamówienie #{$zamowienie->id}",
                                'old_values' => $oldInvoiceNumber ? ['invoice_number' => $oldInvoiceNumber] : null,
                                'new_values' => ['invoice_number' => $invoiceNumber],
                            ]
                        );
                    }
                }

                // Wysyłka e-mailem (jeśli zaznaczono checkbox)
                $sendEmail = $request->input('send_email', false);
                $emailsSent = [];
                $emailErrors = [];

                if ($sendEmail && ! empty($invoiceId)) {
                    $emails = [];

                    if (! empty($zamowienie->orderer_email)) {
                        $emails[] = strtolower(trim($zamowienie->orderer_email));
                    }

                    if (! empty(trim($zamowienie->display_participant_email ?? ''))) {
                        $participantEmail = strtolower(trim($zamowienie->display_participant_email));
                        if (! in_array($participantEmail, $emails)) {
                            $emails[] = $participantEmail;
                        }
                    }

                    foreach ($emails as $email) {
                        try {
                            $sendResult = $ifirmaService->sendInvoiceByEmail(
                                $invoiceId,
                                $email,
                                $invoiceNumber,
                                $zamowienie->id,
                                'invoice'
                            );

                            if ($sendResult['status'] === 'success') {
                                $emailsSent[] = $email;
                                Log::info('Faktura wysłana e-mailem', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                ]);
                            } else {
                                $emailErrors[] = [
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ];
                                Log::warning('Błąd wysyłki faktury', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ]);
                            }
                        } catch (Exception $e) {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $e->getMessage(),
                            ];
                            Log::error('Exception podczas wysyłki faktury', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $message = 'Faktura została pomyślnie wystawiona w iFirma.pl';
                if (! empty($emailsSent)) {
                    $message .= ' i wysłana na: '.implode(', ', $emailsSent);
                }
                if (! empty($emailErrors)) {
                    $message .= ' (Błędy wysyłki: '.count($emailErrors).')';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['data'] ?? $result['raw_response'] ?? null,
                    'emails_sent' => $emailsSent,
                    'email_errors' => $emailErrors,
                    'created_at' => now()->format('d.m.Y H:i'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury',
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['raw_response'] ?? null,
                    'status_code' => $result['status_code'] ?? null,
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wystawia fakturę w iFirma z nabywcą i odbiorcą jako osobne podmioty
     * Nabywca (buyer) - podmiot 2 (Kontrahent)
     * Odbiorca (recipient) - podmiot 3 (DodatkowyPodmiot)
     *
     * @param  int  $id  ID zamówienia
     * @return \Illuminate\Http\JsonResponse
     */
    public function createIfirmaInvoiceWithReceiver(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // ZAWSZE sprawdzamy bazę danych (nie formularz!)
            $hasInvoice = $zamowienie->has_invoice;
            $existingInvoiceNumber = $zamowienie->invoice_number;
            $force = $request->input('force', false);

            // Jeśli faktura już istnieje w bazie i nie ma parametru force, zwróć błąd
            if ($hasInvoice && ! $force) {
                // Logowanie próby ponownego wystawienia faktury
                \App\Models\ActivityLog::logCustom(
                    'Próba ponownego wystawienia faktury z odbiorcą',
                    "Próba wystawienia faktury z odbiorcą dla zamówienia #{$zamowienie->id}, które ma już fakturę: {$existingInvoiceNumber}",
                    [
                        'model_type' => FormOrder::class,
                        'model_id' => $zamowienie->id,
                        'model_name' => "Zamówienie #{$zamowienie->id}",
                        'old_values' => ['invoice_number' => $existingInvoiceNumber],
                    ]
                );

                return response()->json([
                    'success' => false,
                    'error' => 'Faktura dla tego zamówienia została już wystawiona.',
                    'existing_invoice_number' => $existingInvoiceNumber,
                    'message' => 'Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.',
                ], 409); // 409 Conflict
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.',
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.',
                ], 400);
            }

            // Uwagi - edytowalna textarea z UI + automatyczny identyfikator zamówienia.
            // Pozwala m.in. dodać linię „UCZESTNIK: …” / „UCZESTNICY: …” do faktury.
            $uwagi = $this->ifirmaCustomRemarksFromRequest($request, $zamowienie);

            // Wspólny builder (ETAP 3) — `podmiot3_mode=invoice_with_receiver`:
            // przy KSeF źródle `recipient` pełny mapper (role, NIP, fail-fast);
            // przy `none` — legacy `OdbiorcaNaFakturze` z recipient_* jeśli nazwa+kod+miasto
            // kompletne, w przeciwnym razie faktura tylko z nabywcą (bez 400).
            try {
                $kontrahent = (new \App\Services\IfirmaKontrahentBuilder)
                    ->buildForInvoice($zamowienie, [
                        'podmiot3_mode' => \App\Services\IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
                    ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } catch (\App\Services\IfirmaKontrahentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }

            // Sprawdzenie, czy konto jest na RYCZAŁCIE
            $isLumpSum = config('services.ifirma.is_lump_sum', false);
            $vatExempt = config('services.ifirma.vat_exempt', false);

            // Przygotowanie pozycji faktury
            $cenaJednostkowa = (float) round((float) $zamowienie->product_price, 2);

            $pozycja = [];

            // Dla zwolnionych z VAT: NAJPIERW PodstawaPrawna, POTEM StawkaVat = null
            if ($vatExempt) {
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
                $pozycja['StawkaVat'] = null;
            } else {
                $pozycja['StawkaVat'] = 0.23;
                if ($isLumpSum) {
                    $pozycja['StawkaRyczaltu'] = (float) config('services.ifirma.lump_sum_rate', 0.085);
                }
            }

            // Pozostałe pola
            $pozycja['Ilosc'] = (float) 1.0;
            $pozycja['CenaJednostkowa'] = $cenaJednostkowa;
            $pozycja['NazwaPelna'] = $this->ifirmaNazwaPelnaFromRequest($request, (string) $zamowienie->product_name);
            $pozycja['Jednostka'] = 'sztuk';
            $pozycja['TypStawkiVat'] = $vatExempt ? 'ZW' : 'PRC';

            // Przygotowanie danych faktury krajowej z odbiorcą w Kontrahencie
            $invoiceData = [
                'Zaplacono' => 0.00,
                'ZaplaconoNaDokumencie' => 0.00,
                'LiczOd' => 'BRT',
                'NumerKontaBankowego' => null,
                'DataWystawienia' => now()->format('Y-m-d'),
                'MiejsceWystawienia' => 'Bieżuń',
                'DataSprzedazy' => now()->format('Y-m-d'),
                'FormatDatySprzedazy' => 'DZN',
                'SposobZaplaty' => 'PRZ',
                'RodzajPodpisuOdbiorcy' => 'BPO',
                'WidocznyNumerBdo' => false,
                'Numer' => null,
                'Pozycje' => [$pozycja],
                'Kontrahent' => $kontrahent,
            ];

            $this->applyIfirmaPaymentSettlementToInvoiceData($invoiceData, $zamowienie);

            // Uwagi - tylko identyfikator zamówienia
            $invoiceData['Uwagi'] = $uwagi;

            // Numer konta bankowego - dodaj tylko jeśli istnieje
            $bankAccount = config('services.ifirma.bank_account', '');
            if (! empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            $paymentDelay = ! empty($zamowienie->invoice_payment_delay) ? (int) $zamowienie->invoice_payment_delay : 14;

            // Logowanie szczegółowej struktury danych przed wysłaniem
            Log::info('iFirma Invoice With Receiver Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'kontrahent' => $kontrahent,
                'odbiorca_na_fakturze' => $kontrahent['OdbiorcaNaFakturze'] ?? null,
                'payment_delay_days' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie) ? null : $paymentDelay,
                'ifirma_paid_invoice' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie),
                'json_preview' => json_encode($invoiceData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);

            // Wystawienie faktury przez API iFirma
            $ifirmaService = new IfirmaApiService;
            $result = $ifirmaService->createInvoice($invoiceData);

            Log::info('iFirma Invoice With Receiver Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
                'full_response' => $result,
                'parsed_data' => $result['parsed_data'] ?? null,
            ]);

            // Sprawdź czy odpowiedź zawiera błąd (nawet jeśli status_code=200)
            // API iFirma może zwrócić Kod=200 z błędem walidacji w Informacja
            $hasError = false;
            if (isset($result['parsed_data']['response'])) {
                $apiResponse = $result['parsed_data']['response'];
                if (isset($apiResponse['Informacja']) &&
                    (stripos($apiResponse['Informacja'], 'niepoprawna') !== false ||
                     stripos($apiResponse['Informacja'], 'nie można') !== false)) {
                    $hasError = true;
                }
            }

            if ($result['status'] === 'success' && ! $hasError) {
                // Pobierz Identyfikator z odpowiedzi
                $invoiceId = null;
                if (isset($result['data']['response']['Identyfikator'])) {
                    $invoiceId = $result['data']['response']['Identyfikator'];
                } elseif (isset($result['data']['Identyfikator'])) {
                    $invoiceId = $result['data']['Identyfikator'];
                }

                // Pobierz pełny numer faktury
                $invoiceNumber = null;
                $fullInvoiceData = null;

                if (! empty($invoiceId)) {
                    try {
                        $invoiceDetails = $ifirmaService->getInvoice($invoiceId);

                        if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                            $fullInvoiceData = $invoiceDetails['data'];

                            if (isset($fullInvoiceData['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                            } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                            }
                        }

                        Log::info('iFirma Invoice With Receiver - szczegóły pobrane', [
                            'invoice_id' => $invoiceId,
                            'invoice_number' => $invoiceNumber,
                            'details' => $fullInvoiceData,
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                            'invoice_id' => $invoiceId,
                            'error' => $e->getMessage(),
                        ]);
                        $invoiceNumber = $invoiceId;
                    }
                }

                // Aktualizacja numeru faktury w zamówieniu
                if (! empty($invoiceNumber)) {
                    $oldInvoiceNumber = $zamowienie->invoice_number;

                    // Aktualizuj numer faktury (nadpisz jeśli force=true lub jeśli było puste)
                    if (empty($oldInvoiceNumber) || $force) {
                        // Analityka (ADR-005): numer ustawiony przez iFirma → invoice_path_type=ifirma.
                        \App\Services\Analytics\InvoiceAnalyticsTracker::hintSource(
                            \App\Services\Analytics\InvoiceAnalyticsTracker::PATH_IFIRMA
                        );
                        $zamowienie->invoice_number = $invoiceNumber;
                        $zamowienie->save();

                        // Logowanie operacji wystawienia faktury
                        $logDescription = $force && ! empty($oldInvoiceNumber)
                            ? "Wystawiono nową fakturę z odbiorcą {$invoiceNumber} dla zamówienia #{$zamowienie->id} (nadpisano poprzednią fakturę: {$oldInvoiceNumber})"
                            : "Wystawiono fakturę z odbiorcą {$invoiceNumber} dla zamówienia #{$zamowienie->id}";

                        \App\Models\ActivityLog::logCustom(
                            'Wystawienie faktury iFirma z odbiorcą',
                            $logDescription,
                            [
                                'model_type' => FormOrder::class,
                                'model_id' => $zamowienie->id,
                                'model_name' => "Zamówienie #{$zamowienie->id}",
                                'old_values' => $oldInvoiceNumber ? ['invoice_number' => $oldInvoiceNumber] : null,
                                'new_values' => ['invoice_number' => $invoiceNumber],
                            ]
                        );
                    }
                }

                // Wysyłka e-mailem (jeśli zaznaczono checkbox)
                $sendEmail = $request->input('send_email', false);
                $emailsSent = [];
                $emailErrors = [];

                if ($sendEmail && ! empty($invoiceId)) {
                    $emails = [];

                    if (! empty($zamowienie->orderer_email)) {
                        $emails[] = strtolower(trim($zamowienie->orderer_email));
                    }

                    if (! empty(trim($zamowienie->display_participant_email ?? ''))) {
                        $participantEmail = strtolower(trim($zamowienie->display_participant_email));
                        if (! in_array($participantEmail, $emails)) {
                            $emails[] = $participantEmail;
                        }
                    }

                    foreach ($emails as $email) {
                        try {
                            $sendResult = $ifirmaService->sendInvoiceByEmail(
                                $invoiceId,
                                $email,
                                $invoiceNumber,
                                $zamowienie->id,
                                'invoice'
                            );

                            if ($sendResult['status'] === 'success') {
                                $emailsSent[] = $email;
                                Log::info('Faktura z odbiorcą wysłana e-mailem', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                ]);
                            } else {
                                $emailErrors[] = [
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ];
                                Log::warning('Błąd wysyłki faktury z odbiorcą', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd',
                                ]);
                            }
                        } catch (Exception $e) {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $e->getMessage(),
                            ];
                            Log::error('Exception podczas wysyłki faktury z odbiorcą', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $message = 'Faktura z odbiorcą została pomyślnie wystawiona w iFirma.pl';
                if (! empty($emailsSent)) {
                    $message .= ' i wysłana na: '.implode(', ', $emailsSent);
                }
                if (! empty($emailErrors)) {
                    $message .= ' (Błędy wysyłki: '.count($emailErrors).')';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['data'] ?? $result['raw_response'] ?? null,
                    'emails_sent' => $emailsSent,
                    'email_errors' => $emailErrors,
                    'created_at' => now()->format('d.m.Y H:i'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury',
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['raw_response'] ?? null,
                    'status_code' => $result['status_code'] ?? null,
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wyświetla formularz edycji zamówienia
     * Dane uczestnika: preferuje form_order_participants, fallback do form_orders
     */
    public function edit(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::with('primaryParticipant')->findOrFail($id);

            $participant = \App\Models\FormOrderParticipant::where('form_order_id', $id)
                ->where('is_primary', true)
                ->first();

            // Dane do formularza – z form_order_participants; display_* daje spójny fallback przy starych kolumnach
            $participantData = [
                'firstname' => $participant?->participant_firstname ?? '',
                'lastname' => $participant?->participant_lastname ?? '',
                'email' => $participant?->participant_email ?? $zamowienie->display_participant_email ?? '',
            ];
            if (empty($participantData['firstname']) && empty($participantData['lastname']) && ! empty(trim($zamowienie->display_participant_name ?? ''))) {
                $parts = explode(' ', trim($zamowienie->display_participant_name), 2);
                $participantData['firstname'] = $parts[0] ?? '';
                $participantData['lastname'] = $parts[1] ?? '';
            }

            return view('form-orders.edit', compact('zamowienie', 'participant', 'participantData'));
        } catch (Exception $e) {
            return redirect()->route('form-orders.index')->with('error', 'Zamówienie nie zostało znalezione.');
        }
    }

    /**
     * Wystawia fakturę w iFirma, przesyła do KSeF i opcjonalnie wysyła na e-mail
     *
     * @param  int  $id  ID zamówienia
     * @return \Illuminate\Http\JsonResponse
     */
    public function createIfirmaInvoiceWithKsef(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (! $zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.',
                ], 404);
            }

            // ZAWSZE sprawdzamy bazę danych (nie formularz!)
            $hasInvoice = $zamowienie->has_invoice;
            $existingInvoiceNumber = $zamowienie->invoice_number;
            $force = $request->input('force', false);

            // Jeśli faktura już istnieje w bazie i nie ma parametru force, zwróć błąd
            if ($hasInvoice && ! $force) {
                return response()->json([
                    'success' => false,
                    'error' => 'Faktura dla tego zamówienia została już wystawiona.',
                    'existing_invoice_number' => $existingInvoiceNumber,
                    'message' => 'Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.',
                ], 409);
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.',
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.',
                ], 400);
            }

            // Uwagi - edytowalna textarea z UI + automatyczny identyfikator zamówienia.
            // Pozwala m.in. dodać linię "UCZESTNIK: ..." / "UCZESTNICY: ..." do faktury.
            $uwagi = $this->ifirmaCustomRemarksFromRequest($request, $zamowienie);

            // Wspólny builder (ETAP 3) — ten sam tryb co „Wystaw Fakturę iFirma z Odbiorcą”
            // (`podmiot3_mode=invoice_with_receiver`): KSeF metadane → mapper; `none` + kompletne
            // recipient_* → legacy OdbiorcaNaFakturze; inaczej tylko nabywca. Różnica względem
            // fioletowego przycisku: po udanym wystawieniu faktury następuje sendInvoiceToKsef.
            try {
                $kontrahent = (new \App\Services\IfirmaKontrahentBuilder)
                    ->buildForInvoice($zamowienie, [
                        'podmiot3_mode' => \App\Services\IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
                    ]);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } catch (\App\Services\IfirmaKontrahentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }

            // Sprawdzenie, czy konto jest na RYCZAŁCIE
            $isLumpSum = config('services.ifirma.is_lump_sum', false);
            $vatExempt = config('services.ifirma.vat_exempt', false);

            // Przygotowanie pozycji faktury
            $cenaJednostkowa = (float) round((float) $zamowienie->product_price, 2);

            $pozycja = [];

            // Dla zwolnionych z VAT: NAJPIERW PodstawaPrawna, POTEM StawkaVat = null
            if ($vatExempt) {
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
                $pozycja['StawkaVat'] = null;
            } else {
                $pozycja['StawkaVat'] = 0.23;
                if ($isLumpSum) {
                    $pozycja['StawkaRyczaltu'] = (float) config('services.ifirma.lump_sum_rate', 0.085);
                }
            }

            // Pozostałe pola
            $pozycja['Ilosc'] = (float) 1.0;
            $pozycja['CenaJednostkowa'] = $cenaJednostkowa;
            $pozycja['NazwaPelna'] = $this->ifirmaNazwaPelnaFromRequest($request, (string) $zamowienie->product_name);
            $pozycja['Jednostka'] = 'sztuk';
            $pozycja['TypStawkiVat'] = $vatExempt ? 'ZW' : 'PRC';

            // Przygotowanie danych faktury krajowej z odbiorcą w Kontrahencie
            $invoiceData = [
                'Zaplacono' => 0.00,
                'ZaplaconoNaDokumencie' => 0.00,
                'LiczOd' => 'BRT',
                'NumerKontaBankowego' => null,
                'DataWystawienia' => now()->format('Y-m-d'),
                'MiejsceWystawienia' => 'Bieżuń',
                'DataSprzedazy' => now()->format('Y-m-d'),
                'FormatDatySprzedazy' => 'DZN',
                'SposobZaplaty' => 'PRZ',
                'RodzajPodpisuOdbiorcy' => 'BPO',
                'WidocznyNumerBdo' => false,
                'Numer' => null,
                'Pozycje' => [$pozycja],
                'Kontrahent' => $kontrahent,
            ];

            $this->applyIfirmaPaymentSettlementToInvoiceData($invoiceData, $zamowienie);

            // Uwagi - tylko identyfikator zamówienia
            $invoiceData['Uwagi'] = $uwagi;

            // Numer konta bankowego - dodaj tylko jeśli istnieje
            $bankAccount = config('services.ifirma.bank_account', '');
            if (! empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            $paymentDelay = ! empty($zamowienie->invoice_payment_delay) ? (int) $zamowienie->invoice_payment_delay : 14;

            // Logowanie szczegółowej struktury danych przed wysłaniem
            Log::info('iFirma Invoice With KSeF Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'kontrahent' => $kontrahent,
                'odbiorca_na_fakturze' => $kontrahent['OdbiorcaNaFakturze'] ?? null,
                'payment_delay_days' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie) ? null : $paymentDelay,
                'ifirma_paid_invoice' => $this->ifirmaShouldMarkInvoiceAsPaid($zamowienie),
            ]);

            // KROK 1: Wystawienie faktury krajowej przez API iFirma
            $ifirmaService = new IfirmaApiService;
            $result = $ifirmaService->createInvoice($invoiceData);

            Log::info('iFirma Invoice With KSeF Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            // Zwrócenie odpowiedzi
            if ($result['status'] !== 'success') {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury',
                    'step' => 'invoice_creation',
                    'details' => $result,
                ], $result['status_code'] ?? 500);
            }

            // Pobierz Identyfikator z odpowiedzi
            $invoiceId = null;
            if (isset($result['data']['response']['Identyfikator'])) {
                $invoiceId = $result['data']['response']['Identyfikator'];
            } elseif (isset($result['data']['Identyfikator'])) {
                $invoiceId = $result['data']['Identyfikator'];
            }

            if (empty($invoiceId)) {
                Log::error('iFirma Invoice With KSeF: Brak Identyfikatora w odpowiedzi', [
                    'order_id' => $zamowienie->id,
                    'response' => $result,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Nie udało się uzyskać identyfikatora faktury z odpowiedzi iFirma',
                    'step' => 'invoice_creation',
                    'details' => $result,
                ], 500);
            }

            // Pobierz pełny numer faktury
            $invoiceNumber = null;
            try {
                $invoiceDetails = $ifirmaService->getInvoice($invoiceId);
                if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                    $fullInvoiceData = $invoiceDetails['data'];
                    if (isset($fullInvoiceData['PelnyNumer'])) {
                        $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                    } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                        $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                    }
                }
            } catch (Exception $e) {
                Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Aktualizacja numeru faktury w bazie
            // Analityka (ADR-005): numer ustawiony przez iFirma (KSeF) → invoice_path_type=ifirma.
            \App\Services\Analytics\InvoiceAnalyticsTracker::hintSource(
                \App\Services\Analytics\InvoiceAnalyticsTracker::PATH_IFIRMA
            );
            $zamowienie->invoice_number = $invoiceNumber ?: $invoiceId;
            $zamowienie->save();

            // KROK 2: Przesłanie faktury do KSeF
            Log::info('iFirma Invoice With KSeF: Przesyłanie do KSeF', [
                'order_id' => $zamowienie->id,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
            ]);

            $ksefResult = $ifirmaService->sendInvoiceToKsef($invoiceId, 'fakturakraj');

            $ksefNumber = null;

            if ($ksefResult['status'] !== 'success') {
                $ksefError = $ksefResult['message'] ?? 'Nieznany błąd przesyłania do KSeF';

                $zamowienie->ksef_status = 'failed';
                $zamowienie->ksef_error = $ksefError;
                $zamowienie->save();

                Log::error('iFirma Invoice With KSeF: Błąd przesyłania do KSeF', [
                    'order_id' => $zamowienie->id,
                    'invoice_id' => $invoiceId,
                    'error' => $ksefError,
                    'ksef_response' => $ksefResult,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Faktura została wystawiona, ale nie udało się przesłać do KSeF',
                    'step' => 'ksef_send',
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'ksef_error' => $ksefError,
                    'can_retry' => true,
                ], 500);
            }

            // POST …/ksef/send/ zwykle kończy się zanim MF nada numer („Przekazana do wysyłki”).
            // Dopiero po przetworzeniu pojawia się NumerKSeF — polling GET faktury (patrz pomoc iFirma).
            $ksefNumber = $ifirmaService->extractNumerKSeFFromInvoicePayload(
                isset($ksefResult['data']) && is_array($ksefResult['data']) ? $ksefResult['data'] : null
            );

            $poll = ($ksefNumber === null || $ksefNumber === '')
                ? $ifirmaService->waitForKsefInvoiceAccepted($invoiceId)
                : ['outcome' => 'accepted', 'numer_ksef' => $ksefNumber, 'rejection_message' => null, 'attempts' => 0];

            if ($poll['outcome'] === 'timeout') {
                $pendingMsg = 'Faktura została przekazana do KSeF, ale w czasie oczekiwania nie nadano numeru KSeF '
                    .'(sprawdź status w iFirma / MF). E-mail z fakturą nie został wysłany.';

                $zamowienie->ksef_status = 'pending';
                $zamowienie->ksef_error = $pendingMsg;
                $zamowienie->ksef_sent_at = null;
                $zamowienie->save();

                Log::warning('iFirma Invoice With KSeF: timeout oczekiwania na NumerKSeF', [
                    'order_id' => $zamowienie->id,
                    'invoice_id' => $invoiceId,
                    'poll_attempts' => $poll['attempts'],
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $pendingMsg,
                    'step' => 'ksef_acceptance_timeout',
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'poll_attempts' => $poll['attempts'],
                    'can_retry' => true,
                ], 504);
            }

            if ($poll['outcome'] === 'rejected') {
                $ksefError = $poll['rejection_message'] ?? 'KSeF odrzucił lub nie przyjął faktury';

                $zamowienie->ksef_status = 'failed';
                $zamowienie->ksef_error = $ksefError;
                $zamowienie->save();

                Log::error('iFirma Invoice With KSeF: odrzucenie / błąd po przekazaniu do KSeF', [
                    'order_id' => $zamowienie->id,
                    'invoice_id' => $invoiceId,
                    'error' => $ksefError,
                    'poll_attempts' => $poll['attempts'],
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Faktura została wystawiona w iFirma, ale nie została zaakceptowana w KSeF',
                    'step' => 'ksef_rejected',
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'ksef_error' => $ksefError,
                    'can_retry' => true,
                ], 500);
            }

            $ksefNumber = $poll['numer_ksef'];

            $updateData = [
                'ksef_status' => 'sent',
                'ksef_sent_at' => now(),
                'ksef_error' => null,
                'ksef_number' => $ksefNumber,
            ];

            Log::info('iFirma Invoice With KSeF: Przed zapisem do bazy (zaakceptowano w KSeF)', [
                'order_id' => $zamowienie->id,
                'update_data' => $updateData,
                'poll_attempts' => $poll['attempts'],
            ]);

            $zamowienie->update($updateData);
            $zamowienie->refresh();

            Log::info('iFirma Invoice With KSeF: Po zapisie do bazy', [
                'order_id' => $zamowienie->id,
                'ksef_status' => $zamowienie->ksef_status,
                'ksef_sent_at' => $zamowienie->ksef_sent_at,
                'ksef_number' => $zamowienie->ksef_number,
            ]);

            // KROK 3: Wysyłka e-mailem — dopiero po potwierdzeniu nadania numeru KSeF (polling powyżej).
            $sendEmail = $request->input('send_email', false);
            $emailsSent = [];
            $emailErrors = [];

            if ($sendEmail && ! empty($invoiceId)) {
                // Zbierz unikalne adresy e-mail
                $emails = [];

                if (! empty($zamowienie->orderer_email)) {
                    $emails[] = strtolower(trim($zamowienie->orderer_email));
                }

                if (! empty(trim($zamowienie->display_participant_email ?? ''))) {
                    $participantEmail = strtolower(trim($zamowienie->display_participant_email));
                    if (! in_array($participantEmail, $emails)) {
                        $emails[] = $participantEmail;
                    }
                }

                // Wysyłka do wszystkich adresów
                foreach ($emails as $email) {
                    try {
                        // Dodaj informację o numerze KSeF do treści wiadomości
                        $emailMessage = "W załączeniu przesyłamy fakturę nr {$invoiceNumber}";
                        if ($ksefNumber) {
                            $emailMessage .= " (numer KSeF: {$ksefNumber})";
                        }
                        $emailMessage .= " dotyczącą zamówienia nr {$zamowienie->id}.";

                        $sendResult = $ifirmaService->sendInvoiceByEmail(
                            $invoiceId,
                            $email,
                            $invoiceNumber,
                            $zamowienie->id,
                            'invoice'
                        );

                        if ($sendResult['status'] === 'success') {
                            $emailsSent[] = $email;
                            Log::info('Faktura z KSeF wysłana e-mailem', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'ksef_number' => $ksefNumber,
                            ]);
                        } else {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $sendResult['message'] ?? 'Nieznany błąd',
                            ];
                            Log::warning('Błąd wysyłki faktury z KSeF', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'error' => $sendResult['message'] ?? 'Nieznany błąd',
                            ]);
                        }
                    } catch (Exception $e) {
                        $emailErrors[] = [
                            'email' => $email,
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Exception podczas wysyłki faktury z KSeF', [
                            'invoice_id' => $invoiceId,
                            'email' => $email,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Przygotowanie komunikatu sukcesu
            $message = 'Faktura została wystawiona w iFirma.pl';
            if ($ksefNumber) {
                $message .= " i przesłana do KSeF (nr: {$ksefNumber})";
            }
            if (! empty($emailsSent)) {
                $message .= ' i wysłana na: '.implode(', ', $emailsSent);
            }
            if (! empty($emailErrors)) {
                $message .= ' (Błędy wysyłki e-mail: '.count($emailErrors).')';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'ksef_number' => $ksefNumber,
                'ksef_sent_at' => $zamowienie->ksef_sent_at ? $zamowienie->ksef_sent_at->toDateTimeString() : null,
                'email_sent' => ! empty($emailsSent),
                'emails_sent' => $emailsSent,
                'email_errors' => $emailErrors,
            ]);

        } catch (Exception $e) {
            Log::error('Exception podczas wystawiania faktury z KSeF', [
                'order_id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Usuwa zamówienie (soft delete)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);

            // Soft delete (przeniesienie do kosza)
            $zamowienie->delete();

            // Parametry przekierowania
            $redirectParams = [];
            if ($request->has('per_page')) {
                $redirectParams['per_page'] = $request->input('per_page');
            }
            if ($request->has('search')) {
                $redirectParams['search'] = $request->input('search');
            }
            if ($request->filled('quick')) {
                $redirectParams['quick'] = $request->input('quick');
            }
            if ($request->has('filter')) {
                $redirectParams['filter'] = $request->input('filter');
            }
            if ($request->filled('archival')) {
                $redirectParams['archival'] = 1;
            }
            if ($request->has('page')) {
                $redirectParams['page'] = $request->input('page');
            }
            if ($request->filled('order_id')) {
                $redirectParams['order_id'] = $request->input('order_id');
            }
            if ($request->filled('course_id')) {
                $redirectParams['course_id'] = $request->input('course_id');
            }

            return redirect()->route('form-orders.index', $redirectParams)
                ->with('success', 'Zamówienie zostało usunięte i przeniesione do kosza.');
        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Wystąpił błąd podczas usuwania zamówienia: '.$e->getMessage());
        }
    }

    /**
     * Wyświetla listę duplikatów zamówień
     */
    public function duplicates(Request $request)
    {
        // Liczba rekordów na stronę
        $perPage = $request->get('per_page', 25);

        // Pobierz grupy duplikatów
        $duplicateGroups = FormOrder::duplicates()->get();

        // Przygotuj dane do wyświetlenia
        $duplicates = collect();
        foreach ($duplicateGroups as $group) {
            $orderIds = explode(',', $group->order_ids);
            $orders = FormOrder::whereIn('id', $orderIds)
                ->with(['marketingCampaign.sourceType', 'primaryParticipant', 'onlinePaymentOrders', 'coursePriceVariant'])
                ->get()
                ->sortByDesc('priority'); // Sortuj według priorytetu (najważniejsze pierwsze)

            $extensionService = app(FormOrderAccessExtensionService::class);
            $orders->each(function (FormOrder $order) use ($extensionService): void {
                $order->access_extension_preview = $extensionService->preview($order);
            });

            $duplicates->push([
                'email' => $group->participant_email,
                'product_id' => $group->duplicate_course_key,
                'count' => $group->duplicate_count,
                'orders' => $orders,
                'recommended_order' => $orders->first(), // Najwyższy priorytet
                'oldest_order' => $orders->sortBy('id')->first(),
                'newest_order' => $orders->sortBy('id')->last(),
            ]);
        }

        // Paginacja dla grup duplikatów
        $currentPage = $request->get('page', 1);
        $perPage = min($perPage, 50); // Maksymalnie 50 grup na stronę
        $offset = ($currentPage - 1) * $perPage;
        $paginatedDuplicates = $duplicates->slice($offset, $perPage);

        // Tworzenie obiektu paginacji
        $duplicatesPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedDuplicates,
            $duplicates->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        // Statystyki
        $totalDuplicates = $duplicates->sum('count');
        $totalGroups = $duplicates->count();
        $totalOrders = $duplicates->sum(function ($group) {
            return $group['count'];
        });
        $urgentDuplicatesTotal = $this->countUrgentDuplicateGroups($duplicateGroups);

        return view('form-orders.duplicates', compact(
            'duplicatesPaginated',
            'perPage',
            'totalDuplicates',
            'totalGroups',
            'totalOrders',
            'urgentDuplicatesTotal'
        ));
    }

    /**
     * Usuwa duplikat (soft delete)
     */
    public function destroyDuplicate(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::with('primaryParticipant')->findOrFail($id);

            // Sprawdź czy to rzeczywiście duplikat
            $duplicates = FormOrder::findDuplicatesFor($id)->get();
            if ($duplicates->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'To zamówienie nie ma duplikatów.',
                ], 400);
            }

            // Soft delete
            $zamowienie->delete();

            return response()->json([
                'success' => true,
                'message' => 'Duplikat został usunięty.',
                'remaining_duplicates' => $duplicates->count(),
                'email' => $zamowienie->display_participant_email,
                'product_id' => $zamowienie->resolveDuplicateGroupingCourseKey(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatu: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Usuwa wszystkie duplikaty dla konkretnej grupy (oprócz najstarszego)
     */
    public function destroyAllDuplicatesForGroup(Request $request, $email, $productId)
    {
        try {
            $courseKey = $this->resolveDuplicateGroupCourseKeyForUrlParameter($productId);

            // Znajdź wszystkie zamówienia w grupie duplikatów (e-mail + ten sam klucz szkolenia co na /duplicates)
            $orders = FormOrder::whereDuplicateGroupingCourseKey($courseKey)
                ->wherePrimaryParticipantEmailMatches($email)
                ->orderBy('id')
                ->get();

            if ($orders->count() < 2) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono duplikatów dla tej grupy.',
                ], 400);
            }

            // Zostaw najstarsze zamówienie, usuń resztę
            $oldestOrder = $orders->first();
            $duplicatesToDelete = $orders->skip(1);

            $deletedCount = 0;
            foreach ($duplicatesToDelete as $duplicate) {
                $duplicate->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Usunięto {$deletedCount} duplikatów. Zachowano najstarsze zamówienie #{$oldestOrder->id}.",
                'kept_order_id' => $oldestOrder->id,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatów: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Usuwa wszystkie duplikaty oprócz wybranego zamówienia
     */
    public function destroyDuplicatesKeepSelected(Request $request, $email, $productId, $keepOrderId)
    {
        try {
            $courseKey = $this->resolveDuplicateGroupCourseKeyForUrlParameter($productId);

            // Znajdź wszystkie zamówienia w grupie duplikatów (e-mail + ten sam klucz szkolenia co na /duplicates)
            $orders = FormOrder::whereDuplicateGroupingCourseKey($courseKey)
                ->wherePrimaryParticipantEmailMatches($email)
                ->get();

            if ($orders->count() < 2) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono duplikatów dla tej grupy.',
                ], 400);
            }

            // Znajdź zamówienie do zachowania
            $keepOrder = $orders->where('id', $keepOrderId)->first();
            if (! $keepOrder) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono zamówienia do zachowania.',
                ], 400);
            }

            // Usuń wszystkie oprócz wybranego
            $duplicatesToDelete = $orders->where('id', '!=', $keepOrderId);

            $deletedCount = 0;
            foreach ($duplicatesToDelete as $duplicate) {
                $duplicate->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Usunięto {$deletedCount} duplikatów. Zachowano zamówienie #{$keepOrder->id}.",
                'kept_order_id' => $keepOrder->id,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatów: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Oznacz duplikat jako zakończony
     */
    public function markAsCompleted(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);

            // Sprawdź czy to rzeczywiście duplikat
            $duplicates = FormOrder::findDuplicatesFor($id)->get();
            if ($duplicates->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'To zamówienie nie ma duplikatów.',
                ], 400);
            }

            // Oznacz jako zakończone
            $zamowienie->status_completed = 1;

            // Dodaj notatkę jeśli podana
            $notes = $request->input('notes');
            if ($notes) {
                $zamowienie->notes = $notes;
            }

            $zamowienie->save();

            return response()->json([
                'success' => true,
                'message' => "Zamówienie #{$id} zostało oznaczone jako zakończone (duplikat).",
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas oznaczania jako zakończone: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aktualizuj notatkę zamówienia
     */
    public function updateNotes(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);

            $notes = $request->input('notes');
            $zamowienie->notes = $notes;
            $zamowienie->save();

            return response()->json([
                'success' => true,
                'message' => "Notatka dla zamówienia #{$id} została zaktualizowana.",
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas zapisywania notatki: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Zapis metadanych KSeF Podmiot3 z widoku szczegółów (AJAX, bez przeładowania).
     */
    public function updateKsefSettings(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);

            $validated = $request->validate($this->ksefSettingsValidationRules());

            $zamowienie->fill([
                'ksef_entity_source' => $validated['ksef_entity_source'],
                'ksef_additional_entity_role' => ($validated['ksef_additional_entity_role'] ?? null) ?: null,
                'ksef_additional_entity_id_type' => ($validated['ksef_additional_entity_id_type'] ?? null) ?: null,
                'ksef_additional_entity_identifier' => ($validated['ksef_additional_entity_identifier'] ?? null) ?: null,
                'ksef_admin_note' => ($validated['ksef_admin_note'] ?? null) ?: null,
            ]);
            $zamowienie->updated_manually_at = now();
            $zamowienie->save();
            $zamowienie->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Ustawienia KSeF zostały zapisane.',
                'summary_html' => View::make('form-orders.partials.ksef-additional-entity-show', [
                    'zamowienie' => $zamowienie,
                ])->render(),
                'is_active' => $zamowienie->isKsefAdditionalEntityEnabled(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nieprawidłowe ustawienia KSeF.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas zapisywania ustawień KSeF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, string>
     */
    private function ksefSettingsValidationRules(): array
    {
        return [
            'ksef_entity_source' => 'required|string|in:'.implode(',', FormOrder::KSEF_ENTITY_SOURCES),
            'ksef_additional_entity_role' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ROLES),
            'ksef_additional_entity_id_type' => 'nullable|string|in:'.implode(',', FormOrder::KSEF_ADDITIONAL_ENTITY_ID_TYPES),
            'ksef_additional_entity_identifier' => 'nullable|string|max:50',
            'ksef_admin_note' => 'nullable|string',
        ];
    }

    /**
     * Grupy „pilne” — jak filtr „Wymaga oznaczenia duplikatów” / „za dużo faktur” na stronie duplikatów.
     *
     * @param  Collection<int, object>  $duplicateGroups
     */
    private function countUrgentDuplicateGroups(Collection $duplicateGroups): int
    {
        $urgent = 0;
        foreach ($duplicateGroups as $duplicate) {
            $orderIds = array_map('trim', explode(',', $duplicate->order_ids));
            $orders = FormOrder::whereIn('id', $orderIds)->get();

            $activeCount = 0;
            $mainCount = 0;

            foreach ($orders as $order) {
                if ($order->has_invoice) {
                    $mainCount++;
                } elseif (! $order->is_completed) {
                    $activeCount++;
                }
            }

            $needsAction = ($activeCount > 1) || ($mainCount > 0 && $activeCount > 0);
            $hasMultipleInvoices = ($mainCount > 1);

            if ($needsAction || $hasMultipleInvoices) {
                $urgent++;
            }
        }

        return $urgent;
    }

    /**
     * Parametr {productId} z URL akcji na duplikatach: zwykle courses.id z widoku /duplicates;
     * obsługa starych odwołań po samym id_old Publigo (np. 84075 zamiast 476).
     */
    private function resolveDuplicateGroupCourseKeyForUrlParameter(string|int $productId): int
    {
        $asInt = (int) $productId;
        if ($asInt < 0) {
            return 0;
        }
        if ($asInt > 0 && Course::query()->whereKey($asInt)->exists()) {
            return $asInt;
        }
        $byOld = Course::query()
            ->where('id_old', $productId)
            ->value('id');

        return $byOld !== null ? (int) $byOld : $asInt;
    }

    /**
     * Kwota brutto dokumentu (LiczOd=BRT, ilość 1) – zgodna z CenaJednostkowa i product_price.
     */
    private function ifirmaBruttoTotalForOrder(FormOrder $zamowienie): float
    {
        return round((float) $zamowienie->product_price, 2);
    }

    /**
     * Faktura jako opłacona: płatność online (bramka) + status opłacone.
     */
    private function ifirmaShouldMarkInvoiceAsPaid(FormOrder $zamowienie): bool
    {
        return $zamowienie->payment_mode === FormOrder::PAYMENT_MODE_ONLINE_GATEWAY
            && $zamowienie->payment_status === FormOrder::PAYMENT_STATUS_PAID;
    }

    /**
     * Ustawia Zaplacono, ZaplaconoNaDokumencie, TerminPlatnosci wg trybu rozliczenia (faktura krajowa / fakturakraj.json).
     *
     * @param  array<string, mixed>  $invoiceData
     */
    private function applyIfirmaPaymentSettlementToInvoiceData(array &$invoiceData, FormOrder $zamowienie): void
    {
        $brutto = $this->ifirmaBruttoTotalForOrder($zamowienie);

        if ($this->ifirmaShouldMarkInvoiceAsPaid($zamowienie)) {
            $invoiceData['Zaplacono'] = $brutto;
            $invoiceData['ZaplaconoNaDokumencie'] = $brutto;
            $invoiceData['TerminPlatnosci'] = now()->format('Y-m-d');

            return;
        }

        $invoiceData['Zaplacono'] = 0.0;
        $invoiceData['ZaplaconoNaDokumencie'] = 0.0;

        $paymentDelay = ! empty($zamowienie->invoice_payment_delay) ? (int) $zamowienie->invoice_payment_delay : 14;
        $invoiceData['TerminPlatnosci'] = now()->addDays($paymentDelay)->format('Y-m-d');
    }

    /**
     * Termin płatności pro forma (endpoint pro formy – bez pól Zaplacono jak przy fakturze VAT).
     *
     * @param  array<string, mixed>  $invoiceData
     */
    private function applyIfirmaProFormaPaymentTerms(array &$invoiceData, FormOrder $zamowienie): void
    {
        if ($this->ifirmaShouldMarkInvoiceAsPaid($zamowienie)) {
            $invoiceData['TerminPlatnosci'] = now()->format('Y-m-d');

            return;
        }

        $paymentDelay = ! empty($zamowienie->invoice_payment_delay) ? (int) $zamowienie->invoice_payment_delay : 14;
        $invoiceData['TerminPlatnosci'] = now()->addDays($paymentDelay)->format('Y-m-d');
    }

    /**
     * Nazwa pozycji (NazwaPelna) na fakturze iFirma — opcjonalny prefiks z UI: „SZKOLENIE: ”.
     */
    private function ifirmaNazwaPelnaFromRequest(Request $request, string $productName): string
    {
        if (! $request->boolean('prefix_szkolenie_in_product_name')) {
            return $productName;
        }

        if (preg_match('/^\s*SZKOLENIE\s*:/iu', $productName)) {
            return $productName;
        }

        return 'SZKOLENIE: '.$productName;
    }

    /**
     * Buduje pole „Uwagi” iFirma na podstawie edytowalnej textarei z UI, dokleja
     * na końcu identyfikator zamówienia (`pnedu.pl #ID`). Używane przez ścieżki
     * „Wystaw Fakturę iFirma z Odbiorcą” i „… z Odbiorcą i prześlij do KSeF”,
     * gdzie wcześniej uwagi były na sztywno (frontend wysyłał `custom_remarks`,
     * a backend ignorował). Patrz issue: faktura bez „UCZESTNIK: …”.
     *
     * Gdy request nie zawiera `custom_remarks` (np. wywołanie API bez UI),
     * zachowujemy dotychczasowe minimalne zachowanie — same `pnedu.pl #ID`.
     */
    private function ifirmaCustomRemarksFromRequest(Request $request, FormOrder $zamowienie): string
    {
        $uwagi = $request->has('custom_remarks')
            ? trim((string) $request->input('custom_remarks', ''))
            : '';

        if ($uwagi !== '') {
            return $uwagi."\npnedu.pl #{$zamowienie->id}";
        }

        return "pnedu.pl #{$zamowienie->id}";
    }
}
