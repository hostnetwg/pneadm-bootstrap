<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe zamówienie') }} <span class="text-danger">(PNEADM)</span>
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
                    <li class="breadcrumb-item active" aria-current="page">
                        Dodaj nowe zamówienie
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

            @if(!empty($cloneWarning))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $cloneWarning }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(!empty($cloneSourceId))
                <div class="alert alert-info">
                    <i class="bi bi-files"></i>
                    Tworzysz nowe zamówienie na podstawie rekordu #{{ $cloneSourceId }}. Przed zapisem możesz zmienić szkolenie, cenę i dowolne pola.
                </div>
            @endif

            {{-- Formularz --}}
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle"></i> Nowe zamówienie
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('form-orders.store') }}" method="POST">
                        @csrf
                        
                        {{-- SZKOLENIE / KURS ONLINE --}}
                        @php
                            $createOrderKind = old('order_kind', $prefill['order_kind'] ?? 'training');
                            $isCreateProduct = $createOrderKind === 'product';
                            $selectedCourseId = old('course_id', $prefill['course_id'] ?? '');
                            $preselectedCourse = $selectedCourse ?? ($selectedCourseId ? \App\Models\Course::find($selectedCourseId) : null);
                            $selectedCatalogProductId = old('catalog_product_id', $prefill['catalog_product_id'] ?? '');
                            $preselectedCatalogProduct = $selectedCatalogProduct
                                ?? ($selectedCatalogProductId ? \App\Models\Product::withTrashed()->find($selectedCatalogProductId) : null);
                            $selectedPriceId = old('product_price_id', $prefill['product_price_id'] ?? '');
                            $createPriceOptions = $catalogPriceOptions ?? [];
                        @endphp
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar-event"></i> SZKOLENIE / KURS ONLINE
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label d-block">Rodzaj <span class="text-danger">*</span></label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="order_kind" id="order_kind_training"
                                               value="training" @checked($createOrderKind === 'training')>
                                        <label class="form-check-label" for="order_kind_training">Szkolenie (courses)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="order_kind" id="order_kind_product"
                                               value="product" @checked($createOrderKind === 'product')>
                                        <label class="form-check-label" for="order_kind_product">Kurs online nagrany</label>
                                    </div>
                                    @error('order_kind')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div id="training-product-fields" class="{{ $isCreateProduct ? 'd-none' : '' }}">
                                            <label for="course_id" class="form-label">Wybierz szkolenie <span class="text-danger">*</span></label>
                                            <select class="form-control @error('course_id') is-invalid @enderror"
                                                    id="course_id" name="course_id"
                                                    {{ $isCreateProduct ? 'disabled' : 'required' }}>
                                                @if($preselectedCourse)
                                                    <option value="{{ $preselectedCourse->id }}" selected>
                                                        #{{ $preselectedCourse->id }} · {{ $preselectedCourse->plainTitle() }}
                                                        @if($preselectedCourse->start_date) [{{ $preselectedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}] @endif
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

                                        <div id="online-product-fields" class="{{ $isCreateProduct ? '' : 'd-none' }}">
                                            <label for="catalog_product_id" class="form-label">Kurs online nagrany <span class="text-danger">*</span></label>
                                            <select class="form-control @error('catalog_product_id') is-invalid @enderror"
                                                    id="catalog_product_id" name="catalog_product_id"
                                                    {{ $isCreateProduct ? 'required' : 'disabled' }}>
                                                @if($preselectedCatalogProduct)
                                                    <option value="{{ $preselectedCatalogProduct->id }}" selected>
                                                        #{{ $preselectedCatalogProduct->id }} · {{ $preselectedCatalogProduct->name }}
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
                                                        {{ $isCreateProduct ? 'required' : 'disabled' }}>
                                                    <option value="">— wybierz wariant —</option>
                                                    @foreach($createPriceOptions as $priceOption)
                                                        <option value="{{ $priceOption['id'] }}"
                                                                data-amount="{{ $priceOption['amount'] }}"
                                                                @selected((string) $selectedPriceId === (string) $priceOption['id'])>
                                                            {{ $priceOption['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_price_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">Jak `?price=` na publicznym formularzu kursu.</small>
                                            </div>
                                        </div>

                                        <div id="course-select-error" class="alert alert-warning mt-2 mb-0 py-2 small d-none" role="alert">
                                            Nie udało się uruchomić wyszukiwarki. Odśwież stronę.
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="product_price" class="form-label">Cena (PLN)</label>
                                        <input type="number" step="0.01" min="0" class="form-control @error('product_price') is-invalid @enderror"
                                               id="product_price" name="product_price"
                                               value="{{ old('product_price', $prefill['product_price'] ?? '') }}"
                                               placeholder="Opcjonalnie">
                                        @error('product_price')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div id="course-info" class="alert alert-light" style="display: none;">
                                            <h6 class="mb-2">Informacje o wybranym szkoleniu:</h6>
                                            <div id="course-details"></div>
                                        </div>
                                    </div>
                                </div>
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
                                    Na te adresy e-mail trafią dane dostępowe do szkolenia. Przy zwykłej cenie kwota rośnie wraz z liczbą osób. Przy stałej cenie szkolenia zamkniętego kwota w polu Cena zostaje bez zmian.
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
                                               value="{{ old('orderer_name', $prefill['orderer_name'] ?? '') }}" required>
                                        @error('orderer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_phone" class="form-label">Telefon kontaktowy <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control @error('orderer_phone') is-invalid @enderror" 
                                               id="orderer_phone" name="orderer_phone" 
                                               value="{{ old('orderer_phone', $prefill['orderer_phone'] ?? '') }}" required>
                                        @error('orderer_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_email" class="form-label">E-mail - tu prześlemy fakturę <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('orderer_email') is-invalid @enderror" 
                                               id="orderer_email" name="orderer_email" 
                                               value="{{ old('orderer_email', $prefill['orderer_email'] ?? '') }}" required>
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
                                               value="{{ old('buyer_name', $prefill['buyer_name'] ?? '') }}" required>
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
                                               value="{{ old('buyer_postal_code', $prefill['buyer_postal_code'] ?? '') }}" required>
                                        @error('buyer_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="buyer_city" class="form-label">Miasto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_city') is-invalid @enderror" 
                                               id="buyer_city" name="buyer_city" 
                                               value="{{ old('buyer_city', $prefill['buyer_city'] ?? '') }}" required>
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
                                               value="{{ old('buyer_address', $prefill['buyer_address'] ?? '') }}" required>
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
                                                   value="{{ old('buyer_nip', $prefill['buyer_nip'] ?? '') }}"
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
                                    <i class="bi bi-geo-alt"></i> ODBIORCA (opcjonalnie, jeśli inny niż nabywca)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="recipient_name" class="form-label">Nazwa / Imię i nazwisko</label>
                                        <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" 
                                               id="recipient_name" name="recipient_name" 
                                               value="{{ old('recipient_name', $prefill['recipient_name'] ?? '') }}">
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
                                               value="{{ old('recipient_postal_code', $prefill['recipient_postal_code'] ?? '') }}">
                                        @error('recipient_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="recipient_city" class="form-label">Miasto</label>
                                        <input type="text" class="form-control @error('recipient_city') is-invalid @enderror" 
                                               id="recipient_city" name="recipient_city" 
                                               value="{{ old('recipient_city', $prefill['recipient_city'] ?? '') }}">
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
                                               value="{{ old('recipient_address', $prefill['recipient_address'] ?? '') }}">
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
                                                   value="{{ old('recipient_nip', $prefill['recipient_nip'] ?? '') }}"
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
                        @include('form-orders.partials.ksef-additional-entity-form', ['zamowienie' => null])

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
                                                  id="invoice_notes" name="invoice_notes" rows="3">{{ old('invoice_notes', $prefill['invoice_notes'] ?? '') }}</textarea>
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
                                                   value="{{ old('invoice_payment_delay', $prefill['invoice_payment_delay'] ?? 14) }}">
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

                        {{-- NOTATKI --}}
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-sticky"></i> NOTATKI
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="notes" class="form-label">Dodatkowe notatki</label>
                                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                                  id="notes" name="notes" rows="3" 
                                                  placeholder="Dodaj notatki dotyczące zamówienia...">{{ old('notes', $prefill['notes'] ?? '') }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- PRZYCISKI --}}
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('form-orders.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Anuluj
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Utwórz zamówienie
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
        $courseSelectPreselected = $preselectedCourse ? [
            'id' => $preselectedCourse->id,
            'id_old' => $preselectedCourse->id_old,
            'title_text' => $preselectedCourse->plainTitle(''),
            'start_date' => $preselectedCourse->start_date ? $preselectedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'end_date' => $preselectedCourse->end_date ? $preselectedCourse->end_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'status' => $preselectedCourse->getLifecycleStatus(),
            'instructor' => optional($preselectedCourse->instructor)->full_title_name ?? '',
        ] : null;
        $catalogProductSelectPreselected = $preselectedCatalogProduct ? [
            'id' => $preselectedCatalogProduct->id,
            'id_old' => '',
            'title_text' => $preselectedCatalogProduct->name,
            'start_date' => null,
            'end_date' => null,
            'status' => '',
            'instructor' => '',
            'prices' => $createPriceOptions,
            'default_price_id' => $selectedPriceId ?: ($createPriceOptions[0]['id'] ?? null),
            'default_price' => $createPriceOptions[0]['amount'] ?? null,
        ] : null;
    @endphp
    @include('partials.ensure-course-select-init')
    @include('form-orders.partials.order-kind-product-script', [
        'initialOrderKind' => $createOrderKind,
        'courseSearchUrl' => $courseSearchUrl,
        'catalogProductSearchUrl' => $catalogProductSearchUrl,
        'catalogProductPricesUrlTemplate' => $catalogProductPricesUrlTemplate,
        'courseSelectPreselected' => $courseSelectPreselected,
        'catalogProductSelectPreselected' => $catalogProductSelectPreselected,
        'selectedProductPriceId' => $selectedPriceId,
        'showCourseInfo' => true,
    ])
    @include('form-orders.partials.participants-form-script')
    @include('form-orders.partials.gus-nip-lookup-script')
</x-app-layout>
