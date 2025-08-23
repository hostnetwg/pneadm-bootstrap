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
    
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $participant->email) }}">
                </div>
    
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="birth_date" class="form-label">Data urodzenia</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-control" value="{{ old('birth_date', $participant->birth_date) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="birth_place" class="form-label">Miejsce urodzenia</label>
                        <input type="text" name="birth_place" id="birth_place" class="form-control" value="{{ old('birth_place', $participant->birth_place) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="access_expires_at" class="form-label">Data wygaśnięcia dostępu</label>
                    <input type="datetime-local" name="access_expires_at" id="access_expires_at" class="form-control" value="{{ old('access_expires_at', $participant->access_expires_at ? $participant->access_expires_at->format('Y-m-d\TH:i') : '') }}">
                    <div class="form-text">Pozostaw puste dla bezterminowego dostępu</div>
                </div>
    
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                <a href="javascript:history.back()" class="btn btn-secondary">Anuluj</a>                
            </form>
        </div>
    </div>
</x-app-layout>
