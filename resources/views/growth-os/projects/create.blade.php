<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Zaplanuj webinar TIK
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <h2 class="h4 mb-2">Zaplanuj webinar TIK</h2>
            <p class="text-secondary mb-0">
                Krótki start procesu. Po utworzeniu przejdziesz do workspace projektu, gdzie system pokaże następny krok.
            </p>
        </section>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                Sprawdź pola formularza. Prototyp nie zapisuje danych w bazie.
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('growth.projects.store') }}" class="card border">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="type" class="form-label">Co planujemy?</label>
                            <input id="type" name="type" class="form-control @error('type') is-invalid @enderror" value="{{ old('type', 'Webinar TIK') }}" required>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="live_date" class="form-label">Data live</label>
                                <input id="live_date" name="live_date" type="date" class="form-control @error('live_date') is-invalid @enderror" value="{{ old('live_date', now()->addDays(7)->toDateString()) }}" required>
                                @error('live_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="live_time" class="form-label">Godzina</label>
                                <input id="live_time" name="live_time" type="time" class="form-control @error('live_time') is-invalid @enderror" value="{{ old('live_time', '20:00') }}" required>
                                @error('live_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="host" class="form-label">Prowadzący</label>
                            <input id="host" name="host" class="form-control @error('host') is-invalid @enderror" value="{{ old('host', 'Waldemar Grabowski') }}" required>
                            @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mt-3">
                            <label for="goal" class="form-label">Cel</label>
                            <select id="goal" name="goal" class="form-select @error('goal') is-invalid @enderror" required>
                                @foreach($goals as $goal)
                                    <option value="{{ $goal['value'] }}" @selected(old('goal', 'education') === $goal['value'])>
                                        {{ $goal['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('goal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mt-3">
                            <label for="topic" class="form-label">Temat</label>
                            <input id="topic" name="topic" class="form-control @error('topic') is-invalid @enderror" value="{{ old('topic') }}" placeholder="Możesz wpisać temat ręcznie albo skorzystać z sugestii po prawej">
                            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Utwórz projekt webinaru</button>
                    </div>
                </form>
            </div>

            <aside class="col-lg-5">
                <section class="card border h-100" aria-labelledby="ai-topic-help-heading">
                    <div class="card-header bg-white">
                        <h3 class="h6 mb-0" id="ai-topic-help-heading">Pomóż mi znaleźć temat</h3>
                    </div>
                    <div class="card-body">
                        <p class="small text-secondary">
                            To symulowane sugestie AI. Kliknięcie nie uzupełnia pola automatycznie — wybierz kierunek i wpisz go w formularzu.
                        </p>
                        <div class="list-group">
                            @foreach($ideas as $idea)
                                <div class="list-group-item">
                                    <div class="fw-semibold">{{ $idea['title'] }}</div>
                                    <div class="small text-secondary">{{ $idea['why'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
