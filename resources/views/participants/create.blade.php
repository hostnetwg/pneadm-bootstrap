<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Dodaj uczestnika do kursu - {{ $course->title }}
        </h2>
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

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" id="email">
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
                <label for="access_expires_at" class="form-label">Data wygaśnięcia dostępu</label>
                <input type="datetime-local" name="access_expires_at" class="form-control" id="access_expires_at">
                <div class="form-text">Pozostaw puste dla bezterminowego dostępu</div>
            </div>

            <button type="submit" class="btn btn-success">Dodaj uczestnika</button>
            <a href="javascript:history.back()" class="btn btn-secondary">Anuluj</a>
        </form>
    </div>
</x-app-layout>
