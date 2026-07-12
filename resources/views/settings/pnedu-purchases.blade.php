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
                        'show_order_form_alt' => 'Formularz zamówienia z odroczonym terminem płatności (link zdalna-lekcja.pl)',
                    ] as $key => $label)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1"
                                {{ (optional($options)->{$key} ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <hr class="my-4">

                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Wersje formularza zamówienia</h6>
                    <div class="alert alert-info py-2 px-3 small mb-3" role="note">
                        <strong>Bezpieczeństwo sprzedaży:</strong> te checkboxy ukrywają lub pokazują przycisk „Zamawiam szkolenie” na stronie kursu.
                        Linki kampanii (<code>/l/…</code>, UTM) i adres <code>/courses/&#123;id&#125;/order-form</code> nadal otwierają formularz — z parametrem
                        <code>form_variant</code> lub bez niego (wariant globalny). Nie da się zapisać ustawień z wyłączonymi oboma wariantami naraz.
                    </div>
                    @foreach([
                        'show_order_form' => 'Zamawiam szkolenie (uniwersalny formularz zamówienia i płatności online)',
                        'show_order_form_v2' => 'Zamawiam szkolenie v2',
                    ] as $key => $label)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1"
                                {{ (optional($options)->{$key} ?? ($key !== 'show_order_form_v2')) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $key }}">
                                @if($key === 'show_order_form')
                                    <span class="fw-bold">Zamawiam szkolenie</span> (uniwersalny formularz zamówienia i płatności online)
                                @else
                                    {{ $label }}
                                @endif
                            </label>
                            @if($key === 'show_order_form_v2')
                                <div class="form-text text-muted small">
                                    Włącza wariant V2 (kreator). Wyłączenie nie psuje linków kampanii z <code>form_variant=v2</code> — pokażą formularz legacy, jeśli jest włączony.
                                </div>
                            @elseif($key === 'show_order_form')
                                <div class="form-text text-muted small">
                                    Główny formularz uniwersalny (legacy). Zalecane: zawsze włączony na produkcji.
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="mt-3 pt-3 border-top">
                        @include('settings.partials.order-form-variant-radios', [
                            'fieldName' => 'default_signup_order_form_variant',
                            'fieldIdPrefix' => 'default_signup_order_form_variant',
                            'selectedVariant' => old(
                                'default_signup_order_form_variant',
                                optional($options)->default_signup_order_form_variant ?? 'legacy'
                            ),
                            'legend' => 'Domyślna wersja formularza zamówienia',
                            'hint' => 'Przycisk „Zapisz się” (strona główna, oferta) oraz „Zamawiam szkolenie” na stronie kursu — zawsze jeden aktywny wariant.',
                        ])
                    </div>

                    <hr class="my-4">

                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Automatyczne wypełnianie formularza (testy)</h6>

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
                            Pokazuje przycisk „Wypełnij dane testowe” na formularzu zamówienia (legacy i V2) dla zalogowanych kont z podanych adresów. Pola nie są wypełniane automatycznie — dopiero po kliknięciu przycisku.
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
                            Pokazuje przycisk „Wypełnij dane testowe” na formularzu (legacy i V2) dla <strong>wszystkich</strong> odwiedzających — także niezalogowanych. Pola <strong>nie</strong> wypełniają się automatycznie; tylko po kliknięciu przycisku. Na produkcji opcja wyłącza się automatycznie po {{ \App\Models\PaymentDisplayOption::UNRESTRICTED_AUTO_FILL_PRODUCTION_TTL_MINUTES }} min od włączenia (także w tym panelu).
                        </div>
                    </div>

                    <hr class="my-3">

                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Płatność testowa online (konta deweloperskie)</h6>
                    @if(empty($developerOnlinePaymentColumnReady))
                        <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">
                            Brak kolumn płatności testowej w bazie — uruchom migrację: <code>php artisan migrate --force</code>
                        </div>
                    @endif
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox"
                               name="developer_online_payment_test_enabled"
                               id="developer_online_payment_test_enabled"
                               value="1"
                            {{ optional($options)->developer_online_payment_test_enabled ? 'checked' : '' }}
                            @disabled(empty($developerOnlinePaymentColumnReady))>
                        <label class="form-check-label" for="developer_online_payment_test_enabled">
                            Symboliczna kwota <strong>5 PLN</strong> przy płatności online (PayU / PayNow) dla zalogowanych kont
                            <code>waldemar.grabowski@hostnet.pl</code> oraz <code>luman0599@gmail.com</code>
                        </label>
                        <div class="form-text text-muted small">
                            Dotyczy wyłącznie formularza zamówienia (legacy i V2) oraz fioletowego przycisku „Zapłać online” (PayU/PayNow), gdy użytkownik jest <strong>zalogowany</strong> na jedno z powyższych kont.
                            Goście i inni użytkownicy płacą normalną cenę. Zamówienia trafiają do systemu jak zwykle — z kwotą 5 PLN.
                        </div>
                    </div>
                    <fieldset class="mb-0" @disabled(empty($developerOnlinePaymentColumnReady))>
                        <legend class="form-label fs-6 mb-2">Bramka płatności podczas testów deweloperskich</legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio"
                                   name="developer_online_payment_sandbox_gateway"
                                   id="developer_online_payment_sandbox_gateway_sandbox"
                                   value="1"
                                {{ old('developer_online_payment_sandbox_gateway', optional($options)->developer_online_payment_sandbox_gateway ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="developer_online_payment_sandbox_gateway_sandbox">
                                Sandbox (testowa bramka PayU / PayNow)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio"
                                   name="developer_online_payment_sandbox_gateway"
                                   id="developer_online_payment_sandbox_gateway_production"
                                   value="0"
                                {{ ! old('developer_online_payment_sandbox_gateway', optional($options)->developer_online_payment_sandbox_gateway ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="developer_online_payment_sandbox_gateway_production">
                                Produkcyjna (prawdziwa bramka — płatność 5 PLN)
                            </label>
                        </div>
                        <div class="form-text text-muted small">
                            Wybór działa tylko przy włączonej opcji powyżej i zalogowanym koncie deweloperskim. Na serwerze produkcyjnym do trybu sandbox potrzebne są osobne klucze API w <code>.env</code>
                            (<code>PAYU_SANDBOX_*</code>, <code>PAYNOW_SANDBOX_*</code>) — w przeciwnym razie używane są domyślne klucze z konfiguracji.
                        </div>
                    </fieldset>
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
