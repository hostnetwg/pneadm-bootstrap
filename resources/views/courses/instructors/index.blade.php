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
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Zdjęcie</th>
                        <th>Tytuł</th>                        
                        <th>Imię</th>
                        <th>Nazwisko</th>
                        <th>Płeć</th>
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
                                Brak
                            @endif
                        </td>
                        <td>{{ $instructor->title }}</td>                        
                        <td>{{ $instructor->first_name }}</td>
                        <td>{{ $instructor->last_name }}</td>
                        <td>{{ $instructor->gender_label }}</td>
                        <td>{{ $instructor->email }}</td>
                        <td>{{ $instructor->phone }}</td>
                        <td>{{ $instructor->is_active ? 'Tak' : 'Nie' }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('courses.instructors.show', $instructor->id) }}" class="btn btn-info btn-sm" title="Podgląd">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('courses.instructors.edit', $instructor->id) }}" class="btn btn-warning btn-sm" title="Edytuj">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" title="Usuń" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal{{ $instructor->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Dodana paginacja -->
            <div class="d-flex justify-content-center mt-4">
                {{ $instructors->links() }}
            </div>

            {{-- Modale potwierdzenia usunięcia instruktorów --}}
            @foreach ($instructors as $instructor)
            <div class="modal fade" id="deleteModal{{ $instructor->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $instructor->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel{{ $instructor->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć instruktora <strong>#{{ $instructor->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły instruktora:</h6>
                                <ul class="mb-0">
                                    <li><strong>Imię i nazwisko:</strong> {{ $instructor->getFullTitleNameAttribute() }}</li>
                                    <li><strong>Email:</strong> {{ $instructor->email }}</li>
                                    <li><strong>Telefon:</strong> {{ $instructor->phone ?? 'Brak' }}</li>
                                    <li><strong>Płeć:</strong> {{ $instructor->gender_label }}</li>
                                    <li><strong>Status:</strong> {{ $instructor->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                                    <li><strong>Liczba szkoleń:</strong> {{ $instructor->courses()->count() }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Instruktor zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('courses.instructors.destroy', $instructor->id) }}" 
                                  method="POST" 
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń instruktora
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
