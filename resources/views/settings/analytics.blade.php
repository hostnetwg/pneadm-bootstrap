<x-app-layout>
    <x-slot name="header">
        Analityka
    </x-slot>

    @php
        $pneduPublicUrl = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');
        $pneduPublicHost = parse_url($pneduPublicUrl, PHP_URL_HOST) ?: 'pnedu.pl';
        $admHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'adm.pnedu.pl';
    @endphp

    <div class="py-3">
        @if(session('error'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif
        @if(request()->query('funnel') === 'on')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Zliczanie lejka zostało włączone w tej przeglądarce.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @elseif(request()->query('funnel') === 'off')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Zliczanie lejka zostało wyłączone w tej przeglądarce.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif
        @if(request()->query('analytics') === 'on')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Google Analytics / GTM zostały włączone w tej przeglądarce.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @elseif(request()->query('analytics') === 'off')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Google Analytics / GTM zostały wyłączone w tej przeglądarce.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Lejek i analityka {{ $pneduPublicHost }} — włącz/wyłącz dla tej przeglądarki na tym komputerze</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Dwa niezależne przełączniki dla tej przeglądarki — ustawiamy cookie na <strong>{{ $pneduPublicHost }}</strong>
                    i w panelu adm (<code>{{ $admHost }}</code>), potem wracasz tutaj.
                    Kolor i napis <strong>ON/OFF</strong> pokazują aktualny stan; kliknięcie przełącza na przeciwny.
                </p>

                @if(filled($funnelSkipEnableUrl) && filled($funnelSkipDisableUrl))
                    @php
                        $funnelCountingOn = ! $funnelSkipEnabledForBrowser;
                        $analyticsOn = ! $funnelSkipAnalyticsEnabledForBrowser;
                    @endphp

                    <div class="row g-3 mb-3">
                        <div class="col-lg-6">
                            <a href="{{ route('settings.pnedu-purchases.funnel-skip', ['scope' => 'funnel', 'action' => $funnelCountingOn ? 'enable' : 'disable']) }}"
                               class="btn w-100 text-start p-3 border-2 shadow-sm {{ $funnelCountingOn ? 'btn-success' : 'btn-danger' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold"><i class="bi bi-funnel-fill me-1"></i> Lejek</span>
                                    <span class="badge rounded-pill fs-6 px-3 {{ $funnelCountingOn ? 'text-bg-light text-success' : 'text-bg-light text-danger' }}">
                                        {{ $funnelCountingOn ? 'ON' : 'OFF' }}
                                    </span>
                                </div>
                                <span class="small d-block {{ $funnelCountingOn ? 'text-white-50' : 'opacity-75' }}">
                                    Zliczanie wejść na opis szkolenia i formularz w adm
                                </span>
                                <span class="small d-block mt-2 fw-semibold">
                                    Kliknij → {{ $funnelCountingOn ? 'WYŁĄCZ' : 'WŁĄCZ' }}
                                </span>
                            </a>
                        </div>
                        <div class="col-lg-6">
                            <a href="{{ route('settings.pnedu-purchases.funnel-skip', ['scope' => 'analytics', 'action' => $analyticsOn ? 'enable' : 'disable']) }}"
                               class="btn w-100 text-start p-3 border-2 shadow-sm {{ $analyticsOn ? 'btn-success' : 'btn-danger' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Google Analytics / GTM</span>
                                    <span class="badge rounded-pill fs-6 px-3 {{ $analyticsOn ? 'text-bg-light text-success' : 'text-bg-light text-danger' }}">
                                        {{ $analyticsOn ? 'ON' : 'OFF' }}
                                    </span>
                                </div>
                                <span class="small d-block {{ $analyticsOn ? 'text-white-50' : 'opacity-75' }}">
                                    Ładowanie GA4 i tagów GTM na pnedu.pl
                                </span>
                                <span class="small d-block mt-2 fw-semibold">
                                    Kliknij → {{ $analyticsOn ? 'WYŁĄCZ' : 'WŁĄCZ' }}
                                </span>
                            </a>
                        </div>
                    </div>

                    <p class="small text-muted mb-3">
                        <strong>ON</strong> (zielony) = dane są zliczane &nbsp;·&nbsp;
                        <strong>OFF</strong> (czerwony) = wyłączone dla tej przeglądarki
                    </p>

                    <details class="mb-3">
                        <summary class="small text-muted" style="cursor: pointer;">Bezpośrednie linki na {{ $pneduPublicHost }} (zakładki / udostępnienie)</summary>
                        <div class="row g-3 mt-2">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">Lejek — wyłącz</div>
                                    <a href="{{ $funnelSkipEnableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $funnelSkipEnableUrl }}</a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">Lejek — włącz ponownie</div>
                                    <a href="{{ $funnelSkipDisableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $funnelSkipDisableUrl }}</a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">GA/GTM — wyłącz</div>
                                    <a href="{{ $analyticsSkipEnableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $analyticsSkipEnableUrl }}</a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">GA/GTM — włącz ponownie</div>
                                    <a href="{{ $analyticsSkipDisableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $analyticsSkipDisableUrl }}</a>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            Te linki ustawiają cookie tylko na {{ $pneduPublicHost }} — status na localhost może się nie zmienić, dopóki nie użyjesz przycisków powyżej.
                        </p>
                    </details>
                @else
                    <div class="alert alert-warning mb-3">
                        Brak konfiguracji <code>MARKETING_FUNNEL_SKIP_TOKEN</code> — ustaw token w <code>.env</code>, aby generować gotowe linki ON/OFF.
                    </div>
                @endif

                @if($funnelSkipEnabledForBrowser && $funnelSkipUntil)
                    <div class="border rounded p-3">
                        <div class="small text-muted">
                            Cookie lejka ważne do:
                            <strong>{{ $funnelSkipUntil->timezone(config('app.timezone', 'Europe/Warsaw'))->format('d.m.Y H:i') }}</strong>
                            (strefa {{ config('app.timezone', 'Europe/Warsaw') }}).
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
