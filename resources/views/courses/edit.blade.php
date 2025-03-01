<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Edytuj szkolenie: {{ $course->title }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Edytuj szkolenie</h1>

            <!-- Komunikat o sukcesie -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Formularz edycji kursu -->
            <form action="{{ route('courses.update', $course->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł</label>
                    <input type="text" name="title" class="form-control" id="title" value="{{ $course->title }}" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis</label>
                    <textarea name="description" class="form-control" id="description" rows="3">{{ $course->description }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Data rozpoczęcia</label>
                            <input type="datetime-local" name="start_date" class="form-control" id="start_date" value="{{ date('Y-m-d\TH:i', strtotime($course->start_date)) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">Data zakończenia</label>
                            <input type="datetime-local" name="end_date" class="form-control" id="end_date" value="{{ date('Y-m-d\TH:i', strtotime($course->end_date)) }}" required>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="category" class="form-label">Kategoria</label>
                        <select name="category" class="form-control" id="category" required>
                            <option value="open" {{ $course->category == 'open' ? 'selected' : '' }}>Otwarte</option>
                            <option value="closed" {{ $course->category == 'closed' ? 'selected' : '' }}>Zamknięte</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="type" class="form-label">Rodzaj kursu</label>
                        <select name="type" id="type" class="form-control" onchange="toggleCourseFields()">
                            <option value="online" {{ $course->type == 'online' ? 'selected' : '' }}>Online</option>
                            <option value="offline" {{ $course->type == 'offline' ? 'selected' : '' }}>Stacjonarne</option>
                        </select>
                    </div>
                </div>

                <div id="online-fields" style="display: {{ $course->type == 'online' ? 'block' : 'none' }};">
                    <div class="mb-3">
                        <label for="platform" class="form-label">Platforma</label>
                        <input type="text" name="platform" class="form-control" id="platform" 
                            value="{{ $course->onlineDetails->platform ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label for="meeting_link" class="form-label">Link do spotkania</label>
                        <input type="text" name="meeting_link" class="form-control" id="meeting_link" 
                            value="{{ $course->onlineDetails->meeting_link ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                        <input type="text" name="meeting_password" class="form-control" id="meeting_password" 
                            value="{{ $course->onlineDetails->meeting_password ?? '' }}">
                    </div>
                </div>
                

                <div id="offline-fields" style="display: {{ $course->type == 'offline' ? 'block' : 'none' }};">
                    <div class="row">
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" name="address" id="address" class="form-control" 
                                value="{{ $course->location->address ?? '' }}">
                        </div>                        
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control" 
                                value="{{ $course->location->postal_code ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" name="post_office" id="post_office" class="form-control" 
                                value="{{ $course->location->post_office ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" name="country" id="country" class="form-control" 
                                value="{{ $course->location->country ?? 'Polska' }}">
                        </div>
                    </div>
                </div>                

                <div class="mb-3">
                    <label for="instructor_id" class="form-label">Instruktor</label>
                    <select name="instructor_id" class="form-control" id="instructor_id">
                        <option value="">Brak</option>
                        @foreach($instructors as $instructor)
                            <option value="{{ $instructor->id }}" {{ $course->instructor_id == $instructor->id ? 'selected' : '' }}>
                                {{ $instructor->first_name }} {{ $instructor->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $course->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Obrazek kursu</label>
                    <input type="file" name="image" class="form-control" id="image">
                
                    @if ($course->image)
                        <div class="mt-2">
                            <p>Aktualny obrazek:</p>
                            <img src="{{ asset('storage/' . $course->image) }}" alt="Obrazek kursu" width="100">
                        </div>
                    @else
                        <p class="text-muted">Brak aktualnego obrazka</p>
                    @endif
                </div>
                

                <button type="submit" class="btn btn-success">Zapisz zmiany</button>
                <a href="{{ route('courses.index') }}" class="btn btn-secondary">Anuluj</a>
            </form>
        </div>
    </div>
</x-app-layout>

<script>
function toggleCourseFields() {
    const type = document.getElementById('type').value;
    document.getElementById('online-fields').style.display = type === 'online' ? 'block' : 'none';
    document.getElementById('offline-fields').style.display = type === 'offline' ? 'block' : 'none';
}

window.onload = toggleCourseFields;
</script>