<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Nowa sprawa szkolenia zamkniętego</h2>
            <a href="{{ route('closed-training-cases.index') }}" class="btn btn-outline-secondary">Wróć do listy</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container px-4" style="max-width: 920px;">
            <form method="POST" action="{{ route('closed-training-cases.store') }}" class="card">
                @csrf
                <div class="card-body">
                    <p class="text-muted">Dla telefonu albo maila, który nie przeszedł przez formularz na pnedu.pl.</p>

                    <div class="mb-3">
                        <label for="topic" class="form-label">Temat szkolenia <span class="text-danger">*</span></label>
                        <input type="text" name="topic" id="topic" value="{{ old('topic') }}" class="form-control @error('topic') is-invalid @enderror" required>
                        @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_name" class="form-label">Osoba kontaktowa <span class="text-danger">*</span></label>
                            <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name') }}" class="form-control @error('contact_name') is-invalid @enderror" required>
                            @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="institution_type" class="form-label">Rodzaj placówki <span class="text-danger">*</span></label>
                            <select name="institution_type" id="institution_type" class="form-select @error('institution_type') is-invalid @enderror" required>
                                <option value="">Wybierz rodzaj placówki…</option>
                                @foreach(\App\Models\ClosedTrainingCase::institutionTypeOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('institution_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('institution_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3" id="institution_type_other_wrap" @if(old('institution_type') !== \App\Models\ClosedTrainingCase::INSTITUTION_OTHER) hidden @endif>
                        <label for="institution_type_other" class="form-label">Jaki rodzaj placówki? <span class="text-danger">*</span></label>
                        <input type="text" name="institution_type_other" id="institution_type_other" value="{{ old('institution_type_other') }}" class="form-control @error('institution_type_other') is-invalid @enderror" placeholder="np. poradnia psychologiczno-pedagogiczna" @disabled(old('institution_type') !== \App\Models\ClosedTrainingCase::INSTITUTION_OTHER)>
                        @error('institution_type_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="institution" class="form-label">Nazwa szkoły / placówki (opcjonalnie)</label>
                        <input type="text" name="institution" id="institution" value="{{ old('institution') }}" class="form-control @error('institution') is-invalid @enderror">
                        <div class="form-text">Nie musisz podawać pełnej nazwy placówki na tym etapie.</div>
                        @error('institution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="pedagogical_council_size" class="form-label">Liczba osób w radzie pedagogicznej</label>
                        <input type="number"
                               name="pedagogical_council_size"
                               id="pedagogical_council_size"
                               value="{{ old('pedagogical_council_size') }}"
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
                        <label for="institution_www" class="form-label">WWW (opcjonalnie)</label>
                        <input type="text" name="institution_www" id="institution_www" value="{{ old('institution_www') }}" maxlength="2048" placeholder="https://szkola.edu.pl" inputmode="url" autocomplete="url" class="form-control @error('institution_www') is-invalid @enderror">
                        <div class="form-text">Adres strony szkoły / placówki.</div>
                        @error('institution_www')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_email" class="form-label">E-mail</label>
                            <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email') }}" class="form-control @error('contact_email') is-invalid @enderror">
                            @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Telefon</label>
                            <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone') }}" class="form-control @error('contact_phone') is-invalid @enderror">
                            @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="preferred_format" class="form-label">Forma</label>
                        <select name="preferred_format" id="preferred_format" class="form-select @error('preferred_format') is-invalid @enderror">
                            <option value="">Nie wybrano</option>
                            @foreach(\App\Models\ClosedTrainingCase::formatOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('preferred_format') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('preferred_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Opis sprawy <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="5" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assignee_user_id" class="form-label">Opiekun</label>
                            <select name="assignee_user_id" id="assignee_user_id" class="form-select">
                                <option value="">Nieprzypisany</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('assignee_user_id') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="next_action_at" class="form-label">Termin następnego działania</label>
                            <input type="datetime-local" name="next_action_at" id="next_action_at" value="{{ old('next_action_at') }}" class="form-control @error('next_action_at') is-invalid @enderror">
                            @error('next_action_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="next_action" class="form-label">Następne działanie</label>
                        <textarea name="next_action" id="next_action" rows="2" class="form-control" placeholder="Np. jeżeli nie będzie odpowiedzi do piątku, zadzwonić ponownie.">{{ old('next_action') }}</textarea>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary">Zapisz sprawę</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
