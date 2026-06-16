@php
    $landingDefault = $landingDefault ?? 'order_form';
    $landingValue = old('landing_target', $landingDefault);
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
            Formularz zamówienia — /courses/{id}/order-form
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
</div>
