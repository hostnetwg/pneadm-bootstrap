<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe szkolenie') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <form action="{{ route('certgen_publigo.store') }}" method="POST">
                @csrf

                <!-- ID ze starej bazy i Tytuł -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="id_old" class="form-label">ID Publigo (id_old)</label>
                        <input type="text" class="form-control" id="id_old" name="id_old">
                    </div>
                    <div class="col-md-9">
                        <label for="title" class="form-label">Tytuł</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis</label>
                    <textarea class="form-control" id="description" name="description"></textarea>
                </div>

                <!-- Data rozpoczęcia, zakończenia, Płatne, Rodzaj, Kategoria -->
                <div class="row">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data zakończenia</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                    </div>
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label">Płatne?</label>
                        <select class="form-control" id="is_paid" name="is_paid" required>
                            <option value="0">Nie</option>
                            <option value="1" selected>Tak</option> <!-- Domyślnie Tak -->
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rodzaj</label>
                        <select class="form-control" id="type" name="type" required onchange="toggleFields()">
                            <option value="online" selected>Online</option> <!-- Domyślnie Online -->
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Kategoria</label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="open">Otwarte</option>
                            <option value="closed">Zamknięte</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="certificate_format" class="form-label">Format certyfikatu</label>
                    <input type="text" class="form-control" id="certificate_format" name="certificate_format" value="{nr}/{course_id}/{year}/PNE">
                </div>

                <!-- Sekcja dla kursów ONLINE -->
                <div id="onlineFields">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="platform" class="form-label">Platforma</label>
                            <input type="text" class="form-control" id="platform" name="platform" value="ClickMeeting">
                        </div>
                        <div class="col-md-4">
                            <label for="meeting_link" class="form-label">Link do spotkania</label>
                            <input type="text" class="form-control" id="meeting_link" name="meeting_link">
                        </div>
                        <div class="col-md-4">
                            <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                            <input type="text" class="form-control" id="meeting_password" name="meeting_password">
                        </div>
                    </div>
                </div>

                <!-- Sekcja dla kursów OFFLINE -->
                <div id="offlineFields" style="display: none;">
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="location_name" class="form-label">Miejsce szkolenia</label>
                            <input type="text" class="form-control" id="location_name" name="location_name">
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>                        
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-3">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code">
                        </div>

                        <div class="col-md-3">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" class="form-control" id="post_office" name="post_office">
                        </div>

                        <div class="col-md-3">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" class="form-control" id="country" name="country" value="Polska">
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </div>

            </form>
        </div>
    </div>

    <script>
        function toggleFields() {
            let type = document.getElementById('type').value;
            let onlineFields = document.getElementById('onlineFields');
            let offlineFields = document.getElementById('offlineFields');

            if (type === 'online') {
                onlineFields.style.display = 'block';
                offlineFields.style.display = 'none';
            } else {
                onlineFields.style.display = 'none';
                offlineFields.style.display = 'block';
            }
        }

        // Wywołanie przy załadowaniu strony
        document.addEventListener("DOMContentLoaded", function () {
            toggleFields();
        });
    </script>
</x-app-layout>
