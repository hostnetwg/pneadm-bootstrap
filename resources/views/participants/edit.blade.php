<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Edytuj uczestnika - {{ $course->title }}
        </h2>
    </x-slot>

    <div class="container py-3">
        <form action="{{ route('participants.update', [$course, $participant]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="first_name" class="form-label">Imię</label>
                <input type="text" name="first_name" class="form-control" id="first_name" value="{{ $participant->first_name }}" required>
            </div>

            <div class="mb-3">
                <label for="last_name" class="form-label">Nazwisko</label>
                <input type="text" name="last_name" class="form-control" id="last_name" value="{{ $participant->last_name }}" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" id="email" value="{{ $participant->email }}">
            </div>

            <div class="mb-3">
                <label for="birth_date" class="form-label">Data urodzenia</label>
                <input type="date" name="birth_date" class="form-control" id="birth_date" value="{{ $participant->birth_date }}">
            </div>

            <div class="mb-3">
                <label for="birth_place" class="form-label">Miejsce urodzenia</label>
                <input type="text" name="birth_place" class="form-control" id="birth_place" value="{{ $participant->birth_place }}">
            </div>

            <button type="submit" class="btn btn-success">Zapisz zmiany</button>
        </form>
    </div>
</x-app-layout>
