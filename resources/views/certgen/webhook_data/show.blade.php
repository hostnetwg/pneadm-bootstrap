<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły rekordu webhook') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Rekord ID: {{ $webhookRecord->id }}</h3>
                <div>
                    <a href="{{ route('certgen.webhook_data.index') }}" class="btn btn-secondary">
                        ← Powrót do listy
                    </a>
                    <a href="{{ route('certgen.webhook_data.edit', $webhookRecord->id) }}" class="btn btn-warning">
                        ✏️ Edytuj
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        🗑️ Usuń
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informacje o rekordzie</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $webhookRecord->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ID Produktu:</strong></td>
                                    <td>{{ isset($webhookRecord->id_produktu) ? $webhookRecord->id_produktu : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Data:</strong></td>
                                    <td>{{ isset($webhookRecord->data) ? \Carbon\Carbon::parse($webhookRecord->data)->format('d.m.Y H:i') : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ID Sendy:</strong></td>
                                    <td>{{ isset($webhookRecord->id_sendy) ? $webhookRecord->id_sendy : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ClickMeeting:</strong></td>
                                    <td>{{ isset($webhookRecord->clickmeeting) ? $webhookRecord->clickmeeting : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Temat:</strong></td>
                                    <td>{{ isset($webhookRecord->temat) ? $webhookRecord->temat : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Instruktor:</strong></td>
                                    <td>{{ isset($webhookRecord->instruktor) ? $webhookRecord->instruktor : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Utworzono:</strong></td>
                                    <td>{{ isset($webhookRecord->created_at) ? \Carbon\Carbon::parse($webhookRecord->created_at)->format('d.m.Y H:i:s') : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Zaktualizowano:</strong></td>
                                    <td>{{ isset($webhookRecord->updated_at) ? \Carbon\Carbon::parse($webhookRecord->updated_at)->format('d.m.Y H:i:s') : 'Brak' }}</td>
                                </tr>
                            </table>
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
                    <p>Czy na pewno chcesz usunąć rekord webhook <strong>#{{ $webhookRecord->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły rekordu:</h6>
                        <ul class="mb-0">
                            <li><strong>ID:</strong> {{ $webhookRecord->id }}</li>
                            <li><strong>ID Produktu:</strong> {{ $webhookRecord->id_produktu ?? 'Brak' }}</li>
                            <li><strong>Data:</strong> {{ $webhookRecord->data ? \Carbon\Carbon::parse($webhookRecord->data)->format('d.m.Y H:i') : 'Brak' }}</li>
                            <li><strong>ID Sendy:</strong> {{ $webhookRecord->id_sendy ?? 'Brak' }}</li>
                            <li><strong>ClickMeeting:</strong> {{ $webhookRecord->clickmeeting ?? 'Brak' }}</li>
                            <li><strong>Temat:</strong> {{ $webhookRecord->temat ?? 'Brak' }}</li>
                            <li><strong>Instruktor:</strong> {{ $webhookRecord->instruktor ?? 'Brak' }}</li>
                            <li><strong>Utworzono:</strong> {{ $webhookRecord->created_at ? \Carbon\Carbon::parse($webhookRecord->created_at)->format('d.m.Y H:i:s') : 'Brak' }}</li>
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
                    <form action="{{ route('certgen.webhook_data.destroy', $webhookRecord->id) }}" 
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
</x-app-layout>
