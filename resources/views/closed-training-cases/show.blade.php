<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <h2 class="fw-semibold fs-4 text-dark mb-0">{{ $case->topic }}</h2>
            <a href="{{ route('closed-training-cases.index') }}" class="btn btn-outline-secondary">Lista spraw</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span>
                                <span class="text-muted small">{{ $case->sourceLabel() }}</span>
                            </div>
                            <h3 class="h6 text-uppercase text-muted">Następne działanie</h3>
                            <p class="fs-5 mb-1 {{ $case->nextActionIsOverdue() ? 'text-danger' : '' }}">{{ $case->next_action ?: 'Nie ustawiono.' }}</p>
                            @if($case->next_action_at)
                                <p class="mb-0 {{ $case->nextActionIsOverdue() ? 'text-danger' : 'text-muted' }}">Do {{ $case->formatLocal($case->next_action_at) }}</p>
                            @endif
                            <p class="mt-3 mb-0 text-muted">Opiekun: {{ $case->assignee?->name ?: 'nieprzypisany' }}</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-white fw-semibold">Kontakt szkoły</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Osoba</dt>
                                <dd class="col-sm-8">{{ $case->contact_name }}</dd>
                                <dt class="col-sm-4">Placówka</dt>
                                <dd class="col-sm-8">{{ $case->institution ?: '—' }}</dd>
                                <dt class="col-sm-4">E-mail</dt>
                                <dd class="col-sm-8">{{ $case->contact_email ?: '—' }}</dd>
                                <dt class="col-sm-4">Telefon</dt>
                                <dd class="col-sm-8">{{ $case->contact_phone ?: '—' }}</dd>
                                <dt class="col-sm-4">Forma</dt>
                                <dd class="col-sm-8">{{ $case->preferredFormatLabel() }}</dd>
                                <dt class="col-sm-4">Oferta</dt>
                                <dd class="col-sm-8">
                                    @if($case->trainingOffer)
                                        <a href="{{ route('training-offers.show', $case->trainingOffer) }}">{{ $case->trainingOffer->title }}</a>
                                    @elseif($case->offer_url)
                                        <a href="{{ $case->offer_url }}" target="_blank" rel="noopener">Strona oferty</a>
                                    @else
                                        —
                                    @endif
                                </dd>
                                <dt class="col-sm-4">Zgoda z formularza</dt>
                                <dd class="col-sm-8">{{ $case->consent_at ? $case->formatLocal($case->consent_at) : 'Brak (wpis ręczny albo inny kanał)' }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-white fw-semibold">Wiadomość</div>
                        <div class="card-body">
                            <p class="mb-0" style="white-space: pre-wrap;">{{ $case->message }}</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white fw-semibold">Historia</div>
                        <ul class="list-group list-group-flush">
                            @foreach($case->events->sortByDesc('happened_at') as $event)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <strong>{{ $event->typeLabel() }}</strong>
                                        <span class="text-muted small">{{ $case->formatLocal($event->happened_at) }} · {{ $event->user?->name ?: 'pnedu.pl' }}</span>
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
                        <div class="card-header bg-white fw-semibold">Obsługa</div>
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
                        <div class="card-header bg-white fw-semibold">Notatka</div>
                        <div class="card-body">
                            <textarea name="body" rows="4" class="form-control @error('body') is-invalid @enderror" placeholder="Ustalenie z rozmowy, bez zmiany statusu.">{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-outline-primary mt-3">Dodaj notatkę</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
