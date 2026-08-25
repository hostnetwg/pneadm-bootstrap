<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły zamówienia') }} <span class="text-danger">(PNEADM)</span>
        </h2>
    </x-slot>

    @php
        $orderAdminClosedLabel = null;
        $orderAdminClosedTitle = null;
        $orderAdminClosedIcon = 'bi-x-circle';
        $orderAdminClosedBadgeClass = 'bg-secondary';
        if ($zamowienie->isCancelled()) {
            $orderAdminClosedLabel = 'ANULOWANE';
            $orderAdminClosedIcon = 'bi-x-circle';
            $orderAdminClosedBadgeClass = 'bg-danger';
            $orderAdminClosedTitle = 'Anulowano';
            if ($zamowienie->cancelled_at) {
                $orderAdminClosedTitle .= ' '.$zamowienie->cancelled_at->timezone(config('app.timezone'))->format('d.m.Y H:i');
            }
            if (filled($zamowienie->cancelled_reason)) {
                $orderAdminClosedTitle .= ' — '.$zamowienie->cancelled_reason;
            }
        } elseif ($zamowienie->isLegacyHandled()) {
            $orderAdminClosedLabel = 'ZAMKNIĘTE';
            $orderAdminClosedIcon = 'bi-archive';
            $orderAdminClosedBadgeClass = 'bg-secondary';
            $orderAdminClosedTitle = 'Zamknięte';
            if ($zamowienie->legacy_handled_at) {
                $orderAdminClosedTitle .= ' '.$zamowienie->legacy_handled_at->timezone(config('app.timezone'))->format('d.m.Y H:i');
            }
            if (filled($zamowienie->legacy_handled_reason)) {
                $orderAdminClosedTitle .= ' — '.$zamowienie->legacy_handled_reason;
            }
        }

        $formOrderPaymentHighlightClass = $orderAdminClosedLabel
            ? 'form-order-detail--admin-closed'
            : match ($zamowienie->payment_mode) {
                \App\Models\FormOrder::PAYMENT_MODE_ONLINE_GATEWAY => 'form-order-detail--online-gateway',
                \App\Models\FormOrder::PAYMENT_MODE_DEFERRED_INVOICE => 'form-order-detail--deferred-invoice',
                default => null,
            };
    @endphp

    <style>
        /* Spójnie z listą /form-orders: zamówienia z bramką płatności */
        .form-order-detail--online-gateway {
            border-left: 5px solid #0d47a1;
            background: linear-gradient(145deg, #e3f2fd 0%, #bbdefb 35%, #e8f4fc 100%);
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.12);
        }
        /* Ten sam schemat (obramowanie + gradient + cień), inna paleta — faktura z odroczonym terminem */
        .form-order-detail--deferred-invoice {
            border-left: 5px solid #e65100;
            background: linear-gradient(145deg, #fff8e1 0%, #ffe0b2 35%, #fff3e0 100%);
            box-shadow: 0 4px 14px rgba(230, 81, 0, 0.12);
        }
        /* Anulowane / zamknięte przez admina — szara paleta; czerwień zostaje tylko na badge ANULOWANE */
        .form-order-detail--admin-closed {
            border-left: 5px solid #6c757d;
            background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 35%, #f1f3f5 100%);
            box-shadow: 0 4px 14px rgba(73, 80, 87, 0.12);
        }
        /* Uwagi zamawiającego w bloku INFORMACJE O FAKTURZE */
        @keyframes invoice-notes-attention-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.65; }
        }
        .invoice-notes-attention-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.55rem;
            height: 1.55rem;
            border-radius: 50%;
            background: #dc3545;
            color: #fff;
            font-size: 0.95rem;
            vertical-align: -0.15em;
            animation: invoice-notes-attention-pulse 1.1s ease-in-out infinite;
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.55);
        }
        @keyframes invoice-notes-panel-glow {
            0%, 100% { background-color: #fff; box-shadow: none; }
            35%, 65% { background-color: #fff3cd; box-shadow: inset 0 0 0 2px rgba(220, 53, 69, 0.35); }
        }
        .invoice-notes-panel--attention .card-body {
            animation: invoice-notes-panel-glow 1.4s ease-in-out 2;
        }
        .invoice-notes-panel--attention .invoice-notes-customer-text {
            font-weight: 600;
        }
    </style>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if($formOrderPaymentHighlightClass)
                <div class="{{ $formOrderPaymentHighlightClass }} px-3 py-3 rounded-3">
            @endif

            {{-- Numer zamówienia (lewo) + breadcrumb (prawo) --}}
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                <div>
                    <h2 class="d-inline-block mb-0 @if($orderAdminClosedLabel) text-secondary @elseif($zamowienie->is_new) text-danger @elseif($zamowienie->status_completed == 1) text-secondary @else text-success @endif">Zamówienie #{{ $zamowienie->id }}</h2>
                    @if($orderAdminClosedLabel)
                        <span class="badge {{ $orderAdminClosedBadgeClass }} fs-6 ms-2 align-middle" title="{{ $orderAdminClosedTitle }}">
                            <i class="bi {{ $orderAdminClosedIcon }}"></i> {{ $orderAdminClosedLabel }}
                        </span>
                    @endif
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 justify-content-end">
                        <li class="breadcrumb-item">
                            <a href="{{ route('form-orders.index') }}">Zamówienia</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Zamówienie #{{ $zamowienie->id }}
                        </li>
                    </ol>
                </nav>
            </div>

            {{-- Przyciski akcji --}}
            <div class="d-flex justify-content-end align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    @php
                        $navFilterQuery = array_filter([
                            'filter_no_participant' => (!empty($filterNoParticipant) || request()->boolean('filter_no_participant')) ? '1' : null,
                            'filter_no_invoice' => (!empty($filterNoInvoice) || request()->boolean('filter_no_invoice') || (request()->boolean('filter_new') && ! request()->has('filter_no_participant') && ! request()->has('filter_no_invoice'))) ? '1' : null,
                            'filter_no_ksef' => (!empty($filterNoKsef) || request()->boolean('filter_no_ksef')) ? '1' : null,
                            'filter_payment_gateway' => (!empty($filterPaymentGateway) || request()->boolean('filter_payment_gateway')) ? '1' : null,
                            'course_id' => request('course_id') ?: null,
                        ]);
                    @endphp
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterNoParticipantOnly"
                               {{ !empty($navFilterQuery['filter_no_participant']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterNoParticipantOnly">
                            <i class="bi bi-funnel"></i> bez wprowadzonego uczestnika
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterNoInvoiceOnly"
                               {{ !empty($navFilterQuery['filter_no_invoice']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterNoInvoiceOnly">
                            <i class="bi bi-funnel"></i> bez wystawionej faktury
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterNoKsefOnly"
                               {{ !empty($navFilterQuery['filter_no_ksef']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterNoKsefOnly" title="Nabywca z NIP, jest numer FV, brak NumerKSeF">
                            <i class="bi bi-funnel"></i> Tylko z NIP bez KSeF
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterPaymentGatewayOnly"
                               {{ !empty($navFilterQuery['filter_payment_gateway']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterPaymentGatewayOnly" title="payment_mode=online_gateway; bez anulowanych; bez FV odroczonej">
                            <i class="bi bi-funnel"></i> bramka płatności
                        </label>
                    </div>
                    
                    {{-- Pole input do filtrowania po courses.id (Poprzednie/Następne) --}}
                    <div class="input-group" style="width: 200px;">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               id="courseIdFilter" 
                               placeholder="ID szkolenia courses"
                               value="{{ request('course_id') }}"
                               title="Wprowadź ID szkolenia (courses.id) do filtrowania zamówień przy Poprzednie/Następne">
                    </div>
                    <span id="navigationFilterCountBadge"
                          class="badge text-bg-secondary align-self-center"
                          style="min-width: 2.25rem;"
                          title="Liczba zamówień według aktywnych filtrów (ładowane po stronie)"
                          data-count-url="{{ route('form-orders.navigation-filter-count') }}">…</span>

                    <div class="btn-group me-2" role="group">
                        <a href="{{ $prevOrder ? route('form-orders.show', array_merge(['id' => $prevOrder->id], $navFilterQuery)) : '#' }}" 
                           class="btn {{ $prevOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                           title="{{ $prevOrder ? 'Poprzednie zamówienie' : 'Brak poprzedniego zamówienia' }}"
                           @if(!$prevOrder) onclick="return false;" @endif
                           id="prevOrderBtn">
                            <i class="bi bi-chevron-left"></i> Poprzednie
                        </a>
                        <a href="{{ route('form-orders.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-list"></i> Lista
                        </a>
                        <a href="{{ $nextOrder ? route('form-orders.show', array_merge(['id' => $nextOrder->id], $navFilterQuery)) : '#' }}" 
                           class="btn {{ $nextOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                           title="{{ $nextOrder ? 'Następne zamówienie' : 'Brak następnego zamówienia' }}"
                           @if(!$nextOrder) onclick="return false;" @endif
                           id="nextOrderBtn">
                            Następne <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Atrybucja marketingowa — pełna szerokość pod paskiem filtrów / nawigacji --}}
            <div class="w-100 mb-4">
                @include('form-orders.partials.marketing-attribution', [
                    'zamowienie' => $zamowienie,
                    'variant' => 'subtle',
                ])
            </div>

            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(($duplicateSiblingsCount ?? 0) > 0)
                <div class="rounded-3 px-3 py-3 mb-4 bg-danger text-white d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 shadow-sm" role="alert">
                    <div class="flex-grow-1">
                        <i class="bi bi-files"></i>
                        <strong>Duplikaty.</strong>
                        To zamówienie jest w grupie duplikatów (ten sam e-mail głównego uczestnika i to samo szkolenie w panelu).
                        @if($duplicateSiblingsCount === 1)
                            Jest jeszcze <strong>jedno</strong> powiązane zamówienie.
                        @else
                            Są jeszcze <strong>{{ $duplicateSiblingsCount }}</strong> powiązane zamówienia.
                        @endif
                    </div>
                    <a href="{{ route('form-orders.duplicates') }}" class="btn btn-sm btn-light text-dark text-nowrap align-self-md-center">
                        <i class="bi bi-ui-checks-grid"></i> Zarządzanie duplikatami
                    </a>
                </div>
            @endif

            {{-- SZKOLENIE - kompaktowe --}}
            <div class="card mb-3">
                <div class="card-header {{ $orderAdminClosedLabel ? 'bg-secondary' : 'bg-primary' }} text-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event"></i>
                        @if($zamowienie->course)
                            <a href="{{ route('courses.show', $zamowienie->course->id) }}"
                               class="link-light link-underline-opacity-25 link-underline-opacity-100-hover"
                               title="Przejdź do szczegółów szkolenia">
                                {{ $zamowienie->display_product_name }}
                            </a>
                        @else
                            {{ $zamowienie->display_product_name }}
                        @endif
                        @if($zamowienie->product_price)
                            <span class="badge bg-success ms-2 fs-6">
                                {{ number_format($zamowienie->product_price, 2) }} PLN
                            </span>
                        @endif
                        @if($zamowienie->activeDebtCases->isNotEmpty())
                            @php
                                $headerDebtCase = $zamowienie->activeDebtCases->first();
                            @endphp
                            <a href="{{ route('accounting.collections.show', $headerDebtCase) }}"
                               class="badge bg-danger ms-2 fs-6 text-decoration-none"
                               title="Aktywna sprawa windykacyjna: {{ $headerDebtCase->statusLabel() }}">
                                <i class="bi bi-exclamation-octagon"></i> Windykacja
                            </a>
                            @if($headerDebtCase->isVip())
                                <span class="badge bg-warning text-dark ms-1 fs-6" title="{{ $headerDebtCase->vip_reason ?: 'VIP / ważny klient' }}">VIP</span>
                            @endif
                        @endif
                    </h5>
                    @php
                        $headerHasCourseMeta = (bool) $zamowienie->course;
                        $headerHasProductFallback = ! $headerHasCourseMeta && filled($zamowienie->product_id);
                        $headerHasPubligoFallback = ! $headerHasCourseMeta && ! $headerHasProductFallback && filled($zamowienie->publigo_product_id);
                        $headerHasPriceVariant = (bool) $zamowienie->coursePriceVariant;
                        $headerHasOrphanPriceVariant = ! $headerHasPriceVariant && filled($zamowienie->course_price_variant_id);
                        $headerHasMetaRow = $headerHasCourseMeta || $headerHasProductFallback || $headerHasPubligoFallback || $headerHasPriceVariant || $headerHasOrphanPriceVariant;
                    @endphp
                    @if($headerHasMetaRow)
                        <div class="small text-white-50 mt-2 mb-0">
                            @if($headerHasCourseMeta)
                                @php
                                    $courseDateTime = $zamowienie->course->start_date
                                        ? $zamowienie->course->start_date->setTimezone(config('app.timezone', 'Europe/Warsaw'))->format('d.m.Y H:i')
                                        : 'brak daty';
                                    $courseInstructor = trim(($zamowienie->course->instructor->first_name ?? '').' '.($zamowienie->course->instructor->last_name ?? ''));
                                    $courseInstructor = $courseInstructor !== '' ? $courseInstructor : 'brak prowadzącego';
                                @endphp
                                Data i godzina szkolenia: <span class="fw-semibold text-white">{{ $courseDateTime }}</span>
                                <span class="ms-1">·</span> prowadzący: <span class="fw-semibold text-white">{{ $courseInstructor }}</span>
                                <span class="ms-1">·</span> ID szkolenia (courses):
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 align-baseline fw-semibold text-white text-decoration-underline"
                                        style="font-size: inherit; line-height: inherit;"
                                        title="Wstaw {{ $zamowienie->course->id }} do filtra „ID szkolenia courses” (Poprzednie/Następne)"
                                        onclick="fillCourseIdFilter({{ (int) $zamowienie->course->id }})">
                                    {{ $zamowienie->course->id }}
                                </button>
                                @if($zamowienie->course->source_id_old === 'certgen_Publigo' && filled($zamowienie->course->id_old))
                                    <span class="ms-1">·</span> Publigo ID: <span class="fw-semibold text-white">{{ $zamowienie->course->id_old }}</span>
                                @endif
                            @elseif($headerHasProductFallback)
                                <span class="text-warning">Brak rekordu courses</span> dla <code class="text-white">product_id</code> =
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 align-baseline text-white text-decoration-underline"
                                        style="font-size: inherit; line-height: inherit;"
                                        title="Wstaw {{ $zamowienie->product_id }} do filtra „ID szkolenia courses”"
                                        onclick="fillCourseIdFilter({{ (int) $zamowienie->product_id }})">
                                    {{ $zamowienie->product_id }}
                                </button>
                            @elseif($headerHasPubligoFallback)
                                Publigo ID: <span class="fw-semibold text-white">{{ $zamowienie->publigo_product_id }}</span>
                                <span class="ms-1">(brak powiązania z courses)</span>
                            @endif
                            @if($headerHasPriceVariant)
                                @php
                                    $priceVariant = $zamowienie->coursePriceVariant;
                                    $variantLabel = filled($priceVariant->name) ? $priceVariant->name : 'Wariant #'.$priceVariant->id;
                                @endphp
                                @if($headerHasCourseMeta || $headerHasProductFallback || $headerHasPubligoFallback)
                                    <span class="ms-1">·</span>
                                @endif
                                Wariant cenowy:
                                <span class="fw-semibold text-white">{{ $variantLabel }}</span>
                                <span class="text-white-50">(ID {{ $priceVariant->id }})</span>
                                @if($priceVariant->trashed())
                                    <span class="badge bg-secondary ms-1">usunięty</span>
                                @endif
                                <a href="{{ route('courses.price-variants.edit', ['courseId' => $priceVariant->course_id, 'id' => $priceVariant->id]) }}"
                                   class="link-light link-underline-opacity-50 link-underline-opacity-100-hover ms-1"
                                   target="_blank" rel="noopener">edycja wariantu</a>
                            @elseif($headerHasOrphanPriceVariant)
                                @if($headerHasCourseMeta || $headerHasProductFallback || $headerHasPubligoFallback)
                                    <span class="ms-1">·</span>
                                @endif
                                <span class="text-warning">Wariant cenowy: zapisane ID {{ $zamowienie->course_price_variant_id }} — brak rekordu w bazie (np. twardo usunięty).</span>
                            @endif
                        </div>
                    @endif
                </div>
                @if($zamowienie->payment_mode || $orderAdminClosedLabel)
                    <div class="card-body py-2 border-top {{ $orderAdminClosedLabel ? 'border-secondary' : 'border-primary' }} border-opacity-25 bg-white bg-opacity-75 d-flex flex-wrap align-items-center gap-2">
                        <div>
                            @if($zamowienie->payment_mode)
                                <span class="text-muted small me-2">Rozliczenie:</span>
                                <span class="badge bg-{{ $zamowienie->paymentModeBadgeClass() }} fs-6">{{ $zamowienie->paymentModeLabelWithGateway() }}</span>
                                <span class="badge bg-{{ $zamowienie->paymentStatusBadgeClass() }} fs-6 ms-1">{{ \App\Models\FormOrder::paymentStatusLabel($zamowienie->payment_status) }}</span>
                                @include('form-orders.partials.order-form-variant-badge', ['zamowienie' => $zamowienie])
                                @if($zamowienie->isAbandonedUnpaidOnline())
                                    <span class="badge bg-danger fs-6 ms-1"
                                          title="Porzucona płatność online (failed/cancelled lub awaiting ≥ {{ (int) config('form_orders.online_abandonment_minutes', 60) }} min)">
                                        <i class="bi bi-credit-card-2-front"></i> Porzucona płatność
                                    </span>
                                @endif
                            @endif
                            @if($zamowienie->isEligibleForOnlinePaymentRecoveryEmail())
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary ms-2"
                                        id="sendOnlinePaymentRecoveryBtn"
                                        data-order-id="{{ $zamowienie->id }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#sendOnlinePaymentRecoveryModal">
                                    <i class="bi bi-envelope"></i> Wyślij mail recovery płatności
                                </button>
                                @if($zamowienie->online_payment_recovery_sent_at)
                                    <span class="text-muted small ms-1" title="Ostatnia wysyłka recovery e-mail">
                                        (recovery: {{ $zamowienie->online_payment_recovery_sent_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }})
                                    </span>
                                @endif
                            @endif
                        </div>
                        @if($orderAdminClosedLabel)
                            <span class="badge {{ $orderAdminClosedBadgeClass }} fs-6 ms-auto" title="{{ $orderAdminClosedTitle }}">
                                <i class="bi {{ $orderAdminClosedIcon }}"></i> {{ $orderAdminClosedLabel }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Dane kontaktowe w dwóch kolumnach --}}
            <div class="row">
                {{-- Lewa kolumna: Dane do faktury, Uczestnik --}}
                <div class="col-md-6">
                    @if($zamowienie->invoice_notes || $zamowienie->invoice_payment_delay)
                        <div class="card mb-3 @if(filled(trim((string) $zamowienie->invoice_notes))) invoice-notes-panel--attention @endif"
                             id="invoiceNotesInfoCard">
                            <div class="card-header bg-warning text-dark py-2">
                                <h6 class="mb-0 d-flex align-items-center gap-2 flex-wrap">
                                    <span>
                                        <i class="bi bi-receipt"></i> INFORMACJE O FAKTURZE
                                    </span>
                                    @if(filled(trim((string) $zamowienie->invoice_notes)))
                                        <span class="invoice-notes-attention-icon"
                                              title="Zamawiający dodał uwagi do faktury"
                                              aria-label="Zamawiający dodał uwagi do faktury">
                                            <i class="bi bi-chat-left-text-fill" aria-hidden="true"></i>
                                        </span>
                                    @endif
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                @if($zamowienie->invoice_notes)
                                    <div class="mb-1">
                                        <small class="text-muted d-block mb-1">Uwagi zamawiającego (sprawdź przed wystawieniem FV):</small>
                                        <small class="text-danger invoice-notes-customer-text" style="white-space: pre-line;">{{ trim($zamowienie->invoice_notes) }}</small>
                                    </div>
                                @endif
                                @if($zamowienie->invoice_payment_delay)
                                    <div class="mb-1">
                                        <small class="text-danger">
                                            <strong>Odroczenie:</strong>
                                            <span class="badge bg-danger">{{ $zamowienie->invoice_payment_delay }} dni</span>
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- DANE DO FAKTURY - kompaktowe --}}
                    <div class="card mb-3">
                        <div class="card-header bg-dark text-white py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-file-text"></i> DANE DO FAKTURY
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row g-2 mb-2">
                                {{-- NABYWCA --}}
                                <div class="col-md-6">
                                    <div class="border rounded p-2 bg-light h-100" style="font-family: monospace; white-space: pre-line; user-select: text;"><strong>NABYWCA:</strong>
{{ $zamowienie->buyer_name ?? '—' }}
{{ $zamowienie->buyer_address ?? '—' }}
{{ $zamowienie->buyer_postal_code ?? '—' }} {{ $zamowienie->buyer_city ?? '—' }}
@if($zamowienie->buyer_nip)NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip) }}@endif</div>
                                </div>

                                {{-- ODBIORCA --}}
                                <div class="col-md-6">
                                    <div class="border rounded p-2 bg-light h-100" style="font-family: monospace; white-space: pre-line; user-select: text;"><strong>ODBIORCA:</strong>
{{ $zamowienie->recipient_name ?? '—' }}
{{ $zamowienie->recipient_address ?? '—' }}
{{ $zamowienie->recipient_postal_code ?? '—' }} {{ $zamowienie->recipient_city ?? '—' }}
@if($zamowienie->recipient_nip)NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->recipient_nip) }}
@endif
nowoczesna-edukacja.pl </div>
                                </div>
                            </div>

                            {{-- Przyciski kopiowania --}}
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyOdbiorcaData()">
                                    <i class="bi bi-clipboard"></i> ODBIORCA
                                </button>
                                @if($zamowienie->buyer_nip)
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyNipNabywcy()">
                                        <i class="bi bi-clipboard"></i> NIP NABYWCY
                                    </button>
                                @endif
                            </div>

                            {{-- KSeF Podmiot3: edycja (AJAX) w karcie DANE DO FAKTURY --}}
                            @include('form-orders.partials.ksef-additional-entity-inline', ['zamowienie' => $zamowienie])
                        </div>
                    </div>

                    @include('form-orders.partials.participants-cards', ['zamowienie' => $zamowienie])

                    @if($zamowienie->orderer_name || $zamowienie->orderer_phone || $zamowienie->orderer_email)
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white py-2">
                                <h6 class="mb-0">
                                    <i class="bi bi-person"></i> ZAMAWIAJĄCY
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                @if($zamowienie->orderer_name || $zamowienie->orderer_phone)
                                    <div class="mb-2">
                                        <small>
                                            <i class="bi bi-telephone"></i>
                                            <strong>KONTAKT</strong>
                                            @if($zamowienie->orderer_name)
                                                <span class="text-muted"> - {{ $zamowienie->orderer_name }}</span>
                                            @endif
                                            @if($zamowienie->orderer_phone)
                                                <br>
                                                <span class="fs-5 fw-semibold">
                                                    <strong>tel.</strong>
                                                    <a href="tel:{{ $zamowienie->orderer_phone }}" class="text-decoration-none">
                                                        @php
                                                            $phone = preg_replace('/[^0-9]/', '', $zamowienie->orderer_phone);
                                                            if (strlen($phone) == 9) {
                                                                echo '+48 ' . substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 3);
                                                            } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '48') {
                                                                echo '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8, 3);
                                                            } elseif (strlen($phone) >= 10 && strlen($phone) <= 15) {
                                                                $formatted = '+' . $phone;
                                                                $formatted = preg_replace('/(\d{3})(?=\d)/', '$1 ', $formatted);
                                                                echo $formatted;
                                                            } else {
                                                                echo $zamowienie->orderer_phone;
                                                            }
                                                        @endphp
                                                    </a>
                                                </span>
                                            @endif
                                        </small>
                                    </div>
                                @endif
                                @if($zamowienie->orderer_email)
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>
                                            <strong>Fakturę przesłać na:</strong>
                                            <br>
                                            <a href="mailto:{{ $zamowienie->orderer_email }}"
                                               class="fs-6 text-decoration-none @if($zamowienie->display_participant_email == $zamowienie->orderer_email) bg-warning bg-opacity-25 px-1 rounded @endif"
                                               @if($zamowienie->display_participant_email == $zamowienie->orderer_email) title="Ten sam email co uczestnika" @endif>
                                                <i class="bi bi-envelope"></i> {{ $zamowienie->orderer_email }}
                                            </a>
                                        </small>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="copyEmailFaktury()">
                                            <i class="bi bi-clipboard"></i> Email faktury
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($zamowienie->order_date || $zamowienie->ip_address || $zamowienie->submission_source === \App\Models\FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM)
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white py-2">
                                <h6 class="mb-0">
                                    <i class="bi bi-info-circle"></i> DODATKOWE INFORMACJE
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                @if($zamowienie->submission_source === \App\Models\FormOrder::SUBMISSION_SOURCE_PNEDU_ORDER_FORM || filled($zamowienie->order_form_variant))
                                    <div class="mb-1">
                                        <small>
                                            <strong>Wersja formularza:</strong>
                                            <span class="badge bg-{{ $zamowienie->orderFormVariantBadgeClass() }} ms-1">
                                                {{ $zamowienie->orderFormVariantAdminLabel() }}
                                            </span>
                                        </small>
                                    </div>
                                @endif
                                @php
                                    $orderDateFormatted = $zamowienie->formatOrderDateLocal();
                                @endphp
                                @if($orderDateFormatted)
                                    <div class="mb-1">
                                        <small>
                                            <strong>Data zamówienia:</strong> {{ $orderDateFormatted }}
                                        </small>
                                    </div>
                                @endif
                                @if($zamowienie->ip_address)
                                    <div class="mb-1">
                                        <small>
                                            <strong>IP:</strong> {{ $zamowienie->ip_address }}
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Prawa kolumna: Status, Faktura --}}
                <div class="col-md-6">
                    <div id="operationalStatusPanel">
                        @include('form-orders.partials.operational-status-panel', [
                            'zamowienie' => $zamowienie,
                        ])
                    </div>

                    <div class="card mb-3" id="ifirmaIssueInvoiceCard">
                        <div class="card-header bg-warning text-dark py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-file-earmark-plus"></i> WYSTAW FAKTURĘ iFIRMA
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check" style="font-size: 0.875rem;">
                                    <input class="form-check-input" type="checkbox" value="1"
                                           id="ifirma_prefix_szkolenie_in_product_name" checked>
                                    <label class="form-check-label" for="ifirma_prefix_szkolenie_in_product_name">
                                        Dodaj <strong>„SZKOLENIE:”</strong> na początku nazwy towaru lub usługi na fakturze (API iFirma)
                                    </label>
                                </div>

                                <div class="w-100">
                                    <button type="button" class="btn w-100" id="ifirmaInvoiceWithKsefBtn"
                                            style="background-color: #dc3545; border-color: #dc3545; color: white;"
                                            onclick="checkAndCreateInvoiceWithKsef({{ $zamowienie->id }})">
                                        <i class="bi bi-file-earmark-check"></i> Wystaw Fakturę iFirma z Odbiorcą i prześlij do KSeF
                                    </button>
                                    <div class="form-check mt-1" style="font-size: 0.875rem;">
                                        <input class="form-check-input" type="checkbox" id="sendEmailCheckboxInvoiceWithKsef">
                                        <label class="form-check-label text-muted" for="sendEmailCheckboxInvoiceWithKsef">
                                            <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                            @if(!empty($zamowienie->orderer_email))
                                                <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->display_participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->display_participant_email)), {{ strtolower($zamowienie->display_participant_email) }}@endif)</small>
                                            @endif
                                        </label>
                                    </div>
                                </div>

                                <div class="w-100">
                                    <button type="button" class="btn btn-primary w-100" id="ifirmaInvoiceBtn"
                                            onclick="checkAndCreateInvoice({{ $zamowienie->id }})">
                                        <i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma
                                    </button>
                                    <div class="form-check mt-1" style="font-size: 0.875rem;">
                                        <input class="form-check-input" type="checkbox" id="sendEmailCheckboxInvoice">
                                        <label class="form-check-label text-muted" for="sendEmailCheckboxInvoice">
                                            <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                            @if(!empty($zamowienie->orderer_email))
                                                <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->display_participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->display_participant_email)), {{ strtolower($zamowienie->display_participant_email) }}@endif)</small>
                                            @endif
                                        </label>
                                    </div>
                                </div>

                                <div class="w-100">
                                    <button type="button" class="btn w-100" id="ifirmaInvoiceWithReceiverBtn"
                                            style="background-color: #6f42c1; border-color: #6f42c1; color: white;"
                                            onclick="checkAndCreateInvoiceWithReceiver({{ $zamowienie->id }})">
                                        <i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma z Odbiorcą
                                    </button>
                                    <div class="form-check mt-1" style="font-size: 0.875rem;">
                                        <input class="form-check-input" type="checkbox" id="sendEmailCheckboxInvoiceWithReceiver">
                                        <label class="form-check-label text-muted" for="sendEmailCheckboxInvoiceWithReceiver">
                                            <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                            @if(!empty($zamowienie->orderer_email))
                                                <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->display_participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->display_participant_email)), {{ strtolower($zamowienie->display_participant_email) }}@endif)</small>
                                            @endif
                                        </label>
                                    </div>
                                </div>

                                <div class="w-100">
                                    <button type="button" class="btn btn-success w-100" id="ifirmaProFormaBtn" onclick="createIfirmaProForma({{ $zamowienie->id }})">
                                        <i class="bi bi-receipt"></i> Wystaw PRO-FORMA iFirma
                                    </button>
                                    <div class="form-check mt-1" style="font-size: 0.875rem;">
                                        <input class="form-check-input" type="checkbox" id="sendEmailCheckboxProforma">
                                        <label class="form-check-label text-muted" for="sendEmailCheckboxProforma">
                                            <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                            @if(!empty($zamowienie->orderer_email))
                                                <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->display_participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->display_participant_email)), {{ strtolower($zamowienie->display_participant_email) }}@endif)</small>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="ifirmaResult" class="mt-2"></div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-envelope-paper"></i> FAKTURA
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="border-bottom pb-2 mb-3">
                                <div class="small text-muted fw-semibold mb-2">
                                    <i class="bi bi-exclamation-octagon"></i> Windykacja
                                </div>
                                @if($zamowienie->activeDebtCases->isNotEmpty())
                                    @php
                                        $debtCase = $zamowienie->activeDebtCases->first();
                                    @endphp
                                    <div class="alert alert-danger py-2 mb-2">
                                        <div class="fw-semibold">Aktywna sprawa windykacyjna #{{ $debtCase->id }}</div>
                                        <div class="small">
                                            Status: {{ $debtCase->statusLabel() }} · Segment: {{ $debtCase->segmentLabel() }}
                                            @if($debtCase->isVip())
                                                · <span class="fw-semibold">VIP / delikatna obsługa</span>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('accounting.collections.show', $debtCase) }}" class="btn btn-sm btn-outline-danger">
                                        Otwórz sprawę windykacyjną
                                    </a>
                                @else
                                    <p class="small text-muted mb-2">
                                        Brak aktywnej sprawy. Utwórz ją, jeśli faktura wymaga ponaglenia lub ręcznej weryfikacji płatności.
                                    </p>
                                    @if($zamowienie->hasIssuedInvoice())
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#createDebtCaseModal">
                                            <i class="bi bi-plus-circle"></i> Utwórz sprawę windykacyjną
                                        </button>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                disabled
                                                title="Najpierw wystaw fakturę">
                                            <i class="bi bi-plus-circle"></i> Utwórz sprawę windykacyjną
                                        </button>
                                        <div class="small text-muted mt-1">
                                            Najpierw wystaw fakturę — sprawy windykacyjnej nie tworzy się bez FV.
                                        </div>
                                    @endif
                                @endif
                            </div>
                            {{-- Formularz edycji - kompaktowy --}}
                            <form action="{{ route('form-orders.update', $zamowienie->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                {{-- Ukryte pole informujące że formularz jest ze strony szczegółów --}}
                                <input type="hidden" name="from_show_page" value="1">
                                {{-- Przekazujemy parametry filtrów nawigacji --}}
                                @foreach($navFilterQuery as $navFilterKey => $navFilterValue)
                                    <input type="hidden" name="{{ $navFilterKey }}" value="{{ $navFilterValue }}">
                                @endforeach
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="invoice_number" class="form-label small">
                                            <strong>Numer faktury:</strong>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control @if($zamowienie->is_new) border-danger bg-danger bg-opacity-10 @endif"
                                                   id="invoice_number" name="invoice_number"
                                                   value="{{ $zamowienie->invoice_number }}"
                                                   placeholder="Wprowadź numer faktury"
                                                   @if($zamowienie->is_new)
                                                   style="border-width: 2px; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);"
                                                   @endif>
                                            <button type="button"
                                                    class="btn btn-outline-secondary"
                                                    id="syncIfirmaByInvoiceNumberBtn"
                                                    title="Pobierz z iFirma: ID dokumentu, daty FV i numer KSeF na podstawie numeru faktury. Gdy pole numeru jest puste — wyczyść ID iFirma i numer KSeF w tym zamówieniu."
                                                    aria-label="Synchronizuj dane FV z iFirma po numerze faktury">
                                                <i class="bi bi-arrow-repeat" id="syncIfirmaByInvoiceNumberIcon"></i>
                                            </button>
                                        </div>
                                        <div id="ifirmaInvoiceIdDisplay"
                                             class="mt-1 small @if(! $zamowienie->hasIfirmaInvoiceId()) d-none @endif"
                                             title="Wewnętrzny Identyfikator dokumentu w iFirma (nie numer FV)">
                                            <span class="text-muted">ID iFirma:</span>
                                            <code class="text-secondary" id="ifirmaInvoiceIdValue">{{ $zamowienie->hasIfirmaInvoiceId() ? $zamowienie->ifirma_invoice_id : '' }}</code>
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 ms-1 align-baseline text-secondary"
                                                    id="syncIfirmaByIdBtn"
                                                    title="Pobierz z iFirma po ID dokumentu: numer FV, daty i Numer KSeF"
                                                    aria-label="Synchronizuj dane FV z iFirma po ID dokumentu">
                                                <i class="bi bi-arrow-repeat" id="syncIfirmaByIdIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="status_completed" class="form-label small">
                                            <strong>Status (legacy):</strong>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="status_completed" name="status_completed" value="1"
                                                   {{ $zamowienie->status_completed == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label small text-muted" for="status_completed">
                                                Zakończone — pole historyczne; do anulowania użyj „Anuluj zamówienie”
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div id="ksefNumberDisplay"
                                             class="small border rounded px-2 py-1 bg-light @if(! $zamowienie->hasConfirmedKsef() && ! $zamowienie->hasIfirmaInvoiceId() && blank($zamowienie->invoice_number)) d-none @endif"
                                             @if($zamowienie->hasConfirmedKsef())
                                             title="Przyjęte w KSeF{{ $zamowienie->ksef_sent_at ? ': '.$zamowienie->ksef_sent_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '' }}"
                                             @else
                                             title="Synchronizuj z iFirma po numerze FV lub ID dokumentu"
                                             @endif>
                                            <span class="text-muted">Numer KSeF:</span>
                                            <code class="text-success text-break" id="ksefNumberValue">@if($zamowienie->hasConfirmedKsef()){{ $zamowienie->ksef_number }}@else<span class="text-muted">—</span>@endif</code>
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 ms-1 align-baseline text-secondary"
                                                    id="syncIfirmaKsefBtn"
                                                    title="Pobierz ID iFirma, daty FV i numer KSeF z iFirma (po numerze FV lub ID)"
                                                    aria-label="Synchronizuj dane FV i KSeF z iFirma">
                                                <i class="bi bi-arrow-repeat" id="syncIfirmaKsefIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="invoiceDatesDisplay"
                                         class="col-12 mt-2 @if(! $zamowienie->invoice_issue_date && ! $zamowienie->invoice_due_date && ! $zamowienie->hasIfirmaInvoiceId() && blank($zamowienie->invoice_number)) d-none @endif">
                                        <div class="small border rounded px-2 py-1 bg-light">
                                            <span class="text-muted">Data wystawienia FV:</span>
                                            <strong id="invoiceIssueDateValue">{{ $zamowienie->invoice_issue_date?->format('d.m.Y') ?: '—' }}</strong>
                                            <span class="mx-1 text-muted">·</span>
                                            <span class="text-muted">Termin płatności:</span>
                                            <strong id="invoiceDueDateValue">{{ $zamowienie->invoice_due_date?->format('d.m.Y') ?: '—' }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label for="notes" class="form-label small">
                                        <strong>Notatki:</strong>
                                    </label>
                                    <textarea class="form-control form-control-sm" id="notes" name="notes" rows="1" 
                                              placeholder="Dodaj notatki">{{ $zamowienie->notes }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-save"></i> Zapisz
                                </button>
                            </form>

                            {{-- UWAGI DO FAKTURY (edytowalne dla API iFirma) --}}
                            <div class="card mt-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0">
                                        <i class="bi bi-pencil-square"></i> UWAGI DO FAKTURY (dla API iFirma)
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    @php
                                        // Prefiks liczony od LICZBY REKORDÓW uczestników, nie od liczby niepustych
                                        // imion — przy 2 wierszach w bazie zawsze "UCZESTNICY:", nawet gdy
                                        // drugi ma jeszcze puste dane (wcześniej filter() dawał count=1 → błędnie UCZESTNIK).
                                        $invoiceParticipantModels = $zamowienie->participants()
                                            ->orderBy('id')
                                            ->get();
                                        $invoiceParticipantRowsCount = $invoiceParticipantModels->count();
                                        $invoiceParticipantLabels = $invoiceParticipantModels->map(function ($p) {
                                            $n = trim((string) $p->full_name);

                                            return $n !== '' ? $n : ('uczestnik #'.$p->id);
                                        })->all();
                                        $invoiceParticipantsCsv = implode(', ', $invoiceParticipantLabels);
                                        $invoiceParticipantsPrefix = $invoiceParticipantRowsCount > 1 ? 'UCZESTNICY:' : 'UCZESTNIK:';
                                    @endphp
                                    <div class="mb-2">
                                        <label for="invoice_api_remarks" class="form-label small mb-1">
                                            <strong>Uwagi, które pojawią się na fakturze:</strong>
                                            <br>
                                            <small class="text-muted">Możesz edytować ten tekst przed wystawieniem faktury</small>
                                        </label>
                                        <textarea class="form-control form-control-sm" 
                                                  id="invoice_api_remarks" 
                                                  rows="4" 
                                                  placeholder="Wpisz uwagi do faktury..."
                                                  style="font-family: monospace; font-size: 12px;"></textarea>
                                        <div class="form-check mt-2 mb-1">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="ifirma_include_participant_in_remarks"
                                                   data-participants-prefix="{{ $invoiceParticipantsPrefix }}"
                                                   data-participants-names="{{ $invoiceParticipantsCsv }}"
                                                   {{ $invoiceParticipantRowsCount === 0 ? 'disabled' : 'checked' }}>
                                            <label class="form-check-label small" for="ifirma_include_participant_in_remarks">
                                                Dodaj w uwagach faktury <strong>UCZESTNIKÓW</strong>
                                                @if($invoiceParticipantRowsCount > 0)
                                                    <span class="text-muted">(„{{ $invoiceParticipantsPrefix }} {{ $invoiceParticipantsCsv }}")</span>
                                                @else
                                                    <span class="text-muted">(brak danych uczestników)</span>
                                                @endif
                                            </label>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> Ten tekst zostanie użyty jako "Uwagi" na fakturze. 
                                            <strong>Na końcu automatycznie dodamy: "pnedu.pl #{{ $zamowienie->id }}"</strong>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($pneduOrderFormEditUrl))
                <div class="alert alert-light border mt-4 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong><i class="bi bi-link-45deg"></i> Formularz zamówienia na PNEDU</strong>
                            <span class="text-muted small d-block">Wyślij dyrektorowi do uzupełnienia lub edycji (ident: <code>{{ $zamowienie->ident }}</code>)</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="input-group" style="max-width: 36rem;">
                                <input type="text" class="form-control form-control-sm font-monospace" id="pnedu-order-form-edit-url" value="{{ $pneduOrderFormEditUrl }}" readonly>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="navigator.clipboard.writeText(document.getElementById('pnedu-order-form-edit-url').value); this.textContent='Skopiowano!'; setTimeout(() => this.textContent='Kopiuj link', 2000);">
                                    Kopiuj link
                                </button>
                                <a href="{{ $pneduOrderFormEditUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right"></i> Otwórz
                                </a>
                            </div>
                            <a href="{{ route('form-orders.pdf', $zamowienie->id) }}"
                               class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-file-pdf"></i> Pobierz PDF zamówienia
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Przyciski akcji na dole strony --}}
            <div class="d-flex justify-content-end mb-4 {{ empty($pneduOrderFormEditUrl) ? 'mt-4' : '' }}">
                <div class="btn-group" role="group">
                    <a href="{{ route('form-orders.create', ['clone_from' => $zamowienie->id]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-files"></i> Kopiuj zamówienie
                    </a>
                    <a href="{{ route('form-orders.edit', array_merge(['id' => $zamowienie->id], $navFilterQuery)) }}" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edytuj
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Usuń
                    </button>
                </div>
            </div>

            @if($formOrderPaymentHighlightClass)
                </div>
            @endif
        </div>
    </div>

    {{-- JavaScript do kopiowania danych --}}
    <script>
        function copyOdbiorcaData() {
            const odbiorcaData = `ODBIORCA:
{{ $zamowienie->recipient_name ?? '—' }}
{{ $zamowienie->recipient_address ?? '—' }}
{{ $zamowienie->recipient_postal_code ?? '—' }} {{ $zamowienie->recipient_city ?? '—' }}
@if($zamowienie->recipient_nip)NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->recipient_nip) }}
@endif
nowoczesna-edukacja.pl `;
            copyToClipboard(odbiorcaData, 'copyOdbiorcaData');
        }

        function copyNipNabywcy() {
            const nipData = `{{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip) }}`;
            copyToClipboard(nipData, 'copyNipNabywcy');
        }

        function copyUczestnikData() {
            const uczestnikData = `{{ $zamowienie->display_participant_name ?? '—' }}`;
            copyToClipboard(uczestnikData, 'copyUczestnikData');
        }

        function copyEmailUczestnika() {
            const emailData = `{{ $zamowienie->display_participant_email ?? '—' }}`;
            copyToClipboard(emailData, 'copyEmailUczestnika');
        }

        function copyEmailFaktury() {
            const emailData = `{{ $zamowienie->orderer_email ?? '—' }}`;
            copyToClipboard(emailData, 'copyEmailFaktury');
        }

        function copyToClipboard(text, functionName) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess(functionName);
                }).catch(function(err) {
                    console.error('Błąd kopiowania: ', err);
                    fallbackCopyTextToClipboard(text, functionName);
                });
            } else {
                fallbackCopyTextToClipboard(text, functionName);
            }
        }

        function showCopySuccess(functionName) {
            const button = document.querySelector(`button[onclick="${functionName}()"]`);
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> OK!';
                
                // Zapisz oryginalne klasy
                const originalClasses = button.className;
                button.className = 'btn btn-success btn-sm';
                
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.className = originalClasses;
                }, 1500);
            }
        }

        function fallbackCopyTextToClipboard(text, functionName) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(functionName);
                } else {
                    alert('Nie udało się skopiować danych. Spróbuj zaznaczyć i skopiować ręcznie.');
                }
            } catch (err) {
                console.error('Fallback: Nie udało się skopiować', err);
                alert('Nie udało się skopiować danych. Spróbuj zaznaczyć i skopiować ręcznie.');
            }
            
            document.body.removeChild(textArea);
        }

        // Funkcja do tworzenia zamówienia w Publigo
        function createPubligoOrder(orderId) {
            const button = document.getElementById('publigoOrderBtn');
            const resultDiv = document.getElementById('publigoResult');
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Wysłanie zapytania AJAX
            fetch(`/form-orders/${orderId}/publigo/create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sukces
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sukces!</strong> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showPubligoDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.publigoResponseData = data;
                } else {
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.publigo_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showPubligoDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.publigoResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd połączenia:</strong> Wystąpił błąd podczas komunikacji z serwerem.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-plus-circle"></i> Dodaj zamówienie przez PUBLIGO';
            });
        }

        // Dźwięki UI: sukces / porażka FV / porażka KSeF (Web Audio — bez plików mp3)
        (function initFormOrderUiSounds() {
            let audioCtx = null;

            function getAudioContext() {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) {
                    return null;
                }
                if (!audioCtx) {
                    audioCtx = new AudioContextClass();
                }
                return audioCtx;
            }

            function unlockAudio() {
                try {
                    const ctx = getAudioContext();
                    if (ctx && ctx.state === 'suspended') {
                        ctx.resume();
                    }
                } catch (e) {
                    // fail-silent
                }
            }

            function playTone(ctx, frequency, startAt, duration, type, peakGain) {
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                oscillator.type = type || 'sine';
                oscillator.frequency.value = frequency;
                const peak = peakGain == null ? 0.14 : peakGain;
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start(startAt);
                oscillator.stop(startAt + duration + 0.05);
            }

            function playSequence(builder) {
                try {
                    const ctx = getAudioContext();
                    if (!ctx) {
                        return;
                    }
                    if (ctx.state === 'suspended') {
                        ctx.resume().then(function () {
                            playSequence(builder);
                        });
                        return;
                    }
                    builder(ctx, ctx.currentTime);
                } catch (e) {
                    // fail-silent
                }
            }

            /** Sukces: PNEDU / FV / FV+KSeF */
            function playSuccess() {
                playSequence(function (ctx, t0) {
                    playTone(ctx, 523.25, t0, 0.18, 'sine', 0.12);
                    playTone(ctx, 659.25, t0 + 0.12, 0.22, 'sine', 0.13);
                    playTone(ctx, 783.99, t0 + 0.26, 0.28, 'sine', 0.12);
                });
            }

            /** Twarda porażka: FV nie wystawiona / błąd połączenia / PNEDU */
            function playError() {
                playSequence(function (ctx, t0) {
                    playTone(ctx, 220, t0, 0.22, 'square', 0.08);
                    playTone(ctx, 165, t0 + 0.2, 0.28, 'square', 0.07);
                });
            }

            /** FV jest (np. 34/8/2026), ale KSeF nie doszedł — inny dźwięk niż twardy błąd */
            function playKsefError() {
                playSequence(function (ctx, t0) {
                    playTone(ctx, 440, t0, 0.16, 'triangle', 0.11);
                    playTone(ctx, 349.23, t0 + 0.18, 0.16, 'triangle', 0.11);
                    playTone(ctx, 293.66, t0 + 0.36, 0.28, 'triangle', 0.1);
                });
            }

            window.formOrderPlayUiSound = function (kind) {
                if (kind === 'success') {
                    playSuccess();
                } else if (kind === 'ksef_error') {
                    playKsefError();
                } else {
                    playError();
                }
            };

            document.addEventListener('click', unlockAudio, { once: true, passive: true });
            document.addEventListener('keydown', unlockAudio, { once: true });
        })();

        // Ostrzeżenia przed akcją: bramka online (nie opłacone) i/lub uwagi zamawiającego do FV
        window.formOrderPreActionWarnings = {
            unpaidOnline: {
                needed: @json($zamowienie->shouldWarnUnpaidOnlineGateway()),
                statusLabel: @json(\App\Models\FormOrder::paymentStatusLabel($zamowienie->payment_status)),
                modeLabel: @json($zamowienie->paymentModeLabelWithGateway()),
            },
            invoiceNotes: {
                needed: @json(filled(trim((string) $zamowienie->invoice_notes))),
                text: @json(trim((string) $zamowienie->invoice_notes)),
            },
        };

        function withFormOrderPreActionWarnings(confirmButtonLabel, proceedFn, options) {
            options = options || {};
            const warnInvoiceNotes = !!options.warnInvoiceNotes;
            const cfg = window.formOrderPreActionWarnings || {};
            const unpaidNeeded = !!(cfg.unpaidOnline && cfg.unpaidOnline.needed);
            const notesNeeded = warnInvoiceNotes && !!(cfg.invoiceNotes && cfg.invoiceNotes.needed);

            if (!unpaidNeeded && !notesNeeded) {
                proceedFn();
                return;
            }

            const modalEl = document.getElementById('formOrderPreActionWarningModal');
            const confirmBtn = document.getElementById('formOrderPreActionWarningConfirmBtn');
            const titleEl = document.getElementById('formOrderPreActionWarningTitle');
            const unpaidSection = document.getElementById('preActionWarningUnpaidOnlineSection');
            const notesSection = document.getElementById('preActionWarningInvoiceNotesSection');
            const notesTextEl = document.getElementById('preActionWarningInvoiceNotesText');
            const separatorEl = document.getElementById('preActionWarningSectionsSeparator');
            const statusEl = document.getElementById('unpaidOnlinePaymentWarningStatus');
            const modeEl = document.getElementById('unpaidOnlinePaymentWarningMode');

            if (!modalEl || !confirmBtn) {
                proceedFn();
                return;
            }

            if (unpaidSection) {
                unpaidSection.classList.toggle('d-none', !unpaidNeeded);
            }
            if (notesSection) {
                notesSection.classList.toggle('d-none', !notesNeeded);
            }
            if (separatorEl) {
                separatorEl.classList.toggle('d-none', !(unpaidNeeded && notesNeeded));
            }
            if (titleEl) {
                const preActionContext = options.preActionContext || 'invoice';
                if (unpaidNeeded && notesNeeded) {
                    titleEl.textContent = preActionContext === 'participant'
                        ? 'Uwaga przed dodaniem uczestnika'
                        : 'Uwaga przed wystawieniem faktury';
                } else if (notesNeeded) {
                    titleEl.textContent = 'Uwagi zamawiającego do faktury';
                } else {
                    titleEl.textContent = 'Płatność online nie jest opłacona';
                }
            }
            if (statusEl && cfg.unpaidOnline) {
                statusEl.textContent = cfg.unpaidOnline.statusLabel || '—';
            }
            if (modeEl && cfg.unpaidOnline) {
                modeEl.textContent = cfg.unpaidOnline.modeLabel || 'Płatność online';
            }
            if (notesTextEl && cfg.invoiceNotes) {
                notesTextEl.textContent = cfg.invoiceNotes.text || '';
            }

            let effectiveConfirmLabel = confirmButtonLabel || 'Mimo to kontynuuj';
            if (notesNeeded) {
                const labelContext = options.preActionContext || 'invoice';
                if (labelContext === 'participant') {
                    effectiveConfirmLabel = 'Zapoznałem się z uwagą - dodaj uczestnika';
                } else {
                    effectiveConfirmLabel = 'Zapoznałem się z uwagą - wystaw fakturę';
                }
            }

            confirmBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + effectiveConfirmLabel;
            confirmBtn.onclick = function () {
                const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                instance.hide();
                proceedFn();
            };
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        }

        /** @deprecated użyj withFormOrderPreActionWarnings — zachowane dla czytelności wywołań PNEDU */
        function withUnpaidOnlinePaymentWarning(confirmButtonLabel, proceedFn) {
            withFormOrderPreActionWarnings(confirmButtonLabel, proceedFn, { warnInvoiceNotes: false });
        }

        function copyTextToClipboard(text, successMsg) {
            const value = text == null ? '' : String(text);
            navigator.clipboard.writeText(value).then(function () {
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('success');
                }
                // krótki toast przez alert Bootstrap jeśli dostępny — bez natywnego alert()
                const resultDiv = document.getElementById('pneduResult');
                if (resultDiv) {
                    resultDiv.innerHTML = `<div class="alert alert-success py-1 px-2 small mb-0">${successMsg || 'Skopiowano'}</div>`;
                    setTimeout(function () { if (resultDiv.querySelector('.alert-success')) resultDiv.innerHTML = ''; }, 1500);
                }
            }).catch(function () {});
        }

        function clearOrphanModalBackdrop() {
            if (document.querySelector('.modal.show')) {
                return;
            }
            document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        function hideBootstrapModal(modalEl) {
            return new Promise(function (resolve) {
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    clearOrphanModalBackdrop();
                    resolve();
                    return;
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                    modalEl.removeEventListener('hidden.bs.modal', onHidden);
                    clearOrphanModalBackdrop();
                    resolve();
                }, { once: true });
                modal.hide();
            });
        }

        function softRefreshParticipantsCards(expandFopId) {
            const root = document.getElementById('formOrderParticipantsRoot');
            if (!root) {
                return Promise.reject(new Error('Brak kontenera uczestników'));
            }
            const baseUrl = root.getAttribute('data-participants-partial-url');
            if (!baseUrl) {
                return Promise.reject(new Error('Brak URL partial'));
            }
            const scrollY = window.scrollY;
            const url = expandFopId
                ? (baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'expand_fop_id=' + encodeURIComponent(expandFopId))
                : baseUrl;
            return fetch(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('Nie udało się odświeżyć listy uczestników');
                }
                return res.text();
            }).then(function (html) {
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const next = wrap.querySelector('#formOrderParticipantsRoot') || wrap.firstElementChild;
                if (!next) {
                    throw new Error('Pusta odpowiedź partial');
                }
                root.replaceWith(next);
                window.scrollTo(0, scrollY);
                initPneduStatusWidgets({ autoCollapseFopId: expandFopId || null, autoCollapseMs: 2200 });
                // STATUS ZAMÓWIENIA (0/1 → 1/1 itd.) — ten sam partial co po wystawieniu FV iFirma.
                refreshOperationalStatusPanel();
            });
        }

        function resetPneduConfirmButtonLabel(modalEl) {
            if (modalEl && modalEl.dataset.resetAll === '1') {
                return '<i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU wszystkim';
            }
            return '<i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU';
        }

        function setPneduStatusExpanded(widget, expanded) {
            if (!widget) return;
            const details = widget.querySelector('.js-pnedu-status-details');
            const chevronDown = widget.querySelector('.js-pnedu-status-chevron');
            const chevronUp = widget.querySelector('.js-pnedu-status-chevron-up');
            widget.dataset.expanded = expanded ? '1' : '0';
            if (details) {
                details.classList.toggle('d-none', !expanded);
            }
            if (chevronDown) {
                chevronDown.classList.toggle('d-none', expanded);
            }
            if (chevronUp) {
                chevronUp.classList.toggle('d-none', !expanded);
            }
        }

        function initPneduStatusWidgets(options) {
            options = options || {};
            document.querySelectorAll('.js-pnedu-status').forEach(function (widget) {
                const toggle = widget.querySelector('.js-pnedu-status-toggle');
                if (toggle && !toggle.dataset.bound) {
                    toggle.dataset.bound = '1';
                    toggle.addEventListener('click', function () {
                        const expanded = widget.dataset.expanded === '1';
                        setPneduStatusExpanded(widget, !expanded);
                    });
                }
                setPneduStatusExpanded(widget, widget.dataset.expanded === '1');
            });

            const autoId = options.autoCollapseFopId;
            if (autoId) {
                const widget = document.querySelector('.js-pnedu-status[data-fop-id="' + autoId + '"]');
                if (widget) {
                    setPneduStatusExpanded(widget, true);
                    const card = document.querySelector('[data-fop-card="' + autoId + '"]');
                    if (card) {
                        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    const ok = parseInt(widget.getAttribute('data-ok-steps') || '0', 10);
                    const total = parseInt(widget.getAttribute('data-total-steps') || '3', 10);
                    // Pełny sukces (3/3): po chwili zwiń. Problem w kroku: zostaw rozwinięte (ręczne zwinięcie OK).
                    if (ok >= total) {
                        setTimeout(function () {
                            setPneduStatusExpanded(widget, false);
                        }, options.autoCollapseMs || 2200);
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            initPneduStatusWidgets();
        });

        function provisionPnedu(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to dodaj uczestnika', function () {
                    provisionPnedu(orderId, Object.assign({}, options, { skipPreActionWarnings: true }));
                }, { warnInvoiceNotes: true, preActionContext: 'participant' });
                return;
            }

            const fopId = options.formOrderParticipantId || null;
            const buttons = document.querySelectorAll('.js-pnedu-provision-btn');
            const resultDiv = (fopId && document.getElementById('pneduResult_' + fopId))
                || document.getElementById('pneduResult');
            buttons.forEach((btn) => {
                btn.disabled = true;
                if (!btn.dataset.pneduOriginalHtml) {
                    btn.dataset.pneduOriginalHtml = btn.innerHTML;
                }
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            });
            document.querySelectorAll('.js-pnedu-card-result').forEach((el) => {
                if (el !== resultDiv) {
                    el.innerHTML = '';
                }
            });
            const globalResult = document.getElementById('pneduResult');
            if (globalResult && globalResult !== resultDiv) {
                globalResult.innerHTML = '';
            }
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div class="alert alert-info py-2 mb-0 small" role="status">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <strong>Trwa przyznawanie dostępu PNEDU…</strong>
                        </div>
                        <ol class="mb-0 ps-3">
                            <li>Uczestnik w szkoleniu + konto pnedu.pl</li>
                            <li>ClickMeeting (jeśli skonfigurowane)</li>
                            <li>E-mail z dostępem do uczestnika</li>
                        </ol>
                    </div>`;
                resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            const payload = {
                add_participant_to_sendy: false,
            };
            if (fopId) {
                payload.form_order_participant_id = fopId;
                const sendyCb = document.getElementById('addToSendy_' + fopId);
                payload.add_participant_to_sendy = !!(sendyCb && sendyCb.checked);
            }
            fetch(`/form-orders/${orderId}/pnedu/provision`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload),
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, status: response.status, data })))
                .then(({ ok, data }) => {
                    if (data.success) {
                        if (typeof window.formOrderPlayUiSound === 'function') {
                            window.formOrderPlayUiSound('success');
                        }
                        const okSteps = data.ok_steps != null ? data.ok_steps : 3;
                        const totalSteps = data.total_steps != null ? data.total_steps : 3;
                        if (resultDiv) {
                            resultDiv.innerHTML = `
                            <div class="alert alert-success fade show mb-0 py-2 small" role="status">
                                <i class="bi bi-check-circle"></i>
                                <strong>Sukces.</strong> ${okSteps}/${totalSteps} kroków OK — aktualizuję kartę…
                            </div>`;
                        }
                        softRefreshParticipantsCards(fopId || data.form_order_participant_id || null)
                            .catch(function () {
                                if (resultDiv) {
                                    resultDiv.innerHTML = `
                                    <div class="alert alert-warning mb-0 small">
                                        Provision OK, ale nie odświeżono karty. <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="location.reload()">Odśwież stronę</button>
                                    </div>`;
                                }
                                buttons.forEach((btn) => {
                                    btn.disabled = false;
                                    if (btn.dataset.pneduOriginalHtml) {
                                        btn.innerHTML = btn.dataset.pneduOriginalHtml;
                                    }
                                });
                            });
                        return;
                    }
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    const extra = data.sent_at ? ` (wcześniej: ${data.sent_at})` : '';
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error || 'Nieznany błąd'}${extra}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                    }
                    buttons.forEach((btn) => {
                        btn.disabled = false;
                        if (btn.dataset.pneduOriginalHtml) {
                            btn.innerHTML = btn.dataset.pneduOriginalHtml;
                        }
                    });
                })
                .catch(() => {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    buttons.forEach((btn) => {
                        btn.disabled = false;
                        if (btn.dataset.pneduOriginalHtml) {
                            btn.innerHTML = btn.dataset.pneduOriginalHtml;
                        }
                    });
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> Nie udało się połączyć z serwerem.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                    }
                });
        }

        function provisionPneduAll(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to dodaj wszystkich', function () {
                    provisionPneduAll(orderId, { skipPreActionWarnings: true });
                }, { warnInvoiceNotes: true, preActionContext: 'participant' });
                return;
            }

            const buttons = document.querySelectorAll('.js-pnedu-provision-btn');
            const resultDiv = document.getElementById('pneduResultAll')
                || document.getElementById('pneduResult');
            buttons.forEach((btn) => {
                btn.disabled = true;
                if (!btn.dataset.pneduOriginalHtml) {
                    btn.dataset.pneduOriginalHtml = btn.innerHTML;
                }
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            });
            document.querySelectorAll('.js-pnedu-card-result').forEach((el) => {
                if (el !== resultDiv) {
                    el.innerHTML = '';
                }
            });
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div class="alert alert-info py-2 mb-0 small" role="status">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <strong>Trwa dodawanie wszystkich uczestników do PNEDU…</strong>
                        </div>
                        <div class="text-muted">Dla każdej osoby: uczestnik + konto → ClickMeeting → e-mail.</div>
                    </div>`;
                resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            const sendyByFopId = {};
            document.querySelectorAll('.js-add-to-sendy').forEach((cb) => {
                const fopId = cb.getAttribute('data-fop-id');
                if (fopId) {
                    sendyByFopId[fopId] = !!cb.checked;
                }
            });
            const payload = {
                add_participant_to_sendy: false,
                add_participant_to_sendy_by_fop_id: sendyByFopId,
            };
            fetch(`/form-orders/${orderId}/pnedu/provision-all`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload),
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ data }) => {
                    if (data.success) {
                        if (typeof window.formOrderPlayUiSound === 'function') {
                            window.formOrderPlayUiSound('success');
                        }
                        if (resultDiv) {
                            resultDiv.innerHTML = `
                            <div class="alert alert-success fade show mb-0 py-2 small" role="status">
                                <i class="bi bi-check-circle"></i>
                                <strong>Sukces.</strong> ${data.message || ''} — aktualizuję karty…
                            </div>`;
                        }
                        softRefreshParticipantsCards(null).catch(function () {
                            if (resultDiv) {
                                resultDiv.innerHTML = `
                                <div class="alert alert-warning mb-0 small">
                                    Provision OK, ale nie odświeżono kart. <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="location.reload()">Odśwież stronę</button>
                                </div>`;
                            }
                            buttons.forEach((btn) => {
                                btn.disabled = false;
                                if (btn.dataset.pneduOriginalHtml) {
                                    btn.innerHTML = btn.dataset.pneduOriginalHtml;
                                }
                            });
                        });
                        return;
                    }
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error || data.message || 'Nieznany błąd'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                    }
                    buttons.forEach((btn) => {
                        btn.disabled = false;
                        if (btn.dataset.pneduOriginalHtml) {
                            btn.innerHTML = btn.dataset.pneduOriginalHtml;
                        }
                    });
                })
                .catch(() => {
                    buttons.forEach((btn) => {
                        btn.disabled = false;
                        if (btn.dataset.pneduOriginalHtml) {
                            btn.innerHTML = btn.dataset.pneduOriginalHtml;
                        }
                    });
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> Nie udało się połączyć z serwerem.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                    }
                });
        }

        function ifirmaPrefixSzkolenieInProductName() {
            const el = document.getElementById('ifirma_prefix_szkolenie_in_product_name');
            return !!(el && el.checked);
        }

        // Toggle linii "UCZESTNIK: ..." / "UCZESTNICY: ..." w polu "Uwagi, ktore pojawia sie
        // na fakturze". Rucznie wpisane uwagi zostaja na gorze; linia uczestnika jest doklejana
        // na koncu pola (nad automatycznym "pnedu.pl #ID" dodawanym przez backend).
        // Filtrujemy istniejace wystapienia obu wariantow, zeby przelaczanie nie duplikowalo
        // wpisu i zeby zmiana liczby uczestnikow nie zostawiala starej linii.
        function applyParticipantInRemarks() {
            const cb = document.getElementById('ifirma_include_participant_in_remarks');
            const ta = document.getElementById('invoice_api_remarks');
            if (!cb || !ta) {
                return;
            }
            const prefix = (cb.dataset.participantsPrefix || 'UCZESTNIK:').trim();
            const names = (cb.dataset.participantsNames || '').trim();
            const filtered = ta.value
                .split('\n')
                .filter(function (line) { return !/^UCZESTNI(?:K|CY):\s*/i.test(line.trim()); });
            let body = filtered.join('\n').replace(/^\n+/, '').replace(/\n+$/, '');
            if (cb.checked && names !== '') {
                const tail = prefix + ' ' + names;
                body = body.length > 0 ? (body + '\n' + tail) : tail;
            }
            ta.value = body;
        }

        // Funkcja do wystawiania faktury pro forma w iFirma
        function createIfirmaProForma(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to wystaw pro-formę', function () {
                    createIfirmaProForma(orderId, { skipPreActionWarnings: true });
                }, { warnInvoiceNotes: true });
                return;
            }

            const button = document.getElementById('ifirmaProFormaBtn');
            const resultDiv = document.getElementById('ifirmaResult');
            
            // Pobierz edytowalne uwagi do faktury
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';
            
            // Pobierz stan checkboxa "Wyślij automatycznie na e-mail"
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxProforma');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Przygotuj dane do wysłania
            // Zawsze wyślij custom_remarks (nawet jeśli pusty string) - backend użyje dokładnie tego co jest w polu
            // Jeśli pole jest puste, wyślij pusty string - backend użyje pustego stringa (nie generuj danych odbiorcy)
            // Jeśli pole ma wartość, wyślij ją - backend użyje dokładnie tego co jest w polu
            const requestData = {
                custom_remarks: customRemarks, // Zawsze wyślij (nawet jeśli pusty string)
                send_email: sendEmail,
                prefix_szkolenie_in_product_name: ifirmaPrefixSzkolenieInProductName(),
            };
            
            // Wysłanie zapytania AJAX z niestandardowymi uwagami i opcją wysyłki e-mail
            fetch(`/form-orders/${orderId}/ifirma/proforma`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('success');
                    }
                    // Sukces
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sukces!</strong> ${data.message}
                            ${data.invoice_number ? `<br><small>Numer faktury: <strong>${data.invoice_number}</strong></small>` : ''}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                    
                    // Automatyczne wypełnienie pola "Notatki" numerem PRO-FORMA
                    // PRO-FORMA zapisuje się w notes (Notatki), nie w invoice_number!
                    if (data.invoice_number) {
                        const notesTextarea = document.getElementById('notes');
                        if (notesTextarea) {
                            // Pobierz istniejące notatki
                            const existingNotes = notesTextarea.value.trim();
                            
                            // Sprawdź czy numer PRO-FORMA już nie jest w notatkach
                            if (existingNotes.indexOf(data.invoice_number) === -1) {
                                // Dodaj numer PRO-FORMA na początku (na górze), spychając poprzednie wpisy w dół
                                const proFormaNote = existingNotes 
                                    ? `PRO-FORMA: ${data.invoice_number}\n${existingNotes}`
                                    : `PRO-FORMA: ${data.invoice_number}`;
                                
                                notesTextarea.value = proFormaNote;
                                
                                // Wizualny efekt - podświetlenie pola na zielono na moment
                                notesTextarea.style.transition = 'background-color 0.3s';
                                notesTextarea.style.backgroundColor = '#d4edda';
                                setTimeout(() => {
                                    notesTextarea.style.backgroundColor = '';
                                }, 2000);
                            }
                        }
                    }
                } else {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.ifirma_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('error');
                }
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd połączenia:</strong> Wystąpił błąd podczas komunikacji z serwerem.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-receipt"></i> Wystaw PRO-FORMA iFirma';
            });
        }

        // Zmienna globalna do przechowywania informacji o typie faktury (dla modala)
        window.invoiceType = 'standard'; // 'standard' lub 'with-receiver'

        // Funkcja sprawdzająca czy invoice_number jest wypełnione w bazie danych przed wystawieniem faktury
        function checkAndCreateInvoice(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to wystaw fakturę', function () {
                    checkAndCreateInvoice(orderId, { skipPreActionWarnings: true });
                }, { warnInvoiceNotes: true });
                return;
            }

            const button = document.getElementById('ifirmaInvoiceBtn');
            const invoiceNumberInput = document.getElementById('invoice_number');
            
            // Ustaw typ faktury na standardową
            window.invoiceType = 'standard';
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Sprawdzanie...';
            
            // Sprawdź status faktury w bazie danych (nie w formularzu!)
            fetch(`/form-orders/${orderId}/ifirma/check-invoice`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.has_invoice && data.invoice_number) {
                        // Faktura już istnieje w bazie - wypełnij pole formularza i pokaż modal
                        if (invoiceNumberInput) {
                            invoiceNumberInput.value = data.invoice_number;
                            
                            // Zmień podświetlenie na zielone
                            invoiceNumberInput.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
                            invoiceNumberInput.style.borderWidth = '';
                            invoiceNumberInput.style.boxShadow = '';
                            invoiceNumberInput.classList.add('border-success', 'bg-success', 'bg-opacity-10', 'is-valid');
                            invoiceNumberInput.style.borderWidth = '2px';
                            invoiceNumberInput.style.boxShadow = '0 0 0 0.2rem rgba(25, 135, 84, 0.25)';
                            
                            // Wizualny efekt - podświetlenie tła na zielono na moment
                            invoiceNumberInput.style.transition = 'background-color 0.3s, border-color 0.3s, box-shadow 0.3s';
                            invoiceNumberInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                invoiceNumberInput.style.backgroundColor = '';
                            }, 2000);
                        }
                        
                        // Aktualizuj wyświetlany numer faktury w modalu
                        const invoiceNumberDisplay = document.getElementById('currentInvoiceNumberDisplay');
                        if (invoiceNumberDisplay) {
                            invoiceNumberDisplay.textContent = data.invoice_number;
                        }
                        
                        // Aktualizuj przycisk w modalu
                        const confirmBtn = document.getElementById('invoiceWarningConfirmBtn');
                        if (confirmBtn) {
                            confirmBtn.onclick = function() { confirmCreateIfirmaInvoice(orderId); };
                        }
                        
                        // Pokaż modal ostrzeżenia
                        const modal = new bootstrap.Modal(document.getElementById('invoiceWarningModal'));
                        modal.show();
                    } else {
                        // Nie ma faktury w bazie - wystaw fakturę bezpośrednio
                        createIfirmaInvoice(orderId);
                    }
                } else {
                    // Błąd podczas sprawdzania - pokaż komunikat
                    const resultDiv = document.getElementById('ifirmaResult');
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Błąd:</strong> ${data.error || 'Nie udało się sprawdzić statusu faktury.'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('ifirmaResult');
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd połączenia:</strong> Wystąpił błąd podczas sprawdzania statusu faktury.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma';
            });
        }

        // Funkcja potwierdzająca wystawienie faktury (wywoływana z modala ostrzeżenia)
        function confirmCreateIfirmaInvoice(orderId) {
            // Zamknij modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('invoiceWarningModal'));
            if (modal) {
                modal.hide();
            }
            // Wywołaj funkcję wystawiania faktury z parametrem force=true
            createIfirmaInvoice(orderId, true);
        }

        // Funkcja do wystawiania zwykłej faktury w iFirma
        function createIfirmaInvoice(orderId, force = false) {
            const button = document.getElementById('ifirmaInvoiceBtn');
            const resultDiv = document.getElementById('ifirmaResult');
            
            // Pobierz edytowalne uwagi do faktury
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';
            
            // Pobierz stan checkboxa "Wyślij automatycznie na e-mail"
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxInvoice');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Przygotuj dane do wysłania
            // Jeśli customRemarks jest pusty, wyślij pusty string - backend użyje dokładnie tego co jest w polu (pusty string)
            // Jeśli customRemarks ma wartość, wyślij ją - backend użyje dokładnie tego co jest w polu
            const requestData = {
                custom_remarks: customRemarks, // Zawsze wyślij (nawet jeśli pusty string) - backend użyje dokładnie tego co jest w polu
                send_email: sendEmail,
                prefix_szkolenie_in_product_name: ifirmaPrefixSzkolenieInProductName(),
            };
            
            // Dodaj parametr force tylko jeśli jest true
            if (force) {
                requestData.force = true;
            }
            
            // Wysłanie zapytania AJAX z niestandardowymi uwagami i opcją wysyłki e-mail
            fetch(`/form-orders/${orderId}/ifirma/invoice`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                // Sprawdź status odpowiedzi
                if (response.status === 409) {
                    // Konflikt - faktura już istnieje
                    return response.json().then(data => {
                        throw new Error(data.error || 'Faktura już została wystawiona');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('success');
                    }
                    // Sukces
                    const alertClass = force ? 'alert-warning' : 'alert-success';
                    const alertIcon = force ? 'bi-exclamation-triangle' : 'bi-check-circle';
                    const alertMessage = force 
                        ? `<strong>Uwaga!</strong> Faktura została wystawiona mimo istniejącego numeru. ${data.message}`
                        : `<strong>Sukces!</strong> ${data.message}`;
                    
                    resultDiv.innerHTML = `
                        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                            <i class="bi ${alertIcon}"></i>
                            ${alertMessage}
                            ${data.invoice_number ? `<br><small>Numer faktury: <strong>${data.invoice_number}</strong></small>` : ''}
                            ${data.existing_invoice_number && force ? `<br><small class="text-muted">Poprzedni numer: <del>${data.existing_invoice_number}</del></small>` : ''}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                    
                    // Numer FV, ID iFirma, daty, STATUS — bez przeładowania strony
                    applyIssuedInvoiceUi(data, orderId);
                } else {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.ifirma_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('error');
                }
                
                // Sprawdź czy to błąd 409 (konflikt - faktura już istnieje)
                const errorMessage = error.message || 'Wystąpił błąd podczas komunikacji z serwerem.';
                const isConflict = errorMessage.includes('już została wystawiona') || errorMessage.includes('already');
                
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd:</strong> ${errorMessage}
                        ${isConflict ? `<br><small class="text-muted">Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.</small>` : ''}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma';
            });
        }

        // Funkcja sprawdzająca czy invoice_number jest wypełnione w bazie danych przed wystawieniem faktury z odbiorcą
        function checkAndCreateInvoiceWithReceiver(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to wystaw fakturę', function () {
                    checkAndCreateInvoiceWithReceiver(orderId, { skipPreActionWarnings: true });
                }, { warnInvoiceNotes: true });
                return;
            }

            const button = document.getElementById('ifirmaInvoiceWithReceiverBtn');
            const invoiceNumberInput = document.getElementById('invoice_number');
            
            // Sprawdź czy checkbox "Wyślij automatycznie na e-mail" jest zaznaczony
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxInvoiceWithReceiver');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Jeśli checkbox e-mail jest zaznaczony, pokaż modal ostrzegawczy o KSeF
            if (sendEmail) {
                // Ustaw funkcję potwierdzenia w modalu
                const confirmBtn = document.getElementById('ksefWarningConfirmBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        // Zamknij modal KSeF
                        const ksefModal = bootstrap.Modal.getInstance(document.getElementById('ksefWarningModal'));
                        if (ksefModal) {
                            ksefModal.hide();
                        }
                        // Kontynuuj normalny flow po zamknięciu modala
                        proceedWithInvoiceCheck(orderId);
                    };
                }
                
                // Pokaż modal ostrzegawczy o KSeF
                const ksefModal = new bootstrap.Modal(document.getElementById('ksefWarningModal'));
                ksefModal.show();
                return; // Przerwij wykonanie - kontynuacja nastąpi po potwierdzeniu w modalu
            }
            
            // Jeśli checkbox nie jest zaznaczony, kontynuuj normalnie
            proceedWithInvoiceCheck(orderId);
        }

        // Funkcja pomocnicza do sprawdzania statusu faktury i kontynuacji procesu
        function proceedWithInvoiceCheck(orderId) {
            const button = document.getElementById('ifirmaInvoiceWithReceiverBtn');
            const invoiceNumberInput = document.getElementById('invoice_number');
            
            // Ustaw typ faktury na z odbiorcą
            window.invoiceType = 'with-receiver';
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Sprawdzanie...';
            
            // Sprawdź status faktury w bazie danych (nie w formularzu!)
            fetch(`/form-orders/${orderId}/ifirma/check-invoice`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.has_invoice && data.invoice_number) {
                        // Faktura już istnieje w bazie - wypełnij pole formularza i pokaż modal
                        if (invoiceNumberInput) {
                            invoiceNumberInput.value = data.invoice_number;
                            
                            // Zmień podświetlenie na zielone
                            invoiceNumberInput.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
                            invoiceNumberInput.style.borderWidth = '';
                            invoiceNumberInput.style.boxShadow = '';
                            invoiceNumberInput.classList.add('border-success', 'bg-success', 'bg-opacity-10', 'is-valid');
                            invoiceNumberInput.style.borderWidth = '2px';
                            invoiceNumberInput.style.boxShadow = '0 0 0 0.2rem rgba(25, 135, 84, 0.25)';
                            
                            // Wizualny efekt - podświetlenie tła na zielono na moment
                            invoiceNumberInput.style.transition = 'background-color 0.3s, border-color 0.3s, box-shadow 0.3s';
                            invoiceNumberInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                invoiceNumberInput.style.backgroundColor = '';
                            }, 2000);
                        }
                        
                        // Aktualizuj wyświetlany numer faktury w modalu
                        const invoiceNumberDisplay = document.getElementById('currentInvoiceNumberDisplay');
                        if (invoiceNumberDisplay) {
                            invoiceNumberDisplay.textContent = data.invoice_number;
                        }
                        
                        // Aktualizuj przycisk w modalu
                        const confirmBtn = document.getElementById('invoiceWarningConfirmBtn');
                        if (confirmBtn) {
                            confirmBtn.onclick = function() { confirmCreateIfirmaInvoiceWithReceiver(orderId); };
                        }
                        
                        // Pokaż modal ostrzeżenia
                        const modal = new bootstrap.Modal(document.getElementById('invoiceWarningModal'));
                        modal.show();
                    } else {
                        // Nie ma faktury w bazie - wystaw fakturę bezpośrednio
                        createIfirmaInvoiceWithReceiver(orderId);
                    }
                } else {
                    // Błąd podczas sprawdzania - pokaż komunikat
                    const resultDiv = document.getElementById('ifirmaResult');
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Błąd:</strong> ${data.error || 'Nie udało się sprawdzić statusu faktury.'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('ifirmaResult');
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd połączenia:</strong> Wystąpił błąd podczas sprawdzania statusu faktury.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma z Odbiorcą';
            });
        }

        // Funkcja potwierdzająca wystawienie faktury z odbiorcą (wywoływana z modala ostrzeżenia)
        function confirmCreateIfirmaInvoiceWithReceiver(orderId) {
            // Zamknij modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('invoiceWarningModal'));
            if (modal) {
                modal.hide();
            }
            // Wywołaj funkcję wystawiania faktury z parametrem force=true
            createIfirmaInvoiceWithReceiver(orderId, true);
        }

        // Funkcja do wystawiania faktury z odbiorcą w iFirma
        function createIfirmaInvoiceWithReceiver(orderId, force = false) {
            const button = document.getElementById('ifirmaInvoiceWithReceiverBtn');
            const resultDiv = document.getElementById('ifirmaResult');

            // Pobierz edytowalne uwagi do faktury (m.in. linia "UCZESTNIK: ...").
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';

            // Pobierz stan checkboxa "Wyślij automatycznie na e-mail"
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxInvoiceWithReceiver');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Przygotuj dane do wysłania
            const requestData = {
                custom_remarks: customRemarks,
                send_email: sendEmail,
                prefix_szkolenie_in_product_name: ifirmaPrefixSzkolenieInProductName(),
            };
            
            // Dodaj parametr force tylko jeśli jest true
            if (force) {
                requestData.force = true;
            }
            
            // Wysłanie zapytania AJAX z opcją wysyłki e-mail
            fetch(`/form-orders/${orderId}/ifirma/invoice-with-receiver`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                // Sprawdź status odpowiedzi
                if (response.status === 409) {
                    // Konflikt - faktura już istnieje
                    return response.json().then(data => {
                        throw new Error(data.error || 'Faktura już została wystawiona');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('success');
                    }
                    // Sukces
                    const alertClass = force ? 'alert-warning' : 'alert-success';
                    const alertIcon = force ? 'bi-exclamation-triangle' : 'bi-check-circle';
                    const alertMessage = force 
                        ? `<strong>Uwaga!</strong> Faktura z odbiorcą została wystawiona mimo istniejącego numeru. ${data.message}`
                        : `<strong>Sukces!</strong> ${data.message}`;
                    
                    resultDiv.innerHTML = `
                        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                            <i class="bi ${alertIcon}"></i>
                            ${alertMessage}
                            ${data.invoice_number ? `<br><small>Numer faktury: <strong>${data.invoice_number}</strong></small>` : ''}
                            ${data.existing_invoice_number && force ? `<br><small class="text-muted">Poprzedni numer: <del>${data.existing_invoice_number}</del></small>` : ''}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                    
                    // Numer FV, ID iFirma, daty, STATUS — bez przeładowania strony
                    applyIssuedInvoiceUi(data, orderId);
                } else {
                    if (typeof window.formOrderPlayUiSound === 'function') {
                        window.formOrderPlayUiSound('error');
                    }
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.ifirma_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('error');
                }
                
                // Sprawdź czy to błąd 409 (konflikt - faktura już istnieje)
                const errorMessage = error.message || 'Wystąpił błąd podczas komunikacji z serwerem.';
                const isConflict = errorMessage.includes('już została wystawiona') || errorMessage.includes('already');
                
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd:</strong> ${errorMessage}
                        ${isConflict ? `<br><small class="text-muted">Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.</small>` : ''}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma z Odbiorcą';
            });
        }

        // Funkcja sprawdzająca czy invoice_number jest wypełnione przed wystawieniem faktury z KSeF
        function checkAndCreateInvoiceWithKsef(orderId, options) {
            options = options || {};
            if (!options.skipPreActionWarnings) {
                withFormOrderPreActionWarnings('Mimo to wystaw fakturę z KSeF', function () {
                    checkAndCreateInvoiceWithKsef(orderId, { skipPreActionWarnings: true });
                }, { warnInvoiceNotes: true });
                return;
            }

            const button = document.getElementById('ifirmaInvoiceWithKsefBtn');
            const invoiceNumberInput = document.getElementById('invoice_number');
            
            // Ustaw typ faktury na z KSeF
            window.invoiceType = 'with-ksef';
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Sprawdzanie...';
            
            // Sprawdź status faktury w bazie danych
            fetch(`/form-orders/${orderId}/ifirma/check-invoice`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.has_invoice && data.invoice_number) {
                        // Faktura już istnieje w bazie - wypełnij pole formularza i pokaż modal
                        if (invoiceNumberInput) {
                            invoiceNumberInput.value = data.invoice_number;
                            
                            // Zmień podświetlenie na zielone
                            invoiceNumberInput.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
                            invoiceNumberInput.style.borderWidth = '';
                            invoiceNumberInput.style.boxShadow = '';
                            invoiceNumberInput.classList.add('border-success', 'bg-success', 'bg-opacity-10', 'is-valid');
                            invoiceNumberInput.style.borderWidth = '2px';
                            invoiceNumberInput.style.boxShadow = '0 0 0 0.2rem rgba(25, 135, 84, 0.25)';
                            
                            // Wizualny efekt - podświetlenie tła na zielono na moment
                            invoiceNumberInput.style.transition = 'background-color 0.3s, border-color 0.3s, box-shadow 0.3s';
                            invoiceNumberInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                invoiceNumberInput.style.backgroundColor = '';
                            }, 2000);
                        }
                        
                        // Aktualizuj wyświetlany numer faktury w modalu
                        const invoiceNumberDisplay = document.getElementById('currentInvoiceNumberDisplay');
                        if (invoiceNumberDisplay) {
                            invoiceNumberDisplay.textContent = data.invoice_number;
                        }
                        
                        // Aktualizuj przycisk w modalu
                        const confirmBtn = document.getElementById('invoiceWarningConfirmBtn');
                        if (confirmBtn) {
                            confirmBtn.onclick = function() { confirmCreateIfirmaInvoiceWithKsef(orderId); };
                        }
                        
                        // Pokaż modal ostrzeżenia
                        const modal = new bootstrap.Modal(document.getElementById('invoiceWarningModal'));
                        modal.show();
                    } else {
                        // Nie ma faktury w bazie - wystaw fakturę bezpośrednio
                        createIfirmaInvoiceWithKsef(orderId);
                    }
                } else {
                    // Błąd podczas sprawdzania - pokaż komunikat
                    const resultDiv = document.getElementById('ifirmaResult');
                    if (resultDiv) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Błąd:</strong> ${data.error || 'Nie udało się sprawdzić statusu faktury.'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('ifirmaResult');
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd połączenia:</strong> Wystąpił błąd podczas sprawdzania statusu faktury.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-check"></i> Wystaw Fakturę iFirma z Odbiorcą i prześlij do KSeF';
            });
        }

        // Funkcja potwierdzająca wystawienie faktury z KSeF (wywoływana z modala ostrzeżenia)
        function confirmCreateIfirmaInvoiceWithKsef(orderId) {
            // Zamknij modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('invoiceWarningModal'));
            if (modal) {
                modal.hide();
            }
            // Wywołaj funkcję wystawiania faktury z parametrem force=true
            createIfirmaInvoiceWithKsef(orderId, true);
        }

        function refreshOperationalStatusPanel() {
            const panel = document.getElementById('operationalStatusPanel');
            if (!panel) {
                return;
            }
            const orderId = {{ (int) $zamowienie->id }};
            fetch(`/form-orders/${orderId}/operational-status`, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Nie udało się odświeżyć statusu operacyjnego');
                    }
                    return response.text();
                })
                .then((html) => {
                    panel.innerHTML = html;
                })
                .catch((error) => {
                    console.error(error);
                });
        }

        /**
         * Po wystawieniu FV przez API iFirma — aktualizacja pól i STATUS ZAMÓWIENIA bez reloadu.
         */
        function applyIssuedInvoiceUi(data, orderId) {
            data = data || {};
            if (data.invoice_number) {
                applyInvoiceNumberFieldValue(data.invoice_number, { skipStatusRefresh: true });
                const orderStatusAlert = document.getElementById('orderStatusAlert');
                if (orderStatusAlert && orderId) {
                    orderStatusAlert.innerHTML = `
                        <small class="text-success fw-bold">
                            <i class="bi bi-check-circle"></i> Dla zamówienia #${orderId} została wystawiona faktura nr <strong>${data.invoice_number}</strong>
                        </small>
                    `;
                }
            }
            const ifirmaId = data.invoice_id || data.ifirma_invoice_id || null;
            if (ifirmaId) {
                applyIfirmaInvoiceIdDisplay(ifirmaId);
            }
            applyInvoiceDatesDisplay(data.invoice_issue_date, data.invoice_due_date);
            if (data.ksef_number) {
                applyKsefNumberDisplay(data.ksef_number);
            }
            enableCreateDebtCaseButtonAfterInvoice();
            refreshOperationalStatusPanel();
        }

        function enableCreateDebtCaseButtonAfterInvoice() {
            let target = null;
            document.querySelectorAll('button[disabled]').forEach(function (btn) {
                const title = btn.getAttribute('title') || '';
                const text = btn.textContent || '';
                if (title.indexOf('Najpierw wystaw fakturę') !== -1
                    || text.indexOf('Utwórz sprawę windykacyjną') !== -1) {
                    target = btn;
                }
            });
            if (!target) {
                return;
            }
            const replacement = document.createElement('button');
            replacement.type = 'button';
            replacement.className = 'btn btn-sm btn-outline-danger';
            replacement.setAttribute('data-bs-toggle', 'modal');
            replacement.setAttribute('data-bs-target', '#createDebtCaseModal');
            replacement.innerHTML = '<i class="bi bi-plus-circle"></i> Utwórz sprawę windykacyjną';
            const parent = target.parentElement;
            const hint = parent ? parent.querySelector('.small.text-muted.mt-1') : null;
            target.replaceWith(replacement);
            if (hint && (hint.textContent || '').indexOf('Najpierw wystaw fakturę') !== -1) {
                hint.remove();
            }
        }

        function looksLikeIfirmaDocumentId(value) {
            return typeof value === 'string' && /^\d+$/.test(value.trim());
        }

        function applyInvoiceNumberFieldValue(invoiceNumber, options) {
            options = options || {};
            if (!invoiceNumber || looksLikeIfirmaDocumentId(String(invoiceNumber))) {
                return;
            }
            const invoiceNumberInput = document.getElementById('invoice_number');
            if (!invoiceNumberInput) {
                return;
            }
            invoiceNumberInput.value = invoiceNumber;
            invoiceNumberInput.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
            invoiceNumberInput.style.borderWidth = '';
            invoiceNumberInput.style.boxShadow = '';
            invoiceNumberInput.classList.add('border-success', 'bg-success', 'bg-opacity-10', 'is-valid');
            invoiceNumberInput.style.borderWidth = '2px';
            invoiceNumberInput.style.boxShadow = '0 0 0 0.2rem rgba(25, 135, 84, 0.25)';
            invoiceNumberInput.style.transition = 'background-color 0.3s, border-color 0.3s, box-shadow 0.3s';
            invoiceNumberInput.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                invoiceNumberInput.style.backgroundColor = '';
            }, 2000);

            revealInvoiceDatesDisplay();
            if (!options.skipStatusRefresh) {
                refreshOperationalStatusPanel();
            }
        }

        function renderIfirmaKsefProgress(stages) {
            const items = stages.map((stage) => {
                const icon = stage.status === 'done'
                    ? '<i class="bi bi-check-circle-fill text-success me-2"></i>'
                    : (stage.status === 'active'
                        ? '<span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>'
                        : (stage.status === 'error'
                            ? '<i class="bi bi-x-circle-fill text-danger me-2"></i>'
                            : '<i class="bi bi-circle text-muted me-2"></i>'));
                const textClass = stage.status === 'active' ? 'fw-semibold' : (stage.status === 'error' ? 'text-danger' : 'text-muted');
                const detail = stage.detail ? `<div class="small text-muted ms-4">${stage.detail}</div>` : '';
                return `<li class="mb-1 ${textClass}">${icon}${stage.label}${detail}</li>`;
            }).join('');

            return `
                <div class="alert alert-info mb-0">
                    <strong>Postęp wystawiania faktury z KSeF</strong>
                    <ul class="list-unstyled mb-2 mt-2">${items}</ul>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar progress-bar-striped ${stages.some(s => s.status === 'active') ? 'progress-bar-animated' : ''}" role="progressbar" style="width: ${Math.round((stages.filter(s => s.status === 'done').length / stages.length) * 100)}%"></div>
                    </div>
                </div>
            `;
        }

        function applyKsefNumberDisplay(ksefNumber) {
            const wrap = document.getElementById('ksefNumberDisplay');
            const valueEl = document.getElementById('ksefNumberValue');
            if (!wrap || !valueEl) {
                return;
            }
            if (!ksefNumber) {
                valueEl.innerHTML = '<span class="text-muted">—</span>';
                wrap.title = 'Brak numeru KSeF w zamówieniu — użyj synchronizacji z iFirma po ręcznej wysyłce';
                return;
            }
            valueEl.textContent = ksefNumber;
            wrap.classList.remove('d-none');
            wrap.title = 'Przyjęte w KSeF';
        }

        function applyIfirmaInvoiceIdDisplay(invoiceId) {
            const wrap = document.getElementById('ifirmaInvoiceIdDisplay');
            const valueEl = document.getElementById('ifirmaInvoiceIdValue');
            if (!wrap || !valueEl) {
                return;
            }
            if (!invoiceId) {
                valueEl.textContent = '';
                wrap.classList.add('d-none');
                return;
            }
            valueEl.textContent = invoiceId;
            wrap.classList.remove('d-none');
            revealInvoiceDatesDisplay();
        }

        function clearInvoiceMetadataDisplays() {
            const invoiceNumberInput = document.getElementById('invoice_number');
            if (invoiceNumberInput) {
                invoiceNumberInput.value = '';
                invoiceNumberInput.classList.remove('border-success', 'bg-success', 'bg-opacity-10', 'is-valid');
                invoiceNumberInput.style.borderWidth = '';
                invoiceNumberInput.style.boxShadow = '';
                invoiceNumberInput.style.backgroundColor = '';
            }
            clearIfirmaInvoiceIdDisplay();
            applyKsefNumberDisplay(null);
            applyInvoiceDatesDisplay(null, null);
            const ksefDisplay = document.getElementById('ksefNumberDisplay');
            if (ksefDisplay) {
                ksefDisplay.classList.add('d-none');
            }
            refreshOperationalStatusPanel();
        }

        function clearIfirmaInvoiceIdDisplay() {
            applyIfirmaInvoiceIdDisplay(null);
        }

        function formatInvoiceDateDisplay(isoDate) {
            if (!isoDate || typeof isoDate !== 'string') {
                return '—';
            }
            const parts = isoDate.split('-');
            if (parts.length !== 3) {
                return isoDate;
            }
            return `${parts[2]}.${parts[1]}.${parts[0]}`;
        }

        function revealInvoiceDatesDisplay() {
            const wrap = document.getElementById('invoiceDatesDisplay');
            if (wrap) {
                wrap.classList.remove('d-none');
            }
        }

        function applyInvoiceDatesDisplay(issueDate, dueDate) {
            const issueEl = document.getElementById('invoiceIssueDateValue');
            const dueEl = document.getElementById('invoiceDueDateValue');
            const wrap = document.getElementById('invoiceDatesDisplay');
            if (issueEl && issueDate !== undefined) {
                issueEl.textContent = formatInvoiceDateDisplay(issueDate);
            }
            if (dueEl && dueDate !== undefined) {
                dueEl.textContent = formatInvoiceDateDisplay(dueDate);
            }
            if (issueDate || dueDate) {
                revealInvoiceDatesDisplay();
            } else if (wrap && issueDate !== undefined && dueDate !== undefined) {
                wrap.classList.add('d-none');
            }
        }

        async function syncIfirmaKsefFromPanel(orderId, options) {
            options = options || {};
            const preferNumber = !!options.preferNumber;
            const btn = options.button || document.getElementById('syncIfirmaKsefBtn');
            const icon = options.icon || (btn ? btn.querySelector('i') : null) || document.getElementById('syncIfirmaKsefIcon');
            const resultDiv = document.getElementById('ifirmaResult');
            const invoiceNumberInput = document.getElementById('invoice_number');
            const invoiceNumber = (invoiceNumberInput?.value || '').trim();

            if (!btn) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const allSyncBtns = [
                document.getElementById('syncIfirmaKsefBtn'),
                document.getElementById('syncIfirmaByInvoiceNumberBtn'),
                document.getElementById('syncIfirmaByIdBtn'),
            ].filter(Boolean);
            allSyncBtns.forEach(function (el) { el.disabled = true; });
            if (icon) {
                icon.classList.add('spinner-border', 'spinner-border-sm');
                icon.classList.remove('bi-arrow-repeat');
            }

            try {
                const response = await fetch(`/form-orders/${orderId}/ifirma/sync-ksef`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        invoice_number: invoiceNumber || null,
                        prefer_number_lookup: preferNumber,
                    }),
                });
                const data = await response.json();

                if (data.success) {
                    if (data.metadata_cleared) {
                        clearInvoiceMetadataDisplays();
                    } else {
                        if (data.invoice_number) {
                            applyInvoiceNumberFieldValue(data.invoice_number);
                        }
                        applyKsefNumberDisplay(data.ksef_number || null);
                        if (data.ifirma_invoice_id) {
                            applyIfirmaInvoiceIdDisplay(data.ifirma_invoice_id);
                        }
                        applyInvoiceDatesDisplay(data.invoice_issue_date, data.invoice_due_date);
                        const ksefDisplay = document.getElementById('ksefNumberDisplay');
                        if (ksefDisplay) {
                            ksefDisplay.classList.remove('d-none');
                        }
                    }
                    if (resultDiv) {
                        const alertClass = data.metadata_cleared
                            ? (data.changed ? 'alert-info' : 'alert-secondary')
                            : (data.ksef_cleared ? 'alert-info' : 'alert-success');
                        const iconClass = data.metadata_cleared
                            ? 'bi-info-circle'
                            : (data.ksef_cleared ? 'bi-info-circle' : 'bi-check-circle');
                        const datesLine = (data.invoice_issue_date || data.invoice_due_date)
                            ? `<br><span class="text-muted">Data FV:</span> ${formatInvoiceDateDisplay(data.invoice_issue_date)} · <span class="text-muted">Termin:</span> ${formatInvoiceDateDisplay(data.invoice_due_date)}`
                            : '';
                        const idLine = data.ifirma_invoice_id
                            ? `<br><span class="text-muted">ID iFirma:</span> <code>${data.ifirma_invoice_id}</code>`
                            : '';
                        let emailLine = '';
                        if (Array.isArray(data.emails_sent) && data.emails_sent.length) {
                            emailLine += `<br><span class="text-muted">E-mail FV:</span> ${data.emails_sent.join(', ')}`;
                        }
                        if (Array.isArray(data.email_errors) && data.email_errors.length) {
                            emailLine += `<br><span class="text-danger">Błędy e-mail (${data.email_errors.length}) — intencja wysyłki pozostaje.</span>`;
                        }
                        resultDiv.innerHTML = `
                            <div class="alert ${alertClass} alert-dismissible fade show py-2 small mb-0" role="alert">
                                <i class="bi ${iconClass}"></i> ${data.message || 'Zsynchronizowano KSeF z iFirma.'}
                                ${data.ksef_number ? `<br><span class="text-muted">Numer KSeF:</span> <code>${data.ksef_number}</code>` : ''}
                                ${idLine}
                                ${datesLine}
                                ${emailLine}
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                            </div>`;
                    }
                    refreshOperationalStatusPanel();
                } else if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning alert-dismissible fade show py-2 small mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> ${data.error || 'Synchronizacja KSeF nie powiodła się.'}
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                        </div>`;
                }
            } catch (error) {
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show py-2 small mb-0" role="alert">
                            <i class="bi bi-x-circle"></i> Błąd połączenia: ${error.message}
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                        </div>`;
                }
            } finally {
                allSyncBtns.forEach(function (el) { el.disabled = false; });
                if (icon) {
                    icon.classList.remove('spinner-border', 'spinner-border-sm');
                    icon.classList.add('bi-arrow-repeat');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const orderId = {{ $zamowienie->id }};
            const syncKsefBtn = document.getElementById('syncIfirmaKsefBtn');
            if (syncKsefBtn) {
                syncKsefBtn.addEventListener('click', function () {
                    syncIfirmaKsefFromPanel(orderId, {
                        preferNumber: false,
                        button: syncKsefBtn,
                        icon: document.getElementById('syncIfirmaKsefIcon'),
                    });
                });
            }
            const syncByNumberBtn = document.getElementById('syncIfirmaByInvoiceNumberBtn');
            if (syncByNumberBtn) {
                syncByNumberBtn.addEventListener('click', function () {
                    syncIfirmaKsefFromPanel(orderId, {
                        preferNumber: true,
                        button: syncByNumberBtn,
                        icon: document.getElementById('syncIfirmaByInvoiceNumberIcon'),
                    });
                });
            }
            const syncByIdBtn = document.getElementById('syncIfirmaByIdBtn');
            if (syncByIdBtn) {
                syncByIdBtn.addEventListener('click', function () {
                    syncIfirmaKsefFromPanel(orderId, {
                        preferNumber: false,
                        button: syncByIdBtn,
                        icon: document.getElementById('syncIfirmaByIdIcon'),
                    });
                });
            }
        });

        function renderIfirmaKsefResult(data, force, resultDiv) {
            applyIssuedInvoiceUi(data, {{ (int) $zamowienie->id }});

            window.ifirmaResponseData = data;

            if (data.success) {
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('success');
                }
                const alertClass = force ? 'alert-warning' : 'alert-success';
                const alertIcon = force ? 'bi-exclamation-triangle' : 'bi-check-circle';
                const invoiceLine = data.invoice_number
                    ? `<div class="small mt-1">Numer faktury: <strong>${data.invoice_number}</strong></div>`
                    : '';
                const ksefLine = data.ksef_number
                    ? `<div class="small">Numer KSeF: <strong class="text-success">${data.ksef_number}</strong></div>`
                    : '';
                const prevLine = (data.existing_invoice_number && force)
                    ? `<div class="small text-muted">Poprzedni numer: <del>${data.existing_invoice_number}</del></div>`
                    : '';
                const emailLine = (data.email_sent && data.emails_sent)
                    ? `<div class="small text-muted">E-mail wysłany na: ${data.emails_sent.join(', ')}</div>`
                    : '';

                // Nie chowaj podglądu etapów po sukcesie — dołóż tylko podsumowanie na końcu.
                const summaryHtml = `
                    <div class="alert ${alertClass} alert-dismissible fade show mt-2 mb-0" role="alert">
                        <i class="bi ${alertIcon}"></i>
                        <strong>Fakturę wystawiono.</strong>
                        ${invoiceLine}
                        ${ksefLine}
                        ${prevLine}
                        ${emailLine}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                            <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                        </button>
                    </div>
                `;

                // Jeśli w divie jest już progres (najczęściej), dopisz. W przeciwnym razie wyświetl samo podsumowanie.
                if (resultDiv && resultDiv.innerHTML && resultDiv.innerHTML.trim().length > 0) {
                    resultDiv.innerHTML = resultDiv.innerHTML + summaryHtml;
                } else {
                    resultDiv.innerHTML = summaryHtml;
                }
                return;
            }

            const isPartial = data.partial_success || data.invoice_created;
            const isKsefStageFail = isPartial
                || data.step === 'ksef_send'
                || data.step === 'ksef_acceptance_timeout'
                || data.step === 'ksef_rejected';
            if (typeof window.formOrderPlayUiSound === 'function') {
                window.formOrderPlayUiSound(isKsefStageFail ? 'ksef_error' : 'error');
            }
            const alertClass = isPartial ? 'alert-warning' : 'alert-danger';
            const alertTitle = isPartial ? 'Faktura w iFirma — dalszy etap nieudany' : 'Błąd';

            let stepInfo = '';
            if (data.step === 'ksef_send') {
                stepInfo = '<br><small>Faktura jest już w iFirma. Numer faktury zapisano w zamówieniu. Nie udało się rozpocząć wysyłki do KSeF.</small>';
            } else if (data.step === 'ksef_acceptance_timeout') {
                stepInfo = '<br><small>Faktura jest w iFirma i została przekazana do KSeF, ale MF nie nadało numeru KSeF w czasie oczekiwania. Sprawdź status w panelu iFirma. E-mail z fakturą nie został wysłany.</small>';
                if (typeof data.poll_attempts !== 'undefined') {
                    stepInfo += `<br><small class="text-muted">Próby odświeżenia statusu: ${data.poll_attempts}</small>`;
                }
            } else if (data.step === 'ksef_rejected') {
                stepInfo = '<br><small>Faktura jest w iFirma, ale KSeF odrzucił dokument. E-mail nie został wysłany.</small>';
            }

            resultDiv.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="bi ${isPartial ? 'bi-exclamation-triangle' : 'bi-x-circle'}"></i>
                    <strong>${alertTitle}:</strong> ${data.error || 'Nieznany błąd'}
                    ${data.invoice_number ? `<br><small>Numer faktury (zapisany): <strong>${data.invoice_number}</strong></small>` : ''}
                    ${stepInfo}
                    ${data.ksef_error ? `<br><small class="text-muted">Szczegóły KSeF: ${data.ksef_error}</small>` : ''}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                ${data.ifirma_response ? `
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                            <i class="bi bi-info-circle"></i> Pokaż szczegóły
                        </button>
                    </div>
                ` : ''}
            `;
        }

        async function postIfirmaInvoiceWithKsef(orderId, payload) {
            const response = await fetch(`/form-orders/${orderId}/ifirma/invoice-with-ksef`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            });

            if (response.status === 409) {
                const data = await response.json();
                throw new Error(data.error || 'Faktura już została wystawiona');
            }

            const data = await response.json();
            return { response, data };
        }

        // Funkcja do wystawiania faktury z KSeF w iFirma
        async function createIfirmaInvoiceWithKsef(orderId, force = false) {
            const button = document.getElementById('ifirmaInvoiceWithKsefBtn');
            const resultDiv = document.getElementById('ifirmaResult');

            // Pobierz edytowalne uwagi do faktury (m.in. linia "UCZESTNIK: ...").
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';

            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxInvoiceWithKsef');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;

            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            resultDiv.innerHTML = renderIfirmaKsefProgress([
                { label: 'Wystawianie faktury w iFirma', status: 'active' },
                { label: 'Zapis numeru faktury w zamówieniu', status: 'pending' },
                { label: 'Przesyłanie do KSeF', status: 'pending' },
                { label: 'Oczekiwanie na numer KSeF (MF)', status: 'pending' },
                { label: sendEmail ? 'Wysyłka e-mail z fakturą' : 'Zakończenie procesu', status: 'pending' },
            ]);

            const basePayload = {
                custom_remarks: customRemarks,
                send_email: sendEmail,
                prefix_szkolenie_in_product_name: ifirmaPrefixSzkolenieInProductName(),
            };
            if (force) {
                basePayload.force = true;
            }

            try {
                const { data: createData } = await postIfirmaInvoiceWithKsef(orderId, {
                    ...basePayload,
                    phase: 'create',
                });

                if (!createData.success) {
                    renderIfirmaKsefResult(createData, force, resultDiv);
                    return;
                }

                applyIssuedInvoiceUi(createData, orderId);

                resultDiv.innerHTML = renderIfirmaKsefProgress([
                    { label: 'Wystawianie faktury w iFirma', status: 'done', detail: createData.invoice_number ? `Nr ${createData.invoice_number}` : '' },
                    { label: 'Zapis numeru faktury w zamówieniu', status: 'done' },
                    { label: 'Przesyłanie do KSeF', status: 'active' },
                    { label: 'Oczekiwanie na numer KSeF (MF) — może potrwać kilka minut', status: 'pending' },
                    { label: sendEmail ? 'Wysyłka e-mail z fakturą' : 'Zakończenie procesu', status: 'pending' },
                ]);

                const { data: ksefData } = await postIfirmaInvoiceWithKsef(orderId, {
                    ...basePayload,
                    phase: 'ksef',
                    invoice_id: createData.invoice_id,
                });

                // Po sukcesie nie chowamy etapów: ustaw progres na "done", a render wyniku dopisze podsumowanie.
                if (ksefData && ksefData.success) {
                    resultDiv.innerHTML = renderIfirmaKsefProgress([
                        { label: 'Wystawianie faktury w iFirma', status: 'done', detail: createData.invoice_number ? `Nr ${createData.invoice_number}` : '' },
                        { label: 'Zapis numeru faktury w zamówieniu', status: 'done' },
                        { label: 'Przesyłanie do KSeF', status: 'done' },
                        { label: 'Oczekiwanie na numer KSeF (MF)', status: 'done', detail: ksefData.ksef_number ? `KSeF: ${ksefData.ksef_number}` : '' },
                        { label: sendEmail ? 'Wysyłka e-mail z fakturą' : 'Zakończenie procesu', status: 'done' },
                    ]);
                }

                renderIfirmaKsefResult(ksefData, force, resultDiv);
            } catch (error) {
                console.error('Error:', error);
                if (typeof window.formOrderPlayUiSound === 'function') {
                    window.formOrderPlayUiSound('error');
                }
                const errorMessage = error.message || 'Wystąpił błąd podczas komunikacji z serwerem.';
                const isConflict = errorMessage.includes('już została wystawiona') || errorMessage.includes('already');

                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd:</strong> ${errorMessage}
                        ${isConflict ? `<br><small class="text-muted">Aby wystawić nową fakturę, użyj opcji "Mimo to wystaw fakturę" w modalu ostrzeżenia.</small>` : ''}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-check"></i> Wystaw Fakturę iFirma z Odbiorcą i prześlij do KSeF';
            }
        }

        // Funkcja do wyświetlania szczegółów odpowiedzi iFirma
        function showIfirmaDetails() {
            if (!window.ifirmaResponseData) return;
            
            const data = window.ifirmaResponseData;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Szczegóły odpowiedzi iFirma
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-info-circle"></i> Informacje:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Status:</strong> <span class="badge ${data.success ? 'bg-success' : 'bg-danger'}">${data.success ? 'Sukces' : 'Błąd'}</span></li>
                                    ${data.invoice_number ? `<li><strong>Numer faktury:</strong> <code>${data.invoice_number}</code></li>` : ''}
                                    ${data.created_at ? `<li><strong>Utworzono:</strong> ${data.created_at}</li>` : ''}
                                    ${data.status_code ? `<li><strong>Kod HTTP:</strong> <span class="badge ${data.status_code === 200 ? 'bg-success' : 'bg-danger'}">${data.status_code}</span></li>` : ''}
                                </ul>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-send"></i> Wysłane dane do API:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 400px; overflow-y: auto;">${JSON.stringify(data.invoice_data, null, 2)}</pre>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-reply"></i> Pełna odpowiedź z iFirma:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 400px; overflow-y: auto;">${JSON.stringify(data.ifirma_response, null, 2)}</pre>
                                </div>
                            </div>
                            ${data.error ? `
                                <div class="alert alert-danger mt-3">
                                    <strong>Błąd:</strong> ${data.error}
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Usuń modal z DOM po zamknięciu
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.removeChild(modal);
            });
        }

        // Funkcja do wyświetlania szczegółów odpowiedzi Publigo
        function showPubligoDetails() {
            if (!window.publigoResponseData) return;
            
            const data = window.publigoResponseData;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Szczegóły odpowiedzi Publigo
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-send"></i> Wysłane dane:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(data.order_data, null, 2)}</pre>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-reply"></i> Odpowiedź Publigo:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(data.publigo_response, null, 2)}</pre>
                                </div>
                            </div>
                            ${data.http_code ? `
                                <div class="mt-3">
                                    <h6><i class="bi bi-code"></i> Kod HTTP:</h6>
                                    <span class="badge ${data.http_code === 200 ? 'bg-success' : 'bg-danger'}">${data.http_code}</span>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Usuń modal z DOM po zamknięciu
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.removeChild(modal);
            });
        }

        // Funkcja do resetowania statusu Publigo (tylko dla administratorów)
        function resetPubligoStatus(orderId) {
            const button = document.getElementById('resetPubligoConfirmBtn');
            const resultDiv = document.getElementById('publigoResult');
            
            // Zmiana stanu przycisku w modalu
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Resetowanie...';
            
            // Wysłanie zapytania AJAX
            fetch(`/form-orders/${orderId}/publigo/reset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Zamknij modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('resetPubligoModal'));
                    if (modal) {
                        modal.hide();
                    }
                    // Sukces - przeładowanie strony aby pokazać przycisk "Dodaj zamówienie przez PUBLIGO"
                    location.reload();
                } else {
                    // Błąd - pokaż komunikat
                    alert('Błąd: ' + data.error);
                    // Przywróć stan przycisku
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas resetowania statusu.');
                // Przywróć stan przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo';
            });
        }

        // Funkcja do resetowania statusu PNEDU (tylko dla administratorów) — per osoba / wszyscy
        function resetPneduStatus(orderId) {
            const modalEl = document.getElementById('resetPneduModal');
            const button = document.getElementById('resetPneduConfirmBtn');
            const errorEl = document.getElementById('resetPneduError');
            const removeCheckbox = document.getElementById('resetPneduRemoveParticipantCheckbox');

            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }

            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Resetowanie...';

            const payload = {
                remove_participant: !!(removeCheckbox && removeCheckbox.checked),
            };
            if (modalEl && modalEl.dataset.resetAll === '1') {
                payload.reset_all = true;
            } else if (modalEl && modalEl.dataset.formOrderParticipantId) {
                payload.form_order_participant_id = Number(modalEl.dataset.formOrderParticipantId);
            }

            fetch(`/form-orders/${orderId}/pnedu/reset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload),
            })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    hideBootstrapModal(modalEl)
                        .then(function () {
                            return softRefreshParticipantsCards(null);
                        })
                        .then(function () {
                            clearOrphanModalBackdrop();
                            button.disabled = false;
                            button.innerHTML = resetPneduConfirmButtonLabel(modalEl);
                        })
                        .catch(function () {
                            location.reload();
                        });
                    return;
                }

                const message = data.error || 'Nie udało się wycofać dostępu PNEDU.';
                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.classList.remove('d-none');
                }
                button.disabled = false;
                button.innerHTML = resetPneduConfirmButtonLabel(modalEl);
            })
            .catch(error => {
                console.error('Error:', error);
                if (errorEl) {
                    errorEl.textContent = 'Wystąpił błąd podczas wycofywania dostępu PNEDU.';
                    errorEl.classList.remove('d-none');
                }
                button.disabled = false;
                button.innerHTML = resetPneduConfirmButtonLabel(modalEl);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const resetPneduModal = document.getElementById('resetPneduModal');
            if (resetPneduModal) {
                resetPneduModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget && event.relatedTarget.classList.contains('js-reset-pnedu-btn')
                        ? event.relatedTarget
                        : null;
                    const errorEl = document.getElementById('resetPneduError');
                    const checkbox = document.getElementById('resetPneduRemoveParticipantCheckbox');
                    const label = document.getElementById('resetPneduRemoveParticipantLabel');
                    const nameEl = document.getElementById('resetPneduParticipantName');
                    const emailEl = document.getElementById('resetPneduParticipantEmail');
                    const emailRow = document.getElementById('resetPneduEmailRow');
                    const introEl = document.getElementById('resetPneduIntro');
                    const titleEl = document.getElementById('resetPneduModalLabel');
                    const confirmBtn = document.getElementById('resetPneduConfirmBtn');

                    const resetAll = trigger ? trigger.getAttribute('data-reset-all') === '1' : false;
                    const fopId = trigger ? (trigger.getAttribute('data-form-order-participant-id') || '') : '';
                    const name = trigger ? (trigger.getAttribute('data-participant-name') || '—') : '—';
                    const email = trigger ? (trigger.getAttribute('data-participant-email') || '') : '';
                    const hasToken = trigger ? trigger.getAttribute('data-has-cm-token') === '1' : false;

                    resetPneduModal.dataset.resetAll = resetAll ? '1' : '0';
                    resetPneduModal.dataset.formOrderParticipantId = fopId;
                    resetPneduModal.dataset.hasCmToken = hasToken ? '1' : '0';

                    if (errorEl) {
                        errorEl.classList.add('d-none');
                        errorEl.textContent = '';
                    }
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                    if (nameEl) {
                        nameEl.textContent = name;
                    }
                    if (emailEl) {
                        emailEl.textContent = email || '—';
                    }
                    if (emailRow) {
                        emailRow.classList.toggle('d-none', resetAll || email === '');
                    }
                    if (introEl) {
                        introEl.innerHTML = resetAll
                            ? 'Czy na pewno chcesz wycofać dostęp PNEDU dla <strong>wszystkich</strong> provisionowanych uczestników zamówienia <strong>#' + {{ $zamowienie->id }} + '</strong>?'
                            : 'Czy na pewno chcesz wycofać dostęp PNEDU dla wybranego uczestnika w zamówieniu <strong>#' + {{ $zamowienie->id }} + '</strong>?';
                    }
                    if (titleEl) {
                        titleEl.innerHTML = resetAll
                            ? '<i class="bi bi-exclamation-triangle"></i> Wycofaj dostęp PNEDU wszystkim'
                            : '<i class="bi bi-exclamation-triangle"></i> Wycofaj dostęp PNEDU — ' + name;
                    }
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = resetAll
                            ? '<i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU wszystkim'
                            : '<i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU';
                    }
                    if (label) {
                        if (resetAll) {
                            label.textContent = 'Czy usunąć wszystkich wskazanych uczestników ze szkolenia (i unieważnić tokeny CM, jeśli są)?';
                        } else {
                            label.textContent = hasToken
                                ? 'Czy usunąć uczestnika ze szkolenia oraz unieważnić token dostępowy?'
                                : 'Czy usunąć uczestnika ze szkolenia?';
                        }
                    }
                });
            }
        });

        // Wstaw ID szkolenia do filtra (klik w „ID szkolenia (courses): …”)
        function fillCourseIdFilter(courseId) {
            const courseIdInput = document.getElementById('courseIdFilter');
            if (!courseIdInput) {
                return;
            }
            const value = String(courseId ?? '').trim();
            if (!value) {
                return;
            }
            courseIdInput.value = value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('course_id', value);
            window.location.href = currentUrl.toString();
        }

        // Checkboxy filtrów nawigacji + pole course_id (filtr po courses.id / form_orders.product_id)
        document.addEventListener('DOMContentLoaded', function() {
            const filterNoParticipantCheckbox = document.getElementById('filterNoParticipantOnly');
            const filterNoInvoiceCheckbox = document.getElementById('filterNoInvoiceOnly');
            const filterNoKsefCheckbox = document.getElementById('filterNoKsefOnly');
            const filterPaymentGatewayCheckbox = document.getElementById('filterPaymentGatewayOnly');
            const courseIdInput = document.getElementById('courseIdFilter');

            function reloadWithNavFilterParam(paramName, enabled) {
                const currentUrl = new URL(window.location.href);
                // Stare zakładki filter_new — nie mieszaj ze split filtrami
                currentUrl.searchParams.delete('filter_new');
                if (enabled) {
                    currentUrl.searchParams.set(paramName, '1');
                } else {
                    currentUrl.searchParams.delete(paramName);
                }
                window.location.href = currentUrl.toString();
            }

            if (filterNoParticipantCheckbox) {
                filterNoParticipantCheckbox.addEventListener('change', function() {
                    reloadWithNavFilterParam('filter_no_participant', this.checked);
                });
            }
            if (filterNoInvoiceCheckbox) {
                filterNoInvoiceCheckbox.addEventListener('change', function() {
                    reloadWithNavFilterParam('filter_no_invoice', this.checked);
                });
            }
            if (filterNoKsefCheckbox) {
                filterNoKsefCheckbox.addEventListener('change', function() {
                    reloadWithNavFilterParam('filter_no_ksef', this.checked);
                });
            }
            if (filterPaymentGatewayCheckbox) {
                filterPaymentGatewayCheckbox.addEventListener('change', function() {
                    reloadWithNavFilterParam('filter_payment_gateway', this.checked);
                });
            }
            
            // Pole: courses.id → query course_id (prev/next po product_id); Enter lub blur (change).
            // Klik w ID szkolenia na karcie produktu: fillCourseIdFilter().
            courseIdInput.addEventListener('change', function() {
                const courseId = this.value.trim();
                const currentUrl = new URL(window.location);
                
                if (courseId) {
                    currentUrl.searchParams.set('course_id', courseId);
                } else {
                    currentUrl.searchParams.delete('course_id');
                }
                
                // Przeładowujemy stronę z nowym filtrem
                window.location.href = currentUrl.toString();
            });
            courseIdInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.dispatchEvent(new Event('change'));
                }
            });

            // Licznik rekordów wg filtrów — po wczytaniu strony (nie blokuje Poprzednie/Następne)
            loadNavigationFilterCount();
        });

        function loadNavigationFilterCount() {
            const badge = document.getElementById('navigationFilterCountBadge');
            if (!badge) {
                return;
            }
            const url = new URL(badge.dataset.countUrl || '/form-orders/navigation-filter-count', window.location.origin);
            const pageUrl = new URL(window.location.href);
            ['filter_no_participant', 'filter_no_invoice', 'filter_no_ksef', 'filter_payment_gateway', 'filter_new'].forEach(function (key) {
                if (pageUrl.searchParams.get(key) === '1') {
                    url.searchParams.set(key, '1');
                }
            });
            const courseId = pageUrl.searchParams.get('course_id')
                || (document.getElementById('courseIdFilter')?.value || '').trim();
            if (courseId) {
                url.searchParams.set('course_id', courseId);
            }

            badge.textContent = '…';
            badge.classList.remove('text-bg-primary', 'text-bg-warning');
            badge.classList.add('text-bg-secondary');

            fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    const count = Number(data.count || 0);
                    badge.textContent = String(count);
                    const hasFilter = !!(data.filter_no_participant || data.filter_no_invoice || data.filter_no_ksef || data.filter_payment_gateway || data.course_id);
                    badge.classList.remove('text-bg-secondary', 'text-bg-primary', 'text-bg-warning');
                    badge.classList.add(hasFilter ? 'text-bg-primary' : 'text-bg-secondary');
                    let title = 'Zamówień w zakresie nawigacji: ' + count;
                    if (data.course_id) {
                        title += ' · szkolenie #' + data.course_id;
                    }
                    if (data.filter_no_participant) {
                        title += ' · bez wprowadzonego uczestnika';
                    }
                    if (data.filter_no_invoice) {
                        title += ' · bez wystawionej faktury';
                    }
                    if (data.filter_no_ksef) {
                        title += ' · tylko z NIP bez KSeF';
                    }
                    if (data.filter_payment_gateway) {
                        title += ' · bramka płatności';
                    }
                    badge.title = title;
                })
                .catch(function () {
                    badge.textContent = '—';
                    badge.title = 'Nie udało się pobrać liczby zamówień';
                });
        }

        // Inicjalizacja tooltipów Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Zapamiętywanie checkboxów iFirma w preferencjach użytkownika (per operator, baza users.preferences)
        function initializeEmailCheckboxes() {
            const PARTICIPANT_REMARKS_KEY = 'ifirma_include_participant_in_remarks';

            const sendEmailCheckboxConfig = [
                { id: 'sendEmailCheckboxProforma', key: 'ifirma_send_email_proforma' },
                { id: 'sendEmailCheckboxInvoice', key: 'ifirma_send_email_invoice' },
                { id: 'sendEmailCheckboxInvoiceWithKsef', key: 'ifirma_send_email_invoice_with_ksef' },
                { id: 'sendEmailCheckboxInvoiceWithReceiver', key: 'ifirma_send_email_invoice_with_receiver' },
            ];
            const participantRemarksCheckbox = document.getElementById('ifirma_include_participant_in_remarks');

            const sendEmailEntries = sendEmailCheckboxConfig
                .map(function (entry) {
                    return {
                        key: entry.key,
                        element: document.getElementById(entry.id),
                    };
                })
                .filter(function (entry) { return entry.element; });

            if (sendEmailEntries.length === 0 && !participantRemarksCheckbox) {
                return;
            }

            // Domyślnie zaznaczony — uzupełnij uwagi od razu (zanim dojdą preferencje z API)
            if (participantRemarksCheckbox && !participantRemarksCheckbox.disabled && participantRemarksCheckbox.checked) {
                applyParticipantInRemarks();
            }

            function loadPreferences() {
                return new Promise(function (resolve) {
                    var csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) {
                        resolve({});
                        return;
                    }

                    fetch('/api/user/preferences', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (response) {
                            return response.ok ? response.json() : { preferences: {} };
                        })
                        .then(function (data) {
                            resolve(data.preferences || {});
                        })
                        .catch(function () {
                            resolve({});
                        });
                });
            }

            function savePreference(key, value) {
                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    return;
                }

                fetch('/api/user/preferences', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ key: key, value: value }),
                }).catch(function () {});
            }

            loadPreferences().then(function (preferences) {
                sendEmailEntries.forEach(function (entry) {
                    if (preferences[entry.key] !== undefined) {
                        entry.element.checked = !!preferences[entry.key];
                    }

                    entry.element.addEventListener('change', function () {
                        savePreference(entry.key, this.checked);
                    });
                });

                // Checkbox „Dodaj w uwagach faktury UCZESTNIKÓW” — domyślnie zaznaczony, potem ostatni stan admina
                if (participantRemarksCheckbox && !participantRemarksCheckbox.disabled) {
                    var savedParticipantRemarks = preferences[PARTICIPANT_REMARKS_KEY];
                    participantRemarksCheckbox.checked = savedParticipantRemarks === undefined
                        ? true
                        : !!savedParticipantRemarks;
                    applyParticipantInRemarks();

                    participantRemarksCheckbox.addEventListener('change', function () {
                        applyParticipantInRemarks();
                        savePreference(PARTICIPANT_REMARKS_KEY, this.checked);
                    });
                } else if (participantRemarksCheckbox) {
                    participantRemarksCheckbox.addEventListener('change', applyParticipantInRemarks);
                }
            });
        }
        
        // Wywołaj inicjalizację po załadowaniu DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeEmailCheckboxes);
        } else {
            // DOM już załadowany, wywołaj od razu
            initializeEmailCheckboxes();
        }

        // Obsługa resetowania przycisku w modalu KSeF po zamknięciu
        const ksefModalElement = document.getElementById('ksefWarningModal');
        if (ksefModalElement) {
            ksefModalElement.addEventListener('hidden.bs.modal', function () {
                // Resetuj przycisk po zamknięciu modala (jeśli użytkownik anulował)
                const button = document.getElementById('ifirmaInvoiceWithReceiverBtn');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma z Odbiorcą';
                }
            });
        }

        // KSeF Podmiot3 — automatyczny zapis w karcie DANE DO FAKTURY (bez przeładowania strony)
        (function initKsefInlineSettings() {
            const formRoot = document.getElementById('ksefSettingsForm');
            if (!formRoot) {
                return;
            }

            const saveUrl = formRoot.dataset.saveUrl;
            const statusBadge = document.getElementById('ksefSaveStatus');
            const errorsBox = document.getElementById('ksefValidationErrors');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            let saveTimer = null;
            let saveInFlight = false;
            let pendingSave = false;

            function setStatus(text, variant) {
                if (!statusBadge) {
                    return;
                }
                statusBadge.textContent = text;
                statusBadge.className = 'badge bg-' + (variant || 'secondary');
            }

            function isRecipientSourceEnabled() {
                const el = document.getElementById('show_ksef_entity_source');
                return !!(el && el.checked);
            }

            function ensureRecipientSourceForSpecialRoles() {
                const role = document.getElementById('show_ksef_additional_entity_role')?.value;
                const sourceEl = document.getElementById('show_ksef_entity_source');
                if (!sourceEl) {
                    return;
                }
                if ((role === 'jst_recipient' || role === 'vat_group_member') && !sourceEl.checked) {
                    sourceEl.checked = true;
                }
            }

            function updateRoleHints() {
                const role = document.getElementById('show_ksef_additional_entity_role')?.value;
                const idType = document.getElementById('show_ksef_additional_entity_id_type')?.value;
                const isRecipient = isRecipientSourceEnabled();

                document.getElementById('ksefRoleHintJst')?.classList.toggle('d-none', !(isRecipient && role === 'jst_recipient'));
                document.getElementById('ksefRoleHintVat')?.classList.toggle('d-none', !(isRecipient && role === 'vat_group_member'));
                // Obsługiwane: brak / NIP / IDWew — ostrzeżenie tylko dla PESEL, BrakID itd.
                const idTypeSupported = !idType || idType === '' || idType === 'NIP' || idType === 'IDWew';
                document.getElementById('ksefIdTypeWarning')?.classList.toggle(
                    'd-none',
                    !(isRecipient && idType && !idTypeSupported)
                );
            }

            function showValidationErrors(errors) {
                if (!errorsBox) {
                    return;
                }
                const messages = [];
                Object.keys(errors).forEach(function (key) {
                    (errors[key] || []).forEach(function (msg) {
                        messages.push(msg);
                    });
                });
                if (messages.length === 0) {
                    errorsBox.classList.add('d-none');
                    errorsBox.innerHTML = '';
                    return;
                }
                errorsBox.classList.remove('d-none');
                errorsBox.innerHTML = '<strong>Błąd walidacji:</strong><ul class="mb-0 mt-1"><li>'
                    + messages.map(function (m) { return escapeHtml(m); }).join('</li><li>')
                    + '</li></ul>';
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function collectPayload() {
                return {
                    ksef_entity_source: isRecipientSourceEnabled() ? 'recipient' : 'none',
                    ksef_additional_entity_role: document.getElementById('show_ksef_additional_entity_role')?.value || null,
                    ksef_additional_entity_id_type: document.getElementById('show_ksef_additional_entity_id_type')?.value || null,
                    ksef_additional_entity_identifier: document.getElementById('show_ksef_additional_entity_identifier')?.value?.trim() || null,
                    ksef_admin_note: document.getElementById('show_ksef_admin_note')?.value?.trim() || null,
                };
            }

            function saveKsefSettings() {
                if (saveInFlight) {
                    pendingSave = true;
                    return;
                }
                saveInFlight = true;
                pendingSave = false;
                setStatus('Zapisywanie…', 'info');
                if (errorsBox) {
                    errorsBox.classList.add('d-none');
                }

                fetch(saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(collectPayload()),
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data.success) {
                            setStatus('Zapisano', 'success');
                            window.setTimeout(function () {
                                if (statusBadge && statusBadge.textContent === 'Zapisano') {
                                    setStatus('—', 'secondary');
                                }
                            }, 2500);
                            return;
                        }
                        if (result.status === 422 && result.data.errors) {
                            setStatus('Błąd', 'danger');
                            showValidationErrors(result.data.errors);
                            return;
                        }
                        setStatus('Błąd', 'danger');
                        showValidationErrors({ _: [result.data.message || 'Nie udało się zapisać ustawień KSeF.'] });
                    })
                    .catch(function () {
                        setStatus('Błąd', 'danger');
                        showValidationErrors({ _: ['Błąd połączenia z serwerem.'] });
                    })
                    .finally(function () {
                        saveInFlight = false;
                        if (pendingSave) {
                            saveKsefSettings();
                        }
                    });
            }

            function scheduleSave() {
                ensureRecipientSourceForSpecialRoles();
                updateRoleHints();
                if (saveTimer) {
                    window.clearTimeout(saveTimer);
                }
                setStatus('Zmieniono…', 'warning');
                saveTimer = window.setTimeout(saveKsefSettings, 450);
            }

            formRoot.querySelectorAll('[data-ksef-field]').forEach(function (el) {
                el.addEventListener('change', scheduleSave);
                if (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && el.type !== 'checkbox')) {
                    el.addEventListener('input', scheduleSave);
                }
            });

            updateRoleHints();
        })();
    </script>

    {{-- Modal ostrzeżenia o KSeF przed wystawieniem faktury z automatycznym e-mailem --}}
    <div class="modal fade" id="ksefWarningModal" tabindex="-1" aria-labelledby="ksefWarningModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="ksefWarningModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Uwaga - Wysyłka faktury na e-mail bez KSeF
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Zaznaczono opcję automatycznego wysłania faktury na e-mail dla zamówienia <strong>#{{ $zamowienie->id }}</strong>.</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Uwaga:</strong> Zostanie wystawiona faktura w iFirma i automatycznie wysłana na e-mail, <strong>bez późniejszej możliwości wysłania jej do KSeF</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" id="ksefWarningConfirmBtn">
                        <i class="bi bi-check-circle"></i> Kontynuuj - Wystaw fakturę i wyślij na e-mail
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Ostrzeżenie przed akcją: bramka online i/lub uwagi zamawiającego do FV --}}
    <div class="modal fade" id="formOrderPreActionWarningModal" tabindex="-1" aria-labelledby="formOrderPreActionWarningModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="formOrderPreActionWarningModalLabel">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span id="formOrderPreActionWarningTitle">Uwaga</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div id="preActionWarningUnpaidOnlineSection">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-credit-card-2-front"></i> Płatność online
                        </h6>
                        <p class="mb-2 small">
                            Zamówienie <strong>#{{ $zamowienie->id }}</strong> jest przez bramkę online, ale status płatności
                            <strong>nie jest „Opłacone”</strong>.
                        </p>
                        <div class="border rounded p-2 bg-light small mb-2">
                            <div><strong>Rozliczenie:</strong> <span id="unpaidOnlinePaymentWarningMode">{{ $zamowienie->paymentModeLabelWithGateway() }}</span></div>
                            <div><strong>Status płatności:</strong> <span id="unpaidOnlinePaymentWarningStatus">{{ \App\Models\FormOrder::paymentStatusLabel($zamowienie->payment_status) }}</span></div>
                        </div>
                        <div class="alert alert-warning mb-0 small">
                            <i class="bi bi-info-circle"></i>
                            Wystawienie faktury przy statusie „w trakcie” / „Anulowane” / błędzie płatności
                            może skutkować FV bez potwierdzonej wpłaty. Kontynuuj tylko świadomie.
                        </div>
                    </div>

                    <hr id="preActionWarningSectionsSeparator" class="my-3 d-none">

                    <div id="preActionWarningInvoiceNotesSection">
                        <h6 class="fw-semibold mb-2 text-danger">
                            <i class="bi bi-chat-left-text-fill"></i> Uwagi zamawiającego do faktury
                        </h6>
                        <div class="border border-danger border-2 rounded p-3 bg-light mb-2">
                            <pre id="preActionWarningInvoiceNotesText" class="mb-0 small text-danger" style="white-space: pre-wrap; font-family: inherit;">{{ trim((string) $zamowienie->invoice_notes) }}</pre>
                        </div>
                        <p class="small text-muted mb-0">
                            Sprawdź treść uwag przed wystawieniem dokumentu w iFirma (np. odbiorca, JST, termin, inne wymagania).
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Wróć
                    </button>
                    <button type="button" class="btn btn-warning" id="formOrderPreActionWarningConfirmBtn">
                        <i class="bi bi-exclamation-triangle"></i> Mimo to kontynuuj
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal ostrzeżenia przed wystawieniem faktury gdy invoice_number jest już wypełnione --}}
    <div class="modal fade" id="invoiceWarningModal" tabindex="-1" aria-labelledby="invoiceWarningModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="invoiceWarningModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Uwaga - Numer faktury już istnieje
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Dla zamówienia <strong>#{{ $zamowienie->id }}</strong> w polu "Numer faktury" jest już wpisany numer:</p>
                    <div class="bg-light p-3 rounded mb-3">
                        <h6 class="mb-2"><strong>Obecny numer faktury:</strong></h6>
                        <p class="mb-0 fs-5"><code id="currentInvoiceNumberDisplay">{{ $zamowienie->invoice_number }}</code></p>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Uwaga:</strong> Wystawienie nowej faktury przez API iFirma spowoduje nadpisanie tego numeru nowym numerem z iFirma. 
                        Upewnij się, że chcesz kontynuować.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" id="invoiceWarningConfirmBtn">
                        <i class="bi bi-file-earmark-text"></i> Mimo to wystaw fakturę
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($zamowienie->activeDebtCases->isEmpty() && $zamowienie->hasIssuedInvoice())
    <div class="modal fade" id="createDebtCaseModal" tabindex="-1" aria-labelledby="createDebtCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('accounting.collections.store') }}">
                    @csrf
                    <input type="hidden" name="form_order_id" value="{{ $zamowienie->id }}">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="createDebtCaseModalLabel">
                            <i class="bi bi-exclamation-octagon"></i> Utworzyć sprawę windykacyjną?
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Zostanie otwarta sprawa windykacyjna dla zamówienia <strong>#{{ $zamowienie->id }}</strong>.</p>
                        <div class="border rounded p-2 bg-light small mb-3">
                            <div><strong>FV:</strong> {{ $zamowienie->invoice_number ?: '—' }}</div>
                            <div><strong>Kwota:</strong> {{ number_format((float) $zamowienie->product_price, 2, ',', ' ') }} zł</div>
                            <div>
                                <strong>Termin płatności:</strong>
                                {{ $zamowienie->invoice_due_date?->format('d.m.Y') ?: '—' }}
                            </div>
                        </div>
                        <div class="alert alert-warning small mb-0">
                            Twórz sprawę tylko gdy faktura wymaga ponaglenia lub ręcznej weryfikacji płatności.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-plus-circle"></i> Utwórz sprawę
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal potwierdzenia resetowania statusu Publigo --}}
    <div class="modal fade" id="resetPubligoModal" tabindex="-1" aria-labelledby="resetPubligoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="resetPubligoModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Resetowanie statusu Publigo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz zresetować status Publigo dla zamówienia <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły zamówienia:</h6>
                        <ul class="mb-0">
                            <li><strong>Uczestnik:</strong> {{ $zamowienie->display_participant_name }}</li>
                            <li><strong>Email:</strong> {{ $zamowienie->display_participant_email }}</li>
                            <li><strong>Szkolenie:</strong> {{ $zamowienie->display_product_name }}</li>
                            @if($zamowienie->publigo_sent_at)
                                <li><strong>Data wysłania do Publigo:</strong> {{ $zamowienie->publigo_sent_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}</li>
                            @endif
                        </ul>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Uwaga:</strong> Resetowanie statusu pozwoli na ponowne wysłanie zamówienia do Publigo przez API. 
                        Użyj tej opcji tylko gdy zamówienie zostało usunięte z Publigo lub gdy trzeba je dodać ponownie.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" id="resetPubligoConfirmBtn" onclick="resetPubligoStatus({{ $zamowienie->id }})">
                        <i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Ponowna wysyłka e-maila z dostępem PNEDU (krok 3) --}}
    <div class="modal fade" id="resendPneduAccessModal" tabindex="-1" aria-labelledby="resendPneduAccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resendPneduAccessModalLabel">
                        <i class="bi bi-envelope"></i> Prześlij dostęp ponownie — Krok 3: E-mail do uczestnika
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div id="resendPneduAccessLoading" class="text-muted small py-3 text-center">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Ładowanie podglądu wiadomości…
                    </div>
                    <div id="resendPneduAccessError" class="alert alert-danger d-none" role="alert"></div>
                    <div id="resendPneduAccessSuccess" class="alert alert-success d-none" role="alert"></div>
                    <div id="resendPneduAccessContent" class="d-none">
                        <p class="small text-muted mb-2" id="resendPneduAccessVariant"></p>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="resendPneduAccessTo">Do</label>
                            <input type="text" class="form-control form-control-sm" id="resendPneduAccessTo" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="resendPneduAccessSubject">Temat</label>
                            <input type="text" class="form-control form-control-sm" id="resendPneduAccessSubject" readonly>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0" for="resendPneduAccessBodyFrame">Treść (podgląd HTML)</label>
                                <span class="small text-muted">„Skopiuj treść” = HTML (z formatowaniem)</span>
                            </div>
                            <iframe id="resendPneduAccessBodyFrame"
                                    title="Podgląd wiadomości e-mail"
                                    class="w-100 border rounded bg-white"
                                    style="height: 28rem;"
                                    sandbox=""></iframe>
                            <textarea class="d-none" id="resendPneduAccessBody" readonly aria-hidden="true"></textarea>
                        </div>
                        <p class="small text-muted mt-2 mb-0" id="resendPneduAccessHint"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-outline-primary" id="resendPneduAccessCopyBtn" disabled>
                        <i class="bi bi-clipboard"></i> Skopiuj treść
                    </button>
                    <button type="button" class="btn btn-primary" id="resendPneduAccessSendBtn" disabled>
                        <i class="bi bi-send"></i> Wyślij
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal potwierdzenia resetowania statusu PNEDU (per uczestnik / wszyscy) --}}
    <div class="modal fade" id="resetPneduModal" tabindex="-1" aria-labelledby="resetPneduModalLabel" aria-hidden="true"
         data-has-cm-token="0"
         data-reset-all="0"
         data-form-order-participant-id="">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="resetPneduModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Wycofaj dostęp PNEDU
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="resetPneduIntro">Czy na pewno chcesz wycofać dostęp PNEDU?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły:</h6>
                        <ul class="mb-0">
                            <li><strong>Zamówienie:</strong> #{{ $zamowienie->id }}</li>
                            <li><strong>Uczestnik:</strong> <span id="resetPneduParticipantName">—</span></li>
                            <li id="resetPneduEmailRow"><strong>Email:</strong> <span id="resetPneduParticipantEmail">—</span></li>
                            <li><strong>Szkolenie:</strong> {{ $zamowienie->display_product_name }}</li>
                            @if($zamowienie->pnedu_provisioned_at)
                                <li><strong>Data przyznania dostępu PNEDU (zamówienie):</strong> {{ $zamowienie->pnedu_provisioned_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}</li>
                            @endif
                        </ul>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Uwaga:</strong> Reset czyści status PNEDU przy zamówieniu (data provision, kroki ClickMeeting w panelu).
                        Opcjonalnie możesz też usunąć uczestnika z listy szkolenia i unieważnić token dostępowy ClickMeeting.
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="resetPneduRemoveParticipantCheckbox" checked>
                        <label class="form-check-label" for="resetPneduRemoveParticipantCheckbox"
                               id="resetPneduRemoveParticipantLabel">
                            Czy usunąć uczestnika ze szkolenia?
                        </label>
                    </div>
                    <div id="resetPneduError" class="alert alert-danger py-2 small mt-2 mb-0 d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" id="resetPneduConfirmBtn" onclick="resetPneduStatus({{ $zamowienie->id }})">
                        <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal zwolnienia z faktury (bezpłatny dostęp) --}}
    <div class="modal fade" id="invoiceExemptModal" tabindex="-1" aria-labelledby="invoiceExemptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-dark">
                    <h5 class="modal-title" id="invoiceExemptModalLabel"><i class="bi bi-gift"></i> Bezpłatny dostęp — bez faktury</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Zamówienie zostanie uznane za <strong>rozliczone bez FV</strong> (np. bezpłatny dostęp, promocja, voucher wewnętrzny).</p>
                    <p class="small text-muted mb-3">Uczestnik nadal musi być na szkoleniu — to oznaczenie zastępuje tylko wymóg wystawienia faktury. Status „Przetworzone” pojawi się po dodaniu uczestnika.</p>
                    <label for="invoiceExemptReason" class="form-label small">Powód (opcjonalnie)</label>
                    <input type="text" class="form-control form-control-sm" id="invoiceExemptReason" maxlength="255" placeholder="np. bezpłatny dostęp, promocja">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-info" id="confirmInvoiceExemptBtn" data-order-id="{{ $zamowienie->id }}">
                        Oznacz bez faktury
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal cofnięcia zwolnienia z faktury --}}
    <div class="modal fade" id="clearInvoiceExemptModal" tabindex="-1" aria-labelledby="clearInvoiceExemptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="clearInvoiceExemptModalLabel"><i class="bi bi-receipt-cutoff"></i> Cofnij „bez faktury”</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Oznaczenie <strong>bezpłatny dostęp — bez FV</strong> zostanie usunięte.</p>
                    @if($zamowienie->isInvoiceExempt())
                        <p class="small mb-2">
                            <strong>Oznaczono:</strong>
                            {{ $zamowienie->invoice_exempt_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                            @if($zamowienie->invoice_exempt_reason)
                                <br><strong>Powód:</strong> {{ $zamowienie->invoice_exempt_reason }}
                            @endif
                        </p>
                    @endif
                    <div class="alert alert-warning small mb-0 py-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        Zamówienie wróci do statusu <strong>Do wystawienia FV</strong>, jeśli uczestnik jest już dodany do szkolenia.
                        Dostęp uczestnika <strong>nie zostanie usunięty</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-warning" id="confirmClearInvoiceExemptBtn" data-order-id="{{ $zamowienie->id }}">
                        Cofnij oznaczenie
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal recovery e-mail płatności online (podgląd + wysyłka) --}}
    <div class="modal fade" id="sendOnlinePaymentRecoveryModal" tabindex="-1" aria-labelledby="sendOnlinePaymentRecoveryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="sendOnlinePaymentRecoveryModalLabel"><i class="bi bi-envelope"></i> Wyślij mail recovery płatności</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div id="sendOnlinePaymentRecoveryLoading" class="text-muted small py-3 text-center">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Ładowanie podglądu wiadomości…
                    </div>
                    <div id="sendOnlinePaymentRecoveryError" class="alert alert-danger d-none" role="alert"></div>
                    <div id="sendOnlinePaymentRecoverySuccess" class="alert alert-success d-none" role="alert"></div>
                    <div id="sendOnlinePaymentRecoveryContent" class="d-none">
                        <p class="small text-muted mb-2">Klient otrzyma e-mail z linkiem <strong>Zapłać ponownie</strong> oraz opcją <strong>faktury z odroczonym terminem</strong>. Wysyłka z pnedu.pl.</p>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="sendOnlinePaymentRecoveryTo">Do</label>
                            <input type="text" class="form-control form-control-sm" id="sendOnlinePaymentRecoveryTo" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1" for="sendOnlinePaymentRecoverySubject">Temat</label>
                            <input type="text" class="form-control form-control-sm" id="sendOnlinePaymentRecoverySubject" readonly>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0" for="sendOnlinePaymentRecoveryBodyFrame">Treść (podgląd HTML)</label>
                                <span class="small text-muted">„Skopiuj treść” = HTML (z formatowaniem)</span>
                            </div>
                            <iframe id="sendOnlinePaymentRecoveryBodyFrame"
                                    title="Podgląd wiadomości e-mail recovery"
                                    class="w-100 border rounded bg-white"
                                    style="height: 28rem;"
                                    sandbox=""></iframe>
                            <textarea class="d-none" id="sendOnlinePaymentRecoveryBody" readonly aria-hidden="true"></textarea>
                        </div>
                        <p class="small text-muted mt-2 mb-0" id="sendOnlinePaymentRecoveryHint"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-outline-primary" id="sendOnlinePaymentRecoveryCopyBtn" disabled>
                        <i class="bi bi-clipboard"></i> Skopiuj treść
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmSendOnlinePaymentRecoveryBtn" data-order-id="{{ $zamowienie->id }}" disabled>
                        <i class="bi bi-send"></i> Wyślij
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal anulowania zamówienia --}}
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelOrderModalLabel"><i class="bi bi-x-circle"></i> Anuluj zamówienie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Zamówienie zostanie oznaczone jako anulowane i zniknie z listy „Nieprzetworzone”.</p>
                    <p class="small text-muted mb-3">Uczestnicy powiązani przez <code>participant_id</code> zostaną wypisani ze szkolenia. Pozostali wymagają ręcznej kontroli.</p>
                    <label for="cancelOrderReason" class="form-label small">Powód (opcjonalnie)</label>
                    <input type="text" class="form-control form-control-sm" id="cancelOrderReason" maxlength="255" placeholder="np. duplikat, rezygnacja telefoniczna">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelOrderBtn" data-order-id="{{ $zamowienie->id }}">
                        Anuluj zamówienie
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal przywracania zamówienia --}}
    <div class="modal fade" id="restoreOrderModal" tabindex="-1" aria-labelledby="restoreOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="restoreOrderModalLabel"><i class="bi bi-arrow-counterclockwise"></i> Przywróć zamówienie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Anulowanie zostanie cofnięte — zamówienie wróci do normalnej obsługi operacyjnej.</p>
                    @if($zamowienie->cancelled_at)
                        <p class="small mb-2">
                            <strong>Anulowano:</strong>
                            {{ $zamowienie->cancelled_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                            @if($zamowienie->cancelled_reason)
                                <br><strong>Powód:</strong> {{ $zamowienie->cancelled_reason }}
                            @endif
                        </p>
                    @endif
                    <div class="alert alert-warning small mb-0 py-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        Uczestnicy <strong>nie zostaną automatycznie przywróceni</strong> na szkolenie.
                        Jeśli potrzeba dostępu — dodaj ich ponownie (np. „Dodaj do PNEDU” lub ręcznie na liście uczestników).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-success" id="confirmRestoreOrderBtn" data-order-id="{{ $zamowienie->id }}">
                        Przywróć zamówienie
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal potwierdzenia usunięcia --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć zamówienie <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły zamówienia:</h6>
                        <ul class="mb-0">
                            <li><strong>Uczestnik:</strong> {{ $zamowienie->display_participant_name }}</li>
                            <li><strong>Email:</strong> {{ $zamowienie->display_participant_email }}</li>
                            <li><strong>Szkolenie:</strong> {{ $zamowienie->display_product_name }}</li>
                            @php
                                $orderDateFormatted = $zamowienie->formatOrderDateLocal() ?? '—';
                            @endphp
                            <li><strong>Data:</strong> {{ $orderDateFormatted ?? '—' }}</li>
                            <li><strong>Status:</strong> {{ $zamowienie->is_new ? 'Niewprowadzone' : 'Wprowadzone' }}</li>
                            <li><strong>Numer faktury:</strong> {{ $zamowienie->invoice_number ?: 'Brak' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Zamówienie zostanie przeniesione do kosza (soft delete) i będzie można je przywrócić.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('form-orders.destroy', $zamowienie->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń zamówienie
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prześlij dostęp — musi być PO HTML modala (#resendPneduAccessModal); per uczestnik
        (function () {
            const modalEl = document.getElementById('resendPneduAccessModal');
            if (!modalEl) return;

            const loadingEl = document.getElementById('resendPneduAccessLoading');
            const errorEl = document.getElementById('resendPneduAccessError');
            const successEl = document.getElementById('resendPneduAccessSuccess');
            const contentEl = document.getElementById('resendPneduAccessContent');
            const toEl = document.getElementById('resendPneduAccessTo');
            const subjectEl = document.getElementById('resendPneduAccessSubject');
            const bodyEl = document.getElementById('resendPneduAccessBody');
            const bodyFrameEl = document.getElementById('resendPneduAccessBodyFrame');
            const variantEl = document.getElementById('resendPneduAccessVariant');
            const hintEl = document.getElementById('resendPneduAccessHint');
            const copyBtn = document.getElementById('resendPneduAccessCopyBtn');
            const sendBtn = document.getElementById('resendPneduAccessSendBtn');
            const titleEl = document.getElementById('resendPneduAccessModalLabel');
            let lastBodyHtml = '';
            let activeTrigger = null;

            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }

            function resetUi() {
                loadingEl.classList.remove('d-none');
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                successEl.classList.add('d-none');
                successEl.textContent = '';
                contentEl.classList.add('d-none');
                copyBtn.disabled = true;
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                toEl.value = '';
                subjectEl.value = '';
                bodyEl.value = '';
                lastBodyHtml = '';
                if (bodyFrameEl) {
                    bodyFrameEl.removeAttribute('srcdoc');
                    bodyFrameEl.src = 'about:blank';
                }
                variantEl.textContent = '';
                hintEl.textContent = '';
            }

            function copyText(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } finally { document.body.removeChild(ta); }
                return Promise.resolve();
            }

            async function copyHtmlContent(html, plain) {
                const htmlPayload = html || '';
                const plainPayload = plain || htmlPayload.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                if (navigator.clipboard && typeof ClipboardItem !== 'undefined') {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                'text/html': new Blob([htmlPayload], { type: 'text/html' }),
                                'text/plain': new Blob([plainPayload], { type: 'text/plain' }),
                            }),
                        ]);
                        return;
                    } catch (e) {
                        // np. Safari / uprawnienia — spadnij do źródła HTML
                    }
                }
                await copyText(htmlPayload);
            }

            modalEl.addEventListener('show.bs.modal', async function (event) {
                activeTrigger = event.relatedTarget && event.relatedTarget.classList.contains('js-resend-pnedu-access-btn')
                    ? event.relatedTarget
                    : document.querySelector('.js-resend-pnedu-access-btn');
                if (!activeTrigger) {
                    return;
                }

                resetUi();
                const participantName = activeTrigger.getAttribute('data-participant-name') || '';
                if (titleEl) {
                    titleEl.innerHTML = participantName
                        ? '<i class="bi bi-envelope"></i> Prześlij dostęp ponownie — ' + participantName
                        : '<i class="bi bi-envelope"></i> Prześlij dostęp ponownie — Krok 3: E-mail do uczestnika';
                }

                const previewUrl = activeTrigger.getAttribute('data-preview-url');
                const controller = new AbortController();
                const timeoutId = setTimeout(function () { controller.abort(); }, 15000);
                try {
                    const res = await fetch(previewUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                        credentials: 'same-origin',
                    });
                    clearTimeout(timeoutId);
                    const data = await res.json().catch(function () { return {}; });
                    loadingEl.classList.add('d-none');
                    if (!res.ok || !data.success) {
                        errorEl.textContent = data.error || ('Błąd ' + res.status + ' — sprawdź route:cache dla access-email-preview.');
                        errorEl.classList.remove('d-none');
                        return;
                    }
                    toEl.value = data.to || '';
                    subjectEl.value = data.subject || '';
                    bodyEl.value = data.body || '';
                    lastBodyHtml = data.body_html || '';
                    if (bodyFrameEl) {
                        if (lastBodyHtml) {
                            bodyFrameEl.srcdoc = lastBodyHtml;
                        } else {
                            const esc = String(data.body || '')
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;');
                            bodyFrameEl.srcdoc = '<pre style="font-family:system-ui,sans-serif;padding:1rem;white-space:pre-wrap;margin:0;">' + esc + '</pre>';
                        }
                    }
                    const nameLabel = data.participant_name || participantName;
                    variantEl.textContent = (nameLabel ? (nameLabel + ' — ') : '')
                        + (data.variant_label
                            ? ('Krok 3: E-mail do uczestnika — ' + data.variant_label)
                            : 'Krok 3: E-mail do uczestnika');
                    hintEl.textContent = data.variant === 'new_user'
                        ? 'Przy wysyłce zostanie wygenerowany świeży link do ustawienia hasła (w podglądzie jest placeholder).'
                        : 'Zostanie wysłany ten sam typ wiadomości co przy pierwotnym przyznaniu dostępu.';
                    contentEl.classList.remove('d-none');
                    copyBtn.disabled = false;
                    sendBtn.disabled = false;
                } catch (e) {
                    clearTimeout(timeoutId);
                    loadingEl.classList.add('d-none');
                    errorEl.textContent = (e && e.name === 'AbortError')
                        ? 'Podgląd nie odpowiedział w 15 s. Odśwież stronę i spróbuj ponownie.'
                        : 'Nie udało się pobrać podglądu wiadomości.';
                    errorEl.classList.remove('d-none');
                }
            });

            copyBtn.addEventListener('click', function () {
                const htmlToCopy = lastBodyHtml
                    || ('<p><strong>Temat:</strong> ' + String(subjectEl.value)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</p><pre>'
                        + String(bodyEl.value).replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</pre>');
                const plainToCopy = 'Temat: ' + subjectEl.value + '\n\n' + bodyEl.value;
                copyHtmlContent(htmlToCopy, plainToCopy).then(function () {
                    const prev = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="bi bi-check2"></i> Skopiowano HTML';
                    setTimeout(function () { copyBtn.innerHTML = prev; }, 1500);
                });
            });

            sendBtn.addEventListener('click', async function () {
                if (!activeTrigger) {
                    return;
                }
                const sendUrl = activeTrigger.getAttribute('data-send-url');
                const fopId = activeTrigger.getAttribute('data-form-order-participant-id');
                sendBtn.disabled = true;
                copyBtn.disabled = true;
                sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Wysyłanie…';
                errorEl.classList.add('d-none');
                successEl.classList.add('d-none');
                try {
                    const payload = {};
                    if (fopId) {
                        payload.form_order_participant_id = Number(fopId);
                    }
                    const res = await fetch(sendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok || !data.success) {
                        errorEl.textContent = data.error || ('Błąd ' + res.status);
                        errorEl.classList.remove('d-none');
                        sendBtn.disabled = false;
                        copyBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                        return;
                    }
                    successEl.textContent = data.message || 'Wiadomość została wysłana.';
                    successEl.classList.remove('d-none');
                    sendBtn.innerHTML = '<i class="bi bi-check2"></i> Wysłano';
                    copyBtn.disabled = false;
                } catch (e) {
                    errorEl.textContent = 'Nie udało się wysłać wiadomości.';
                    errorEl.classList.remove('d-none');
                    sendBtn.disabled = false;
                    copyBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                }
            });
        })();

        (function () {
            const modalEl = document.getElementById('sendOnlinePaymentRecoveryModal');
            if (!modalEl) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const orderId = document.getElementById('confirmSendOnlinePaymentRecoveryBtn')?.dataset.orderId;
            const loadingEl = document.getElementById('sendOnlinePaymentRecoveryLoading');
            const errorEl = document.getElementById('sendOnlinePaymentRecoveryError');
            const successEl = document.getElementById('sendOnlinePaymentRecoverySuccess');
            const contentEl = document.getElementById('sendOnlinePaymentRecoveryContent');
            const toEl = document.getElementById('sendOnlinePaymentRecoveryTo');
            const subjectEl = document.getElementById('sendOnlinePaymentRecoverySubject');
            const bodyEl = document.getElementById('sendOnlinePaymentRecoveryBody');
            const bodyFrameEl = document.getElementById('sendOnlinePaymentRecoveryBodyFrame');
            const hintEl = document.getElementById('sendOnlinePaymentRecoveryHint');
            const copyBtn = document.getElementById('sendOnlinePaymentRecoveryCopyBtn');
            const sendBtn = document.getElementById('confirmSendOnlinePaymentRecoveryBtn');
            let lastBodyHtml = '';

            function resetUi() {
                loadingEl?.classList.remove('d-none');
                errorEl?.classList.add('d-none');
                if (errorEl) errorEl.textContent = '';
                successEl?.classList.add('d-none');
                if (successEl) successEl.textContent = '';
                contentEl?.classList.add('d-none');
                if (copyBtn) copyBtn.disabled = true;
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                }
                if (toEl) toEl.value = '';
                if (subjectEl) subjectEl.value = '';
                if (bodyEl) bodyEl.value = '';
                lastBodyHtml = '';
                if (bodyFrameEl) {
                    bodyFrameEl.removeAttribute('srcdoc');
                    bodyFrameEl.src = 'about:blank';
                }
                if (hintEl) hintEl.textContent = '';
            }

            async function copyHtmlContent(html, plain) {
                const htmlPayload = html || '';
                const plainPayload = plain || htmlPayload.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                if (navigator.clipboard && typeof ClipboardItem !== 'undefined') {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                'text/html': new Blob([htmlPayload], { type: 'text/html' }),
                                'text/plain': new Blob([plainPayload], { type: 'text/plain' }),
                            }),
                        ]);
                        return;
                    } catch (e) {}
                }
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(plainPayload || htmlPayload);
                    return;
                }
                const ta = document.createElement('textarea');
                ta.value = plainPayload || htmlPayload;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } finally { document.body.removeChild(ta); }
            }

            modalEl.addEventListener('show.bs.modal', async function () {
                resetUi();
                if (!orderId) {
                    loadingEl?.classList.add('d-none');
                    if (errorEl) {
                        errorEl.textContent = 'Brak ID zamówienia.';
                        errorEl.classList.remove('d-none');
                    }
                    return;
                }
                try {
                    const res = await fetch(`/form-orders/${orderId}/online-payment/recovery-email-preview`, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    });
                    const data = await res.json();
                    loadingEl?.classList.add('d-none');
                    if (!data.success) {
                        if (errorEl) {
                            errorEl.textContent = data.error || 'Nie udało się pobrać podglądu.';
                            errorEl.classList.remove('d-none');
                        }
                        return;
                    }
                    if (toEl) toEl.value = data.to || '';
                    if (subjectEl) subjectEl.value = data.subject || '';
                    lastBodyHtml = data.body_html || '';
                    if (bodyEl) bodyEl.value = data.body || '';
                    if (bodyFrameEl) bodyFrameEl.srcdoc = lastBodyHtml;
                    if (hintEl) hintEl.textContent = data.hint || '';
                    contentEl?.classList.remove('d-none');
                    if (copyBtn) copyBtn.disabled = !lastBodyHtml;
                    if (sendBtn) sendBtn.disabled = false;
                } catch (e) {
                    loadingEl?.classList.add('d-none');
                    if (errorEl) {
                        errorEl.textContent = 'Błąd połączenia przy pobieraniu podglądu.';
                        errorEl.classList.remove('d-none');
                    }
                }
            });

            copyBtn?.addEventListener('click', async function () {
                try {
                    await copyHtmlContent(lastBodyHtml, bodyEl?.value || '');
                    const original = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check2"></i> Skopiowano';
                    setTimeout(() => { this.innerHTML = original; }, 1500);
                } catch (e) {}
            });

            sendBtn?.addEventListener('click', async function () {
                if (!orderId) return;
                this.disabled = true;
                if (copyBtn) copyBtn.disabled = true;
                errorEl?.classList.add('d-none');
                successEl?.classList.add('d-none');
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Wysyłanie…';
                try {
                    const res = await fetch(`/form-orders/${orderId}/online-payment/send-recovery-email`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (successEl) {
                            successEl.textContent = 'Wiadomość została wysłana.';
                            successEl.classList.remove('d-none');
                        }
                        setTimeout(() => { window.location.reload(); }, 700);
                        return;
                    }
                    if (errorEl) {
                        errorEl.textContent = data.error || 'Nie udało się wysłać recovery e-mail.';
                        errorEl.classList.remove('d-none');
                    }
                    this.disabled = false;
                    if (copyBtn) copyBtn.disabled = !lastBodyHtml;
                    this.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                } catch (e) {
                    if (errorEl) {
                        errorEl.textContent = 'Błąd połączenia.';
                        errorEl.classList.remove('d-none');
                    }
                    this.disabled = false;
                    if (copyBtn) copyBtn.disabled = !lastBodyHtml;
                    this.innerHTML = '<i class="bi bi-send"></i> Wyślij';
                }
            });
        })();

        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            document.getElementById('confirmCancelOrderBtn')?.addEventListener('click', async function () {
                const orderId = this.dataset.orderId;
                const reason = document.getElementById('cancelOrderReason')?.value || '';
                this.disabled = true;
                try {
                    const res = await fetch(`/form-orders/${orderId}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ reason }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    alert(data.error || 'Nie udało się anulować zamówienia.');
                } catch (e) {
                    alert('Błąd połączenia.');
                } finally {
                    this.disabled = false;
                }
            });

            document.getElementById('confirmRestoreOrderBtn')?.addEventListener('click', async function () {
                const orderId = this.dataset.orderId;
                this.disabled = true;
                try {
                    const res = await fetch(`/form-orders/${orderId}/restore`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    alert(data.error || 'Nie udało się przywrócić zamówienia.');
                } catch (e) {
                    alert('Błąd połączenia.');
                } finally {
                    this.disabled = false;
                }
            });

            document.getElementById('confirmInvoiceExemptBtn')?.addEventListener('click', async function () {
                const orderId = this.dataset.orderId;
                const reason = document.getElementById('invoiceExemptReason')?.value || '';
                this.disabled = true;
                try {
                    const res = await fetch(`/form-orders/${orderId}/invoice-exempt`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ reason }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    alert(data.error || 'Nie udało się oznaczyć zamówienia.');
                } catch (e) {
                    alert('Błąd połączenia.');
                } finally {
                    this.disabled = false;
                }
            });

            document.getElementById('confirmClearInvoiceExemptBtn')?.addEventListener('click', async function () {
                const orderId = this.dataset.orderId;
                this.disabled = true;
                try {
                    const res = await fetch(`/form-orders/${orderId}/invoice-exempt/clear`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    alert(data.error || 'Nie udało się cofnąć oznaczenia.');
                } catch (e) {
                    alert('Błąd połączenia.');
                } finally {
                    this.disabled = false;
                }
            });
        })();
    </script>
</x-app-layout>


