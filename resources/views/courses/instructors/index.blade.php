<!-- resources/views/courses/instructors/index.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Instruktorzy') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Lista instruktorów</h1>

            <!-- Komunikat o sukcesie -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Przycisk dodawania nowego instruktora -->
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('courses.instructors.create') }}" class="btn btn-primary">
                    Dodaj instruktora
                </a>
            </div>

            <!-- Tabela z listą instruktorów -->
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Zdjęcie</th>
                        <th>Imię</th>
                        <th>Nazwisko</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Aktywny</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instructors as $instructor)
                    <tr>
                        <td>{{ $instructor->id }}</td>
                        <td>
                            @if ($instructor->photo)
                                <img src="{{ asset('storage/' . $instructor->photo) }}" alt="Zdjęcie" width="50">
                            @else
                                Brak zdjęcia
                            @endif
                        </td>
                        <td>{{ $instructor->first_name }}</td>
                        <td>{{ $instructor->last_name }}</td>
                        <td>{{ $instructor->email }}</td>
                        <td>{{ $instructor->phone }}</td>
                        <td>{{ $instructor->is_active ? 'Tak' : 'Nie' }}</td>
                        <td>
                            <a href="{{ route('courses.instructors.edit', $instructor->id) }}" class="btn btn-warning btn-sm">Edytuj</a>
                            <!-- Formularz usuwania -->
                            <form action="{{ route('courses.instructors.destroy', $instructor->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Czy na pewno chcesz usunąć?')">Usuń</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Dodana paginacja -->
            <div class="d-flex justify-content-center mt-4">
                {{ $instructors->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
