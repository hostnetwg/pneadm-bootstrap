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
                    <form action="{{ route('certgen.webhook_data.destroy', $webhookRecord->id) }}" 
                          method="POST" class="d-inline"
                          onsubmit="return confirm('Czy na pewno chcesz usunąć ten rekord?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            🗑️ Usuń
                        </button>
                    </form>
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
</x-app-layout>
