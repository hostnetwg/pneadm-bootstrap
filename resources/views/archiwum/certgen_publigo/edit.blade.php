<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Edytuj szkolenie</h2>
    </x-slot>

    <div class="container py-3">
        <form action="{{ route('certgen_publigo.update', $szkolenie->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- ID OLD & TYTUŁ -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="id_old" class="form-label">ID ze starej bazy</label>
                    <input type="text" class="form-control" id="id_old" name="id_old" value="{{ $szkolenie->id_old }}" readonly>
                </div>
                <div class="col-md-9">
                    <label for="title" class="form-label">Tytuł</label>
                    <input type="text" class="form-control" id="title" name="title" value="{{ $szkolenie->title }}" required>
                </div>
            </div>

            <!-- OPIS -->
            <div class="mb-3">
                <label for="description" class="form-label">Opis</label>
                <textarea class="form-control" id="description" name="description" rows="3">{{ $szkolenie->description }}</textarea>
            </div>

            <!-- DATA, PŁATNE, RODZAJ, KATEGORIA -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Data rozpoczęcia</label>
                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="{{ $szkolenie->start_date }}" required>
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Data zakończenia</label>
                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="{{ $szkolenie->end_date }}">
                </div>
                <div class="col-md-2">
                    <label for="is_paid" class="form-label">Płatne?</label>
                    <select class="form-control" id="is_paid" name="is_paid">
                        <option value="1" {{ $szkolenie->is_paid ? 'selected' : '' }}>Tak</option>
                        <option value="0" {{ !$szkolenie->is_paid ? 'selected' : '' }}>Nie</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label">Rodzaj</label>
                    <select class="form-control" id="type" name="type" onchange="toggleFormSections()">
                        <option value="online" {{ $szkolenie->type == 'online' ? 'selected' : '' }}>Online</option>
                        <option value="offline" {{ $szkolenie->type == 'offline' ? 'selected' : '' }}>Offline</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label">Kategoria</label>
                    <select class="form-control" id="category" name="category">
                        <option value="open" {{ $szkolenie->category == 'open' ? 'selected' : '' }}>Otwarte</option>
                        <option value="closed" {{ $szkolenie->category == 'closed' ? 'selected' : '' }}>Zamknięte</option>
                    </select>
                </div>
            </div>

            <!-- Sekcja ONLINE -->
            <div class="row mb-3" id="online_options" style="display: {{ $szkolenie->type == 'online' ? 'flex' : 'none' }};">
                <div class="col-md-4">
                    <label for="platform" class="form-label">Platforma</label>
                    <input type="text" class="form-control" id="platform" name="platform" value="{{ $szkolenie->platform }}">
                </div>
                <div class="col-md-4">
                    <label for="meeting_link" class="form-label">Link do spotkania</label>
                    <input type="text" class="form-control" id="meeting_link" name="meeting_link" value="{{ $szkolenie->meeting_link }}">
                </div>
                <div class="col-md-4">
                    <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                    <input type="text" class="form-control" id="meeting_password" name="meeting_password" value="{{ $szkolenie->meeting_password }}">
                </div>
            </div>

            <!-- Sekcja OFFLINE -->
            <div class="row mb-3" id="offline_options" style="display: {{ $szkolenie->type == 'offline' ? 'flex' : 'none' }};">
                <div class="col-md-6">
                    <label for="location_name" class="form-label">Miejsce szkolenia</label>
                    <input type="text" class="form-control" id="location_name" name="location_name" value="{{ $szkolenie->location_name }}">
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Adres</label>
                    <input type="text" class="form-control" id="address" name="address" value="{{ $szkolenie->address }}">
                </div>
                <div class="col-md-3">
                    <label for="postal_code" class="form-label">Kod pocztowy</label>
                    <input type="text" class="form-control" id="postal_code" name="postal_code" value="{{ $szkolenie->postal_code }}">
                </div>
                <div class="col-md-3">
                    <label for="post_office" class="form-label">Poczta</label>
                    <input type="text" class="form-control" id="post_office" name="post_office" value="{{ $szkolenie->post_office }}">
                </div>
                <div class="col-md-3">
                    <label for="country" class="form-label">Kraj</label>
                    <input type="text" class="form-control" id="country" name="country" value="{{ $szkolenie->country }}">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
        </form>
    </div>
</x-app-layout>

<script>
    function toggleFormSections() {
        var type = document.getElementById("type").value;
        document.getElementById("online_options").style.display = type === "online" ? "flex" : "none";
        document.getElementById("offline_options").style.display = type === "offline" ? "flex" : "none";
    }

    // Wywołaj funkcję przy załadowaniu strony
    document.addEventListener("DOMContentLoaded", function () {
        toggleFormSections();
    });
</script>
