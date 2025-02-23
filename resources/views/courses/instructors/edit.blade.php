<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Edytuj instruktora
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Edytuj instruktora</h1>

            <form action="{{ route('courses.instructors.update', $instructor->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="first_name" class="form-label">Imię</label>
                    <input type="text" name="first_name" class="form-control" id="first_name" value="{{ $instructor->first_name }}" required>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Nazwisko</label>
                    <input type="text" name="last_name" class="form-control" id="last_name" value="{{ $instructor->last_name }}" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" id="email" value="{{ $instructor->email }}" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-control" id="phone" value="{{ $instructor->phone }}">
                </div>
                <div class="mb-3">
                    <label for="bio" class="form-label">Biografia</label>
                    <textarea name="bio" class="form-control" id="bio" rows="3">{{ $instructor->bio }}</textarea>
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">Zdjęcie</label>
                    <input type="file" name="photo" class="form-control" id="photo">
                    @if ($instructor->photo)
                        <img src="{{ asset('storage/' . $instructor->photo) }}" alt="Zdjęcie" width="100" class="mt-2">
                    @endif
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $instructor->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>
                <button type="submit" class="btn btn-success">Zapisz zmiany</button>
            </form>
        </div>
    </div>
</x-app-layout>
