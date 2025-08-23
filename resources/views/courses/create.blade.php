<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe szkolenie') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Formularz dodawania kursu -->
            <form action="{{ route('courses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł kursu</label>
                    <input type="text" name="title" id="title" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis</label>
                    <textarea name="description" id="description" class="form-control"></textarea>
                </div>

                <div class="row row mb-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="datetime-local" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Data zakończenia</label>
                        <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $course->end_date ?? '') }}">
                        @error('end_date')
                            <div class="text-danger">{{ $message }}</div> <!-- ✅ Wyświetlanie błędu -->
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label">Płatność</label>
                        <select name="is_paid" class="form-control" id="is_paid" required>
                            <option value="1">Płatne</option>
                            <option value="0">Bezpłatne</option>
                        </select>
                    </div>                    
                    <div class="col-md-2">
                        <label for="category" class="form-label">Kategoria</label>
                        <select name="category" class="form-control" id="category" required>
                            <option value="open">Otwarte</option>
                            <option value="closed">Zamknięte</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rodzaj kursu</label>
                        <select name="type" id="type" class="form-control" onchange="toggleCourseFields()">
                            <option value="online" selected>Online</option>
                            <option value="offline">Stacjonarne</option>
                        </select>
                    </div>
                </div>

                <!-- Pola dla kursów online -->
                <div id="onlineFields" class="row">
                    <div class="col-md-4">
                        <label for="platform" class="form-label">Platforma</label>
                        <input type="text" name="platform" id="platform" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_link" class="form-label">Link do spotkania</label>
                        <input type="url" name="meeting_link" id="meeting_link" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                        <input type="text" name="meeting_password" id="meeting_password" class="form-control">
                    </div>
                </div>


                <!-- Pola dla kursów stacjonarnych -->
                <div id="offlineFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="location_name">Nazwa lokalizacji</label>
                            <input type="text" name="location_name" id="location_name" class="form-control" value="{{ old('location_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="address">Adres</label>
                            <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}">
                        </div>
                    </div>
                    <div class="row mt-2">                    
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" name="post_office" id="post_office" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" name="country" id="country" class="form-control" value="Polska">
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="instructor_id" class="form-label">Instruktor</label>
                            <select name="instructor_id" id="instructor_id" class="form-control">
                                <option value="">Brak</option>
                                @foreach ($instructors as $instructor)
                                    <option value="{{ $instructor->id }}">{{ $instructor->first_name }} {{ $instructor->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="image" class="form-label">Obrazek</label>
                            <input type="file" name="image" id="image" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="certificate_format">Format numeracji certyfikatów</label>
                    <input type="text" name="certificate_format" id="certificate_format" class="form-control" 
                           value="{{ old('certificate_format', isset($course) ? $course->certificate_format : '{nr}/{course_id}/{year}/PNE') }}" 
                           placeholder="Wpisz format, np. RL/{nr}/{course_id}/2/{year}/PNE">
                    <small class="form-text text-muted">
                        Możesz używać zmiennych: <code>{nr}</code>, <code>{course_id}</code>, <code>{year}</code>.
                    </small>
                </div>

                <!-- Pola dla integracji ze starym systemem -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_old" class="form-label">ID ze starej bazy</label>
                        <input type="text" name="id_old" class="form-control" id="id_old" value="{{ old('id_old') }}" placeholder="np. 12345">
                        <div class="form-text">ID kursu w zewnętrznym systemie (opcjonalne)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="source_id_old" class="form-label">Źródło danych</label>
                        <select name="source_id_old" class="form-control" id="source_id_old">
                            <option value="">Brak</option>
                            <option value="certgen_Publigo" {{ old('source_id_old') == 'certgen_Publigo' ? 'selected' : '' }}>Publigo</option>
                            <option value="certgen_NODN" {{ old('source_id_old') == 'certgen_NODN' ? 'selected' : '' }}>NODN</option>
                            <option value="BD:Certgen-education" {{ old('source_id_old') == 'BD:Certgen-education' ? 'selected' : '' }}>Webinar TIK</option>
                        </select>
                        <div class="form-text">Źródło danych kursu (opcjonalne)</div>
                    </div>
                </div>                

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <button type="submit" class="btn btn-primary">Dodaj kurs</button>
                <a href="{{ route('courses.index') }}" class="btn btn-secondary">Anuluj</a>
            </form>
        </div>
    </div>

    <script>
        function toggleCourseFields() {
            const type = document.getElementById('type').value;
            document.getElementById('onlineFields').style.display = (type === 'online') ? 'flex' : 'none';
            document.getElementById('offlineFields').style.display = (type === 'offline') ? 'block' : 'none';
        }

        // Wywołanie funkcji przy załadowaniu strony, aby ukryć/pokazać odpowiednie pola
        document.addEventListener('DOMContentLoaded', function () {
            toggleCourseFields();
        });
    </script>

</x-app-layout>
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
