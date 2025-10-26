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
                                            <form action="{{ route('marketing-source-types.destroy', $sourceType) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz usunąć ten typ źródła?')">
                                                    <i class="bi bi-trash"></i> Usuń
                                                </button>
                                            </form>
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
        </div>
    </div>
</x-app-layout>
