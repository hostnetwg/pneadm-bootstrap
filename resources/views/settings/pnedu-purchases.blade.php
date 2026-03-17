<x-app-layout>
    <x-slot name="header">
        Zakupy pnedu.pl
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-4">Włącz lub wyłącz poszczególne opcje płatności i zamawiania wyświetlane na stronie płatnego szkolenia (np. <code>/courses/467</code>). Dzięki temu możesz tymczasowo ukryć daną opcję w razie błędów lub podczas prac nad nowym „Formularzem zamówienia”.</p>
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
                        'show_pay_publigo' => 'Zapłać online PUBLIGO',
                        'show_pay_online' => 'Zapłać online',
                        'show_deferred_order' => 'Formularz zamówienia z odroczonym terminem płatności',
                        'show_order_form' => 'Formularz zamówienia',
                        'show_order_form_alt' => 'Alternatywny formularz zamówienia',
                    ] as $key => $label)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1"
                                {{ (optional($options)->{$key} ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
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
