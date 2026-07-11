@php
    use App\Support\OrderFormVariant;

    $landingDefault = $landingDefault ?? 'order_form';
    $landingValue = old('landing_target', $landingDefault);
    $orderFormVariantDefault = $orderFormVariantDefault ?? OrderFormVariant::LEGACY;
    $orderFormVariantValue = OrderFormVariant::normalizeCampaignVariant(
        old('order_form_variant', $orderFormVariantDefault)
    );
@endphp

<div class="mb-0">
    <label for="landing_target" class="form-label fw-semibold">
        Strona docelowa
        <span class="text-muted fw-normal small">· ścieżka URL</span>
    </label>
    <select class="form-select @error('landing_target') is-invalid @enderror"
            id="landing_target"
            name="landing_target">
        <option value="order_form" {{ $landingValue === 'order_form' ? 'selected' : '' }}>
            Formularz zamówienia
        </option>
        <option value="course_show" {{ $landingValue === 'course_show' ? 'selected' : '' }}>
            Opis szkolenia — /courses/{id}
        </option>
    </select>
    @error('landing_target')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <x-campaign-field-hint summary="Domyślnie formularz zamówienia — krótsza ścieżka do zapisu.">
        Wybierz opis szkolenia, gdy link ma najpierw pokazać program (post edukacyjny, remarketing z kontekstem).
        To nie jest parametr UTM.
    </x-campaign-field-hint>

    <div id="campaign-order-form-variant-wrap" class="mt-3 pt-3 border-top" @if($landingValue !== 'order_form') hidden @endif>
        @include('settings.partials.order-form-variant-radios', [
            'fieldName' => 'order_form_variant',
            'fieldIdPrefix' => 'campaign_order_form_variant',
            'selectedVariant' => $orderFormVariantValue,
            'includeGlobalOption' => true,
            'legend' => 'Wersja formularza zamówienia',
            'hint' => 'Domyślnie: wersja globalna (bez form_variant). Dla jednorazowej wysyłki wybierz legacy lub V2, aby zamrozić formularz w linku.',
        ])
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var landingSelect = document.getElementById('landing_target');
                var variantWrap = document.getElementById('campaign-order-form-variant-wrap');
                if (!landingSelect || !variantWrap) {
                    return;
                }
                function syncOrderFormVariantVisibility() {
                    variantWrap.hidden = landingSelect.value !== 'order_form';
                }
                landingSelect.addEventListener('change', syncOrderFormVariantVisibility);
                syncOrderFormVariantVisibility();
            });
        </script>
    @endpush
@endonce
