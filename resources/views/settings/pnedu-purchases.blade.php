<x-app-layout>
    <x-slot name="header">
        Zakupy pnedu.pl
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-4">Włącz lub wyłącz opcje w <strong>tej samej kolejności</strong>, w jakiej pojawiają się przyciski na stronie płatnego szkolenia (np. <code>/courses/471</code>): od góry do dołu. Dwa pierwsze przyciski mają na stronie ten sam napis „Zapłać online” — tutaj rozróżniamy je w nawiasach (Publigo vs PayU/PayNow).</p>
        <p class="text-muted small mb-4">Jeśli po kliknięciu „Zapisz zmiany” zobaczysz błąd <strong>„419 Page Expired”</strong> lub „Strona wygasła”, odśwież stronę (F5) i zapisz ponownie — zwykle oznacza to, że sesja w tej karcie wygasła.</p>

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

        <form method="POST" action="{{ route('settings.pnedu-purchases.store') }}" class="card">
            @csrf
            <div class="card-header bg-light">
                <h5 class="mb-0">Widoczność opcji na stronie kursu (pnedu.pl)</h5>
            </div>
            <div class="card-body">
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
            </div>
            <div class="card-footer bg-light">
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </div>
        </form>
    </div>
</x-app-layout>
