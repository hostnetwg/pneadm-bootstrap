@php
    $currentUtmContent = $currentUtmContent ?? '';
    $sourceTypeDefaults = $sourceTypeDefaults ?? [];
    $utmContentPresets = config('marketing.utm_content_presets', []);
@endphp

<div class="mb-0" id="utm-content-fields">
    <label for="utm_content" class="form-label fw-semibold">
        Wariant / taktyka
        <span class="text-muted fw-normal small">· <code>utm_content</code></span>
    </label>

    <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2 py-2 px-2 rounded bg-light border">
        <span>Domyślnie z typu źródła:</span>
        <span class="badge bg-white border text-dark">
            <code id="utm-content-default-preview">{{ $currentUtmContent !== '' && $currentUtmContent !== null ? $currentUtmContent : '—' }}</code>
        </span>
        <span class="text-muted" id="utm-content-default-hint"></span>
    </div>

    <input type="text"
           class="form-control font-monospace @error('utm_content') is-invalid @enderror"
           id="utm_content"
           name="utm_content"
           value="{{ old('utm_content', $currentUtmContent) }}"
           maxlength="100"
           list="utm-content-presets"
           placeholder="prospecting, remarketing, cta-hero…"
           autocomplete="off">
    <datalist id="utm-content-presets">
        @foreach(array_keys($utmContentPresets) as $preset)
            <option value="{{ $preset }}"></option>
        @endforeach
    </datalist>
    @error('utm_content')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <x-campaign-field-hint summary="Rozróżnia taktykę w GA4 przy tym samym utm_source/utm_medium — standard branżowy.">
        <code>prospecting</code> — płatny ruch zimny (Facebook, TikTok);
        <code>remarketing</code> — retargeting;
        <code>organic</code> — post bez promocji;
        <code>cta-hero</code> / <code>cta-stopka</code> — warianty w mailu;
        <code>video-description</code> / <code>live-event</code> — YouTube (opis vs wydarzenie).
        Puste pole = wartość z typu źródła.
    </x-campaign-field-hint>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceTypeSelect = document.getElementById('source_type_id');
    const utmContentInput = document.getElementById('utm_content');
    const defaultPreview = document.getElementById('utm-content-default-preview');
    const defaultHint = document.getElementById('utm-content-default-hint');
    const sourceTypeDefaults = @json($sourceTypeDefaults);
    const contentPresets = @json($utmContentPresets);

    if (!utmContentInput) {
        return;
    }

    let lastAutoFilled = utmContentInput.value || '';
    let userEdited = utmContentInput.dataset.userEdited === '1';

    function getTypeMeta(typeId) {
        return sourceTypeDefaults[String(typeId)] || { default_utm_content: '' };
    }

    function updateDefaultPreview() {
        const meta = getTypeMeta(sourceTypeSelect?.value);
        const defaultContent = (meta.default_utm_content || '').trim();
        if (defaultPreview) {
            defaultPreview.textContent = defaultContent || '—';
        }
        if (defaultHint) {
            defaultHint.textContent = defaultContent && contentPresets[defaultContent]
                ? '· ' + contentPresets[defaultContent]
                : '';
        }
    }

    function syncUtmContentFromSourceType() {
        const meta = getTypeMeta(sourceTypeSelect?.value);
        const defaultContent = (meta.default_utm_content || '').trim();
        const current = (utmContentInput.value || '').trim();

        if (!userEdited && (current === '' || current === lastAutoFilled)) {
            utmContentInput.value = defaultContent;
            lastAutoFilled = defaultContent;
        }

        updateDefaultPreview();
        document.dispatchEvent(new CustomEvent('pne:campaign-form-change'));
    }

    utmContentInput.addEventListener('input', function () {
        const current = (utmContentInput.value || '').trim();
        userEdited = current !== '' && current !== lastAutoFilled;
        utmContentInput.dataset.userEdited = userEdited ? '1' : '0';
        document.dispatchEvent(new CustomEvent('pne:campaign-form-change'));
    });

    if (sourceTypeSelect) {
        sourceTypeSelect.addEventListener('change', syncUtmContentFromSourceType);
    }

    updateDefaultPreview();
    if (!utmContentInput.value && sourceTypeSelect?.value) {
        syncUtmContentFromSourceType();
    }
});
</script>
@endpush
