@php
    $isEdit = $price?->exists === true;
    $formId = $isEdit ? 'productPriceForm'.$price->id : 'newProductPriceForm';
    $action = $isEdit
        ? route('online-courses.sales.prices.update', [$course, $price])
        : route('online-courses.sales.prices.store', $course);
    $promotionStarts = $price?->promotion_starts_at?->timezone('Europe/Warsaw')->format('Y-m-d\TH:i');
    $promotionEnds = $price?->promotion_ends_at?->timezone('Europe/Warsaw')->format('Y-m-d\TH:i');
    $accessExpires = $price?->access_expires_at?->timezone('Europe/Warsaw')->format('Y-m-d\TH:i');
    $accessStarts = $price?->access_starts_at?->timezone('Europe/Warsaw')->format('Y-m-d\TH:i');
    $taxRatePercent = $price?->tax_rate !== null ? (float) $price->tax_rate * 100 : 23;
@endphp

<form id="{{ $formId }}" method="POST" action="{{ $action }}" data-product-price-form>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label" for="{{ $formId }}Name">Nazwa wariantu</label>
            <input id="{{ $formId }}Name" type="text" name="name" class="form-control" required maxlength="255"
                   value="{{ old('name', $price?->name) }}" placeholder="np. Dostęp na 12 miesięcy">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ $formId }}Price">Cena za uczestnika</label>
            <div class="input-group">
                <input id="{{ $formId }}Price" type="number" name="price" class="form-control" required min="0" step="0.01"
                       value="{{ old('price', $price?->price) }}">
                <span class="input-group-text">zł</span>
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="{{ $formId }}SortOrder">Kolejność</label>
            <input id="{{ $formId }}SortOrder" type="number" name="sort_order" class="form-control" min="0" max="9999"
                   value="{{ old('sort_order', $price?->sort_order ?? 0) }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="{{ $formId }}Description">Opis wariantu</label>
            <textarea id="{{ $formId }}Description" name="description" class="form-control" rows="2"
                      placeholder="Krótka informacja widoczna przy wyborze ceny">{{ old('description', $price?->description) }}</textarea>
        </div>

        <div class="col-md-5">
            <label class="form-label" for="{{ $formId }}AccessPolicy">Okres dostępu</label>
            <select id="{{ $formId }}AccessPolicy" name="access_policy" class="form-select" data-access-policy>
                <option value="unlimited" @selected(old('access_policy', $price?->access_policy ?? 'unlimited') === 'unlimited')>Bezterminowy</option>
                <option value="duration_from_grant" @selected(old('access_policy', $price?->access_policy) === 'duration_from_grant')>Przez określony czas od startu dostępu</option>
                <option value="fixed_until" @selected(old('access_policy', $price?->access_policy) === 'fixed_until')>Do ustalonej daty</option>
            </select>
            <div class="form-text">Koniec dostępu. Start ustawiasz osobno — pusta data startu oznacza dostęp od nadania.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="{{ $formId }}AccessStartsAt">Start dostępu (przedsprzedaż)</label>
            <input id="{{ $formId }}AccessStartsAt" type="datetime-local" name="access_starts_at" class="form-control"
                   value="{{ old('access_starts_at', $accessStarts) }}">
            <div class="form-text">Puste = od razu po nadaniu. Przyszła data = klient widzi kartę z datą, lekcje od tej chwili. Zmiana dotyczy tylko nowych zamówień.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ $formId }}AccessNote">Notatka przy dacie startu</label>
            <input id="{{ $formId }}AccessNote" type="text" name="access_note" class="form-control" maxlength="2000"
                   value="{{ old('access_note', $price?->access_note) }}"
                   placeholder="np. Materiały publikujemy partiami">
        </div>
        <div class="col-md-4" data-access-duration-group>
            <label class="form-label" for="{{ $formId }}AccessDurationValue">Długość</label>
            <div class="input-group">
                <input id="{{ $formId }}AccessDurationValue" type="number" name="access_duration_value" class="form-control" min="1" max="999"
                       value="{{ old('access_duration_value', $price?->access_duration_value) }}">
                <select name="access_duration_unit" class="form-select" aria-label="Jednostka długości dostępu">
                    <option value="days" @selected(old('access_duration_unit', $price?->access_duration_unit) === 'days')>dni</option>
                    <option value="months" @selected(old('access_duration_unit', $price?->access_duration_unit) === 'months')>miesiące</option>
                    <option value="years" @selected(old('access_duration_unit', $price?->access_duration_unit ?? 'years') === 'years')>lata</option>
                </select>
            </div>
        </div>
        <div class="col-md-3" data-access-fixed-group>
            <label class="form-label" for="{{ $formId }}AccessExpiresAt">Dostęp do</label>
            <input id="{{ $formId }}AccessExpiresAt" type="datetime-local" name="access_expires_at" class="form-control"
                   value="{{ old('access_expires_at', $accessExpires) }}">
        </div>

        <div class="col-md-5">
            <label class="form-label" for="{{ $formId }}TaxTreatment">Rozliczenie VAT</label>
            <select id="{{ $formId }}TaxTreatment" name="tax_treatment" class="form-select" data-tax-treatment>
                <option value="exempt" @selected(old('tax_treatment', $price?->tax_treatment ?? 'exempt') === 'exempt')>Zwolniony (ZW) — obecne ustawienie</option>
                <option value="standard" @selected(old('tax_treatment', $price?->tax_treatment) === 'standard')>Stawka procentowa VAT</option>
            </select>
            <div class="form-text">ZW nie jest stawką 0%. Podstawa prawna musi być zgodna z ustawieniami iFirma.</div>
        </div>
        <div class="col-md-3" data-tax-standard-group>
            <label class="form-label" for="{{ $formId }}TaxRatePercent">Stawka VAT</label>
            <div class="input-group">
                <input id="{{ $formId }}TaxRatePercent" type="number" name="tax_rate_percent" class="form-control" min="0" max="100" step="0.01"
                       value="{{ old('tax_rate_percent', $taxRatePercent) }}">
                <span class="input-group-text">%</span>
            </div>
        </div>
        <div class="col-md-4" data-tax-exempt-group>
            <label class="form-label" for="{{ $formId }}TaxExemptionBasis">Podstawa zwolnienia (opcjonalnie)</label>
            <input id="{{ $formId }}TaxExemptionBasis" type="text" name="tax_exemption_basis" class="form-control" maxlength="500"
                   value="{{ old('tax_exemption_basis', $price?->tax_exemption_basis) }}"
                   placeholder="Puste = ustawienie globalne iFirma">
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_complimentary" value="0">
                <input id="{{ $formId }}IsComplimentary" type="checkbox" name="is_complimentary" value="1" class="form-check-input"
                       @checked((bool) old('is_complimentary', $price?->is_complimentary ?? false))>
                <label class="form-check-label" for="{{ $formId }}IsComplimentary">Bezpłatny zapis publiczny</label>
                <div class="form-text">Na ofercie pojawia się przycisk „Zapisz się bezpłatnie”. Cena zostaje ustawiona na 0 zł i nie idzie przez checkout ani PayU.</div>
            </div>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_promotion" value="0">
                <input id="{{ $formId }}IsPromotion" type="checkbox" name="is_promotion" value="1" class="form-check-input"
                       data-promotion-toggle @checked((bool) old('is_promotion', $price?->is_promotion ?? false))>
                <label class="form-check-label" for="{{ $formId }}IsPromotion">Cena promocyjna</label>
            </div>
        </div>
        <div class="col-md-4" data-promotion-group>
            <label class="form-label" for="{{ $formId }}PromotionPrice">Cena promocyjna</label>
            <div class="input-group">
                <input id="{{ $formId }}PromotionPrice" type="number" name="promotion_price" class="form-control" min="0" step="0.01"
                       value="{{ old('promotion_price', $price?->promotion_price) }}">
                <span class="input-group-text">zł</span>
            </div>
        </div>
        <div class="col-md-4" data-promotion-group>
            <label class="form-label" for="{{ $formId }}PromotionStartsAt">Promocja od</label>
            <input id="{{ $formId }}PromotionStartsAt" type="datetime-local" name="promotion_starts_at" class="form-control"
                   value="{{ old('promotion_starts_at', $promotionStarts) }}">
        </div>
        <div class="col-md-4" data-promotion-group>
            <label class="form-label" for="{{ $formId }}PromotionEndsAt">Promocja do</label>
            <input id="{{ $formId }}PromotionEndsAt" type="datetime-local" name="promotion_ends_at" class="form-control"
                   value="{{ old('promotion_ends_at', $promotionEnds) }}">
        </div>
        <div class="col-12" data-promotion-group>
            <div class="form-check">
                <input type="hidden" name="show_promotion_countdown" value="0">
                <input id="{{ $formId }}ShowCountdown" type="checkbox" name="show_promotion_countdown" value="1" class="form-check-input"
                       @checked((bool) old('show_promotion_countdown', $price?->show_promotion_countdown ?? false))>
                <label class="form-check-label" for="{{ $formId }}ShowCountdown">Pokaż licznik do końca promocji</label>
            </div>
            <div class="form-text">Widoczny na katalogu `/kursy` i na ofercie, tylko gdy ustawisz datę „Promocja do”.</div>
        </div>

        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
            <div class="form-check mb-0">
                <input type="hidden" name="is_active" value="0">
                <input id="{{ $formId }}IsActive" type="checkbox" name="is_active" value="1" class="form-check-input"
                       @checked((bool) old('is_active', $price?->is_active ?? true))>
                <label class="form-check-label" for="{{ $formId }}IsActive">Wariant aktywny</label>
                <div class="form-text">Wyłączenie chowa wariant z nowej sprzedaży. Nie odbiera już nadanych dostępów, także bezterminowych.</div>
            </div>
            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? 'Zapisz wariant' : 'Dodaj wariant' }}
            </button>
        </div>
    </div>
</form>
