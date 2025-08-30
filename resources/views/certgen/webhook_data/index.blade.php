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
                                                <form action="{{ route('certgen.webhook_data.destroy', $record->id) }}" 
                                                      method="POST" class="d-inline"
                                                      onsubmit="return confirm('Czy na pewno chcesz usunąć ten rekord?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Usuń">
                                                        🗑️
                                                    </button>
                                                </form>
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
</x-app-layout>
