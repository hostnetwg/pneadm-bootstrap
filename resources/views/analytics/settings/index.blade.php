<x-app-layout>
    @php
        $modeLabels = [
            'off' => 'off — analityka wyłączona',
            'aggregate_only' => 'aggregate_only — bardzo ograniczony zakres eventów',
            'light' => 'light — lekki zakres eventów',
            'standard' => 'standard — standardowy zakres',
            'full' => 'full — pełny zakres (obecnie zbliżony do standard, docelowo pod JS tracking)',
        ];
        $sourceLabels = [
            'config' => '.env / config',
            'runtime_override' => 'runtime override (baza pneadm)',
            'hard_kill_switch' => 'hard kill switch (.env ANALYTICS_ENABLED=false)',
        ];
        $boolText = fn (?bool $value) => $value === null ? 'użyj .env/config' : ($value ? 'włączone' : 'wyłączone');
    @endphp

    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Analityka — Ustawienia
            </h2>
            @if(config('analytics.sales_funnel_dashboard.enabled', true))
                <a href="{{ route('analytics.sales-funnel.index') }}" class="btn btn-outline-secondary btn-sm">
                    Lejek sprzedaży
                </a>
            @endif
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid px-0" style="max-width: 960px;">

            @if(session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 1. Efektywny stan --}}
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Aktualny efektywny stan analityki</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Zbieranie eventów</div>
                            <div class="fs-5 fw-semibold">
                                @if($effectiveEnabled)
                                    <span class="badge text-bg-success">WŁĄCZONE</span>
                                @else
                                    <span class="badge text-bg-danger">WYŁĄCZONE</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Efektywny tryb</div>
                            <div class="fs-5 fw-semibold"><code>{{ $effectiveMode }}</code></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Źródło wartości</div>
                            <div>
                                <div class="small">Włączenie: <strong>{{ $sourceLabels[$enabledSource] ?? $enabledSource }}</strong></div>
                                <div class="small">Tryb: <strong>{{ $sourceLabels[$modeSource] ?? $modeSource }}</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2 + 3. Config vs override --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light"><h6 class="mb-0">Konfiguracja .env / config</h6></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small">
                                <li><code>ANALYTICS_ENABLED</code>: <strong>{{ $configEnabled ? 'true' : 'false' }}</strong></li>
                                <li><code>ANALYTICS_DEFAULT_MODE</code>: <strong>{{ $configMode }}</strong></li>
                                <li><code>ANALYTICS_SAMPLE_RATE</code>: <strong>{{ $sampleRate }}</strong> <span class="text-muted">(tylko podgląd)</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light"><h6 class="mb-0">Runtime override (baza pneadm)</h6></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small">
                                <li><code>enabled_override</code>: <strong>{{ $boolText($enabledOverride) }}</strong></li>
                                <li><code>default_mode_override</code>: <strong>{{ $modeOverride ?? 'użyj .env/config' }}</strong></li>
                                <li><code>updated_by</code>: <strong>{{ $updatedBy ?? '—' }}</strong></li>
                                <li><code>updated_at</code>: <strong>{{ $updatedAt ? $updatedAt->format('Y-m-d H:i') : '—' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Opis trybów --}}
            <div class="card mb-3">
                <div class="card-header bg-light"><h6 class="mb-0">Tryby analityki</h6></div>
                <div class="card-body">
                    <ul class="small mb-0">
                        @foreach($modeLabels as $value => $label)
                            <li>{{ $label }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- 5. Ostrzeżenia --}}
            <div class="alert alert-warning small" role="alert">
                <ul class="mb-0">
                    <li>Zmiana trybu wpływa na <strong>zbieranie danych analitycznych</strong>.</li>
                    <li>Zmiana <strong>nie wpływa</strong> na sprzedaż, płatności ani fakturowanie.</li>
                    <li><code>.env ANALYTICS_ENABLED=false</code> ma priorytet (hard kill switch) i wyłączy analitykę niezależnie od panelu.</li>
                    <li><code>pnedu</code> i <code>pneadm</code> mają osobne procesy, ale runtime override jest wspólny przez bazę <code>pneadm</code>. <code>pnedu</code> może mieć własny hard kill switch w swoim <code>.env</code>.</li>
                    <li><code>sample_rate</code> jest na tym etapie <strong>tylko podglądowe</strong> (edycja w osobnym etapie).</li>
                </ul>
            </div>

            {{-- 6. Formularz zmiany --}}
            <div class="card">
                <div class="card-header bg-light"><h5 class="mb-0">Zmień ustawienia analityki</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('analytics.settings.update') }}" id="analytics-settings-form">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="enabled_override" class="form-label">Włączenie analityki (override)</label>
                                <select name="enabled_override" id="enabled_override" class="form-select">
                                    <option value="use_config" @selected($enabledOverride === null)>Użyj .env/config</option>
                                    <option value="enabled" @selected($enabledOverride === true)>Włącz</option>
                                    <option value="disabled" @selected($enabledOverride === false)>Wyłącz</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="default_mode_override" class="form-label">Domyślny tryb (override)</label>
                                <select name="default_mode_override" id="default_mode_override" class="form-select">
                                    <option value="use_config" @selected($modeOverride === null)>Użyj .env/config</option>
                                    @foreach($allowedModes as $mode)
                                        <option value="{{ $mode }}" @selected($modeOverride === $mode)>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-check mt-3" id="confirm-impact-wrapper">
                            <input class="form-check-input" type="checkbox" name="confirm_impact" id="confirm_impact" value="1">
                            <label class="form-check-label small" for="confirm_impact">
                                Rozumiem, że ta zmiana może zatrzymać zbieranie danych analitycznych.
                            </label>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const enabled = document.getElementById('enabled_override');
            const mode = document.getElementById('default_mode_override');
            const wrapper = document.getElementById('confirm-impact-wrapper');
            const checkbox = document.getElementById('confirm_impact');

            function refresh() {
                const disabling = enabled.value === 'disabled' || mode.value === 'off';
                wrapper.classList.toggle('text-danger', disabling);
                checkbox.required = disabling;
            }

            enabled.addEventListener('change', refresh);
            mode.addEventListener('change', refresh);
            refresh();
        })();
    </script>
</x-app-layout>
