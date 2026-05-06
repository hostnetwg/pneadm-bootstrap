<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edycja danych uczestnika') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Formularz edycji kursu -->
            <form action="{{ route('participants.update', ['course' => $participant->course_id, 'participant' => $participant->id]) }}" method="POST">
                @csrf
                @method('PUT')
    
                <input type="hidden" name="course_id" value="{{ $participant->course_id }}">
    
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">Imię</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $participant->first_name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Nazwisko</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $participant->last_name) }}" required>
                    </div>
                </div>
    
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $participant->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefon</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $participant->phone) }}" maxlength="50" autocomplete="tel">
                    </div>
                </div>
    
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="birth_date" class="form-label">Data urodzenia</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-control" value="{{ old('birth_date', $participant->birth_date ? $participant->birth_date->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="birth_place" class="form-label">Miejsce urodzenia</label>
                        <input type="text" name="birth_place" id="birth_place" class="form-control" value="{{ old('birth_place', $participant->birth_place) }}">
                    </div>
                </div>

                @if($duplicateParticipants->isNotEmpty())
                    <div class="card border-warning mb-3">
                        <div class="card-header bg-warning bg-opacity-10">
                            <strong><i class="fas fa-copy me-1"></i>Zduplikowany adres e-mail</strong>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                Ten sam adres e-mail (<strong>{{ $participant->email }}</strong>) występuje u
                                <strong>{{ $duplicateParticipants->count() + 1 }}</strong> rekordów uczestnika
                                (poniżej widoczne są pozostałe {{ $duplicateParticipants->count() }}; bieżący rekord
                                ID {{ $participant->id }} jest pominięty). Zakres synchronizacji ustala się wg tego adresu z bazy
                                <em>przed zapisem</em>.
                            </p>
                            @if($duplicateNameMismatchAmongOthers)
                                <div class="alert alert-danger py-2 small mb-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    W części rekordów widać <strong>inne imię lub nazwisko</strong> przy tym e-mailu
                                    — może to być jedna osoba na wielu szkoleniach albo celowo wspólna skrzynka dla kilku osób.
                                    Opcja masowej aktualizacji może nadpisać ich dane. W razie potrzeby dodatkowo zaznacz
                                    świadome potwierdzenie poniżej.
                                </div>
                            @else
                                <div class="alert alert-info py-2 small mb-3">
                                    W widocznych rekordach imię i nazwisko są spójne z tą edycją (na podstawie danych z bazy). Przy zmianie
                                    imienia lub nazwiska w formularzu system ponownie sprawdzi zgodność przy zapisie.
                                </div>
                            @endif

                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Szkolenie</th>
                                            <th>Imię</th>
                                            <th>Nazwisko</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($duplicateParticipants as $dup)
                                            <tr>
                                                <td>{{ $dup->id }}</td>
                                                <td>
                                                    @if($dup->course)
                                                        <a href="{{ route('courses.show', $dup->course->id) }}" target="_blank" rel="noopener">
                                                            {{ str_replace('&nbsp;', ' ', strip_tags($dup->course->title ?? '')) }}
                                                        </a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>{{ $dup->first_name }}</td>
                                                <td>{{ $dup->last_name }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-check mb-2">
                                <input type="checkbox" name="sync_duplicate_email_profiles" id="sync_duplicate_email_profiles"
                                       class="form-check-input @error('sync_duplicate_email_profiles') is-invalid @enderror"
                                       value="1" {{ old('sync_duplicate_email_profiles') ? 'checked' : '' }}>
                                <label class="form-check-label" for="sync_duplicate_email_profiles">
                                    Po zapisie zaktualizuj <strong>we wszystkich rekordach z tym adresem e-mail</strong> (wymienionych powyżej)
                                    pola: imię, nazwisko, e-mail, telefon, data i miejsce urodzenia.
                                    <span class="text-muted d-block mt-1 small">
                                        Nie zmieniamy ani numeracji listy (<code>order</code>), ani daty dostępu, ani notatek, ani niczego ściśle związanego z pojedynczym szkoleniem.
                                    </span>
                                </label>
                                @error('sync_duplicate_email_profiles')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-0 {{ ($duplicateNameMismatchAmongOthers || $errors->has('sync_duplicate_email_confirm_mismatch')) ? '' : 'd-none' }}"
                                 id="sync_duplicate_mismatch_confirm_wrap">
                                <input type="checkbox" name="sync_duplicate_email_confirm_mismatch" id="sync_duplicate_email_confirm_mismatch"
                                       class="form-check-input @error('sync_duplicate_email_confirm_mismatch') is-invalid @enderror"
                                       value="1" {{ old('sync_duplicate_email_confirm_mismatch') ? 'checked' : '' }}>
                                <label class="form-check-label" for="sync_duplicate_email_confirm_mismatch">
                                    Świadomie potwierdzam zsynchronizowanie tych pól pomimo że przy tym e-mailu występują
                                    rekordy z innym zapisanym imieniem lub nazwiskiem niż wartości w tym formularzu.
                                </label>
                                @error('sync_duplicate_email_confirm_mismatch')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                @elseif(\App\Models\Participant::normalizeEmail($participant->email))
                    <div class="alert alert-secondary small mb-3">
                        Dla adresu {{ $participant->email }} nie znaleziono innych rekordów uczestnika (poza bieżącym wpisem).
                    </div>
                @endif

                <div class="mb-3">
                    <label for="notes" class="form-label">Notatki</label>
                    <textarea name="notes" class="form-control" id="notes" rows="4" maxlength="10000" placeholder="Uwagi wewnętrzne, kontekst rejestracji itd.">{{ old('notes', $participant->notes) }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="access_expires_at" class="form-label">Data wygaśnięcia dostępu</label>
                    <input type="datetime-local" name="access_expires_at" id="access_expires_at" class="form-control" value="{{ old('access_expires_at', $participant->access_expires_at ? $participant->access_expires_at->format('Y-m-d\TH:i') : '') }}">
                    <div class="form-text">Pozostaw puste dla bezterminowego dostępu. Czas jest w strefie lokalnej (Polska).</div>
                </div>
    
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                <a href="javascript:history.back()" class="btn btn-secondary">Anuluj</a>                
            </form>
        </div>
    </div>
</x-app-layout>
