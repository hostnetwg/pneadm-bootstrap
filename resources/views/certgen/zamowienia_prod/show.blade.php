<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Szczegóły produktu formularza #{{ $zamowienie->id }}
            </h2>
            <div>
                <a href="{{ route('certgen.zamowienia_prod.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Powrót do listy
                </a>
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Nawigacja między rekordami --}}
            <div class="mb-3 d-flex justify-content-between">
                <div>
                    @if($prevOrder)
                        <a href="{{ route('certgen.zamowienia_prod.show', $prevOrder->id) }}" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i> Poprzedni
                        </a>
                    @else
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-chevron-left"></i> Poprzedni
                        </button>
                    @endif
                </div>
                <div>
                    @if($nextOrder)
                        <a href="{{ route('certgen.zamowienia_prod.show', $nextOrder->id) }}" 
                           class="btn btn-outline-primary">
                            Następny <i class="bi bi-chevron-right"></i>
                        </a>
                    @else
                        <button class="btn btn-outline-secondary" disabled>
                            Następny <i class="bi bi-chevron-right"></i>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Podstawowe informacje --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informacje o produkcie</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%">ID produktu:</th>
                                    <td><strong>#{{ $zamowienie->id }}</strong></td>
                                </tr>
                                <tr>
                                    <th>ID Publigo:</th>
                                    <td>
                                        @if($zamowienie->idProdPubligo)
                                            <code>{{ $zamowienie->idProdPubligo }}</code>
                                        @else
                                            <span class="text-muted">Brak</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>ID Ceny Publigo:</th>
                                    <td>
                                        @if($zamowienie->price_id_ProdPubligo)
                                            <code>{{ $zamowienie->price_id_ProdPubligo }}</code>
                                        @else
                                            <span class="text-muted">Brak</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%">Status:</th>
                                    <td>
                                        @if($zamowienie->status)
                                            <span class="badge bg-{{ $zamowienie->status == 1 ? 'success' : 'secondary' }} fs-6">
                                                {{ $zamowienie->status == 1 ? 'Aktywny' : 'Nieaktywny' }}
                                            </span>
                                        @else
                                            <span class="text-muted">Brak statusu</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Promocja:</th>
                                    <td>
                                        @if($zamowienie->promocja)
                                            <span class="badge bg-warning text-dark">{{ $zamowienie->promocja }}</span>
                                        @else
                                            <span class="text-muted">Brak</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Nazwa produktu --}}
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-box"></i> Nazwa produktu</h5>
                </div>
                <div class="card-body">
                    <h4>{{ $zamowienie->nazwa ?? 'Brak nazwy' }}</h4>
                </div>
            </div>

            {{-- Warianty cenowe --}}
            @php
                $warianty = DB::connection('mysql_certgen')
                    ->table('zamowienia_PROD_warianty')
                    ->where('id_PROD', $zamowienie->id)
                    ->orderBy('lp')
                    ->get();
            @endphp

            @if($warianty->count() > 0)
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-tag"></i> Warianty cenowe ({{ $warianty->count() }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Lp</th>
                                    <th>Opis</th>
                                    <th>Cena</th>
                                    <th>Cena promocyjna</th>
                                    <th>Okres promocji</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($warianty as $wariant)
                                <tr>
                                    <td><strong>{{ $wariant->lp }}</strong></td>
                                    <td>{{ $wariant->opis }}</td>
                                    <td><strong>{{ number_format($wariant->cena, 2, ',', ' ') }} zł</strong></td>
                                    <td>
                                        @if($wariant->cena_prom && $wariant->cena_prom != $wariant->cena)
                                            <strong class="text-danger">{{ number_format($wariant->cena_prom, 2, ',', ' ') }} zł</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($wariant->data_p_start && $wariant->data_p_end)
                                            <small>
                                                {{ \Carbon\Carbon::parse($wariant->data_p_start)->format('d.m.Y H:i') }}
                                                <br>
                                                {{ \Carbon\Carbon::parse($wariant->data_p_end)->format('d.m.Y H:i') }}
                                            </small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $wariant->status == 1 ? 'success' : 'secondary' }}">
                                            {{ $wariant->status == 1 ? 'Aktywny' : 'Nieaktywny' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @else
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Brak wariantów cenowych dla tego produktu.
            </div>
            @endif

            {{-- Surowe dane (dla debugowania) --}}
            <div class="card mb-4">
                <div class="card-header">
                    <button class="btn btn-link text-decoration-none p-0" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#rawDataCollapse">
                        <i class="bi bi-code-square"></i> Pokaż surowe dane (JSON)
                    </button>
                </div>
                <div class="collapse" id="rawDataCollapse">
                    <div class="card-body">
                        <h6>Produkt:</h6>
                        <pre class="bg-light p-3 rounded"><code>{{ json_encode($zamowienie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        
                        @if($warianty->count() > 0)
                        <h6 class="mt-3">Warianty:</h6>
                        <pre class="bg-light p-3 rounded"><code>{{ json_encode($warianty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Przycisk powrotu --}}
            <div class="text-center">
                <a href="{{ route('certgen.zamowienia_prod.index') }}" class="btn btn-secondary btn-lg">
                    <i class="bi bi-arrow-left"></i> Powrót do listy
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
