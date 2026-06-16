<x-app-layout>
    <x-slot name="header">
        Zakupy pnedu.pl
    </x-slot>

    @php
        $pneduPublicUrl = rtrim((string) config('marketing.pnedu_public_url', 'https://pnedu.pl'), '/');
        $pneduPublicHost = parse_url($pneduPublicUrl, PHP_URL_HOST) ?: 'pnedu.pl';
        $admHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'adm.pnedu.pl';
    @endphp

    <div class="py-3">
        <p class="text-muted mb-4">
            Ustawienia publicznej strony <strong>{{ $pneduPublicHost }}</strong>:
            widoczność przycisków zakupu na kursach, domyślny dostęp po zakończeniu szkolenia
            oraz szybkie linki opt-out statystyk lejka dla zespołu.
        </p>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif
        @if(request()->query('funnel_skip') === 'enabled')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Liczenie lejka zostało wyłączone w tej przeglądarce (cookie na {{ $pneduPublicHost }} i {{ $admHost }}).
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @elseif(request()->query('funnel_skip') === 'disabled')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Liczenie lejka zostało przywrócone w tej przeglądarce.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.pnedu-purchases.store') }}" class="card mb-4">
            @csrf
            <div class="card-header bg-light">
                <h5 class="mb-0">Widoczność opcji na stronie kursu ({{ $pneduPublicHost }})</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Włącz lub wyłącz opcje w <strong>tej samej kolejności</strong>, w jakiej pojawiają się przyciski
                    na stronie płatnego szkolenia (np. <code>/courses/471</code>): od góry do dołu.
                    Dwa pierwsze przyciski mają na stronie ten sam napis „Zapłać online” — tutaj rozróżniamy je
                    w nawiasach (Publigo vs PayU/PayNow).
                </p>

                <div class="mb-0">
                    @foreach([
                        'show_pay_publigo' => 'Zapłać online (Publigo — niebieski przycisk)',
                        'show_pay_online' => 'Zapłać online (PayU / PayNow — fioletowy przycisk)',
                        'show_deferred_order' => 'Formularz zamówienia z odroczonym terminem płatności (w PNEDU)',
                        'show_order_form' => 'Zamawiam szkolenie (uniwersalny formularz zamówienia i płatności online)',
                        'show_order_form_alt' => 'Formularz zamówienia z odroczonym terminem płatności (link zdalna-lekcja.pl)',
                        'order_form_auto_fill_test_data' => 'Automatyczne wypełnianie formularza zamówienia danymi testowymi (ułatwia testy; niewidoczne na stronie kursu)',
                    ] as $key => $label)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1"
                                {{ (optional($options)->{$key} ?? ($key === 'order_form_auto_fill_test_data' ? false : true)) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $key }}">
                                @if($key === 'show_order_form')
                                    <span class="fw-bold">Zamawiam szkolenie</span> (uniwersalny formularz zamówienia i płatności online)
                                @else
                                    {{ $label }}
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">

                <h5 class="mb-2">Domyślny dostęp po zakończeniu szkolenia</h5>
                <p class="text-muted small mb-3">
                    Używane dla szkoleń online, gdy konkretne szkolenie lub wariant cenowy nie ma własnej reguły.
                    Dotyczy m.in. zakupu nagrania po zakończeniu, rejestracji zaświadczenia i domyślnej daty
                    w formularzu ręcznego dodawania uczestnika.
                </p>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="default_post_end_access_duration_value" class="form-label">Okres</label>
                        <input type="number"
                               min="1"
                               max="999"
                               name="default_post_end_access_duration_value"
                               id="default_post_end_access_duration_value"
                               class="form-control @error('default_post_end_access_duration_value') is-invalid @enderror"
                               value="{{ old('default_post_end_access_duration_value', optional($options)->default_post_end_access_duration_value ?? 2) }}"
                               required>
                        @error('default_post_end_access_duration_value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="default_post_end_access_duration_unit" class="form-label">Jednostka</label>
                        <select name="default_post_end_access_duration_unit"
                                id="default_post_end_access_duration_unit"
                                class="form-select @error('default_post_end_access_duration_unit') is-invalid @enderror"
                                required>
                            @foreach(['days' => 'Dni', 'weeks' => 'Tygodnie', 'months' => 'Miesiące', 'years' => 'Lata'] as $unit => $label)
                                <option value="{{ $unit }}" {{ old('default_post_end_access_duration_unit', optional($options)->default_post_end_access_duration_unit ?? 'months') === $unit ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('default_post_end_access_duration_unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light">
                <p class="text-muted small mb-3 mb-md-2">
                    Jeśli po kliknięciu „Zapisz zmiany” zobaczysz błąd <strong>„419 Page Expired”</strong>
                    lub „Strona wygasła”, odśwież stronę (F5) i zapisz ponownie — zwykle oznacza to wygasłą sesję w tej karcie.
                </p>
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Lejek {{ $pneduPublicHost }} — szybkie linki dla zespołu</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Kliknij przycisk poniżej — ustawimy cookie w panelu adm i na <strong>{{ $pneduPublicHost }}</strong>,
                    potem wrócisz tutaj ze zaktualizowanym statusem.
                    Wyłącza to liczenie wejść do statystyk lejka (opis szkolenia + formularz zamówienia) oraz powiązane eventy GA.
                    Nie wymaga zapisywania formularza powyżej.
                </p>

                @if(filled($funnelSkipEnableUrl) && filled($funnelSkipDisableUrl))
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <a href="{{ route('settings.pnedu-purchases.funnel-skip', ['action' => 'enable']) }}" class="btn btn-outline-danger">
                            Wyłącz liczenie lejka
                        </a>
                        <a href="{{ route('settings.pnedu-purchases.funnel-skip', ['action' => 'disable']) }}" class="btn btn-outline-secondary">
                            Włącz ponownie liczenie lejka
                        </a>
                    </div>

                    <details class="mb-3">
                        <summary class="small text-muted" style="cursor: pointer;">Bezpośrednie linki na {{ $pneduPublicHost }} (zakładka zakładek / udostępnienie)</summary>
                        <div class="row g-3 mt-2">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">Wyłącz (opt-out)</div>
                                    <a href="{{ $funnelSkipEnableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $funnelSkipEnableUrl }}</a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 small">Włącz ponownie</div>
                                    <a href="{{ $funnelSkipDisableUrl }}" target="_blank" rel="noopener noreferrer" class="small d-inline-block text-break">{{ $funnelSkipDisableUrl }}</a>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            Te linki ustawiają cookie tylko na {{ $pneduPublicHost }} — status poniżej na localhost może się nie zmienić, dopóki nie użyjesz przycisków powyżej.
                        </p>
                    </details>
                @else
                    <div class="alert alert-warning mb-3">
                        Brak konfiguracji <code>MARKETING_FUNNEL_SKIP_TOKEN</code> — ustaw token w <code>.env</code>, aby generować gotowe linki ON/OFF.
                    </div>
                @endif

                <div class="border rounded p-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="fw-semibold">Status cookie w tej przeglądarce ({{ $admHost }}):</span>
                        @if($funnelSkipEnabledForBrowser)
                            <span class="badge text-bg-success">Opt-out włączony</span>
                        @else
                            <span class="badge text-bg-secondary">Opt-out wyłączony</span>
                        @endif
                    </div>
                    @if($funnelSkipEnabledForBrowser && $funnelSkipUntil)
                        <div class="small text-muted">
                            Cookie ważne do:
                            <strong>{{ $funnelSkipUntil->timezone(config('app.timezone', 'Europe/Warsaw'))->format('d.m.Y H:i') }}</strong>
                            (strefa {{ config('app.timezone', 'Europe/Warsaw') }}).
                        </div>
                    @elseif($funnelSkipEnabledForBrowser)
                        <div class="small text-muted">
                            Cookie opt-out jest ustawione, ale brak daty wygaśnięcia pomocniczego cookie.
                        </div>
                    @else
                        <div class="small text-muted">
                            W tej przeglądarce na <code>{{ $admHost }}</code> nie wykryto cookie <code>{{ $funnelSkipCookie }}</code>.
                        </div>
                    @endif
                    <div class="small text-muted mt-2">
                        Przyciski powyżej ustawiają cookie na <strong>{{ $pneduPublicHost }}</strong> i w panelu adm (<code>{{ $admHost }}</code>).
                        Na produkcji oba hosty dzielą domenę cookie <code>.pnedu.pl</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
