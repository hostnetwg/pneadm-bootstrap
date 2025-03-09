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

                    <!-- Terminy i szczegóły -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label fw-bold">Data rozpoczęcia *</label>
                            <input type="datetime-local" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $course->start_date) }}" required>
                            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label for="end_date" class="form-label fw-bold">Data zakończenia *</label>
                            <input type="datetime-local" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $course->end_date) }}" required>
                            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label for="is_paid" class="form-label fw-bold">Płatność</label>
                            <select name="is_paid" class="form-select @error('is_paid') is-invalid @enderror">
                                <option value="1" {{ old('is_paid', $course->is_paid) == 1 ? 'selected' : '' }}>Płatne</option>
                                <option value="0" {{ old('is_paid', $course->is_paid) == 0 ? 'selected' : '' }}>Bezpłatne</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="category" class="form-label fw-bold">Kategoria *</label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="open" {{ old('category', $course->category) == 'open' ? 'selected' : '' }}>Otwarte</option>
                                <option value="closed" {{ old('category', $course->category) == 'closed' ? 'selected' : '' }}>Zamknięte</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="type" class="form-label fw-bold">Rodzaj *</label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" onchange="toggleCourseFields()" required>
                                <option value="offline" {{ old('type', $course->type) == 'offline' ? 'selected' : '' }}>Stacjonarne</option>
                                <option value="online" {{ old('type', $course->type) == 'online' ? 'selected' : '' }}>Online</option>
                            </select>
                        </div>
                    </div>

                    <div id="online-fields" style="display: {{ $course->type == 'online' ? 'block' : 'none' }};">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="platform" class="form-label">Platforma</label>
                                <input type="text" name="platform" class="form-control" id="platform" 
                                    value="{{ $course->onlineDetails->platform ?? '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="meeting_link" class="form-label">Link do spotkania</label>
                                <input type="text" name="meeting_link" class="form-control" id="meeting_link" 
                                    value="{{ $course->onlineDetails->meeting_link ?? '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                                <input type="text" name="meeting_password" class="form-control" id="meeting_password" 
                                    value="{{ $course->onlineDetails->meeting_password ?? '' }}">
                            </div>
                        </div>
                    </div>
                    
                

                <div id="offline-fields" style="display: {{ $course->type == 'offline' ? 'block' : 'none' }};">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="location_name">Nazwa lokalizacji</label>
                                <input type="text" name="location_name" id="location_name" class="form-control"
                                    value="{{ old('location_name', $course->location->location_name ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="address">Adres</label>
                                <input type="text" name="address" id="address" class="form-control"
                                    value="{{ old('address', $course->location->address ?? '') }}">
                            </div>
                        </div>
                        <div class="row mt-2">                     
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

                <div class="row">
                    <div class="col-md-6 mb-3">
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
                    <div class="col-md-6 mb-3">
                        <label for="image" class="form-label">Obrazek kursu</label>                
                        <input type="file" name="image" class="form-control" id="image">
                    
                        @if (!empty($course->image))
                            <div class="mt-2">
                                <img src="{{ asset('storage/' . $course->image) }}" alt="Obrazek kursu" class="img-thumbnail" style="max-width: 200px;">
                    
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image">
                                    <label class="form-check-label" for="remove_image">Usuń grafikę z kursu</label>
                                </div>
                            </div>
                        @endif                   
                    </div>
                    
                </div>

                <div class="form-group">
                    <label for="certificate_format">Format numeracji certyfikatów</label>
                    <input type="text" name="certificate_format" id="certificate_format" class="form-control" 
                           value="{{ old('certificate_format', isset($course) ? $course->certificate_format : '{nr}/{course_id}/{year}/PNE') }}" 
                           placeholder="Wpisz format, np. RL/{nr}/{course_id}/2/{year}/PNE">
                    <small class="form-text text-muted">
                        Możesz używać zmiennych: <code>{nr}</code>, <code>{course_id}</code>, <code>{year}</code>.
                    </small>
                </div>                

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $course->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktywny</label>
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
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let startDateInput = document.querySelector('input[name="start_date"]');
        let endDateInput = document.querySelector('input[name="end_date"]');

        function validateDates() {
            let startDate = new Date(startDateInput.value);
            let endDate = new Date(endDateInput.value);

            if (endDate <= startDate) {
                alert("Data zakończenia musi być późniejsza niż data rozpoczęcia!");
                endDateInput.value = ""; // Resetowanie błędnej wartości
            }
        }

        endDateInput.addEventListener("change", validateDates);
    });
</script>
