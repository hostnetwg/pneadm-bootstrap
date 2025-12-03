<x-app-layout>
    <x-slot name="header">
        Szablony Certyfikatów
    </x-slot>

    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Zarządzanie Szablonami
            </h3>
            <a href="{{ route('admin.certificate-templates.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Nowy Szablon
            </a>
        </div>

        @if($templates->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nazwa</th>
                            <th>Slug</th>
                            <th>Opis</th>
                            <th>Status</th>
                            <th>Domyślny</th>
                            <th>Plik</th>
                            <th>Utworzono</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            <tr>
                                <td>{{ $template->id }}</td>
                                <td>
                                    <strong>{{ $template->name }}</strong>
                                </td>
                                <td>
                                    <code>{{ $template->slug }}</code>
                                </td>
                                <td>{{ Str::limit($template->description, 50) }}</td>
                                <td>
                                    @if($template->is_active)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Aktywny
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-dash-circle"></i> Nieaktywny
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($template->is_default ?? false)
                                        <span class="badge bg-primary">
                                            <i class="bi bi-star-fill"></i> Domyślny
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($template->bladeFileExists())
                                        <span class="badge bg-success">
                                            <i class="bi bi-file-earmark-check"></i> Istnieje
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="bi bi-file-earmark-x"></i> Brak pliku
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $template->created_at->format('d.m.Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.certificate-templates.preview', $template) }}" 
                                           class="btn btn-sm btn-info" 
                                           target="_blank"
                                           title="Podgląd">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.certificate-templates.edit', $template) }}" 
                                           class="btn btn-sm btn-warning"
                                           title="Edytuj">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('admin.certificate-templates.clone', $template) }}" 
                                           class="btn btn-sm btn-secondary"
                                           title="Klonuj">
                                            <i class="bi bi-files"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger"
                                                title="Usuń"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal{{ $template->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Nie znaleziono żadnych szablonów. 
                <a href="{{ route('admin.certificate-templates.create') }}" class="alert-link">Utwórz pierwszy szablon</a>.
            </div>
        @endif

        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>Informacje
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Lokalizacja plików:</strong> <code>resources/views/certificates/</code></p>
                    <p class="mb-0"><strong>Liczba szablonów:</strong> {{ $templates->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Modale potwierdzenia usunięcia szablonów --}}
    @foreach ($templates as $template)
    <div class="modal fade" id="deleteModal{{ $template->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $template->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $template->id }}">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @php
                        $coursesUsing = $template->courses()->get();
                    @endphp
                    
                    @if($coursesUsing->count() > 0)
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Uwaga!</strong> Ten szablon jest używany przez {{ $coursesUsing->count() }} {{ $coursesUsing->count() === 1 ? 'szkolenie' : 'szkoleń' }}.
                            Nie można go usunąć dopóki nie zmienisz szablonu w tych szkoleniach.
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="mb-2">Szkolenia używające tego szablonu:</h6>
                            <ul class="list-group">
                                @foreach($coursesUsing as $course)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $course->title }}</strong>
                                            <br>
                                            <small class="text-muted">ID: {{ $course->id }} | Data: {{ $course->start_date ? $course->start_date->format('d.m.Y') : 'Brak' }}</small>
                                        </div>
                                        <a href="{{ route('courses.edit', $course->id) }}" 
                                           class="btn btn-sm btn-primary"
                                           target="_blank">
                                            <i class="bi bi-pencil"></i> Edytuj
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Kliknij "Edytuj" przy każdym szkoleniu, aby zmienić szablon na inny lub ustawić "Domyślny szablon".
                        </div>
                    @else
                        <p>Czy na pewno chcesz usunąć szablon <strong>#{{ $template->id }}</strong>?</p>
                        <div class="bg-light p-3 rounded">
                            <h6 class="mb-2">Szczegóły szablonu:</h6>
                            <ul class="mb-0">
                                <li><strong>Nazwa:</strong> {{ $template->name }}</li>
                                <li><strong>Slug:</strong> {{ $template->slug }}</li>
                                <li><strong>Opis:</strong> {{ $template->description ? Str::limit($template->description, 100) : 'Brak' }}</li>
                                <li><strong>Status:</strong> {{ $template->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                                <li><strong>Plik szablonu:</strong> {{ $template->bladeFileExists() ? 'Istnieje' : 'Brak pliku' }}</li>
                                <li><strong>Data utworzenia:</strong> {{ $template->created_at->format('d.m.Y H:i') }}</li>
                            </ul>
                        </div>
                        <p class="text-muted mt-3">
                            <i class="bi bi-info-circle"></i>
                            Szablon zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    @if($coursesUsing->count() === 0)
                        <form action="{{ route('admin.certificate-templates.destroy', $template) }}" 
                              method="POST" 
                              class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Usuń szablon
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-danger" disabled>
                            <i class="bi bi-trash"></i> Nie można usunąć
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</x-app-layout>

