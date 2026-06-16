@php
    $selectedSourceTypeId = $selectedSourceTypeId ?? null;
    $currentUtmMedium = $currentUtmMedium ?? null;
    $typeDefaultMedium = $sourceTypes->firstWhere('id', (int) $selectedSourceTypeId)?->default_utm_medium ?? 'paid';
    $isCustomMedium = old('utm_medium_custom')
        ? (bool) old('utm_medium_custom')
        : (filled($currentUtmMedium) && $currentUtmMedium !== $typeDefaultMedium);
    $effectiveMedium = $isCustomMedium && filled($currentUtmMedium)
        ? $currentUtmMedium
        : $typeDefaultMedium;
    $sourceTypeUtmDefaults = $sourceTypes->mapWithKeys(fn ($type) => [
        $type->id => [
            'utm_source' => $type->utm_source ?? '',
            'default_utm_medium' => $type->default_utm_medium ?: 'paid',
        ],
    ]);
@endphp

<div class="mb-0" id="utm-medium-fields">
    <label for="utm_medium" class="form-label fw-semibold">
        Medium <span class="text-muted fw-normal small">· <code>utm_medium</code></span>
    </label>

    <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2 py-2 px-2 rounded bg-light border">
        <span>Domyślnie z typu źródła:</span>
        <span class="badge bg-white border text-dark"><code id="utm-source-preview">—</code></span>
        <span class="text-muted">/</span>
        <span class="badge bg-white border text-dark"><code id="utm-medium-default-preview">{{ $effectiveMedium }}</code></span>
    </div>

    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox"
               id="utm_medium_custom" name="utm_medium_custom" value="1"
               {{ $isCustomMedium ? 'checked' : '' }}>
        <label class="form-check-label" for="utm_medium_custom">
            Niestandardowe <code>utm_medium</code> (zaawansowane)
        </label>
    </div>

    <select class="form-select @error('utm_medium') is-invalid @enderror"
            id="utm_medium" name="utm_medium"
            {{ $isCustomMedium ? '' : 'disabled' }}>
        @foreach($utmMediumOptions as $value => $label)
            <option value="{{ $value }}" {{ $effectiveMedium === $value ? 'selected' : '' }}>
                {{ $label }} ({{ $value }})
            </option>
        @endforeach
    </select>
    @error('utm_medium')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <x-campaign-field-hint summary="Zaznacz checkbox tylko przy świadomym override medium w GA.">
        W większości kampanii wystarczy wartość z typu źródła.
    </x-campaign-field-hint>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceTypeSelect = document.getElementById('source_type_id');
    const mediumSelect = document.getElementById('utm_medium');
    const customCheckbox = document.getElementById('utm_medium_custom');
    const sourcePreview = document.getElementById('utm-source-preview');
    const mediumPreview = document.getElementById('utm-medium-default-preview');
    const sourceTypeDefaults = @json($sourceTypeUtmDefaults);

    if (!sourceTypeSelect || !mediumSelect || !customCheckbox) {
        return;
    }

    function getTypeMeta(typeId) {
        return sourceTypeDefaults[String(typeId)] || { utm_source: '—', default_utm_medium: 'paid' };
    }

    function setMediumSelectValue(value) {
        if (!value) {
            return;
        }
        for (const option of mediumSelect.options) {
            option.selected = option.value === value;
        }
    }

    function updatePreview() {
        const meta = getTypeMeta(sourceTypeSelect.value);
        const defaultMedium = meta.default_utm_medium || 'paid';
        sourcePreview.textContent = meta.utm_source || '—';
        mediumPreview.textContent = customCheckbox.checked
            ? (mediumSelect.value || defaultMedium)
            : defaultMedium;
        document.dispatchEvent(new CustomEvent('pne:campaign-form-change'));
    }

    function syncMediumField() {
        const meta = getTypeMeta(sourceTypeSelect.value);
        const defaultMedium = meta.default_utm_medium || 'paid';

        if (customCheckbox.checked) {
            mediumSelect.disabled = false;
        } else {
            mediumSelect.disabled = true;
            setMediumSelectValue(defaultMedium);
        }

        updatePreview();
        document.dispatchEvent(new CustomEvent('pne:campaign-form-change'));
    }

    sourceTypeSelect.addEventListener('change', syncMediumField);
    customCheckbox.addEventListener('change', syncMediumField);
    mediumSelect.addEventListener('change', updatePreview);

    syncMediumField();
});
</script>
@endpush
