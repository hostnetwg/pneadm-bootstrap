<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-trash3 me-2"></i>
                Kosz systemowy (Soft Delete)
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Powrót do Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Statystyki kosza -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bar-chart me-2"></i>
                            Statystyki kosza
                        </h5>
                        <div class="row text-center">
                            @php
                                $totalTrashed = 0;
                                foreach($tableDefinitions as $tableName => $definition) {
                                    $count = $definition['model']::onlyTrashed()->count();
                                    $totalTrashed += $count;
                                }
                            @endphp
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-danger">{{ $totalTrashed }}</h4>
                                    <small class="text-muted">Łącznie usuniętych rekordów</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-warning">{{ count($tableDefinitions) }}</h4>
                                    <small class="text-muted">Tabel z rekordami</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info">{{ $trashRecords->count() }}</h4>
                                    <small class="text-muted">Rekordów na stronie</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success">{{ $trashRecords->total() }}</h4>
                                    <small class="text-muted">Wszystkich rekordów</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtry i wyszukiwanie -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('trash.index') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Wyszukaj</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}" 
                               placeholder="Szukaj w usuniętych rekordach...">
                    </div>
                    <div class="col-md-3">
                        <label for="table" class="form-label">Tabela</label>
                        <select class="form-select" id="table" name="table">
                            <option value="all" {{ $table === 'all' ? 'selected' : '' }}>Wszystkie tabele</option>
                            @foreach($tableDefinitions as $tableName => $definition)
                                <option value="{{ $tableName }}" {{ $table === $tableName ? 'selected' : '' }}>
                                    {{ $definition['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="per_page" class="form-label">Na stronie</label>
                        <select class="form-select" id="per_page" name="per_page">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                            <a href="{{ route('trash.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista usuniętych rekordów -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list me-2"></i>
                    Usunięte rekordy
                    @if($table !== 'all')
                        - {{ $tableDefinitions[$table]['label'] ?? $table }}
                    @endif
                </h5>
                @if($table !== 'all' && isset($tableDefinitions[$table]))
                    <div class="btn-group">
                        <button type="button" 
                                class="btn btn-warning btn-sm"
                                onclick="confirmEmptyTable('{{ $table }}', '{{ $tableDefinitions[$table]['label'] }}')">
                            <i class="bi bi-trash"></i> Opróżnij tabelę
                        </button>
                    </div>
                @endif
                @if($table === 'all')
                    <button type="button" 
                            class="btn btn-danger btn-sm"
                            onclick="confirmEmptyAll()">
                        <i class="bi bi-trash3"></i> Opróżnij cały kosz
                    </button>
                @endif
            </div>
            <div class="card-body p-0">
                @if($trashRecords->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%;">ID</th>
                                    <th style="width: 15%;">Tabela</th>
                                    <th style="width: 35%;">Dane</th>
                                    <th style="width: 15%;">Usunięto</th>
                                    <th style="width: 27%;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trashRecords as $item)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#{{ $item['id'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                {{ $tableDefinitions[$item['table']]['label'] ?? $item['table'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                @foreach($item['display_data'] as $field => $value)
                                                    @if($value)
                                                        <div>
                                                            <strong>{{ $field }}:</strong> 
                                                            {{ Str::limit($value, 50) }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i>
                                                {{ $item['deleted_at']->format('d.m.Y H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <form action="{{ route('trash.restore', [$item['table'], $item['id']]) }}" 
                                                      method="POST" 
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="btn btn-success"
                                                            title="Przywróć rekord"
                                                            onclick="return confirm('Czy na pewno chcesz przywrócić ten rekord?')">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" 
                                                        class="btn btn-danger"
                                                        title="Usuń trwale"
                                                        onclick="confirmForceDelete('{{ $item['table'] }}', '{{ $item['id'] }}')">
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
                    <div class="text-center py-5">
                        <i class="bi bi-trash3 text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">Kosz jest pusty</h5>
                        <p class="text-muted">
                            @if($search || $table !== 'all')
                                Nie znaleziono rekordów spełniających kryteria wyszukiwania.
                            @else
                                Brak usuniętych rekordów w systemie.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Paginacja -->
        @if($trashRecords->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $trashRecords->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    <!-- Modal potwierdzenia trwałego usunięcia -->
    <div class="modal fade" id="forceDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Potwierdź trwałe usunięcie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>UWAGA!</strong> Ta operacja jest nieodwracalna!</p>
                    <p>Czy na pewno chcesz trwale usunąć ten rekord?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <form id="forceDeleteForm" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń trwale
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmForceDelete(table, id) {
            const form = document.getElementById('forceDeleteForm');
            form.action = `/trash/force-delete/${table}/${id}`;
            
            const modal = new bootstrap.Modal(document.getElementById('forceDeleteModal'));
            modal.show();
        }

        function confirmEmptyTable(table, label) {
            if (confirm(`Czy na pewno chcesz opróżnić kosz dla tabeli "${label}"?\n\nTa operacja jest nieodwracalna!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/trash/empty-table/${table}`;
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmEmptyAll() {
            if (confirm('Czy na pewno chcesz opróżnić cały kosz?\n\nTa operacja jest nieodwracalna i usunie WSZYSTKIE usunięte rekordy!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/trash/empty-all';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</x-app-layout>


