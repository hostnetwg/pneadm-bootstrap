@php
    $selectedSourceTypeId = $selectedSourceTypeId ?? null;
@endphp

<div class="mb-0">
    <label for="source_type_id" class="form-label fw-semibold">
        Typ źródła <span class="text-danger">*</span>
    </label>
    <select class="form-select @error('source_type_id') is-invalid @enderror"
            id="source_type_id" name="source_type_id" required>
        <option value="">— wybierz kanał (newsletter, Facebook, strona…) —</option>
        @foreach($sourceTypes as $sourceType)
            <option value="{{ $sourceType->id }}" {{ (int) $selectedSourceTypeId === (int) $sourceType->id ? 'selected' : '' }}>
                {{ $sourceType->name }}@if(filled($sourceType->utm_source)) · {{ $sourceType->utm_source }}@endif
            </option>
        @endforeach
    </select>
    @error('source_type_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <x-campaign-field-hint summary="Określa domyślne utm_source i utm_medium w generowanym linku — nie zastępuje kodu kampanii.">
        Kolejność na liście ustawiasz w
        <a href="{{ route('marketing-source-types.index') }}">Typach źródeł</a>
        (przeciąganie wierszy). Dla newsletterów wybierz typ z właściwym adresem nadawcy.
    </x-campaign-field-hint>
</div>
