<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <h2 class="fw-semibold fs-4 text-dark mb-0">{{ $case->trainingTopicLabel() }}</h2>
            <a href="{{ route('closed-training-cases.index') }}" class="btn btn-outline-secondary">Lista spraw</a>
        </div>
    </x-slot>

    @include('growth-os.partials.readable-styles')

    <div class="container-fluid growth-readable">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Temat szkolenia</div>
                        <div class="card-body">
                            <p class="fs-5 fw-bold mb-0">{{ $case->trainingTopicLabel() }}</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span>
                                <span class="text-secondary small">{{ $case->sourceLabel() }}</span>
                            </div>
                            <h3 class="h6 text-uppercase text-secondary">Następne działanie</h3>
                            <p class="fs-5 mb-1 {{ $case->nextActionIsOverdue() ? 'text-danger' : '' }}">{{ $case->next_action ?: 'Nie ustawiono.' }}</p>
                            @if($case->next_action_at)
                                <p class="mb-0 {{ $case->nextActionIsOverdue() ? 'text-danger' : 'text-secondary' }}">Do {{ $case->formatLocal($case->next_action_at) }}</p>
                            @endif
                            <p class="mt-3 mb-0 text-secondary">Opiekun: {{ $case->assignee?->name ?: 'nieprzypisany' }}</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Kontakt szkoły</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('closed-training-cases.contact.update', $case) }}" class="mb-4">
                                @csrf
                                @method('PUT')
                                <div class="mb-3">
                                    <label for="contact_name" class="form-label">Osoba</label>
                                    <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name', $case->contact_name) }}" required maxlength="255" class="form-control @error('contact_name') is-invalid @enderror">
                                    @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">E-mail</label>
                                    <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email', $case->contact_email) }}" maxlength="255" class="form-control @error('contact_email') is-invalid @enderror">
                                    @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Telefon</label>
                                    <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone', $case->contact_phone) }}" maxlength="50" class="form-control @error('contact_phone') is-invalid @enderror">
                                    @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="institution" class="form-label">Nazwa szkoły / placówki (opcjonalnie)</label>
                                    <input type="text" name="institution" id="institution" value="{{ old('institution', $case->institution) }}" maxlength="255" class="form-control @error('institution') is-invalid @enderror">
                                    @error('institution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="institution_type" class="form-label">Rodzaj placówki</label>
                                    <select name="institution_type" id="institution_type" class="form-select @error('institution_type') is-invalid @enderror">
                                        <option value="">Wybierz rodzaj placówki…</option>
                                        @foreach(\App\Models\ClosedTrainingCase::institutionTypeOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(old('institution_type', $case->institution_type) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('institution_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3" id="institution_type_other_wrap" @if(old('institution_type', $case->institution_type) !== \App\Models\ClosedTrainingCase::INSTITUTION_OTHER) hidden @endif>
                                    <label for="institution_type_other" class="form-label">Jaki rodzaj placówki?</label>
                                    <input type="text" name="institution_type_other" id="institution_type_other" value="{{ old('institution_type_other', $case->institution_type_other) }}" maxlength="255" class="form-control @error('institution_type_other') is-invalid @enderror" placeholder="np. poradnia psychologiczno-pedagogiczna" @disabled(old('institution_type', $case->institution_type) !== \App\Models\ClosedTrainingCase::INSTITUTION_OTHER)>
                                    @error('institution_type_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="preferred_format" class="form-label">Forma</label>
                                    <select name="preferred_format" id="preferred_format" class="form-select @error('preferred_format') is-invalid @enderror">
                                        <option value="">Nie wybrano</option>
                                        @foreach(\App\Models\ClosedTrainingCase::formatOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(old('preferred_format', $case->preferred_format) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('preferred_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="training_offer_id" class="form-label">Oferta</label>
                                    <select name="training_offer_id" id="training_offer_id" class="form-select @error('training_offer_id') is-invalid @enderror">
                                        <option value="">Brak powiązania z ofertą</option>
                                        @foreach($offers as $offer)
                                            <option value="{{ $offer->id }}" @selected((string) old('training_offer_id', $case->training_offer_id) === (string) $offer->id)>{{ $offer->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Oferta z katalogu, której dotyczyło zapytanie. Temat szkolenia zostaje bez zmian.</div>
                                    @error('training_offer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @if($case->trainingOffer)
                                        <a href="{{ route('training-offers.show', $case->trainingOffer) }}" class="small">Otwórz ofertę w panelu</a>
                                    @elseif($case->offer_url)
                                        <a href="{{ $case->offer_url }}" class="small" target="_blank" rel="noopener">Strona oferty</a>
                                    @endif
                                </div>
                                <script>
                                    (function () {
                                        const select = document.getElementById('institution_type');
                                        const wrap = document.getElementById('institution_type_other_wrap');
                                        const input = document.getElementById('institution_type_other');
                                        if (!select || !wrap || !input) {
                                            return;
                                        }

                                        const sync = function () {
                                            const other = select.value === 'other';
                                            wrap.hidden = !other;
                                            input.required = other;
                                            input.disabled = !other;
                                        };

                                        select.addEventListener('change', function () {
                                            if (select.value !== 'other') {
                                                input.value = '';
                                            }
                                            sync();
                                        });
                                        sync();
                                    })();
                                </script>
                                <button type="submit" class="btn btn-primary">Zapisz dane zapytania</button>
                            </form>
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Zgoda z formularza</dt>
                                <dd class="col-sm-8">{{ $case->consent_at ? $case->formatLocal($case->consent_at) : 'Brak (wpis ręczny albo inny kanał)' }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">{{ $case->originalSubmissionHeading() }}</div>
                        <div class="card-body">
                            @if($originalRows = $case->originalSubmissionRows())
                                <dl class="row mb-0">
                                    @foreach($originalRows as $label => $value)
                                        <dt class="col-sm-4">{{ $label }}</dt>
                                        <dd class="col-sm-8" @if($label === 'Wiadomość') style="white-space: pre-wrap;" @endif>{{ $value }}</dd>
                                    @endforeach
                                </dl>
                            @else
                                <p class="mb-0">Oryginał nie został zapisany.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Wiadomość</div>
                        <div class="card-body">
                            <p class="mb-0" style="white-space: pre-wrap;">{{ $case->message }}</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header fw-semibold">Historia</div>
                        <ul class="list-group list-group-flush">
                            @foreach($case->events->sortByDesc('happened_at') as $event)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <strong>{{ $event->typeLabel() }}</strong>
                                        <span class="text-secondary small">{{ $case->formatLocal($event->happened_at) }} · {{ $event->user?->name ?: 'pnedu.pl' }}</span>
                                    </div>
                                    @if($event->body)
                                        <p class="mb-0 mt-1" style="white-space: pre-wrap;">{{ $event->body }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="col-lg-5">
                    <form method="POST" action="{{ route('closed-training-cases.update', $case) }}" class="card mb-4">
                        @csrf
                        @method('PUT')
                        <div class="card-header fw-semibold">Obsługa</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                    @foreach(\App\Models\ClosedTrainingCase::statusOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $case->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Status nie opisuje kroku negocjacji. Szczegół wpisz w następnym działaniu.</div>
                            </div>
                            <div class="mb-3">
                                <label for="assignee_user_id" class="form-label">Opiekun</label>
                                <select name="assignee_user_id" id="assignee_user_id" class="form-select">
                                    <option value="">Nieprzypisany</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected((string) old('assignee_user_id', $case->assignee_user_id) === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="next_action" class="form-label">Następne działanie</label>
                                <textarea name="next_action" id="next_action" rows="3" class="form-control @error('next_action') is-invalid @enderror">{{ old('next_action', $case->next_action) }}</textarea>
                                @error('next_action')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="next_action_at" class="form-label">Termin</label>
                                <input type="datetime-local" name="next_action_at" id="next_action_at" value="{{ old('next_action_at', $case->next_action_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('next_action_at') is-invalid @enderror">
                                @error('next_action_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary">Zapisz obsługę</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('closed-training-cases.notes.store', $case) }}" class="card">
                        @csrf
                        <div class="card-header fw-semibold">Notatka</div>
                        <div class="card-body">
                            <textarea name="body" rows="4" class="form-control @error('body') is-invalid @enderror" placeholder="Ustalenie z rozmowy, bez zmiany statusu.">{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-outline-primary mt-3">Dodaj notatkę</button>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</x-app-layout>
