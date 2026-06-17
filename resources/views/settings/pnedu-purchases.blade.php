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
            i konfiguracja formularza zamówienia.
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
        @if(empty($developersOnlyColumnReady))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Brak kolumny <code>order_form_auto_fill_test_data_developers_only</code> w bazie — opcja dla kont deweloperskich nie zadziała, dopóki nie uruchomisz migracji na produkcji:
                <code>php artisan migrate --force</code>
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
                    ] as $key => $label)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1"
                                {{ (optional($options)->{$key} ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $key }}">
                                @if($key === 'show_order_form')
                                    <span class="fw-bold">Zamawiam szkolenie</span> (uniwersalny formularz zamówienia i płatności online)
                                @else
                                    {{ $label }}
                                @endif
                            </label>
                        </div>
                    @endforeach

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox"
                               name="order_form_auto_fill_test_data_developers_only"
                               id="order_form_auto_fill_test_data_developers_only"
                               value="1"
                            {{ optional($options)->order_form_auto_fill_test_data_developers_only ? 'checked' : '' }}>
                        <label class="form-check-label" for="order_form_auto_fill_test_data_developers_only">
                            Automatyczne wypełnianie formularza zamówienia danymi testowymi (wyłącznie dla kont waldemar.grabowski@hostnet.pl oraz luman0599@gmail.com)
                        </label>
                        <div class="form-text text-muted small">
                            Działa tylko dla zalogowanych użytkowników z podanymi adresami e-mail. Nie wyłącza się automatycznie.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox"
                               name="order_form_auto_fill_test_data"
                               id="order_form_auto_fill_test_data"
                               value="1"
                            {{ optional($options)->order_form_auto_fill_test_data ? 'checked' : '' }}>
                        <label class="form-check-label text-danger fw-semibold" for="order_form_auto_fill_test_data">
                            Automatyczne wypełnianie formularza zamówienia danymi testowymi (ułatwia testy; niewidoczne na stronie kursu)
                        </label>
                        <div class="form-text text-danger small">
                            Bez ograniczeń e-mail (także dla niezalogowanych). Na produkcji wyłącza się automatycznie po {{ \App\Models\PaymentDisplayOption::UNRESTRICTED_AUTO_FILL_PRODUCTION_TTL_MINUTES }} min od włączenia — także w tym panelu, bez ręcznego odznaczania.
                        </div>
                    </div>
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
    </div>
</x-app-layout>
