<!-- resources/views/courses/series/index.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Serie szkoleń') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <h1>Lista serii szkoleń</h1>

            <!-- Komunikat o sukcesie -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Przycisk dodawania nowej serii -->
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('courses.series.create') }}" class="btn btn-primary">
                    Dodaj serię
                </a>
            </div>

            <!-- Tabela z listą serii -->
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Kolejność</th>
                        <th>Nazwa Serii</th>
                        <th>Liczba kursów</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($series as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->sort_order }}</td>
                        <td>
                            <div class="fw-bold">{{ $item->name }}</div>
                            @if($item->description)
                                <small class="text-muted">{{ Str::limit($item->description, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ $item->courses_count }}</span>
                        </td>
                        <td>
                            @if($item->is_active)
                                <span class="badge bg-success">Aktywna</span>
                            @else
                                <span class="badge bg-danger">Nieaktywna</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('courses.series.show', $item) }}" class="btn btn-info btn-sm" title="Podgląd">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('courses.series.edit', $item) }}" class="btn btn-warning btn-sm" title="Edytuj">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" title="Usuń" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal{{ $item->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            Brak zdefiniowanych serii. <a href="{{ route('courses.series.create') }}">Dodaj pierwszą serię.</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Modale potwierdzenia usunięcia serii --}}
            @foreach ($series as $item)
            <div class="modal fade" id="deleteModal{{ $item->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $item->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel{{ $item->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć serię <strong>#{{ $item->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły serii:</h6>
                                <ul class="mb-0">
                                    <li><strong>Nazwa:</strong> {{ $item->name }}</li>
                                    <li><strong>Slug:</strong> {{ $item->slug }}</li>
                                    <li><strong>Status:</strong> {{ $item->is_active ? 'Aktywna' : 'Nieaktywna' }}</li>
                                    <li><strong>Kolejność:</strong> {{ $item->sort_order }}</li>
                                    <li><strong>Liczba kursów:</strong> {{ $item->courses_count }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Seria zostanie przeniesiona do kosza (soft delete) i będzie można ją przywrócić.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('courses.series.destroy', $item) }}" 
                                  method="POST" 
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń serię
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
