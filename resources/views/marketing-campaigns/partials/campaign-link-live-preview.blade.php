@php
    $pneduBaseUrl = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');
    $sourceTypesForPreview = $sourceTypes->mapWithKeys(fn ($type) => [
        $type->id => [
            'utm_source' => $type->utm_source,
            'default_utm_medium' => $type->default_utm_medium ?: 'paid',
            'default_utm_content' => $type->default_utm_content ?? '',
            'slug' => $type->slug,
        ],
    ]);
    $isDraft = $isDraft ?? true;
@endphp

<div class="card border-info-subtle bg-info-subtle bg-opacity-10 mb-0 shadow-sm" id="campaign-link-live-preview">
    <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-eye text-info"></i>
            <h6 class="fw-semibold mb-0">Podgląd linku na żywo</h6>
        </div>
        @if($isDraft)
            <p class="small text-muted mb-3">
                Aktualizuje się przy zmianie pól powyżej. Po zapisaniu skopiujesz linki z podglądu kampanii.
            </p>
        @else
            <p class="small text-muted mb-3">
                Podgląd przed zapisem. Zapisane linki są w zielonej sekcji u góry strony.
            </p>
        @endif

        <div class="row g-2">
            <div class="col-12">
                <label class="form-label small text-muted mb-1">Link UTM</label>
                <div class="font-monospace small border rounded bg-white px-2 py-2 text-break"
                     id="campaign-link-preview-utm"
                     style="word-break: break-all;">—</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Link krótki <span class="fw-normal">/l/…</span></label>
                <div class="font-monospace small border rounded bg-white px-2 py-2 text-break"
                     id="campaign-link-preview-short"
                     style="word-break: break-all;">—</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Legacy <code>fb</code></label>
                <div class="font-monospace small border rounded bg-white px-2 py-2 text-break"
                     id="campaign-link-preview-legacy"
                     style="word-break: break-all;">—</div>
            </div>
            <div class="col-12">
                <div class="small text-muted pt-1" id="campaign-link-preview-params">—</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pneduBaseUrl = @json($pneduBaseUrl);
    const sourceTypeMeta = @json($sourceTypesForPreview);

    const campaignCodeInput = document.getElementById('campaign_code');
    const sourceTypeSelect = document.getElementById('source_type_id');
    const mediumSelect = document.getElementById('utm_medium');
    const customMediumCheckbox = document.getElementById('utm_medium_custom');
    const courseSelect = document.getElementById('course_id');
    const landingTargetSelect = document.getElementById('landing_target');
    const orderFormVariantInputs = document.querySelectorAll('input[name="order_form_variant"]');
    const utmContentInput = document.getElementById('utm_content');
    const utmPreview = document.getElementById('campaign-link-preview-utm');
    const shortPreview = document.getElementById('campaign-link-preview-short');
    const legacyPreview = document.getElementById('campaign-link-preview-legacy');
    const paramsPreview = document.getElementById('campaign-link-preview-params');

    if (!utmPreview || !legacyPreview || !paramsPreview) {
        return;
    }

    function getTypeMeta(typeId) {
        return sourceTypeMeta[String(typeId)] || null;
    }

    function resolveUtmSource(meta) {
        if (!meta) {
            return 'other';
        }
        if (meta.utm_source) {
            return String(meta.utm_source);
        }
        switch (meta.slug) {
            case 'email':
                return 'newsletter';
            case 'website':
                return 'pnedu';
            case 'training':
                return 'webinar';
            default:
                return meta.slug ? String(meta.slug) : 'other';
        }
    }

    function resolveUtmMedium(meta) {
        const defaultMedium = meta?.default_utm_medium || 'paid';
        if (customMediumCheckbox?.checked && mediumSelect?.value) {
            return mediumSelect.value;
        }
        return defaultMedium;
    }

    function buildQuery(params) {
        return Object.keys(params)
            .map(function (key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            })
            .join('&');
    }

    function updateLinkPreview() {
        const typeMeta = getTypeMeta(sourceTypeSelect?.value);
        const utmSource = resolveUtmSource(typeMeta);
        const utmMedium = resolveUtmMedium(typeMeta);
        const utmCampaign = (campaignCodeInput?.value || '').trim();
        const utmContent = (utmContentInput?.value || '').trim()
            || (typeMeta?.default_utm_content || '').trim();
        const courseId = (courseSelect?.value || '').trim();
        const landingTarget = landingTargetSelect?.value || 'order_form';
        const orderFormVariantInput = document.querySelector('input[name="order_form_variant"]:checked');
        const orderFormVariant = orderFormVariantInput?.value || 'global';

        let paramsText = 'utm_source=' + utmSource
            + ', utm_medium=' + utmMedium
            + ', utm_campaign=' + (utmCampaign || '—');
        if (utmContent) {
            paramsText += ', utm_content=' + utmContent;
        }
        paramsPreview.textContent = paramsText;

        if (!courseId) {
            utmPreview.textContent = 'Wybierz powiązane szkolenie, aby zobaczyć pełny adres URL.';
            if (shortPreview) {
                shortPreview.textContent = utmCampaign
                    ? pneduBaseUrl + '/l/' + encodeURIComponent(utmCampaign) + ' (wymaga szkolenia do działania przekierowania)'
                    : 'Uzupełnij kod kampanii.';
            }
            legacyPreview.textContent = 'Wybierz powiązane szkolenie, aby zobaczyć pełny adres URL.';
            return;
        }

        const path = landingTarget === 'order_form'
            ? '/courses/' + courseId + '/order-form'
            : '/courses/' + courseId;

        const utmParams = {
            utm_source: utmSource,
            utm_medium: utmMedium,
            utm_campaign: utmCampaign || '—',
        };
        if (utmContent) {
            utmParams.utm_content = utmContent;
        }
        if (landingTarget === 'order_form' && orderFormVariant !== 'global') {
            utmParams.form_variant = orderFormVariant;
        }

        const utmQuery = buildQuery(utmParams);

        const legacyQuery = landingTarget === 'order_form'
            ? buildQuery(orderFormVariant === 'global'
                ? { fb: utmCampaign || '' }
                : { fb: utmCampaign || '', form_variant: orderFormVariant })
            : ('fb=' + encodeURIComponent(utmCampaign || ''));

        utmPreview.textContent = pneduBaseUrl + path + '?' + utmQuery;
        if (shortPreview && utmCampaign) {
            shortPreview.textContent = pneduBaseUrl + '/l/' + encodeURIComponent(utmCampaign);
        }
        legacyPreview.textContent = pneduBaseUrl + path + '?' + legacyQuery;
    }

    const form = campaignCodeInput?.closest('form');
    if (form) {
        form.addEventListener('input', updateLinkPreview);
        form.addEventListener('change', updateLinkPreview);
    }

    document.addEventListener('pne:campaign-form-change', updateLinkPreview);

    updateLinkPreview();
});
</script>
@endpush
