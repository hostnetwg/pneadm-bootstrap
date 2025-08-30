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
                    <form action="{{ route('certgen.zamowienia.destroy', $zamowienie->id) }}" 
                          method="POST" class="d-inline"
                          onsubmit="return confirm('Czy na pewno chcesz usunąć ten zakup?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            🗑️ Usuń
                        </button>
                    </form>
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
                                <tr>
                                    <td><strong>Utworzono:</strong></td>
                                    <td>{{ isset($zamowienie->created_at) ? \Carbon\Carbon::parse($zamowienie->created_at)->format('d.m.Y H:i:s') : 'Brak' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Zaktualizowano:</strong></td>
                                    <td>{{ isset($zamowienie->updated_at) ? \Carbon\Carbon::parse($zamowienie->updated_at)->format('d.m.Y H:i:s') : 'Brak' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
