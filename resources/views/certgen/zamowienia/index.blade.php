<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Zakupy - Baza Certgen') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Lista zakupów (tabela: zamowienia)</h3>
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
                                        <a href="{{ route('certgen.zamowienia.index', ['sort' => 'id', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
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
                                        <a href="{{ route('certgen.zamowienia.index', ['sort' => 'id_zam', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            ID Zamówienia
                                            @if(request('sort') == 'id_zam')
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
                                        <a href="{{ route('certgen.zamowienia.index', ['sort' => 'data_wplaty', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            Data Wpłaty
                                            @if(request('sort') == 'data_wplaty')
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
                                        <a href="{{ route('certgen.zamowienia.index', ['sort' => 'imie', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-white">
                                            Dane osobowe
                                            @if(request('sort') == 'imie')
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
                                    <th>Adres</th>
                                    <th>Produkt</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($zamowienia as $zamowienie)
                                    <tr>
                                        <td>{{ $zamowienie->id }}</td>
                                        <td>{{ isset($zamowienie->id_zam) ? $zamowienie->id_zam : 'Brak' }}</td>
                                        <td>{{ isset($zamowienie->data_wplaty) ? \Carbon\Carbon::parse($zamowienie->data_wplaty)->format('d.m.Y H:i') : 'Brak' }}</td>
                                        <td>
                                            @if(isset($zamowienie->imie) || isset($zamowienie->nazwisko) || isset($zamowienie->email))
                                                <div class="text-truncate" style="max-width: 250px;" 
                                                     title="Imię: {{ isset($zamowienie->imie) ? $zamowienie->imie : 'Brak' }} | Nazwisko: {{ isset($zamowienie->nazwisko) ? $zamowienie->nazwisko : 'Brak' }} | Email: {{ isset($zamowienie->email) ? $zamowienie->email : 'Brak' }}">
                                                    <strong>{{ isset($zamowienie->imie) ? $zamowienie->imie : 'Brak' }} {{ isset($zamowienie->nazwisko) ? $zamowienie->nazwisko : 'Brak' }}</strong><br>
                                                    <small class="text-primary">{{ isset($zamowienie->email) ? $zamowienie->email : 'Brak email' }}</small>
                                                </div>
                                            @else
                                                <span class="text-muted">Brak danych osobowych</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($zamowienie->kod) || isset($zamowienie->poczta) || isset($zamowienie->adres))
                                                <div class="text-truncate" style="max-width: 250px;" 
                                                     title="Kod: {{ isset($zamowienie->kod) ? $zamowienie->kod : 'Brak' }} | Poczta: {{ isset($zamowienie->poczta) ? $zamowienie->poczta : 'Brak' }} | Adres: {{ isset($zamowienie->adres) ? $zamowienie->adres : 'Brak' }}">
                                                    <strong>{{ isset($zamowienie->kod) ? $zamowienie->kod : 'Brak' }} {{ isset($zamowienie->poczta) ? $zamowienie->poczta : 'Brak' }}</strong><br>
                                                    <small>{{ isset($zamowienie->adres) ? $zamowienie->adres : 'Brak adresu' }}</small>
                                                </div>
                                            @else
                                                <span class="text-muted">Brak danych adresowych</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($zamowienie->produkt_id) || isset($zamowienie->produkt_nazwa) || isset($zamowienie->produkt_cena))
                                                <div class="text-truncate" style="max-width: 250px;" 
                                                     title="ID: {{ isset($zamowienie->produkt_id) ? $zamowienie->produkt_id : 'Brak' }} | Nazwa: {{ isset($zamowienie->produkt_nazwa) ? $zamowienie->produkt_nazwa : 'Brak' }} | Cena: {{ isset($zamowienie->produkt_cena) ? number_format($zamowienie->produkt_cena, 2) . ' zł' : 'Brak' }}">
                                                    <strong>ID: {{ isset($zamowienie->produkt_id) ? $zamowienie->produkt_id : 'Brak' }}</strong><br>
                                                    <small>{{ isset($zamowienie->produkt_nazwa) ? $zamowienie->produkt_nazwa : 'Brak nazwy' }}</small><br>
                                                    <span class="text-success">{{ isset($zamowienie->produkt_cena) ? number_format($zamowienie->produkt_cena, 2) . ' zł' : 'Brak ceny' }}</span>
                                                </div>
                                            @else
                                                <span class="text-muted">Brak danych produktu</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('certgen.zamowienia.show', $zamowienie->id) }}" 
                                                   class="btn btn-sm btn-info" title="Szczegóły">
                                                    👁️
                                                </a>
                                                <form action="{{ route('certgen.zamowienia.destroy', $zamowienie->id) }}" 
                                                      method="POST" class="d-inline"
                                                      onsubmit="return confirm('Czy na pewno chcesz usunąć ten zakup?');">
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
                                                <p>Brak zakupów</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($zamowienia->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $zamowienia->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
