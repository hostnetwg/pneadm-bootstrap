@php
    $idPrefix = $idPrefix ?? 'campaign';
    $showHeading = $showHeading ?? true;
    $compact = $compact ?? false;
    $verifyShortLinkUrl = $verifyShortLinkUrl ?? null;
    $showDocsLink = $showDocsLink ?? true;
    $marketingCampaign = $marketingCampaign ?? null;
@endphp

@if($showHeading)
    <h5 class="{{ $compact ? 'h6' : '' }} {{ $compact ? 'mb-2' : 'mt-0' }}">Linki kampanii</h5>
@endif

@if(!empty($campaignUrls['utm']))
    <div class="campaign-links-panel {{ $compact ? 'campaign-links-panel--compact' : '' }}">
        @unless($compact)
            @if(app()->environment('local'))
                <div class="alert alert-info border-0 small py-2 mb-3">
                    <i class="bi bi-laptop"></i>
                    <strong>Dev:</strong> linki wskazują na <code>{{ config('marketing.pnedu_public_url') }}</code>
                    (nie <code>pnedu.pl</code>). Otwórz „Otwórz w nowej karcie” — powinno trafić na lokalny pnedu.
                </div>
            @endif
            <div class="mb-3">
                <div class="small text-muted mb-2">Parametry UTM w generowanych linkach:</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill text-bg-light border">
                        <span class="text-muted">utm_source</span>
                        <code class="ms-1">{{ $campaignUrls['utm_source'] }}</code>
                    </span>
                    <span class="badge rounded-pill text-bg-light border">
                        <span class="text-muted">utm_medium</span>
                        <code class="ms-1">{{ $campaignUrls['utm_medium'] }}</code>
                    </span>
                    <span class="badge rounded-pill text-bg-light border">
                        <span class="text-muted">utm_campaign</span>
                        <code class="ms-1">{{ $campaignUrls['utm_campaign'] }}</code>
                    </span>
                    @if(filled($campaignUrls['utm_content'] ?? null))
                        <span class="badge rounded-pill text-bg-light border">
                            <span class="text-muted">utm_content</span>
                            <code class="ms-1">{{ $campaignUrls['utm_content'] }}</code>
                        </span>
                    @endif
                </div>
                @if($marketingCampaign?->course)
                    <div class="small text-muted mt-2">
                        Szkolenie:
                        <strong>#{{ $marketingCampaign->course_id }}</strong>
                        · {{ Str::limit(strip_tags($marketingCampaign->course->title), 60) }}
                        · {{ ($marketingCampaign->landing_target ?? 'course_show') === 'order_form' ? 'formularz zamówienia' : 'opis szkolenia' }}
                    </div>
                @endif
            </div>
        @endunless

        {{-- 1. Pełny link UTM --}}
        <div class="card mb-3 border-primary-subtle">
            <div class="card-header py-2 bg-primary-subtle d-flex align-items-start justify-content-between gap-2 flex-wrap">
                <div>
                    <span class="badge bg-primary me-1">1</span>
                    <strong class="small">Link pełny UTM</strong>
                    <span class="text-muted small d-block mt-1">Newsletter HTML, precyzyjna analityka GA4, raporty w adm</span>
                </div>
            </div>
            <div class="card-body py-2">
                <label class="visually-hidden" for="{{ $idPrefix }}UtmUrl">Link UTM</label>
                <div class="input-group {{ $compact ? 'input-group-sm' : '' }}">
                    <input type="text" class="form-control font-monospace small" readonly
                           value="{{ $campaignUrls['utm'] }}" id="{{ $idPrefix }}UtmUrl">
                    <button type="button" class="btn btn-primary btn-copy-campaign-url"
                            data-copy-target="#{{ $idPrefix }}UtmUrl" title="Kopiuj link UTM">
                        <i class="bi bi-clipboard"></i> Kopiuj
                    </button>
                </div>
            </div>
        </div>

        {{-- 2. Link krótki — pod pełnym UTM --}}
        <div class="card mb-3 border-success-subtle">
            <div class="card-header py-2 bg-success-subtle">
                <span class="badge bg-success me-1">2</span>
                <strong class="small">Link krótki</strong>
                <span class="text-muted small d-block mt-1">YouTube, Facebook, Instagram, SMS — krótszy adres, to samo przekierowanie z UTM</span>
            </div>
            <div class="card-body py-2">
                <label class="visually-hidden" for="{{ $idPrefix }}ShortUrl">Link krótki</label>
                <div class="input-group {{ $compact ? 'input-group-sm' : '' }}">
                    <input type="text" class="form-control font-monospace small" readonly
                           value="{{ $campaignUrls['short'] ?? '' }}" id="{{ $idPrefix }}ShortUrl">
                    <button type="button" class="btn btn-success btn-copy-campaign-url"
                            data-copy-target="#{{ $idPrefix }}ShortUrl" title="Kopiuj link krótki">
                        <i class="bi bi-clipboard"></i> Kopiuj
                    </button>
                </div>
                <div class="small text-muted mt-2 mb-0">
                    <code>/l/{{ $campaignUrls['utm_campaign'] }}</code> → przekierowanie <strong>302</strong> na link pełny UTM powyżej.
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @if(!empty($campaignUrls['short']))
                        <a href="{{ $campaignUrls['short'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-box-arrow-up-right"></i> Otwórz w nowej karcie
                        </a>
                    @endif
                    @if($verifyShortLinkUrl)
                        <button type="button" class="btn btn-sm btn-outline-info btn-verify-short-link"
                                data-verify-url="{{ $verifyShortLinkUrl }}"
                                data-result-target="#{{ $idPrefix }}ShortLinkTest">
                            <i class="bi bi-shield-check"></i> Sprawdź przekierowanie
                        </button>
                    @endif
                </div>
                <div id="{{ $idPrefix }}ShortLinkTest" class="mt-2" style="display: none;"></div>
            </div>
        </div>

        {{-- 3. Legacy --}}
        <div class="card mb-0 border-secondary-subtle">
            <div class="card-header py-2 bg-light">
                <span class="badge bg-secondary me-1">3</span>
                <strong class="small">Link legacy</strong>
                <span class="text-muted small d-block mt-1">Tylko stare materiały — parametr <code>fb</code> zamiast UTM</span>
            </div>
            <div class="card-body py-2">
                <label class="visually-hidden" for="{{ $idPrefix }}LegacyUrl">Link legacy</label>
                <div class="input-group {{ $compact ? 'input-group-sm' : '' }}">
                    <input type="text" class="form-control font-monospace small" readonly
                           value="{{ $campaignUrls['legacy'] }}" id="{{ $idPrefix }}LegacyUrl">
                    <button type="button" class="btn btn-outline-secondary btn-copy-campaign-url"
                            data-copy-target="#{{ $idPrefix }}LegacyUrl" title="Kopiuj link legacy">
                        <i class="bi bi-clipboard"></i> Kopiuj
                    </button>
                </div>
            </div>
        </div>

        @if($showDocsLink && !$compact)
            <div class="mt-3 pt-3 border-top">
                <a href="{{ route('marketing.help.links') }}" class="small text-decoration-none">
                    <i class="bi bi-book"></i> Jak działają linki kampanii i parametry UTM?
                </a>
            </div>
        @elseif($showDocsLink && $compact)
            <p class="small text-muted mb-0 mt-3">
                <a href="{{ route('marketing.help.links') }}" class="text-decoration-none">Pomoc: linki i UTM</a>
            </p>
        @endif
    </div>
