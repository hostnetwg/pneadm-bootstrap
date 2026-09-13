<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Sprzedaż kursu: {{ $course->title }}</h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Nie zapisano zmian.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('online-courses.partials.navigation-tabs', [
                'onlineCourse' => $course,
                'activeTab' => 'sales',
            ])

            <div class="alert alert-info" role="alert">
                <strong>Katalog i sprzedaż na pnedu.pl.</strong>
                Możesz pokazać kurs w <code>/kursy</code> bez sprzedaży (portfolio / nieaktualna wersja). Zakup wymaga włączonej sprzedaży, sposobu płatności i aktywnego wariantu ceny.
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-shop me-1"></i> Katalog i kanały sprzedaży</span>
                    @if($product)
                        <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $product->is_active ? 'Gotowy w katalogu danych' : 'Nieaktywny' }}
                        </span>
                    @else
                        <span class="badge text-bg-warning">Nie skonfigurowano</span>
                    @endif
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('online-courses.sales.update', $course) }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-lg-7">
                                <label class="form-label" for="product_slug">Slug publicznej oferty</label>
                                <input id="product_slug" type="text" name="product_slug" class="form-control" required maxlength="191"
                                       value="{{ old('product_slug', $product?->slug ?? $course->slug) }}">
                                <div class="form-text">Docelowy adres: <code>/kursy-online/{slug}</code>. Musi być unikalny w całym katalogu produktów.</div>
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label" for="sku">SKU / kod wewnętrzny</label>
                                <input id="sku" type="text" name="sku" class="form-control" maxlength="100"
                                       value="{{ old('sku', $product?->sku) }}" placeholder="np. KO-DYREKTOR-2026">
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="meta_title">SEO title</label>
                                <input id="meta_title" type="text" name="meta_title" class="form-control" maxlength="255"
                                       value="{{ old('meta_title', $product?->meta_title) }}"
                                       placeholder="{{ $course->title }}">
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="meta_description">SEO meta description</label>
                                <textarea id="meta_description" name="meta_description" class="form-control" rows="2" maxlength="500">{{ old('meta_description', $product?->meta_description) }}</textarea>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="sales_enabled" value="0">
                                    <input id="sales_enabled" type="checkbox" name="sales_enabled" value="1" class="form-check-input"
                                           @checked((bool) old('sales_enabled', $offer?->is_active ?? false))>
                                    <label class="form-check-label fw-semibold" for="sales_enabled">Można kupić</label>
                                    <div class="form-text">Wyłącz, gdy kurs ma zostać w katalogu jako archiwum.</div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_public" value="0">
                                    <input id="is_public" type="checkbox" name="is_public" value="1" class="form-check-input"
                                           @checked((bool) old('is_public', $offer?->is_public ?? false))>
                                    <label class="form-check-label fw-semibold" for="is_public">Pokazuj w katalogu /kursy</label>
                                    <div class="form-text">Działa niezależnie od sprzedaży. Strona archiwalna nie idzie do sitemapy.</div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <span class="form-label d-block">Model zakupu</span>
                                <span class="badge text-bg-primary">Cena za uczestnika × liczba osób</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <span class="form-label d-block fw-semibold">Dozwolone sposoby płatności</span>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input type="hidden" name="allow_deferred_invoice" value="0">
                                    <input id="allow_deferred_invoice" type="checkbox" name="allow_deferred_invoice" value="1" class="form-check-input"
                                           @checked((bool) old('allow_deferred_invoice', $offer?->allow_deferred_invoice ?? true))>
                                    <label class="form-check-label" for="allow_deferred_invoice">Faktura odroczona</label>
                                </div>
                                <div class="form-check">
                                    <input type="hidden" name="allow_payu" value="0">
                                    <input id="allow_payu" type="checkbox" name="allow_payu" value="1" class="form-check-input"
                                           @checked((bool) old('allow_payu', $offer?->allow_payu ?? true))>
                                    <label class="form-check-label" for="allow_payu">PayU</label>
                                </div>
                                <div class="form-check">
                                    <input type="hidden" name="allow_paynow" value="0">
                                    <input id="allow_paynow" type="checkbox" name="allow_paynow" value="1" class="form-check-input"
                                           @checked((bool) old('allow_paynow', $offer?->allow_paynow ?? true))>
                                    <label class="form-check-label" for="allow_paynow">PayNow</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold" for="satisfaction_guarantee_days">Gwarancja satysfakcji (dni)</label>
                            <input id="satisfaction_guarantee_days" type="number" name="satisfaction_guarantee_days"
                                   class="form-control" min="0" max="365" style="max-width: 10rem;"
                                   value="{{ old('satisfaction_guarantee_days', $offer?->satisfaction_guarantee_days ?? 30) }}">
                            <div class="form-text">
                                30 = klasyczna gwarancja kursów online. 0 = wyłączona (np. przy e-booku).
                                Zwrot przyjmujemy tylko e-mailem lub telefonicznie — bez przycisku na koncie klienta.
                            </div>
                        </div>

                        <div class="alert alert-light border small mt-3 mb-3">
                            Zakup może obejmować wielu uczestników. Przy fakturze odroczonej wystawienie dokumentu i nadanie dostępu pozostaną niezależnymi akcjami operatora.
                            Okres dostępu liczy się od późniejszej daty: nadanie albo zaplanowany start wariantu.
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz ustawienia sprzedaży</button>
                    </form>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h3 class="h5 mb-1">Warianty cenowe</h3>
                    <p class="text-muted small mb-0">Każdy wariant określa cenę za jednego uczestnika oraz długość dostępu.</p>
                </div>
                @if($offer)
                    <span class="badge text-bg-secondary">{{ $prices->count() }} wariant(y)</span>
                @endif
            </div>

            @if(!$offer)
                <div class="alert alert-warning">
                    Najpierw zapisz ustawienia sprzedaży. Potem będzie można dodać warianty cenowe.
                </div>
            @else
                @foreach($prices as $price)
                    <div class="card shadow-sm mb-3">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>
                                <strong>{{ $price->name }}</strong>
                                <span class="text-muted ms-2">{{ number_format($price->currentPrice(), 2, ',', ' ') }} zł</span>
                            </span>
                            <span class="d-flex gap-2">
                                <span class="badge {{ $price->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $price->is_active ? 'Aktywny' : 'Nieaktywny' }}
                                </span>
                                <span class="badge text-bg-light border">{{ $price->accessLabel() }}</span>
                                <span class="badge text-bg-light border">VAT: {{ $price->taxLabel() }}</span>
                            </span>
                        </div>
                        <div class="card-body">
                            @include('online-courses.sales.partials.price-form', ['price' => $price])
                            @include('partials.price-omnibus-history', [
                                'subject' => $price,
                                'excludeRoute' => fn ($row) => route('online-courses.sales.prices.omnibus.exclude', [$course, $price, $row]),
                                'restoreRoute' => fn ($row) => route('online-courses.sales.prices.omnibus.restore', [$course, $price, $row]),
                            ])

                            <form id="deleteProductPriceForm{{ $price->id }}"
                                  method="POST"
                                  action="{{ route('online-courses.sales.prices.destroy', [$course, $price]) }}"
                                  class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger mt-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#formConfirmModal"
                                    data-confirm-title="Usuń wariant cenowy"
                                    data-confirm-message="Przenieść wariant „{{ e($price->name) }}” do kosza?"
                                    data-confirm-form="#deleteProductPriceForm{{ $price->id }}"
                                    data-confirm-btn-class="btn-danger"
                                    data-confirm-btn-text="Usuń wariant"
                                    data-confirm-header-class="bg-danger text-white">
                                Usuń wariant
                            </button>
                        </div>
                    </div>
                @endforeach

                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white">Nowy wariant cenowy</div>
                    <div class="card-body">
                        @include('online-courses.sales.partials.price-form', ['price' => null])
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('participants.partials.form-confirm-modal')

    @push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-product-price-form]').forEach(function (form) {
                var accessPolicy = form.querySelector('[data-access-policy]');
                var promotionToggle = form.querySelector('[data-promotion-toggle]');
                var taxTreatment = form.querySelector('[data-tax-treatment]');

                function toggleGroup(selector, visible) {
                    form.querySelectorAll(selector).forEach(function (group) {
                        group.classList.toggle('d-none', !visible);
                        group.querySelectorAll('input, select, textarea').forEach(function (field) {
                            field.disabled = !visible;
                        });
                    });
                }

                function refresh() {
                    var policy = accessPolicy ? accessPolicy.value : 'unlimited';
                    toggleGroup('[data-access-duration-group]', policy === 'duration_from_grant');
                    toggleGroup('[data-access-fixed-group]', policy === 'fixed_until');
                    toggleGroup('[data-promotion-group]', Boolean(promotionToggle && promotionToggle.checked));
                    var tax = taxTreatment ? taxTreatment.value : 'exempt';
                    toggleGroup('[data-tax-standard-group]', tax === 'standard');
                    toggleGroup('[data-tax-exempt-group]', tax === 'exempt');
                }

                accessPolicy?.addEventListener('change', refresh);
                promotionToggle?.addEventListener('change', refresh);
                taxTreatment?.addEventListener('change', refresh);
                refresh();
            });
        })();
    </script>
    @endpush
</x-app-layout>
