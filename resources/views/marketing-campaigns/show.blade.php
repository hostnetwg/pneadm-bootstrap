<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły kampanii marketingowej') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Kampania: {{ $marketingCampaign->campaign_code }}</h4>
                            <div>
                                <a href="{{ route('marketing-campaigns.edit', $marketingCampaign) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i> Edytuj
                                </a>
                                <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i> Powrót
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Informacje podstawowe</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Kod kampanii:</strong></td>
                                            <td><code>{{ $marketingCampaign->campaign_code }}</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nazwa:</strong></td>
                                            <td>{{ $marketingCampaign->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Typ źródła:</strong></td>
                                            <td>
                                                @if($marketingCampaign->sourceType)
                                                    <span class="badge" style="background-color: {{ $marketingCampaign->sourceType->color }}; color: white;">
                                                        {{ $marketingCampaign->sourceType->name }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">Nieznany</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                @if($marketingCampaign->is_active)
                                                    <span class="badge bg-success">Aktywna</span>
                                                @else
                                                    <span class="badge bg-secondary">Nieaktywna</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Utworzona:</strong></td>
                                            <td>{{ $marketingCampaign->created_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Zaktualizowana:</strong></td>
                                            <td>{{ $marketingCampaign->updated_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    @if($marketingCampaign->description)
                                        <h5>Opis</h5>
                                        <p>{{ $marketingCampaign->description }}</p>
                                    @endif
                                    
                                    <h5>Statystyki</h5>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body">
                                                    <h3>{{ $marketingCampaign->formOrders->count() }}</h3>
                                                    <p class="mb-0">Zamówień</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-success text-white">
                                                <div class="card-body">
                                                    <h3>{{ $marketingCampaign->formOrders->where('status_completed', 1)->count() }}</h3>
                                                    <p class="mb-0">Zakończonych</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($formOrders->count() > 0)
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Zamówienia z tej kampanii ({{ $formOrders->total() }})</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Data zamówienia</th>
                                                <th>Uczestnik</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($formOrders as $order)
                                                <tr>
                                                    <td>{{ $order->id }}</td>
                                                    <td>{{ $order->order_date ? $order->order_date->format('d.m.Y H:i') : '—' }}</td>
                                                    <td>
                                                        @if($order->primaryParticipant)
                                                            {{ $order->primaryParticipant->first_name }} {{ $order->primaryParticipant->last_name }}
                                                        @else
                                                            {{ $order->first_name }} {{ $order->last_name }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($order->primaryParticipant)
                                                            {{ $order->primaryParticipant->email }}
                                                        @else
                                                            {{ $order->email }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($order->status_completed)
                                                            <span class="badge bg-success">Zakończone</span>
                                                        @else
                                                            <span class="badge bg-warning">W trakcie</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Podgląd
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    {{ $formOrders->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-inbox fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">Brak zamówień</h4>
                                <p class="text-muted">Ta kampania nie ma jeszcze żadnych zamówień.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
