<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe szkolenie Publigo') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <form action="{{ route('certgen_publigo.store') }}" method="POST">
                @csrf

                <!-- Wiersz: ID Old + Tytuł -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="id_old" class="form-label">ID ze starej bazy</label>
                        <input type="text" class="form-control" name="id_old" id="id_old">
                    </div>
                    <div class="col-md-9">
                        <label for="title" class="form-label">Tytuł</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                </div>

                <!-- Wiersz: Data rozpoczęcia, Data zakończenia, Płatne, Rodzaj, Kategoria -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="datetime-local" class="form-control" name="start_date" id="start_date" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data zakończenia</label>
                        <input type="datetime-local" class="form-control" name="end_date" id="end_date">
                    </div>
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label">Płatne?</label>
                        <select class="form-select" name="is_paid" id="is_paid">
                            <option value="1" selected>Tak</option>
                            <option value="0">Nie</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rodzaj</label>
                        <select class="form-select" name="type" id="type">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Kategoria</label>
                        <select class="form-select" name="category" id="category">
                            <option value="open">Otwarte</option>
                            <option value="closed">Zamknięte</option>
                        </select>
                    </div>
                </div>

                <!-- Wiersz: Instruktor, Format certyfikatu -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="instructor_id" class="form-label">Instruktor</label>
                        <select class="form-select" name="instructor_id" id="instructor_id">
                            <option value="">Wybierz instruktora</option>
                            @foreach ($instructors as $instructor)
                                <option value="{{ $instructor->id }}">
                                    {{ $instructor->first_name }} {{ $instructor->last_name }}                                    
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="certificate_format" class="form-label">Format certyfikatu</label>
                        <input type="text" class="form-control" name="certificate_format" id="certificate_format" value="{nr}/{course_id}/{year}/PNE">
                    </div>
                </div>

                <!-- Sekcja dla kursów online -->
                <div id="onlineFields">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="platform" class="form-label">Platforma</label>
                            <input type="text" class="form-control" name="platform" id="platform" value="ClickMeeting">
                        </div>
                        <div class="col-md-4">
                            <label for="meeting_link" class="form-label">Link do spotkania</label>
                            <input type="text" class="form-control" name="meeting_link" id="meeting_link">
                        </div>
                        <div class="col-md-4">
                            <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                            <input type="text" class="form-control" name="meeting_password" id="meeting_password">
                        </div>
                    </div>
                </div>

                <!-- Sekcja dla kursów offline -->
                <div id="offlineFields" style="display: none;">
                    <!-- Miejsce szkolenia + Adres -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="location_name" class="form-label">Miejsce szkolenia</label>
                            <input type="text" class="form-control" name="location_name" id="location_name">
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" class="form-control" name="address" id="address">
                        </div>
                    </div>
                    
                    <!-- Kod pocztowy + Poczta + Kraj -->
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" class="form-control" name="postal_code" id="postal_code">
                        </div>
                        <div class="col-md-5">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" class="form-control" name="post_office" id="post_office">
                        </div>
                        <div class="col-md-5">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" class="form-control" name="country" id="country" value="Polska">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Dodaj szkolenie</button>
                <a href="javascript:history.back()" class="btn btn-secondary">Anuluj</a>                  
            </form>
        </div>
    </div>

    <script>
        document.getElementById('type').addEventListener('change', function () {
            let onlineFields = document.getElementById('onlineFields');
            let offlineFields = document.getElementById('offlineFields');
            if (this.value === 'online') {
                onlineFields.style.display = 'block';
                offlineFields.style.display = 'none';
            } else {
                onlineFields.style.display = 'none';
                offlineFields.style.display = 'block';
            }
        });
    </script>
</x-app-layout>