@else
    <div class="alert alert-light border small mb-0">
        <i class="bi bi-link-45deg text-muted"></i>
        Ustaw <strong>powiązane szkolenie</strong>{{ $compact ? '' : ' w edycji kampanii' }}, aby wygenerować linki.
        @if($showDocsLink)
            <span class="d-block mt-1">
                <a href="{{ route('marketing.help.links') }}">Jak działają linki kampanii?</a>
            </span>
        @endif
    </div>
@endif

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-copy-campaign-url').forEach(function (btn) {
            if (btn.dataset.copyBound === '1') {
                return;
            }
            btn.dataset.copyBound = '1';
            btn.addEventListener('click', function () {
                const selector = btn.getAttribute('data-copy-target');
                const input = selector ? document.querySelector(selector) : null;
                if (!input || !input.value) {
                    return;
                }
                navigator.clipboard.writeText(input.value).then(function () {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check2"></i> Skopiowano';
                    const wasPrimary = btn.classList.contains('btn-primary');
                    const wasSuccess = btn.classList.contains('btn-success');
                    btn.classList.add('btn-success');
                    btn.classList.remove('btn-primary', 'btn-outline-secondary', 'btn-outline-success', 'btn-outline-primary');
                    setTimeout(function () {
                        btn.innerHTML = original;
                        btn.classList.remove('btn-success');
                        if (wasSuccess) {
                            btn.classList.add('btn-success');
                        } else if (selector && selector.includes('Legacy')) {
                            btn.classList.add('btn-outline-secondary');
                        } else if (wasPrimary) {
                            btn.classList.add('btn-primary');
                        } else {
                            btn.classList.add('btn-outline-secondary');
                        }
                    }, 1500);
                });
            });
        });

        document.querySelectorAll('.btn-verify-short-link').forEach(function (btn) {
            if (btn.dataset.verifyBound === '1') {
                return;
            }
            btn.dataset.verifyBound = '1';
            btn.addEventListener('click', function () {
                const verifyUrl = btn.getAttribute('data-verify-url');
                const resultSelector = btn.getAttribute('data-result-target');
                const resultEl = resultSelector ? document.querySelector(resultSelector) : null;
                if (!verifyUrl || !resultEl) {
                    return;
                }

                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sprawdzam…';

                fetch(verifyUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (payload) {
                        const data = payload.data || {};
                        const alertClass = data.ok ? 'alert-success' : 'alert-warning';
                        let html = '<div class="alert ' + alertClass + ' small py-2 mb-0">'
                            + '<strong>' + (data.ok ? 'OK' : 'Uwaga') + ':</strong> '
                            + (data.message || 'Brak odpowiedzi.') + '</div>';

                        if (data.redirect_to) {
                            html += '<div class="small text-muted mt-1">Przekierowanie → <code class="text-break">' + data.redirect_to + '</code></div>';
                        }

                        resultEl.innerHTML = html;
                        resultEl.style.display = 'block';
                    })
                    .catch(function () {
                        resultEl.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Nie udało się wykonać testu przekierowania.</div>';
                        resultEl.style.display = 'block';
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    });
            });
        });
    });
    </script>
    @endpush
@endonce
