@php
    $mediumLabel = $utmMediumOptions[$marketingSourceType->default_utm_medium] ?? $marketingSourceType->default_utm_medium;
@endphp
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
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="mb-0">Typ źródła: {{ $marketingSourceType->name }}</h4>
                            <div class="d-flex flex-wrap gap-1">
                                @if($marketingSourceType->marketingCampaigns->count() > 0)
                                    <a href="{{ route('marketing-campaigns.index', ['source_type_id' => $marketingSourceType->id]) }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-megaphone"></i> Kampanie ({{ $marketingSourceType->marketingCampaigns->count() }})
                                    </a>
                                @endif
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
                                    <table class="table table-borderless table-sm">
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
                                                    <span class="badge bg-secondary">Wyłączony</span>
                                                    <span class="small text-muted d-block">Nie pojawia się przy tworzeniu nowej kampanii</span>
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
                                    <h6>Parametry UTM (generator linków)</h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td><strong><code>utm_source</code>:</strong></td>
                                            <td>
                                                @if(filled($marketingSourceType->utm_source))
                                                    <code>{{ $marketingSourceType->utm_source }}</code>
                                                @else
                                                    <span class="text-warning">Brak — uzupełnij w edycji</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><code>utm_medium</code>:</strong></td>
                                            <td>
                                                <code>{{ $marketingSourceType->default_utm_medium }}</code>
                                                <span class="text-muted">({{ $mediumLabel }})</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><code>utm_content</code>:</strong></td>
                                            <td>
                                                @if(filled($marketingSourceType->default_utm_content))
                                                    <code>{{ $marketingSourceType->default_utm_content }}</code>
                                                @else
                                                    <span class="text-muted">— (ustaw w kampanii, np. cta-hero)</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><code>utm_campaign</code>:</strong></td>
                                            <td class="text-muted">Z <a href="{{ route('marketing-campaigns.index', ['source_type_id' => $marketingSourceType->id]) }}">kampanii</a> — pole „Kod kampanii”</td>
                                        </tr>
                                    </table>
                                    <p class="small text-muted mb-0">Typ źródła dostarcza domyślne UTM; kampania może nadpisać <code>utm_medium</code> i <code>utm_content</code>.</p>
                                </div>
                            </div>

                            @if($marketingSourceType->description)
                                <div class="mt-3">
                                    <h6>Opis</h6>
                                    <p class="text-muted mb-0">{{ $marketingSourceType->description }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Statystyki (cała historia)</h6></div>
                        <div class="card-body">
                            <div class="row text-center g-3">
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <div class="fs-4 fw-bold text-primary">{{ $marketingSourceType->marketingCampaigns->count() }}</div>
                                        <div class="small text-muted">Kampanii</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <div class="fs-4 fw-bold text-success">{{ $marketingSourceType->form_orders_count ?? 0 }}</div>
                                        <div class="small text-muted">Zamówień (przez kampanie)</div>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mt-3 mb-0">
                                Zamówienia w wybranym okresie: <a href="{{ route('marketing-funnel.index', ['source_type_id' => $marketingSourceType->id]) }}">Lejek konwersji</a> (filtr typu źródła).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Kampanie</h6>
                            <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-sm btn-outline-primary">+ Nowa</a>
                        </div>
                        <div class="card-body">
                            @if($marketingSourceType->marketingCampaigns->count() > 0)
                                <div class="list-group list-group-flush">
                                    @foreach($marketingSourceType->marketingCampaigns->take(15) as $campaign)
                                        <a href="{{ route('marketing-campaigns.show', $campaign) }}"
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                            <div>
                                                <strong><code>{{ $campaign->campaign_code }}</code></strong><br>
                                                <small class="text-muted">{{ Str::limit($campaign->name, 40) }}</small>
                                            </div>
                                            <span class="badge bg-primary" title="Zamówienia — cała historia">{{ $campaign->form_orders_count }}</span>
                                        </a>
                                    @endforeach
                                </div>
                                @if($marketingSourceType->marketingCampaigns->count() > 15)
                                    <div class="text-center mt-2">
                                        <a href="{{ route('marketing-campaigns.index', ['source_type_id' => $marketingSourceType->id]) }}" class="small">
                                            Wszystkie {{ $marketingSourceType->marketingCampaigns->count() }} kampanii →
                                        </a>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted mb-0">Brak kampanii dla tego typu źródła.</p>
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
                            <li><strong>utm_source:</strong> {{ $marketingSourceType->utm_source ?: '—' }}</li>
                            <li><strong>utm_medium:</strong> {{ $marketingSourceType->default_utm_medium }}</li>
                            <li><strong>Status:</strong> {{ $marketingSourceType->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Nie można usunąć typu używanego przez kampanie.
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
