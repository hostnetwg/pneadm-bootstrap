@php
    $nameValue = old('name', $nameDefault ?? '');
    $descriptionValue = old('description', $descriptionDefault ?? '');
@endphp

<div class="mb-3">
    <label for="name" class="form-label fw-semibold">Nazwa kampanii <span class="text-danger">*</span></label>
    <input type="text"
           class="form-control @error('name') is-invalid @enderror"
           id="name"
           name="name"
           value="{{ $nameValue }}"
           maxlength="255"
           placeholder="np. Newsletter 2026-09 — AI dla nauczycieli"
           required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <x-campaign-field-hint summary="Czytelna etykieta tylko w adm — nie trafia do UTM. Co + kiedy + opcjonalnie kanał.">
        Przykłady: <em>Newsletter 2026-09 — AI dla nauczycieli</em>,
        <em>FB Ads — Dyrektorzy, nadzór pedagogiczny</em>.
        Przycisk <strong>Zaproponuj</strong> przy kodzie bierze slug z nazwy (gdy wypełniona).
    </x-campaign-field-hint>
</div>

<div class="mb-0">
    <label for="description" class="form-label fw-semibold">
        Opis <span class="text-muted fw-normal small">opcjonalnie</span>
    </label>
    <textarea class="form-control @error('description') is-invalid @enderror"
              id="description"
              name="description"
              rows="2"
              maxlength="5000"
              placeholder="Notatki: lista Sendy, segment, temat maila, reminder…">{{ $descriptionValue }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <x-campaign-field-hint summary="Notatka wewnętrzna — poza linkiem, GA i zamówieniami.">
        Np. lista Sendy, wykluczenia segmentu, wariant banera, budżet reklamy, uwagi po poprzedniej edycji.
    </x-campaign-field-hint>
</div>
