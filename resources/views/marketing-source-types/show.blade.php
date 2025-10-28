<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły typu źródła') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Typ źródła: {{ $marketingSourceType->name }}</h4>
                            <div>
                                <a href="{{ route('marketing-source-types.edit', $marketingSourceType) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i> Edytuj
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i> Usuń
                                </button>
                                <a href="{{ route('marketing-source-types.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i> Powrót
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Podstawowe informacje</h6>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Nazwa:</strong></td>
                                            <td>{{ $marketingSourceType->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Slug:</strong></td>
                                            <td><code>{{ $marketingSourceType->slug }}</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kolor:</strong></td>
                                            <td>
                                                <span class="badge" style="background-color: {{ $marketingSourceType->color }}; color: white;">
                                                    {{ $marketingSourceType->color }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                @if($marketingSourceType->is_active)
                                                    <span class="badge bg-success">Aktywny</span>
                                                @else
                                                    <span class="badge bg-secondary">Nieaktywny</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Kolejność:</strong></td>
                                            <td>{{ $marketingSourceType->sort_order }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>Statystyki</h6>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Liczba kampanii:</strong></td>
                                            <td>
                                                <span class="badge bg-primary">{{ $marketingSourceType->marketingCampaigns->count() }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Utworzony:</strong></td>
                                            <td>{{ $marketingSourceType->created_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ostatnia aktualizacja:</strong></td>
                                            <td>{{ $marketingSourceType->updated_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            @if($marketingSourceType->description)
                                <div class="mt-3">
                                    <h6>Opis</h6>
                                    <p class="text-muted">{{ $marketingSourceType->description }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Kampanie marketingowe</h6>
                        </div>
                        <div class="card-body">
                            @if($marketingSourceType->marketingCampaigns->count() > 0)
                                <div class="list-group">
                                    @foreach($marketingSourceType->marketingCampaigns->take(10) as $campaign)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $campaign->campaign_code }}</strong><br>
                                                <small class="text-muted">{{ Str::limit($campaign->name, 30) }}</small>
                                            </div>
                                            <span class="badge bg-primary">{{ $campaign->formOrders->count() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @if($marketingSourceType->marketingCampaigns->count() > 10)
                                    <div class="text-center mt-2">
                                        <small class="text-muted">
                                            I {{ $marketingSourceType->marketingCampaigns->count() - 10 }} więcej...
                                        </small>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted">Brak kampanii dla tego typu źródła.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal potwierdzenia usunięcia --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć typ źródła <strong>#{{ $marketingSourceType->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły typu źródła:</h6>
                        <ul class="mb-0">
                            <li><strong>Nazwa:</strong> {{ $marketingSourceType->name }}</li>
                            <li><strong>Slug:</strong> {{ $marketingSourceType->slug }}</li>
                            <li><strong>Kolor:</strong> 
                                <span class="badge" style="background-color: {{ $marketingSourceType->color }}; color: white;">
                                    {{ $marketingSourceType->color }}
                                </span>
                            </li>
                            <li><strong>Opis:</strong> {{ $marketingSourceType->description ? Str::limit($marketingSourceType->description, 100) : 'Brak' }}</li>
                            <li><strong>Status:</strong> {{ $marketingSourceType->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                            <li><strong>Data utworzenia:</strong> {{ $marketingSourceType->created_at ? $marketingSourceType->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
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
                    <form action="{{ route('marketing-source-types.destroy', $marketingSourceType) }}" 
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
</x-app-layout>
