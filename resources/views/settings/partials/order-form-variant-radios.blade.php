@php
    use App\Support\OrderFormVariant;

    $fieldName = $fieldName ?? 'order_form_variant';
    $fieldIdPrefix = $fieldIdPrefix ?? 'order_form_variant';
    $includeGlobalOption = $includeGlobalOption ?? false;
    $campaignDefault = $includeGlobalOption ? OrderFormVariant::GLOBAL : OrderFormVariant::LEGACY;
    $selectedVariant = $includeGlobalOption
        ? OrderFormVariant::normalizeCampaignVariant($selectedVariant ?? $campaignDefault, $campaignDefault)
        : OrderFormVariant::normalize($selectedVariant ?? OrderFormVariant::LEGACY);
    $legend = $legend ?? 'Wersja formularza zamówienia';
    $hint = $hint ?? null;
    $variantOptions = [];
    if ($includeGlobalOption) {
        $variantOptions[OrderFormVariant::GLOBAL] = 'Domyślna globalna — <code>/courses/{id}/order-form</code> (bez <code>form_variant</code>; jak archiwalne linki FB/newsletter i przycisk na stronie kursu)';
    }
    $variantOptions[OrderFormVariant::LEGACY] = 'Formularz uniwersalny (legacy) — <code>/courses/{id}/order-form?form_variant=legacy</code>';
    $variantOptions[OrderFormVariant::V2] = 'Formularz V2 — <code>/courses/{id}/order-form?form_variant=v2</code>';
@endphp

<fieldset class="mb-0">
    <legend class="form-label fw-semibold small text-muted text-uppercase mb-2">{{ $legend }}</legend>
    @if($hint)
        <p class="form-text text-muted small mb-2">{{ $hint }}</p>
    @endif
    @foreach($variantOptions as $value => $label)
        <div class="form-check mb-2">
            <input class="form-check-input"
                   type="radio"
                   name="{{ $fieldName }}"
                   id="{{ $fieldIdPrefix }}_{{ $value }}"
                   value="{{ $value }}"
                @checked($selectedVariant === $value)>
            <label class="form-check-label" for="{{ $fieldIdPrefix }}_{{ $value }}">{!! $label !!}</label>
        </div>
    @endforeach
    @error($fieldName)
        <div class="text-danger small">{{ $message }}</div>
    @enderror
    <p class="form-text text-muted small mb-0">
        @if($includeGlobalOption)
            <strong>Domyślna globalna</strong> (zalecana dla nowych kampanii) — formularz z ustawień Zakupy pnedu.pl w chwili wejścia.
            <strong>Legacy / V2</strong> — wariant zamrożony w URL (np. jednorazowy newsletter, gdy formularz ma się nie zmieniać po wysyłce).
            Jeśli przypięty wariant jest wyłączony checkboxem, brama użyje dostępnego wariantu.
        @else
            Jeśli wybrana wersja jest wyłączona w ustawieniach zakupów pnedu.pl, nowe skróty (Zapisz się, przycisk na kursie) użyją dostępnej wersji.
        @endif
    </p>
</fieldset>
