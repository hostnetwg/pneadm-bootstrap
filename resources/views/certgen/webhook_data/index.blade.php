<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dane dla webhook - Baza Certgen') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Lista danych webhook (tabela: webhook_publigo)</h3>
                <div>
                    <a href="{{ route('certgen.webhook_data.create') }}" class="btn btn-success">
                        ➕ Dodaj nowy rekord
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>
                                        <a href="{{ route('certgen.webhook_data.index', ['sort' => 'id', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            ID
                                            @if(request('sort') == 'id')
                                                @if(request('order') == 'asc')
                                                    🔼
                                                @else
                                                    🔽
                                                @endif
                                            @else
                                                🔽
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('certgen.webhook_data.index', ['sort' => 'id_produktu', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            ID Produktu
                                            @if(request('sort') == 'id_produktu')
                                                @if(request('order') == 'asc')
                                                    🔼
                                                @else
                                                    🔽
                                                @endif
                                            @else
                                                🔽
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('certgen.webhook_data.index', ['sort' => 'data', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            Data
                                            @if(request('sort') == 'data')
                                                @if(request('order') == 'asc')
                                                    🔼
                                                @else
                                                    🔽
                                                @endif
                                            @else
                                                🔽
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('certgen.webhook_data.index', ['sort' => 'id_sendy', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            ID Sendy
                                            @if(request('sort') == 'id_sendy')
                                                @if(request('order') == 'asc')
                                                    🔼
                                                @else
                                                    🔽
                                                @endif
                                            @else
                                                🔽
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ route('certgen.webhook_data.index', ['sort' => 'clickmeeting', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            ClickMeeting
                                            @if(request('sort') == 'clickmeeting')
                                                @if(request('order') == 'asc')
                                                    🔼
                                                @else
                                                    🔽
                                                @endif
                                            @else
                                                🔽
                                            @endif
                                        </a>
                                    </th>
                                    <th>Temat</th>
                                    <th>Instruktor</th>

                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($webhookData as $record)
                                    <tr>
                                        <td>{{ $record->id }}</td>
                                        <td>{{ isset($record->id_produktu) ? $record->id_produktu : 'Brak' }}</td>
                                        <td>{{ isset($record->data) ? \Carbon\Carbon::parse($record->data)->format('d.m.Y H:i') : 'Brak' }}</td>
                                        <td>{{ isset($record->id_sendy) ? $record->id_sendy : 'Brak' }}</td>
                                        <td>{{ isset($record->clickmeeting) ? $record->clickmeeting : 'Brak' }}</td>
                                        <td>
                                            @if(isset($record->temat) && $record->temat)
                                                <div class="text-truncate" style="max-width: 200px;" title="{{ $record->temat }}">
                                                    {{ $record->temat }}
                                                </div>
                                            @else
                                                <span class="text-muted">Brak tematu</span>
                                            @endif
                                        </td>
                                        <td>{{ isset($record->instruktor) ? $record->instruktor : 'Brak' }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('certgen.webhook_data.show', $record->id) }}" 
                                                   class="btn btn-sm btn-info" title="Szczegóły">
                                                    👁️
                                                </a>
                                                <a href="{{ route('certgen.webhook_data.edit', $record->id) }}" 
                                                   class="btn btn-sm btn-warning" title="Edytuj">
                                                    ✏️
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        title="Usuń"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal{{ $record->id }}">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                📭
                                                <p>Brak danych webhook</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($webhookData->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $webhookData->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modale potwierdzenia usunięcia --}}
    @foreach ($webhookData as $record)
    <div class="modal fade" id="deleteModal{{ $record->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $record->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $record->id }}">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć rekord webhook <strong>#{{ $record->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły rekordu:</h6>
                        <ul class="mb-0">
                            <li><strong>ID:</strong> {{ $record->id }}</li>
                            <li><strong>ID Produktu:</strong> {{ $record->id_produktu ?? 'Brak' }}</li>
                            <li><strong>Data:</strong> {{ $record->data ? \Carbon\Carbon::parse($record->data)->format('d.m.Y H:i') : 'Brak' }}</li>
                            <li><strong>ID Sendy:</strong> {{ $record->id_sendy ?? 'Brak' }}</li>
                            <li><strong>ClickMeeting:</strong> {{ $record->clickmeeting ?? 'Brak' }}</li>
                            <li><strong>Temat:</strong> {{ $record->temat ?? 'Brak' }}</li>
                            <li><strong>Instruktor:</strong> {{ $record->instruktor ?? 'Brak' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Rekord zostanie trwale usunięty z bazy danych. Ta operacja jest nieodwracalna!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('certgen.webhook_data.destroy', $record->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń rekord
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</x-app-layout>
