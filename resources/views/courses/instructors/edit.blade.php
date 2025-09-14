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
                @method('PUT')

                <!-- Wiersz z tytułem, imieniem i nazwiskiem -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="title" class="form-label">Tytuł naukowy</label>
                        <input type="text" name="title" class="form-control" id="title" value="{{ $instructor->title }}" placeholder="">
                    </div>
                    <div class="col-md-4">
                        <label for="first_name" class="form-label">Imię</label>
                        <input type="text" name="first_name" class="form-control" id="first_name" value="{{ $instructor->first_name }}" required>
                    </div>
                    <div class="col-md-5">
                        <label for="last_name" class="form-label">Nazwisko</label>
                        <input type="text" name="last_name" class="form-control" id="last_name" value="{{ $instructor->last_name }}" required>
                    </div>
                </div>

                <!-- Wiersz z e-mailem i telefonem -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" id="email" value="{{ $instructor->email }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" id="phone" value="{{ $instructor->phone }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="bio" class="form-label">Biografia</label>
                    <textarea name="bio" class="form-control" id="bio" rows="3">{{ $instructor->bio }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="photo" class="form-label">Zdjęcie</label>
                    <input type="file" name="photo" class="form-control" id="photo">
                    
                    @if ($instructor->photo)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $instructor->photo) }}" alt="Zdjęcie instruktora" class="img-thumbnail" style="max-width: 200px;">
                            
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="remove_photo" name="remove_photo">
                                <label class="form-check-label" for="remove_photo">Usuń zdjęcie instruktora</label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="signature" class="form-label">Podpis (grafika)</label>
                    <input type="file" name="signature" class="form-control" id="signature">
                    
                    @if ($instructor->signature)
                        <div class="mt-2">
                            <p>Aktualny podpis:</p>
                            <img src="{{ asset('storage/' . $instructor->signature) }}" alt="Podpis instruktora" class="img-thumbnail" style="max-width: 200px;">
                            
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="remove_signature" name="remove_signature">
                                <label class="form-check-label" for="remove_signature">Usuń podpis instruktora</label>
                            </div>
                        </div>
                    @else
                        <p class="text-muted">Brak podpisu</p>
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
