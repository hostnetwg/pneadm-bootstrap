<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły zamówienia') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Breadcrumb --}}
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('sales.index') }}">Zamówienia</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Zamówienie #{{ $zamowienie->id }}
                    </li>
                </ol>
            </nav>

            {{-- Przyciski akcji --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Zamówienie #{{ $zamowienie->id }}</h2>
                <div class="btn-group" role="group">
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Powrót do listy
                    </a>
                </div>
            </div>

            {{-- Status zamówienia --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Zamówienie #{{ $zamowienie->id }}</strong>
                        @if($zamowienie->data_zamowienia)
                            <br>Data zamówienia: {{ \Carbon\Carbon::parse($zamowienie->data_zamowienia)->format('d.m.Y H:i') }}
                        @endif
                    </div>
                </div>
            </div>

            {{-- Szczegóły zamówienia --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person"></i> Dane kontaktowe
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Imię i nazwisko:</strong></td>
                                    <td>{{ $zamowienie->konto_imie_nazwisko ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>
                                        @if($zamowienie->konto_email)
                                            <a href="mailto:{{ $zamowienie->konto_email }}">{{ $zamowienie->konto_email }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Telefon:</strong></td>
                                    <td>
                                        @if($zamowienie->zam_tel)
                                            <a href="tel:{{ $zamowienie->zam_tel }}">{{ $zamowienie->zam_tel }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Adres zamówienia:</strong></td>
                                    <td>
                                        {{ $zamowienie->zam_nazwa ?? '—' }}<br>
                                        {{ $zamowienie->zam_adres ?? '—' }}<br>
                                        {{ $zamowienie->zam_kod ?? '—' }} {{ $zamowienie->zam_poczta ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Adres nabywcy:</strong></td>
                                    <td>
                                        {{ $zamowienie->nab_nazwa ?? '—' }}<br>
                                        {{ $zamowienie->nab_adres ?? '—' }}<br>
                                        {{ $zamowienie->nab_kod ?? '—' }} {{ $zamowienie->nab_poczta ?? '—' }}<br>
                                        NIP: {{ $zamowienie->nab_nip ?? '—' }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-cart"></i> Szczegóły zamówienia
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Data zamówienia:</strong></td>
                                    <td>
                                        @if($zamowienie->data_zamowienia)
                                            {{ \Carbon\Carbon::parse($zamowienie->data_zamowienia)->format('d.m.Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Produkt:</strong></td>
                                    <td>{{ $zamowienie->produkt_nazwa ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Cena:</strong></td>
                                    <td>
                                        @if($zamowienie->produkt_cena)
                                            <strong>{{ number_format($zamowienie->produkt_cena, 2) }} zł</strong>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>ID produktu:</strong></td>
                                    <td>{{ $zamowienie->produkt_id ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Numer faktury:</strong></td>
                                    <td>{{ $zamowienie->nr_fakury ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>IP klienta:</strong></td>
                                    <td>{{ $zamowienie->ip ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Notatki:</strong></td>
                                    <td>
                                        @if($zamowienie->notatki)
                                            <div class="alert alert-info">
                                                <i class="bi bi-sticky"></i> {{ $zamowienie->notatki }}
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dodatkowe informacje --}}
            @if($zamowienie->produkt_opis || $zamowienie->faktura_uwagi)
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-chat-text"></i> Dodatkowe informacje
                                </h5>
                            </div>
                            <div class="card-body">
                                @if($zamowienie->produkt_opis)
                                    <h6>Opis produktu:</h6>
                                    <p class="text-muted">{{ $zamowienie->produkt_opis }}</p>
                                @endif
                                
                                @if($zamowienie->faktura_uwagi)
                                    <h6>Uwagi do faktury:</h6>
                                    <p class="text-muted">{{ $zamowienie->faktura_uwagi }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
