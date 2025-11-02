<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły zakupu') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Zakup ID: {{ $zamowienie->id }}</h3>
                <div>
                    <a href="{{ route('certgen.zamowienia.edit', $zamowienie->id) }}" 
                       class="btn btn-warning">
                        ✏️ Edytuj
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        🗑️ Usuń
                    </button>
                </div>
            </div>

            <!-- Przyciski nawigacyjne -->
            <div class="d-flex justify-content-center mb-4">
                <div class="btn-group" role="group">
                    @if($previous)
                        <a href="{{ route('certgen.zamowienia.show', $previous->id) }}" class="btn btn-outline-primary">
                            ← Poprzedni (ID: {{ $previous->id }})
                        </a>
                    @else
                        <button class="btn btn-outline-secondary" disabled>
                            ← Poprzedni
                        </button>
                    @endif
                    
                    <a href="{{ route('certgen.zamowienia.index') }}" class="btn btn-primary">
                        📋 Lista
                    </a>
                    
                    @if($next)
                        <a href="{{ route('certgen.zamowienia.show', $next->id) }}" class="btn btn-outline-primary">
                            Następny (ID: {{ $next->id }}) →
                        </a>
                    @else
                        <button class="btn btn-outline-secondary" disabled>
                            Następny →
                        </button>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informacje o zakupie</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $zamowienie->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ID Zamówienia:</strong></td>
                                    <td>{{ isset($zamowienie->id_zam) ? $zamowienie->id_zam : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Data Wpłaty:</strong></td>
                                    <td>{{ isset($zamowienie->data_wplaty) ? \Carbon\Carbon::parse($zamowienie->data_wplaty)->format('d.m.Y H:i:s') : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Imię:</strong></td>
                                    <td>{{ isset($zamowienie->imie) ? $zamowienie->imie : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Nazwisko:</strong></td>
                                    <td>{{ isset($zamowienie->nazwisko) ? $zamowienie->nazwisko : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>{{ isset($zamowienie->email) ? $zamowienie->email : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Kod:</strong></td>
                                    <td>{{ isset($zamowienie->kod) ? $zamowienie->kod : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Poczta:</strong></td>
                                    <td>{{ isset($zamowienie->poczta) ? $zamowienie->poczta : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Adres:</strong></td>
                                    <td>{{ isset($zamowienie->adres) ? $zamowienie->adres : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ID Produktu:</strong></td>
                                    <td>{{ isset($zamowienie->produkt_id) ? $zamowienie->produkt_id : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Nazwa Produktu:</strong></td>
                                    <td>{{ isset($zamowienie->produkt_nazwa) ? $zamowienie->produkt_nazwa : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Cena Produktu:</strong></td>
                                    <td>{{ isset($zamowienie->produkt_cena) ? number_format($zamowienie->produkt_cena, 2) . ' zł' : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Wysyłka:</strong></td>
                                    <td>{{ isset($zamowienie->wysylka) ? $zamowienie->wysylka : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>ID Edu:</strong></td>
                                    <td>{{ isset($zamowienie->id_edu) ? $zamowienie->id_edu : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>NR:</strong></td>
                                    <td>{{ isset($zamowienie->NR) ? $zamowienie->NR : 'Brak' }}</td>
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
                    <p>Czy na pewno chcesz usunąć zakup <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły zakupu:</h6>
                        <ul class="mb-0">
                            <li><strong>Imię:</strong> {{ $zamowienie->imie ?? 'Brak' }}</li>
                            <li><strong>Nazwisko:</strong> {{ $zamowienie->nazwisko ?? 'Brak' }}</li>
                            <li><strong>Email:</strong> {{ $zamowienie->email ?? 'Brak' }}</li>
                            <li><strong>Produkt:</strong> {{ $zamowienie->produkt_nazwa ?? 'Brak' }}</li>
                            <li><strong>Cena:</strong> {{ $zamowienie->produkt_cena ? number_format($zamowienie->produkt_cena, 2) . ' zł' : 'Brak' }}</li>
                            <li><strong>Data wpłaty:</strong> {{ $zamowienie->data_wplaty ? \Carbon\Carbon::parse($zamowienie->data_wplaty)->format('d.m.Y H:i') : 'Brak' }}</li>
                            <li><strong>Adres:</strong> {{ $zamowienie->adres ?? 'Brak' }}</li>
                            <li><strong>Poczta:</strong> {{ $zamowienie->poczta ?? 'Brak' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Zakup zostanie trwale usunięty z systemu. Ta operacja jest nieodwracalna!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('certgen.zamowienia.destroy', $zamowienie->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń zakup
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
