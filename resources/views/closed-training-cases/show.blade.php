<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <span class="font-monospace text-secondary me-2" title="Identyfikator w temacie e-maila do szkoły">[{{ $case->referenceCode() }}]</span>
                {{ $case->trainingTopicLabel() }}
            </h2>
            <a href="{{ route('closed-training-cases.index') }}" class="btn btn-outline-secondary">Lista spraw</a>
        </div>
    </x-slot>

    @include('growth-os.partials.readable-styles')

    <div class="container-fluid growth-readable">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
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
                                    <label for="pedagogical_council_size" class="form-label">Liczba osób w radzie pedagogicznej</label>
                                    <input type="number"
                                           name="pedagogical_council_size"
                                           id="pedagogical_council_size"
                                           value="{{ old('pedagogical_council_size', $case->pedagogical_council_size) }}"
                                           min="1"
                                           max="9999"
                                           step="1"
                                           inputmode="numeric"
                                           class="form-control @error('pedagogical_council_size') is-invalid @enderror"
                                           style="max-width: 10rem;"
                                           placeholder="np. 42">
                                    <div class="form-text">Opcjonalnie. Ile osób liczy rada pedagogiczna.</div>
                                    @error('pedagogical_council_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="institution_www" class="form-label">WWW</label>
                                    <input type="text" name="institution_www" id="institution_www" value="{{ old('institution_www', $case->institution_www) }}" maxlength="2048" placeholder="https://szkola.edu.pl" inputmode="url" autocomplete="url" class="form-control @error('institution_www') is-invalid @enderror">
                                    <div class="form-text">Adres strony szkoły / placówki.</div>
                                    @error('institution_www')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @if($case->institution_www)
                                        <a href="{{ $case->institution_www }}" class="small" target="_blank" rel="noopener">Otwórz stronę</a>
                                    @endif
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

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Ustalenia (negocjacja)</div>
                        <div class="card-body">
                            <p class="small text-secondary mb-3">
                                Cena = ryczałt za całe szkolenie zamknięte, PLN brutto.
                                Wynagrodzenie instruktora = osobna kwota PLN brutto.
                                @if($negotiationLocked)
                                    Po powiązaniu ze szkoleniem aktualny trener i termin pochodzą ze szkolenia; poniżej widać ustalenia sprzed powiązania (tylko do odczytu).
                                @endif
                            </p>

                            @if($negotiationLocked)
                                <div class="mb-3">
                                    <div class="small text-uppercase text-secondary">Aktualnie ze szkolenia</div>
                                    <p class="mb-1">
                                        Trener:
                                        <strong>{{ $case->course?->instructor?->full_title_name ?: '—' }}</strong>
                                    </p>
                                    <p class="mb-0">
                                        Termin:
                                        <strong>{{ $case->formatScheduleRange($case->course?->start_date, $case->course?->end_date, true) }}</strong>
                                    </p>
                                    @if($case->linkedCourseInstructorMismatch() || $case->linkedCourseScheduleMismatch())
                                        <div class="alert alert-warning py-2 small mt-2 mb-0">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                <div>
                                                    Ustalenia na sprawie różnią się od szkolenia — źródłem realizacji jest szkolenie.
                                                    Przycisk <strong>Synchronizuj</strong> nadpisze trenera i termin na sprawie danymi ze szkolenia.
                                                </div>
                                                <form method="POST" action="{{ route('closed-training-cases.course.sync-negotiation', $case) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning">Synchronizuj</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <hr>
                                <div class="small text-uppercase text-secondary mb-2">Ustalenia sprzed powiązania (historyczne)</div>
                            @endif

                            <form method="POST" action="{{ route('closed-training-cases.negotiation.update', $case) }}">
                                @csrf
                                @method('PUT')
                                <div class="mb-3">
                                    <label for="instructor_id" class="form-label">Instruktor</label>
                                    <select name="instructor_id" id="instructor_id" class="form-select @error('instructor_id') is-invalid @enderror" @disabled($negotiationLocked)>
                                        <option value="">— nie wybrano —</option>
                                        @foreach($instructors as $instructor)
                                            <option value="{{ $instructor->id }}" @selected((string) old('instructor_id', $case->instructor_id) === (string) $instructor->id)>
                                                {{ $instructor->full_title_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('instructor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Przypisanie nie oznacza potwierdzenia dostępności.</div>
                                    @if($negotiationLocked)
                                        <input type="hidden" name="instructor_id" value="{{ $case->instructor_id }}">
                                    @endif
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="client_price" class="form-label">Cena dla szkoły (brutto)</label>
                                        <input type="number" step="0.01" min="0" name="client_price" id="client_price"
                                               value="{{ old('client_price', $case->client_price) }}"
                                               class="form-control @error('client_price') is-invalid @enderror"
                                               placeholder="np. 2500.00">
                                        @error('client_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" name="client_price_confirmed" id="client_price_confirmed" value="1"
                                                   @checked(old('client_price_confirmed', $case->client_price_confirmed_at ? '1' : '') == '1')>
                                            <label class="form-check-label" for="client_price_confirmed">Potwierdzone ze szkołą</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="instructor_fee" class="form-label">Wynagrodzenie instruktora</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" name="instructor_fee" id="instructor_fee"
                                                   value="{{ old('instructor_fee', $case->instructor_fee) }}"
                                                   class="form-control @error('instructor_fee') is-invalid @enderror"
                                                   placeholder="np. 1200.00">
                                            <select name="instructor_fee_basis" id="instructor_fee_basis"
                                                    class="form-select @error('instructor_fee_basis') is-invalid @enderror"
                                                    style="max-width: 7.5rem;"
                                                    aria-label="Brutto lub netto">
                                                @foreach(\App\Models\ClosedTrainingCase::instructorFeeBasisOptions() as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('instructor_fee_basis', $case->instructor_fee_basis ?: 'gross') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('instructor_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        @error('instructor_fee_basis')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" name="instructor_fee_confirmed" id="instructor_fee_confirmed" value="1"
                                                   @checked(old('instructor_fee_confirmed', $case->instructor_fee_confirmed_at ? '1' : '') == '1')>
                                            <label class="form-check-label" for="instructor_fee_confirmed">Potwierdzone z instruktorem</label>
                                        </div>
                                        <div class="form-text">Zmiana trenera, kwoty lub brutto/netto cofa potwierdzenie, jeśli nie zaznaczysz go ponownie.</div>
                                    </div>
                                </div>

                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="schedule_time_known" id="schedule_time_known" value="1"
                                           @checked(old('schedule_time_known', $case->schedule_time_known ? '1' : '') == '1')
                                           @disabled($negotiationLocked)
                                           onchange="document.getElementById('negotiation-start').type = this.checked ? 'datetime-local' : 'date'; document.getElementById('negotiation-end').type = this.checked ? 'datetime-local' : 'date';">
                                    <label class="form-check-label" for="schedule_time_known">Godzina jest już znana</label>
                                </div>
                                @if($negotiationLocked)
                                    <input type="hidden" name="schedule_time_known" value="{{ $case->schedule_time_known ? '1' : '0' }}">
                                @endif

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="negotiation-start" class="form-label">Początek szkolenia</label>
                                        <input
                                            type="{{ old('schedule_time_known', $case->schedule_time_known ? '1' : '') == '1' ? 'datetime-local' : 'date' }}"
                                            name="start_date"
                                            id="negotiation-start"
                                            value="{{ old('start_date', $case->datetimeLocalValue($case->start_date, (bool) old('schedule_time_known', $case->schedule_time_known))) }}"
                                            class="form-control @error('start_date') is-invalid @enderror"
                                            @disabled($negotiationLocked)>
                                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if($negotiationLocked)
                                            <input type="hidden" name="start_date" value="{{ $case->datetimeLocalValue($case->start_date, (bool) $case->schedule_time_known) }}">
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label for="negotiation-end" class="form-label">Koniec szkolenia</label>
                                        <input
                                            type="{{ old('schedule_time_known', $case->schedule_time_known ? '1' : '') == '1' ? 'datetime-local' : 'date' }}"
                                            name="end_date"
                                            id="negotiation-end"
                                            value="{{ old('end_date', $case->datetimeLocalValue($case->end_date, (bool) old('schedule_time_known', $case->schedule_time_known))) }}"
                                            class="form-control @error('end_date') is-invalid @enderror"
                                            @disabled($negotiationLocked)>
                                        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if($negotiationLocked)
                                            <input type="hidden" name="end_date" value="{{ $case->datetimeLocalValue($case->end_date, (bool) $case->schedule_time_known) }}">
                                        @endif
                                    </div>
                                </div>
                                <div class="form-text mb-3">Można zapisać sam początek. Koniec wymaga początku. Wpisanie terminu nie tworzy szkolenia.</div>

                                <button type="submit" class="btn btn-primary">Zapisz ustalenia</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Szkolenie (realizacja)</div>
                        <div class="card-body">
                            @if($case->course)
                                <p class="mb-1">
                                    <a href="{{ route('courses.show', $case->course) }}" class="fw-semibold">
                                        #{{ $case->course->id }} {{ $case->course->title }}
                                    </a>
                                </p>
                                @if($case->course->start_date)
                                    <p class="text-secondary small mb-1">
                                        {{ $case->formatLocal($case->course->start_date) }}
                                        @if($case->course->end_date)
                                            – {{ $case->formatLocal($case->course->end_date) }}
                                        @endif
                                    </p>
                                @endif
                                @if($case->course->instructor)
                                    <p class="text-secondary small mb-3">Trener: {{ $case->course->instructor->full_title_name }}</p>
                                @endif
                                @if($case->linkedCourseOfferMismatch())
                                    <div class="alert alert-warning py-2 small">
                                        Oferta na sprawie i na szkoleniu różni się — zakres mógł zmienić się w negocjacjach. Pola nie są synchronizowane automatycznie.
                                    </div>
                                @endif
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('courses.show', $case->course) }}" class="btn btn-outline-primary btn-sm">Otwórz szkolenie</a>
                                    <a href="{{ route('courses.edit', $case->course) }}" class="btn btn-outline-secondary btn-sm">Edytuj termin / trenera</a>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#closedTrainingCaseReplaceCourseModal">
                                        Zmień powiązanie
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#closedTrainingCaseUnbindCourseModal">
                                        Odłącz
                                    </button>
                                </div>
                                @if($case->status !== \App\Models\ClosedTrainingCase::STATUS_CLOSED)
                                    <p class="form-text mb-0 mt-3">Status sprawy nie zmienia się automatycznie. Możesz zamknąć sprawę w panelu Obsługa.</p>
                                @endif
                            @else
                                <p class="text-secondary mb-3">Brak powiązanego szkolenia. Po uzgodnieniu terminu utwórz rekord albo powiąż istniejący.</p>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="{{ route('closed-training-cases.create-course', $case) }}" class="btn btn-primary btn-sm">Utwórz szkolenie ze sprawy</a>
                                </div>
                                @if(session('course_bind_mismatch'))
                                    <div class="alert alert-warning">
                                        <p class="mb-2 fw-semibold">Szkolenie różni się od ustaleń na sprawie:</p>
                                        <ul class="mb-3">
                                            @foreach(session('course_bind_mismatch') as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                        <p class="small mb-3">Po powiązaniu źródłem realizacji będzie szkolenie. Ustalenia na sprawie zostaną historyczne.</p>
                                        <form method="POST" action="{{ route('closed-training-cases.course.bind', $case) }}">
                                            @csrf
                                            <input type="hidden" name="course_id" value="{{ session('course_bind_id', old('course_id')) }}">
                                            <input type="hidden" name="confirm_mismatch" value="1">
                                            <button type="submit" class="btn btn-warning">Potwierdź powiązanie mimo różnic</button>
                                        </form>
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('closed-training-cases.course.bind', $case) }}" class="row g-2 align-items-end">
                                    @csrf
                                    <div class="col-sm-8">
                                        <label for="course_id" class="form-label">Powiąż istniejące (ID szkolenia)</label>
                                        <input type="number" name="course_id" id="course_id" min="1" value="{{ old('course_id', session('course_bind_id')) }}" class="form-control @error('course_id') is-invalid @enderror" placeholder="np. 578" required>
                                        @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-4">
                                        <button type="submit" class="btn btn-outline-primary w-100">Powiąż</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header fw-semibold">Historia</div>
                        <ul class="list-group border-0 p-1 mb-0" style="display: flex; flex-direction: column; gap: 2px;">
                            @foreach($case->events->sortByDesc('happened_at') as $event)
                                <li class="list-group-item rounded border {{ $event->historyItemClass() }}">
                                    <div class="d-flex justify-content-between gap-3 align-items-start">
                                        <div class="d-flex gap-2 align-items-start min-w-0">
                                            <i class="bi {{ $event->historyIconClass() }} flex-shrink-0 mt-1"
                                               title="{{ $event->historyIconTitle() }}"
                                               aria-hidden="true"></i>
                                            <div class="min-w-0">
                                            <strong>{{ $event->typeLabel() }}</strong>
                                            @if($event->noteContextLabel())
                                                <span class="text-secondary"> · {{ $event->noteContextLabel() }}</span>
                                            @endif
                                            <div class="text-secondary small">{{ $case->formatLocal($event->happened_at) }} · {{ $event->user?->name ?: 'pnedu.pl' }}</div>
                                            </div>
                                        </div>
                                        <script type="application/json" id="closed-training-case-event-body-{{ $event->id }}">@json($event->body ?? '')</script>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button"
                                                class="btn btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#closedTrainingCaseEventEditModal"
                                                data-event-update-url="{{ route('closed-training-cases.events.update', [$case, $event]) }}"
                                                data-event-label="{{ $event->typeLabel() }}"
                                                data-event-body-id="closed-training-case-event-body-{{ $event->id }}"
                                                data-event-is-email="{{ $event->event_type === \App\Models\ClosedTrainingCaseEvent::TYPE_EMAIL_SENT ? '1' : '0' }}"
                                                data-event-is-note="{{ $event->event_type === \App\Models\ClosedTrainingCaseEvent::TYPE_NOTE ? '1' : '0' }}"
                                                data-note-channel="{{ $event->note_channel ?? '' }}"
                                                data-note-channel-other="{{ $event->note_channel_other ?? '' }}"
                                                data-note-party="{{ $event->note_party ?? '' }}">
                                                Edytuj
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#closedTrainingCaseEventDeleteModal"
                                                data-event-delete-url="{{ route('closed-training-cases.events.destroy', [$case, $event]) }}"
                                                data-event-label="{{ $event->typeLabel() }} · {{ $case->formatLocal($event->happened_at) }}">
                                                Usuń
                                            </button>
                                        </div>
                                    </div>
                                    @if($event->event_type === \App\Models\ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)
                                        <p class="mb-0 mt-2" style="white-space: pre-wrap;">{{ $event->schoolEmailMeta() }}</p>
                                        @if($event->schoolEmailHtml())
                                            <div class="border rounded p-2 mt-2 bg-white small">{!! $event->schoolEmailHtml() !!}</div>
                                        @endif
                                    @elseif($event->body)
                                        <p class="mb-0 mt-2" style="white-space: pre-wrap;">{{ $event->body }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="col-lg-5">
                    <form method="POST"
                          action="{{ route('closed-training-cases.notes.store', $case) }}"
                          id="closed-training-note-form"
                          class="card mb-4 shadow-sm position-sticky"
                          style="top: 1rem; z-index: 1020; max-height: calc(100vh - 2rem); overflow-y: auto;"
                          data-note-sticky="1">
                        @csrf
                        <div class="card-header fw-semibold d-flex align-items-center justify-content-between gap-2">
                            <span>Notatka</span>
                            <button type="button"
                                    id="closed-training-note-pin"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="Przypnij — zostaje na swoim miejscu"
                                    aria-pressed="false"
                                    aria-label="Przypnij notatkę">
                                <i class="bi bi-pin-angle" aria-hidden="true"></i>
                                <span data-note-pin-label>Przypnij</span>
                            </button>
                        </div>
                        <div class="card-body">
                            @php
                                $instructorOptionLabel = $case->listInstructorName();
                                $instructorOptionLabel = $instructorOptionLabel === '—'
                                    ? 'Trener (brak na sprawie)'
                                    : 'Trener: '.$instructorOptionLabel;
                                $clientOptionLabel = filled($case->contact_name)
                                    ? 'Zamawiający: '.$case->contact_name
                                    : 'Zamawiający (brak osoby)';
                                $noteChannelIsOther = old('note_channel') === \App\Models\ClosedTrainingCaseEvent::NOTE_CHANNEL_OTHER;
                            @endphp
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label for="note_channel" class="form-label">Forma kontaktu</label>
                                    <select name="note_channel" id="note_channel" class="form-select @error('note_channel') is-invalid @enderror">
                                        <option value="">Bez oznaczenia</option>
                                        @foreach(\App\Models\ClosedTrainingCaseEvent::noteChannelOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(old('note_channel') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('note_channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label for="note_party" class="form-label">Z kim</label>
                                    <select name="note_party" id="note_party" class="form-select @error('note_party') is-invalid @enderror">
                                        <option value="">Bez oznaczenia</option>
                                        <option value="instructor" @selected(old('note_party') === 'instructor')>{{ $instructorOptionLabel }}</option>
                                        <option value="client" @selected(old('note_party') === 'client')>{{ $clientOptionLabel }}</option>
                                    </select>
                                    @error('note_party')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="mb-2 {{ $noteChannelIsOther ? '' : 'd-none' }}" id="note-channel-other-wrap">
                                <label for="note_channel_other" class="form-label">Doprecyzowanie formy</label>
                                <input type="text"
                                       name="note_channel_other"
                                       id="note_channel_other"
                                       value="{{ old('note_channel_other') }}"
                                       maxlength="100"
                                       class="form-control @error('note_channel_other') is-invalid @enderror"
                                       placeholder="np. SMS, spotkanie">
                                @error('note_channel_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text mb-3">Opcjonalnie. Puste „Z kim” = notatka nie z rozmowy z trenerem ani zamawiającym.</div>
                            <textarea name="body" rows="4" class="form-control @error('body') is-invalid @enderror" placeholder="Ustalenie z rozmowy, bez zmiany statusu.">{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-outline-primary mt-3">Dodaj notatkę</button>
                        </div>
                    </form>

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

                    <div class="card mb-4">
                        <div class="card-header fw-semibold">Wyślij e-mail</div>
                        <div class="card-body">
                            @if($case->hasContactEmail())
                                <form method="POST" action="{{ route('closed-training-cases.email.send', $case) }}" id="closed-training-case-email-form">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Do</label>
                                        <input type="text" class="form-control" value="{{ $case->contact_email }}" readonly>
                                        <div class="form-text">Adres z danych zapytania. Odpowiedź przyjdzie na kontakt@pnedu.pl.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email_subject" class="form-label">Temat</label>
                                        <input type="text" name="email_subject" id="email_subject" value="{{ old('email_subject', $case->defaultSchoolEmailSubject()) }}" maxlength="255" required class="form-control @error('email_subject') is-invalid @enderror">
                                        @error('email_subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Identyfikator {{ $case->referenceCode() }} zostanie dopisany, jeśli go usuniesz.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email_body" class="form-label">Treść</label>
                                        <textarea name="email_body" id="email_body" rows="12" class="form-control @error('email_body') is-invalid @enderror">{!! \App\Models\ClosedTrainingCase::sanitizeSchoolEmailHtml((string) old('email_body', \App\Models\ClosedTrainingCase::defaultSchoolEmailBodyHtml())) !!}</textarea>
                                        @error('email_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Stopka jest już w treści. Możesz ją edytować.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Wyślij e-mail</button>
                                </form>
                            @else
                                <p class="mb-0 text-secondary">Brak poprawnego adresu e-mail w sprawie. Uzupełnij go w danych zapytania, żeby wysłać wiadomość.</p>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
    </div>

    @if($case->course)
        <div class="modal fade" id="closedTrainingCaseUnbindCourseModal" tabindex="-1" aria-labelledby="closedTrainingCaseUnbindCourseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('closed-training-cases.course.unbind', $case) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h5 class="modal-title" id="closedTrainingCaseUnbindCourseModalLabel">Odłącz szkolenie</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Odłączyć szkolenie od tej sprawy?</p>
                            <div class="alert alert-warning mb-0">
                                Szkolenie #{{ $case->course->id }} „{{ $case->course->title }}” pozostanie w systemie — usunięte zostanie tylko powiązanie. Wpis trafi do historii sprawy.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                            <button type="submit" class="btn btn-danger">Odłącz</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="closedTrainingCaseReplaceCourseModal" tabindex="-1" aria-labelledby="closedTrainingCaseReplaceCourseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('closed-training-cases.course.bind', $case) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="closedTrainingCaseReplaceCourseModalLabel">Zmień powiązanie</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Obecne szkolenie: <strong>#{{ $case->course->id }} {{ $case->course->title }}</strong></p>
                            <div class="alert alert-warning">
                                Nowe szkolenie nie może być już powiązane z inną sprawą. Zmiana zostanie zapisana w historii.
                            </div>
                            <label for="replace_course_id" class="form-label">ID nowego szkolenia</label>
                            <input type="number" name="course_id" id="replace_course_id" min="1" class="form-control" required placeholder="np. 578">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                            <button type="submit" class="btn btn-primary">Zmień powiązanie</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="closedTrainingCaseEventEditModal" tabindex="-1" aria-labelledby="closedTrainingCaseEventEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="#" id="closedTrainingCaseEventEditForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="closedTrainingCaseEventEditModalLabel">Edytuj wpis historii</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary small mb-2" id="closedTrainingCaseEventEditType">—</p>
                        <div class="mb-3 d-none" id="closedTrainingCaseEventEditMetaWrap">
                            <label for="closedTrainingCaseEventEditMeta" class="form-label">Nagłówek e-maila</label>
                            <textarea id="closedTrainingCaseEventEditMeta" class="form-control" rows="4" readonly></textarea>
                        </div>
                        <div class="d-none" id="closedTrainingCaseEventEditNoteContextWrap">
                            @php
                                $editInstructorOptionLabel = $case->listInstructorName();
                                $editInstructorOptionLabel = $editInstructorOptionLabel === '—'
                                    ? 'Trener (brak na sprawie)'
                                    : 'Trener: '.$editInstructorOptionLabel;
                                $editClientOptionLabel = filled($case->contact_name)
                                    ? 'Zamawiający: '.$case->contact_name
                                    : 'Zamawiający (brak osoby)';
                            @endphp
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label for="closedTrainingCaseEventEditNoteChannel" class="form-label">Forma kontaktu</label>
                                    <select name="note_channel" id="closedTrainingCaseEventEditNoteChannel" class="form-select">
                                        <option value="">Bez oznaczenia</option>
                                        @foreach(\App\Models\ClosedTrainingCaseEvent::noteChannelOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="closedTrainingCaseEventEditNoteParty" class="form-label">Z kim</label>
                                    <select name="note_party" id="closedTrainingCaseEventEditNoteParty" class="form-select">
                                        <option value="">Bez oznaczenia</option>
                                        <option value="instructor">{{ $editInstructorOptionLabel }}</option>
                                        <option value="client">{{ $editClientOptionLabel }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2 d-none" id="closedTrainingCaseEventEditNoteChannelOtherWrap">
                                <label for="closedTrainingCaseEventEditNoteChannelOther" class="form-label">Doprecyzowanie formy</label>
                                <input type="text"
                                       name="note_channel_other"
                                       id="closedTrainingCaseEventEditNoteChannelOther"
                                       maxlength="100"
                                       class="form-control"
                                       placeholder="np. SMS, spotkanie">
                            </div>
                            <div class="form-text mb-3">Opcjonalnie. Puste „Z kim” = notatka nie z rozmowy z trenerem ani zamawiającym.</div>
                        </div>
                        <label for="closedTrainingCaseEventEditBody" class="form-label">Treść</label>
                        <textarea name="body" id="closedTrainingCaseEventEditBody" rows="10" class="form-control"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">Zapisz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="closedTrainingCaseEventDeleteModal" tabindex="-1" aria-labelledby="closedTrainingCaseEventDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="#" id="closedTrainingCaseEventDeleteForm">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title" id="closedTrainingCaseEventDeleteModalLabel">Usuń wpis historii</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Czy na pewno usunąć ten wpis z historii?</p>
                        <div class="border rounded p-2 bg-light small fw-semibold" id="closedTrainingCaseEventDeleteSummary">—</div>
                        <div class="alert alert-warning small mt-3 mb-0">
                            Tej operacji nie da się cofnąć. Usunięcie wpisu o e-mailu nie cofa wysłanej wiadomości.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-danger">Usuń</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
    <script>
        (function () {
            const tinyOptions = {
                menubar: false,
                branding: false,
                plugins: 'link lists autoresize',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link unlink | removeformat',
                relative_urls: false,
                convert_urls: false,
                browser_spellcheck: true,
                autoresize_bottom_margin: 16,
                extended_valid_elements: 'p[style],table[cellpadding|cellspacing|border|style|role],tr,td[align|style],th[align|style],a[href|target|rel]',
                content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 15px; line-height: 1.6; } p { margin: 0 0 0.75em; } table { border-collapse: collapse; margin: 0; } td { vertical-align: top; padding: 0; }',
            };

            const sendForm = document.getElementById('closed-training-case-email-form');
            if (sendForm && window.tinymce) {
                window.tinymce.init(Object.assign({
                    selector: '#email_body',
                    height: 320,
                }, tinyOptions));
                sendForm.addEventListener('submit', function () {
                    window.tinymce.triggerSave();
                });
            }

            function readEventBody(button) {
                const bodyId = button.getAttribute('data-event-body-id');
                const holder = bodyId ? document.getElementById(bodyId) : null;
                if (!holder) {
                    return '';
                }
                try {
                    return JSON.parse(holder.textContent || '""');
                } catch (error) {
                    return holder.textContent || '';
                }
            }

            function destroyEditEditor() {
                if (!window.tinymce) {
                    return;
                }
                const editor = window.tinymce.get('closedTrainingCaseEventEditBody');
                if (editor) {
                    editor.remove();
                }
            }

            const editModal = document.getElementById('closedTrainingCaseEventEditModal');
            const editForm = document.getElementById('closedTrainingCaseEventEditForm');
            const editType = document.getElementById('closedTrainingCaseEventEditType');
            const editBody = document.getElementById('closedTrainingCaseEventEditBody');
            const editMetaWrap = document.getElementById('closedTrainingCaseEventEditMetaWrap');
            const editMeta = document.getElementById('closedTrainingCaseEventEditMeta');
            const editNoteWrap = document.getElementById('closedTrainingCaseEventEditNoteContextWrap');
            const editNoteChannel = document.getElementById('closedTrainingCaseEventEditNoteChannel');
            const editNoteChannelOtherWrap = document.getElementById('closedTrainingCaseEventEditNoteChannelOtherWrap');
            const editNoteChannelOther = document.getElementById('closedTrainingCaseEventEditNoteChannelOther');
            const editNoteParty = document.getElementById('closedTrainingCaseEventEditNoteParty');
            let editingEmail = false;

            function syncEditNoteChannelOtherVisibility() {
                if (!editNoteChannel || !editNoteChannelOtherWrap) {
                    return;
                }
                if (editNoteChannel.value === 'other') {
                    editNoteChannelOtherWrap.classList.remove('d-none');
                } else {
                    editNoteChannelOtherWrap.classList.add('d-none');
                }
            }

            if (editNoteChannel) {
                editNoteChannel.addEventListener('change', syncEditNoteChannelOtherVisibility);
            }

            if (editModal && editForm && editType && editBody && editMetaWrap && editMeta) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) {
                        return;
                    }

                    destroyEditEditor();
                    editingEmail = button.getAttribute('data-event-is-email') === '1';
                    const editingNote = button.getAttribute('data-event-is-note') === '1';
                    editForm.action = button.getAttribute('data-event-update-url') || '#';
                    editType.textContent = button.getAttribute('data-event-label') || '—';

                    const rawBody = readEventBody(button);
                    if (editingEmail) {
                        const parts = rawBody.split(/\n\n/);
                        editMeta.value = parts[0] || '';
                        editBody.value = parts.slice(1).join('\n\n');
                        editMetaWrap.classList.remove('d-none');
                    } else {
                        editMeta.value = '';
                        editBody.value = rawBody;
                        editMetaWrap.classList.add('d-none');
                    }

                    if (editNoteWrap && editNoteChannel && editNoteChannelOther && editNoteParty) {
                        if (editingNote) {
                            editNoteWrap.classList.remove('d-none');
                            editNoteChannel.value = button.getAttribute('data-note-channel') || '';
                            editNoteChannelOther.value = button.getAttribute('data-note-channel-other') || '';
                            editNoteParty.value = button.getAttribute('data-note-party') || '';
                            syncEditNoteChannelOtherVisibility();
                        } else {
                            editNoteWrap.classList.add('d-none');
                            editNoteChannel.value = '';
                            editNoteChannelOther.value = '';
                            editNoteParty.value = '';
                            syncEditNoteChannelOtherVisibility();
                        }
                    }
                });

                editModal.addEventListener('shown.bs.modal', function () {
                    if (!editingEmail || !window.tinymce) {
                        return;
                    }
                    destroyEditEditor();
                    window.tinymce.init(Object.assign({
                        selector: '#closedTrainingCaseEventEditBody',
                        height: 280,
                    }, tinyOptions));
                });

                editModal.addEventListener('hidden.bs.modal', function () {
                    destroyEditEditor();
                    editingEmail = false;
                });

                editForm.addEventListener('submit', function () {
                    if (window.tinymce) {
                        window.tinymce.triggerSave();
                    }
                    if (editingEmail) {
                        const meta = (editMeta.value || '').trim();
                        const html = (editBody.value || '').trim();
                        editBody.value = html === '' ? meta : meta + '\n\n' + html;
                    }
                });
            }

            const deleteModal = document.getElementById('closedTrainingCaseEventDeleteModal');
            const deleteForm = document.getElementById('closedTrainingCaseEventDeleteForm');
            const deleteSummary = document.getElementById('closedTrainingCaseEventDeleteSummary');
            if (deleteModal && deleteForm && deleteSummary) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) {
                        return;
                    }
                    deleteForm.action = button.getAttribute('data-event-delete-url') || '#';
                    deleteSummary.textContent = button.getAttribute('data-event-label') || '—';
                });
            }

            const noteChannel = document.getElementById('note_channel');
            const noteChannelOtherWrap = document.getElementById('note-channel-other-wrap');
            function syncNoteChannelOtherVisibility() {
                if (!noteChannel || !noteChannelOtherWrap) {
                    return;
                }
                if (noteChannel.value === 'other') {
                    noteChannelOtherWrap.classList.remove('d-none');
                } else {
                    noteChannelOtherWrap.classList.add('d-none');
                }
            }
            if (noteChannel) {
                noteChannel.addEventListener('change', syncNoteChannelOtherVisibility);
                syncNoteChannelOtherVisibility();
            }

            const noteForm = document.getElementById('closed-training-note-form');
            const notePinBtn = document.getElementById('closed-training-note-pin');
            const notePinStorageKey = 'closed_training_case_note_pinned';
            if (noteForm && notePinBtn) {
                const notePinIcon = notePinBtn.querySelector('i.bi');
                const notePinLabel = notePinBtn.querySelector('[data-note-pin-label]');
                const stickyStyle = 'top: 1rem; z-index: 1020; max-height: calc(100vh - 2rem); overflow-y: auto;';
                const pinnedStyle = 'max-height: none; overflow-y: visible;';

                const applyNotePin = function (pinned) {
                    noteForm.classList.toggle('position-sticky', !pinned);
                    noteForm.style.cssText = pinned ? pinnedStyle : stickyStyle;
                    noteForm.setAttribute('data-note-sticky', pinned ? '0' : '1');
                    notePinBtn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
                    notePinBtn.setAttribute(
                        'aria-label',
                        pinned ? 'Odepnij notatkę' : 'Przypnij notatkę'
                    );
                    notePinBtn.title = pinned
                        ? 'Odepnij — znów pływa przy przewijaniu'
                        : 'Przypnij — zostaje na swoim miejscu';
                    if (notePinIcon) {
                        notePinIcon.className = pinned ? 'bi bi-pin-angle-fill' : 'bi bi-pin-angle';
                    }
                    if (notePinLabel) {
                        notePinLabel.textContent = pinned ? 'Odepnij' : 'Przypnij';
                    }
                };

                let notePinned = false;
                try {
                    notePinned = window.localStorage.getItem(notePinStorageKey) === '1';
                } catch (e) {
                    notePinned = false;
                }
                applyNotePin(notePinned);

                notePinBtn.addEventListener('click', function () {
                    notePinned = !notePinned;
                    applyNotePin(notePinned);
                    try {
                        window.localStorage.setItem(notePinStorageKey, notePinned ? '1' : '0');
                    } catch (e) {}
                });
            }
        })();
    </script>
</x-app-layout>
