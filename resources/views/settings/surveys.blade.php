<x-app-layout>
    <x-slot name="header">
        Ustawienia ankiet
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-4">
            Domyślne zachowanie ankiet po szkoleniu: kanał (natywna / Google), anonimowość oraz tryb otwierania (ręczny / automatyczny).
            Szablony pytań edytujesz w <a href="{{ route('surveys.templates.index') }}">Ankiety → Szablony</a>.
        </p>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.surveys.update') }}" class="card mb-4">
            @csrf
            <div class="card-header bg-light">
                <h5 class="mb-0">Domyślne ustawienia nowych ankiet</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label class="form-label fw-semibold">Domyślny kanał</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="default_channel" id="ch_native" value="native"
                            {{ old('default_channel', $settings->default_channel) === 'native' ? 'checked' : '' }}>
                        <label class="form-check-label" for="ch_native">Natywna ankieta na pnedu.pl</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="default_channel" id="ch_external" value="external"
                            {{ old('default_channel', $settings->default_channel) === 'external' ? 'checked' : '' }}>
                        <label class="form-check-label" for="ch_external">Zewnętrzna (Google Forms / Microsoft Forms…)</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Tryb otwierania okna czasowego</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="open_mode" id="open_manual" value="manual"
                            {{ old('open_mode', $settings->open_mode) === 'manual' ? 'checked' : '' }}>
                        <label class="form-check-label" for="open_manual">
                            <strong>Ręczny</strong> — daty otwarcia/zamknięcia ustawiasz przy każdej ankiecie (lub zostawiasz puste = zawsze dostępna gdy aktywna)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="open_mode" id="open_auto" value="auto"
                            {{ old('open_mode', $settings->open_mode) === 'auto' ? 'checked' : '' }}>
                        <label class="form-check-label" for="open_auto">
                            <strong>Automatyczny</strong> — jeśli nie podasz dat, system ustawi otwarcie po końcu szkolenia (+ offset) i opcjonalne zamknięcie
                        </label>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="auto_open_offset_hours" class="form-label">Offset otwarcia (godziny względem końca szkolenia)</label>
                        <input type="number" min="-24" max="720" class="form-control" id="auto_open_offset_hours" name="auto_open_offset_hours"
                               value="{{ old('auto_open_offset_hours', $settings->auto_open_offset_hours) }}">
                        <div class="form-text">
                            Ujemny = przed planowanym końcem (np. <code>-2</code> = 2&nbsp;h wcześniej).
                            Dodatni = po końcu.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="auto_close_after_days" class="form-label">Zamknięcie po (dni od otwarcia)</label>
                        <input type="number" min="1" max="365" class="form-control" id="auto_close_after_days" name="auto_close_after_days"
                               value="{{ old('auto_close_after_days', $settings->auto_close_after_days) }}" placeholder="np. 14">
                        <div class="form-text">Puste = bez automatycznego zamknięcia</div>
                    </div>
                    <div class="col-md-4">
                        <label for="default_template_id" class="form-label">Domyślny szablon</label>
                        <select class="form-select" id="default_template_id" name="default_template_id">
                            <option value="">— brak —</option>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('default_template_id', $settings->default_template_id) == $tpl->id)>
                                    {{ $tpl->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="default_is_anonymous" id="default_is_anonymous" value="1"
                        {{ old('default_is_anonymous', $settings->default_is_anonymous) ? 'checked' : '' }}>
                    <label class="form-check-label" for="default_is_anonymous">
                        Domyślnie ankieta anonimowa (można zmienić per ankieta na karcie szkolenia)
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="allow_multiple_responses" id="allow_multiple_responses" value="1"
                        {{ old('allow_multiple_responses', $settings->allow_multiple_responses) ? 'checked' : '' }}>
                    <label class="form-check-label" for="allow_multiple_responses">
                        Domyślnie zezwalaj na wielokrotne wypełnienie (przy tworzeniu nowej ankiety)
                    </label>
                    <div class="form-text">
                        Dotyczy tylko nowych ankiet — na karcie szkolenia można zmienić per ankieta.
                        Gdy wyłączone przy ankiecie: nieanonimowa = max 1× na e-mail/konto;
                        anonimowa = miękki limit (cookie w przeglądarce).
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <a href="{{ route('surveys.templates.index') }}" class="btn btn-outline-secondary btn-sm">Edytuj szablony pytań</a>
                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </div>
        </form>
    </div>
</x-app-layout>
