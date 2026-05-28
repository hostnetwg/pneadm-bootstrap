<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-semibold fs-4 text-dark mb-1">
                <i class="fas fa-user-plus me-2"></i>Dodaj uczestnika
            </h2>
            <p class="text-muted mb-0">
                <strong>{!! $course->title !!}</strong>
                <span class="ms-2">
                    <i class="fas fa-calendar me-1"></i>
                    @if($course->start_date)
                        {{ date('d.m.Y H:i', strtotime($course->start_date)) }}
                    @else
                        Brak daty rozpoczęcia
                    @endif
                </span>
                <span class="ms-2">
                    <i class="fas fa-chalkboard-teacher me-1"></i>
                    {{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}
                </span>
            </p>
        </div>
    </x-slot>

    <div class="container py-3">
        <form action="{{ route('participants.store', $course) }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">Imię</label>
                    <input type="text" name="first_name" class="form-control" id="first_name" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Nazwisko</label>
                    <input type="text" name="last_name" class="form-control" id="last_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" id="email">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-control" id="phone" maxlength="50" autocomplete="tel">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="birth_date" class="form-label">Data urodzenia</label>
                    <input type="date" name="birth_date" class="form-control" id="birth_date">
                </div>
                <div class="col-md-6">
                    <label for="birth_place" class="form-label">Miejsce urodzenia</label>
                    <input type="text" name="birth_place" class="form-control" id="birth_place">
                </div>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notatki</label>
                <textarea name="notes" class="form-control" id="notes" rows="4" maxlength="10000" placeholder="Uwagi wewnętrzne, kontekst rejestracji itd."></textarea>
            </div>

            <div class="mb-3">
                <label for="access_expires_at" class="form-label">Data wygaśnięcia dostępu</label>
                <input type="datetime-local"
                       name="access_expires_at"
                       class="form-control"
                       id="access_expires_at"
                       value="{{ old('access_expires_at', $defaultAccessExpiresAt ? $defaultAccessExpiresAt->timezone('Europe/Warsaw')->format('Y-m-d\TH:i') : '') }}">
                <div class="form-text">Domyślnie: data zakończenia szkolenia + okres ustawiony dla szkolenia lub globalnie. Możesz zmienić datę albo wyczyścić pole dla dostępu bezterminowego.</div>
            </div>

            <button type="submit" class="btn btn-success">Dodaj uczestnika</button>
            <a href="javascript:history.back()" class="btn btn-secondary">Anuluj</a>
        </form>
    </div>
</x-app-layout>
