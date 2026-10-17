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
                            <label for="institution" class="form-label">Szkoła / placówka</label>
                            <input type="text" name="institution" id="institution" value="{{ old('institution') }}" class="form-control @error('institution') is-invalid @enderror">
                            @error('institution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

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
