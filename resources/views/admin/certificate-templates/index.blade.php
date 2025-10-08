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
                                        <form action="{{ route('admin.certificate-templates.clone', $template) }}" 
                                              method="POST" 
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-secondary"
                                                    title="Klonuj">
                                                <i class="bi bi-files"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.certificate-templates.destroy', $template) }}" 
                                              method="POST" 
                                              class="d-inline"
                                              onsubmit="return confirm('Czy na pewno chcesz usunąć ten szablon?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger"
                                                    title="Usuń">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
</x-app-layout>

