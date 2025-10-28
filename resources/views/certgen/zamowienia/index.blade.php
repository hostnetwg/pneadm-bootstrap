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
                                                <button type="button" class="btn btn-sm btn-danger" title="Usuń"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal{{ $zamowienie->id }}">
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

    {{-- Modale potwierdzenia usunięcia zamówień --}}
    @foreach ($zamowienia as $zamowienie)
    <div class="modal fade" id="deleteModal{{ $zamowienie->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $zamowienie->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $zamowienie->id }}">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć zakup <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły zakupu:</h6>
                        <ul class="mb-0">
                            <li><strong>Imię:</strong> {{ $zamowienie->imie }}</li>
                            <li><strong>Nazwisko:</strong> {{ $zamowienie->nazwisko }}</li>
                            <li><strong>Email:</strong> {{ $zamowienie->email }}</li>
                            <li><strong>Produkt:</strong> {{ $zamowienie->produkt_nazwa }}</li>
                            <li><strong>Cena:</strong> {{ number_format($zamowienie->produkt_cena, 2) }} zł</li>
                            <li><strong>Data wpłaty:</strong> {{ $zamowienie->data_wplaty ? $zamowienie->data_wplaty->format('d.m.Y H:i') : 'Brak' }}</li>
                            <li><strong>Status:</strong> {{ $zamowienie->status ?? 'Nieznany' }}</li>
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
    @endforeach
</x-app-layout>
