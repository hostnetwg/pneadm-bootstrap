<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Typy źródeł marketingowych') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Typy źródeł marketingowych</h3>
                <a href="{{ route('marketing-source-types.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Dodaj typ źródła
                </a>
            </div>

            @if($sourceTypes->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Nazwa</th>
                                <th>Slug</th>
                                <th>Opis</th>
                                <th>Kolor</th>
                                <th>Status</th>
                                <th>Kolejność</th>
                                <th>Kampanie</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sourceTypes as $sourceType)
                                <tr>
                                    <td>
                                        <strong>{{ $sourceType->name }}</strong>
                                    </td>
                                    <td>
                                        <code>{{ $sourceType->slug }}</code>
                                    </td>
                                    <td>{{ $sourceType->description ?? '-' }}</td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $sourceType->color }}; color: white;">
                                            {{ $sourceType->color }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($sourceType->is_active)
                                            <span class="badge bg-success">Aktywny</span>
                                        @else
                                            <span class="badge bg-secondary">Nieaktywny</span>
                                        @endif
                                    </td>
                                    <td>{{ $sourceType->sort_order }}</td>
                                    <td>
                                        <span class="badge bg-primary">{{ $sourceType->marketingCampaigns->count() }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('marketing-source-types.show', $sourceType) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Podgląd
                                            </a>
                                            <a href="{{ route('marketing-source-types.edit', $sourceType) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i> Edytuj
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal{{ $sourceType->id }}">
                                                <i class="bi bi-trash"></i> Usuń
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $sourceTypes->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-tags fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Brak typów źródeł</h4>
                    <p class="text-muted">Dodaj pierwszy typ źródła, aby rozpocząć kategoryzację kampanii.</p>
                    <a href="{{ route('marketing-source-types.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Dodaj pierwszy typ źródła
                    </a>
                </div>
            @endif

            {{-- Modale potwierdzenia usunięcia typów źródeł --}}
            @foreach ($sourceTypes as $sourceType)
            <div class="modal fade" id="deleteModal{{ $sourceType->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $sourceType->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel{{ $sourceType->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć typ źródła <strong>#{{ $sourceType->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły typu źródła:</h6>
                                <ul class="mb-0">
                                    <li><strong>Nazwa:</strong> {{ $sourceType->name }}</li>
                                    <li><strong>Kolor:</strong> 
                                        <span class="badge" style="background-color: {{ $sourceType->color }}; color: white;">
                                            {{ $sourceType->color }}
                                        </span>
                                    </li>
                                    <li><strong>Opis:</strong> {{ $sourceType->description ? Str::limit($sourceType->description, 100) : 'Brak' }}</li>
                                    <li><strong>Status:</strong> {{ $sourceType->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                                    <li><strong>Data utworzenia:</strong> {{ $sourceType->created_at ? $sourceType->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Typ źródła zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('marketing-source-types.destroy', $sourceType) }}" 
                                  method="POST" 
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń typ źródła
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
