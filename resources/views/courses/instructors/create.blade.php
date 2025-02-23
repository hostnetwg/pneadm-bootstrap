<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Dodaj nowego instruktora
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Dodaj nowego instruktora</h1>

            <!-- Komunikat o błędach -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('courses.instructors.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="first_name" class="form-label">Imię</label>
                    <input type="text" name="first_name" class="form-control" id="first_name" required>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Nazwisko</label>
                    <input type="text" name="last_name" class="form-control" id="last_name" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" id="email" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-control" id="phone">
                </div>
                <div class="mb-3">
                    <label for="bio" class="form-label">Biografia</label>
                    <textarea name="bio" class="form-control" id="bio" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">Zdjęcie</label>
                    <input type="file" name="photo" class="form-control" id="photo">
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>
                <button type="submit" class="btn btn-success">Dodaj instruktora</button>
            </form>
        </div>
    </div>
</x-app-layout>
