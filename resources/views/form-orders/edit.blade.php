<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edycja zamówienia') }} #{{ $zamowienie->id }} <span class="text-danger">(PNEADM)</span>
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">

            {{-- Breadcrumb --}}
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('form-orders.index') }}">Zamówienia</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('form-orders.show', $zamowienie->id) }}">Zamówienie #{{ $zamowienie->id }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Edycja
                    </li>
                </ol>
            </nav>

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

            {{-- Formularz edycji --}}
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil"></i> Edycja zamówienia #{{ $zamowienie->id }}
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('form-orders.update', $zamowienie->id) }}">
                        @csrf
                        @method('PUT')
                        
                        {{-- Ukryte pole oznaczające pochodzenie --}}
                        <input type="hidden" name="from_edit_page" value="1">
                        
                        {{-- Przekazanie parametrów filtrów --}}
                        @foreach(array_filter([
                            'filter_no_participant' => request('filter_no_participant') ? '1' : null,
                            'filter_no_invoice' => request('filter_no_invoice') || (request('filter_new') && ! request()->has('filter_no_participant') && ! request()->has('filter_no_invoice')) ? '1' : null,
                            'filter_no_ksef' => request('filter_no_ksef') ? '1' : null,
                            'filter_payment_gateway' => request('filter_payment_gateway') ? '1' : null,
                            'filter_new' => request('filter_new') ? '1' : null,
                            'course_id' => request('course_id') ?: null,
                        ]) as $navFilterKey => $navFilterValue)
                            <input type="hidden" name="{{ $navFilterKey }}" value="{{ $navFilterValue }}">
                        @endforeach

                        {{-- Informacje o szkoleniu / kursie online --}}
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar-event"></i>
                                    Szkolenie / kurs online
                                </h6>
                            </div>
                            <div class="card-body">
                                @php
                                    $editOrderKind = old('order_kind', $zamowienie->isProductOrder() ? 'product' : 'training');
                                    $isProductOrderEdit = $editOrderKind === 'product';
                                    $editPreselectedCourseId = old('course_id', $zamowienie->isProductOrder() ? null : $zamowienie->product_id);
                                    $editPreselectedCourse = $editPreselectedCourseId
                                        ? \App\Models\Course::find($editPreselectedCourseId)
                                        : null;
                                    $editCatalogItem = $zamowienie->orderItems->first();
                                    $editPreselectedProductId = old('catalog_product_id', $editCatalogItem?->product_id);
                                    $editPreselectedProduct = $selectedCatalogProduct
                                        ?? ($editPreselectedProductId
                                            ? ($editCatalogItem?->product && (int) $editCatalogItem->product_id === (int) $editPreselectedProductId
                                                ? $editCatalogItem->product
                                                : \App\Models\Product::withTrashed()->find($editPreselectedProductId))
                                            : null);
                                    $editPriceOptions = $catalogPriceOptions ?? [];
                                    $editSelectedPriceId = old('product_price_id', $selectedProductPriceId ?? $editCatalogItem?->product_price_id);
                                    $hasPubligoSent = (int) ($zamowienie->publigo_sent ?? 0) === 1;
                                    $hasPneduProvisioned = ! empty($zamowienie->pnedu_provisioned_at);
                                    $hasInvoice = ! empty($zamowienie->invoice_number);
                                @endphp

                                @if($hasPubligoSent || $hasPneduProvisioned || $hasInvoice)
                                    <div class="alert alert-warning small mb-3">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Uwaga:</strong>
                                        @if($hasInvoice)
                                            zamówienie ma już numer faktury
                                        @endif
                                        @if($hasInvoice && ($hasPubligoSent || $hasPneduProvisioned))
                                            ;
                                        @endif
                                        @if($hasPubligoSent)
                                            wysłano do <strong>Publigo</strong>
                                        @endif
                                        @if($hasPubligoSent && $hasPneduProvisioned)
                                            i
                                        @endif
                                        @if($hasPneduProvisioned)
                                            przyznano dostęp <strong>PNEDU</strong>
                                        @endif.
                                        Zmiana rodzaju (szkolenie ↔ kurs online) albo produktu <u>nie cofa</u> automatycznie FV.
                                        Przy zamianie produkt → szkolenie system wycofuje nadane dostępy produktowe.
                                        Przy szkoleniu → produkt rozważ najpierw reset dostępu PNEDU / Publigo, jeśli był nadany.
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label d-block">Rodzaj <span class="text-danger">*</span></label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="order_kind" id="order_kind_training"
                                               value="training" @checked($editOrderKind === 'training')>
                                        <label class="form-check-label" for="order_kind_training">Szkolenie (courses)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="order_kind" id="order_kind_product"
                                               value="product" @checked($editOrderKind === 'product')>
                                        <label class="form-check-label" for="order_kind_product">Kurs online nagrany</label>
                                    </div>
                                    @error('order_kind')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div id="training-product-fields" class="{{ $isProductOrderEdit ? 'd-none' : '' }}">
                                            <label for="course_id" class="form-label">Szkolenie <span class="text-danger">*</span></label>
                                            <select class="form-control @error('course_id') is-invalid @enderror"
                                                    id="course_id" name="course_id"
                                                    {{ $isProductOrderEdit ? 'disabled' : 'required' }}>
                                                @if($editPreselectedCourse)
                                                    <option value="{{ $editPreselectedCourse->id }}" selected>
                                                        #{{ $editPreselectedCourse->id }} · {{ $editPreselectedCourse->plainTitle() }}
                                                        @if($editPreselectedCourse->start_date) [{{ $editPreselectedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}] @endif
                                                    </option>
                                                @endif
                                            </select>
                                            @error('course_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                                                <small class="form-text text-muted mb-0">Domyślnie pokazujemy nadchodzące i trwające. Wpisz tytuł / ID / Publigo ID, by szukać też w archiwum.</small>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" id="course_include_archived">
                                                    <label class="form-check-label small" for="course_include_archived">
                                                        Pokaż również archiwalne
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="online-product-fields" class="{{ $isProductOrderEdit ? '' : 'd-none' }}">
                                            <label for="catalog_product_id" class="form-label">Kurs online nagrany <span class="text-danger">*</span></label>
                                            <select class="form-control @error('catalog_product_id') is-invalid @enderror"
                                                    id="catalog_product_id" name="catalog_product_id"
                                                    {{ $isProductOrderEdit ? 'required' : 'disabled' }}>
                                                @if($editPreselectedProduct)
                                                    <option value="{{ $editPreselectedProduct->id }}" selected>
                                                        #{{ $editPreselectedProduct->id }} · {{ $editPreselectedProduct->name }}
                                                    </option>
                                                @endif
                                            </select>
                                            @error('catalog_product_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="mt-3">
                                                <label for="product_price_id" class="form-label">Wariant ceny <span class="text-danger">*</span></label>
                                                <select class="form-select @error('product_price_id') is-invalid @enderror"
                                                        id="product_price_id" name="product_price_id"
                                                        {{ $isProductOrderEdit ? 'required' : 'disabled' }}>
                                                    <option value="">— wybierz wariant —</option>
                                                    @foreach($editPriceOptions as $priceOption)
                                                        <option value="{{ $priceOption['id'] }}"
                                                                data-amount="{{ $priceOption['amount'] }}"
                                                                @selected((string) $editSelectedPriceId === (string) $priceOption['id'])>
                                                            {{ $priceOption['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_price_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">Jak `?price=` na publicznym formularzu kursu. Snapshot trafia do <code>order_items</code>.</small>
                                            </div>
                                        </div>

                                        <div id="course-select-error" class="alert alert-warning mt-2 mb-0 py-2 small d-none" role="alert">
                                            Nie udało się uruchomić wyszukiwarki. Odśwież stronę.
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="product_price" class="form-label">Cena (PLN)</label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                               id="product_price" name="product_price"
                                               value="{{ old('product_price', $zamowienie->product_price) }}">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="product_name" class="form-label">Nazwa (aktualizowana po wyborze szkolenia / kursu)</label>
                                        <input type="text" class="form-control"
                                               id="product_name" name="product_name"
                                               value="{{ old('product_name', $zamowienie->display_product_name) }}" readonly>
                                    </div>
                                </div>
                                @if(! $zamowienie->isProductOrder() && $zamowienie->publigo_product_id)
                                    <div class="mt-2">
                                        <span class="badge bg-info">Publigo Product ID: {{ $zamowienie->publigo_product_id }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- UCZESTNICY --}}
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-people"></i> UCZESTNICY
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    @if($zamowienie->isProductOrder())
                                        Na te adresy e-mail trafią dane dostępowe do kursu online. Możesz dodać lub usunąć osoby — kwota przelicza się automatycznie (cena jednostkowa × liczba osób).
                                    @else
                                        Na te adresy e-mail trafią dane dostępowe do szkolenia. Przy zwykłej cenie kwota rośnie wraz z liczbą osób. Przy stałej cenie szkolenia zamkniętego kwota w polu Cena zostaje bez zmian.
                                    @endif
                                </p>
                                @include('form-orders.partials.participants-form', [
                                    'participantsPrefill' => $participantsPrefill ?? [],
                                    'unitPrice' => $participantUnitPrice ?? null,
                                ])
                            </div>
                        </div>

                        {{-- ZAMAWIAJĄCY --}}
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="bi bi-telephone"></i> ZAMAWIAJĄCY (KONTAKT)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="orderer_name" class="form-label">Nazwa / Imię i nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('orderer_name') is-invalid @enderror" 
                                               id="orderer_name" name="orderer_name" 
                                               value="{{ old('orderer_name', $zamowienie->orderer_name) }}" required>
                                        @error('orderer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_phone" class="form-label">Telefon kontaktowy <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control @error('orderer_phone') is-invalid @enderror" 
                                               id="orderer_phone" name="orderer_phone" 
                                               value="{{ old('orderer_phone', $zamowienie->orderer_phone) }}" required>
                                        @error('orderer_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_email" class="form-label">E-mail - tu prześlemy fakturę <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('orderer_email') is-invalid @enderror" 
                                               id="orderer_email" name="orderer_email" 
                                               value="{{ old('orderer_email', $zamowienie->orderer_email) }}" required>
                                        @error('orderer_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- NABYWCA --}}
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-building"></i> NABYWCA (DO FAKTURY)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="buyer_name" class="form-label">Nazwa / Imię i nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_name') is-invalid @enderror" 
                                               id="buyer_name" name="buyer_name" 
                                               value="{{ old('buyer_name', $zamowienie->buyer_name) }}" required>
                                        @error('buyer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label for="buyer_postal_code" class="form-label">Kod pocztowy <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_postal_code') is-invalid @enderror" 
                                               id="buyer_postal_code" name="buyer_postal_code" 
                                               value="{{ old('buyer_postal_code', $zamowienie->buyer_postal_code) }}" required>
                                        @error('buyer_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="buyer_city" class="form-label">Miasto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_city') is-invalid @enderror" 
                                               id="buyer_city" name="buyer_city" 
                                               value="{{ old('buyer_city', $zamowienie->buyer_city) }}" required>
                                        @error('buyer_city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="buyer_address" class="form-label">Adres <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_address') is-invalid @enderror" 
                                               id="buyer_address" name="buyer_address" 
                                               value="{{ old('buyer_address', $zamowienie->buyer_address) }}" required>
                                        @error('buyer_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="buyer_nip" class="form-label">NIP</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control @error('buyer_nip') is-invalid @enderror"
                                                   id="buyer_nip" name="buyer_nip"
                                                   value="{{ old('buyer_nip', $zamowienie->buyer_nip) }}"
                                                   inputmode="numeric" autocomplete="off">
                                            <button class="btn btn-success" type="button" data-gus-target="buyer">
                                                Wpisz NIP i pobierz dane z GUS
                                            </button>
                                            @error('buyer_nip')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-text" id="buyer-gus-status" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ODBIORCA --}}
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-geo-alt"></i> ODBIORCA
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="recipient_name" class="form-label">Nazwa / Imię i nazwisko</label>
                                        <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" 
                                               id="recipient_name" name="recipient_name" 
                                               value="{{ old('recipient_name', $zamowienie->recipient_name) }}">
                                        @error('recipient_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label for="recipient_postal_code" class="form-label">Kod pocztowy</label>
                                        <input type="text" class="form-control @error('recipient_postal_code') is-invalid @enderror" 
                                               id="recipient_postal_code" name="recipient_postal_code" 
                                               value="{{ old('recipient_postal_code', $zamowienie->recipient_postal_code) }}">
                                        @error('recipient_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="recipient_city" class="form-label">Miasto</label>
                                        <input type="text" class="form-control @error('recipient_city') is-invalid @enderror" 
                                               id="recipient_city" name="recipient_city" 
                                               value="{{ old('recipient_city', $zamowienie->recipient_city) }}">
                                        @error('recipient_city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="recipient_address" class="form-label">Adres</label>
                                        <input type="text" class="form-control @error('recipient_address') is-invalid @enderror" 
                                               id="recipient_address" name="recipient_address" 
                                               value="{{ old('recipient_address', $zamowienie->recipient_address) }}">
                                        @error('recipient_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="recipient_nip" class="form-label">NIP (opcjonalnie) - proszę podać tylko jeżeli ma znaleźć się na fakturze</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control @error('recipient_nip') is-invalid @enderror"
                                                   id="recipient_nip" name="recipient_nip"
                                                   value="{{ old('recipient_nip', $zamowienie->recipient_nip) }}"
                                                   inputmode="numeric" autocomplete="off">
                                            <button class="btn btn-success" type="button" data-gus-target="recipient">
                                                Wpisz NIP i pobierz dane z GUS
                                            </button>
                                            @error('recipient_nip')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-text" id="recipient-gus-status" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- KSeF – Podmiot3 (metadane) — ETAP 3 (adm) --}}
                        @include('form-orders.partials.ksef-additional-entity-form', ['zamowienie' => $zamowienie])

                        {{-- UWAGI DO FAKTURY --}}
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-receipt"></i> UWAGI DO FAKTURY
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="invoice_notes" class="form-label">Uwagi do faktury</label>
                                        <textarea class="form-control @error('invoice_notes') is-invalid @enderror" 
                                                  id="invoice_notes" name="invoice_notes" rows="3">{{ old('invoice_notes', $zamowienie->invoice_notes) }}</textarea>
                                        @error('invoice_notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label for="invoice_payment_delay" class="form-label">Proszę o wystawienie faktury z odroczonym terminem płatności</label>
                                        <div class="input-group">
                                            <input type="number" min="0" max="31" class="form-control @error('invoice_payment_delay') is-invalid @enderror" 
                                                   id="invoice_payment_delay" name="invoice_payment_delay" 
                                                   value="{{ old('invoice_payment_delay', $zamowienie->invoice_payment_delay ?? 14) }}">
                                            <span class="input-group-text">dni</span>
                                        </div>
                                        @error('invoice_payment_delay')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Zakres: 0-31 dni</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Faktura --}}
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #17a2b8; color: white;">
                                <h6 class="mb-0">
                                    <i class="bi bi-receipt"></i> Faktura
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">Numer faktury</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="invoice_number" 
                                               name="invoice_number" 
                                               value="{{ old('invoice_number', $zamowienie->invoice_number) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Status i notatki --}}
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-clipboard-check"></i> Status i notatki
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="status_completed" 
                                                   name="status_completed" 
                                                   value="1" 
                                                   {{ old('status_completed', $zamowienie->status_completed) == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label" for="status_completed">
                                                <strong>Zamówienie zakończone</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="notes" class="form-label">Notatki wewnętrzne</label>
                                        <textarea class="form-control" 
                                                  id="notes" 
                                                  name="notes" 
                                                  rows="4" 
                                                  placeholder="Dodaj notatki...">{{ old('notes', $zamowienie->notes) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Przyciski akcji --}}
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('form-orders.show', array_merge(['id' => $zamowienie->id], array_filter([
                                'filter_no_participant' => request('filter_no_participant') ? '1' : null,
                                'filter_no_invoice' => request('filter_no_invoice') || (request('filter_new') && ! request()->has('filter_no_participant') && ! request()->has('filter_no_invoice')) ? '1' : null,
                                'filter_no_ksef' => request('filter_no_ksef') ? '1' : null,
                                'filter_payment_gateway' => request('filter_payment_gateway') ? '1' : null,
                                'course_id' => request('course_id') ?: null,
                            ]))) }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Zapisz zmiany
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- TomSelect: szkolenie live albo kurs online z katalogu --}}
    @php
        $courseSearchUrl = route('form-orders.courses.search');
        $catalogProductSearchUrl = route('form-orders.products.search');
        $catalogProductPricesUrlTemplate = route('form-orders.products.prices', ['product' => '__ID__']);
        $courseSelectPreselected = $editPreselectedCourse ? [
            'id' => $editPreselectedCourse->id,
            'id_old' => $editPreselectedCourse->id_old,
            'title_text' => $editPreselectedCourse->plainTitle(''),
            'start_date' => $editPreselectedCourse->start_date ? $editPreselectedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'end_date' => $editPreselectedCourse->end_date ? $editPreselectedCourse->end_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'status' => $editPreselectedCourse->getLifecycleStatus(),
            'instructor' => optional($editPreselectedCourse->instructor)->full_title_name ?? '',
        ] : null;
        $catalogProductSelectPreselected = $editPreselectedProduct ? [
            'id' => $editPreselectedProduct->id,
            'id_old' => '',
            'title_text' => $editPreselectedProduct->name,
            'start_date' => null,
            'end_date' => null,
            'status' => '',
            'instructor' => '',
            'prices' => $editPriceOptions,
            'default_price_id' => $editSelectedPriceId ?: ($editPriceOptions[0]['id'] ?? null),
            'default_price' => (collect($editPriceOptions)->firstWhere('id', (int) $editSelectedPriceId)['amount'] ?? null)
                ?? ($editPriceOptions[0]['amount'] ?? null),
        ] : null;
    @endphp
    @include('partials.ensure-course-select-init')
    @include('form-orders.partials.order-kind-product-script', [
        'initialOrderKind' => $editOrderKind,
        'courseSearchUrl' => $courseSearchUrl,
        'catalogProductSearchUrl' => $catalogProductSearchUrl,
        'catalogProductPricesUrlTemplate' => $catalogProductPricesUrlTemplate,
        'courseSelectPreselected' => $courseSelectPreselected,
        'catalogProductSelectPreselected' => $catalogProductSelectPreselected,
        'selectedProductPriceId' => $editSelectedPriceId,
        'showCourseInfo' => false,
    ])
    @include('form-orders.partials.participants-form-script')
    @include('form-orders.partials.gus-nip-lookup-script')
</x-app-layout>


