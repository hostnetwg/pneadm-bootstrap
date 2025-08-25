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
                <h2 class="@if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0) text-danger @elseif($zamowienie->status_zakonczone == 1) text-secondary @else text-success @endif">Zamówienie #{{ $zamowienie->id }}</h2>
                <div class="btn-group" role="group">
                    <a href="{{ $prevOrder ? route('sales.show', $prevOrder->id) : '#' }}" 
                       class="btn {{ $prevOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                       title="{{ $prevOrder ? 'Poprzednie zamówienie' : 'Brak poprzedniego zamówienia' }}"
                       @if(!$prevOrder) onclick="return false;" @endif>
                        <i class="bi bi-chevron-left"></i> Poprzednie
                    </a>
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-list"></i> Lista
                    </a>
                    <a href="{{ $nextOrder ? route('sales.show', $nextOrder->id) : '#' }}" 
                       class="btn {{ $nextOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                       title="{{ $nextOrder ? 'Następne zamówienie' : 'Brak następnego zamówienia' }}"
                       @if(!$nextOrder) onclick="return false;" @endif>
                        Następne <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>

            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Status zamówienia --}}
            @if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0)
                <div class="text-center mb-3">
                    <small class="text-danger fw-bold">ZAMÓWIENIE OCZEKUJE NA WYSTAWIENIE FAKTURY!</small>
                </div>
            @endif

            {{-- SZKOLENIE - kompaktowe --}}
            <div class="card mb-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event"></i> {{ $zamowienie->produkt_nazwa ?? '—' }}
                        @if($zamowienie->produkt_cena)
                            <span class="badge bg-success ms-2 fs-6">
                                {{ number_format($zamowienie->produkt_cena, 2) }} PLN
                            </span>
                        @endif
                    </h5>
                </div>
            </div>

            {{-- Dane kontaktowe w dwóch kolumnach --}}
            <div class="row">
                {{-- Lewa kolumna: Dane do faktury, Uczestnik --}}
                <div class="col-md-6">
                    {{-- DANE DO FAKTURY - kompaktowe --}}
                    <div class="card mb-3">
                        <div class="card-header bg-dark text-white py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-file-text"></i> DANE DO FAKTURY
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            {{-- NABYWCA --}}
                            <div class="mb-2">
                                <div class="border rounded p-2 bg-light" style="font-family: monospace; white-space: pre-line; user-select: text;"><strong>NABYWCA:</strong>
{{ $zamowienie->nab_nazwa ?? '—' }}
{{ $zamowienie->nab_adres ?? '—' }}
{{ $zamowienie->nab_kod ?? '—' }} {{ $zamowienie->nab_poczta ?? '—' }}
@if($zamowienie->nab_nip)NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->nab_nip) }}@endif</div>
                            </div>

                            {{-- ODBIORCA --}}
                            <div class="mb-2">
                                <div class="border rounded p-2 bg-light" style="font-family: monospace; white-space: pre-line; user-select: text;"><strong>ODBIORCA:</strong>
{{ $zamowienie->odb_nazwa ?? '—' }}
{{ $zamowienie->odb_adres ?? '—' }}
{{ $zamowienie->odb_kod ?? '—' }} {{ $zamowienie->odb_poczta ?? '—' }}
nowoczesna-edukacja.pl </div>
                            </div>

                            {{-- Przyciski kopiowania --}}
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyOdbiorcaData()">
                                    <i class="bi bi-clipboard"></i> ODBIORCA
                                </button>
                                @if($zamowienie->nab_nip)
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyNipNabywcy()">
                                        <i class="bi bi-clipboard"></i> NIP NABYWCY
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- UCZESTNIK - kompaktowe --}}
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-person"></i> UCZESTNIK
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>{{ $zamowienie->konto_imie_nazwisko ?? '—' }}</strong>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="copyUczestnikData()">
                                    <i class="bi bi-clipboard"></i> Uczestnik
                                </button>
                            </div>
                            @if($zamowienie->konto_email)
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        <i class="bi bi-envelope"></i> 
                                        <a href="mailto:{{ $zamowienie->konto_email }}" 
                                           class="text-decoration-none @if($zamowienie->konto_email == $zamowienie->zam_email) bg-warning bg-opacity-25 px-1 rounded @endif"
                                           @if($zamowienie->konto_email == $zamowienie->zam_email) title="Ten sam email co do faktury" @endif>
                                            {{ $zamowienie->konto_email }}
                                        </a>
                                    </small>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="copyEmailUczestnika()">
                                        <i class="bi bi-clipboard"></i> Email uczestnika
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Prawa kolumna: Faktura - kompaktowe --}}
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white py-2">
                            <h6 class="mb-0">
                                <i class="bi bi-envelope-paper"></i> FAKTURA
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            @if($zamowienie->zam_email)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>
                                        <strong>Fakturę przesłać na:</strong>
                                        <br>
                                        <a href="mailto:{{ $zamowienie->zam_email }}" 
                                           class="text-decoration-none @if($zamowienie->konto_email == $zamowienie->zam_email) bg-warning bg-opacity-25 px-1 rounded @endif"
                                           @if($zamowienie->konto_email == $zamowienie->zam_email) title="Ten sam email co uczestnika" @endif>
                                            <i class="bi bi-envelope"></i> {{ $zamowienie->zam_email }}
                                        </a>
                                    </small>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="copyEmailFaktury()">
                                        <i class="bi bi-clipboard"></i> Email faktury
                                    </button>
                                </div>
                            @endif
                            @if($zamowienie->zam_tel)
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small>
                                        <i class="bi bi-telephone"></i> 
                                        <strong>tel.</strong> 
                                        <a href="tel:{{ $zamowienie->zam_tel }}" class="text-decoration-none">
                                            @php
                                                $phone = preg_replace('/[^0-9]/', '', $zamowienie->zam_tel);
                                                if (strlen($phone) == 9) {
                                                    echo substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 3);
                                                } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '48') {
                                                    echo substr($phone, 2, 3) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8, 3);
                                                } else {
                                                    echo $phone;
                                                }
                                            @endphp
                                        </a>
                                    </small>
                                </div>
                            @endif
                            
                            {{-- Formularz edycji - kompaktowy --}}
                            <form action="{{ route('sales.update', $zamowienie->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="nr_fakury" class="form-label small">
                                            <strong>Numer faktury:</strong>
                                        </label>
                                        <input type="text" class="form-control form-control-sm @if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0) border-danger bg-danger bg-opacity-10 @endif" 
                                               id="nr_fakury" name="nr_fakury" 
                                               value="{{ $zamowienie->nr_fakury }}" 
                                               placeholder="Wprowadź numer faktury"
                                               @if((!$zamowienie->nr_fakury || $zamowienie->nr_fakury == '' || $zamowienie->nr_fakury == '0') && $zamowienie->status_zakonczone == 0) 
                                               style="border-width: 2px; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);" 
                                               @endif>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="status_zakonczone" class="form-label small">
                                            <strong>Status:</strong>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="status_zakonczone" name="status_zakonczone" value="1" 
                                                   {{ $zamowienie->status_zakonczone == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="status_zakonczone">
                                                Zakończone
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label for="notatki" class="form-label small">
                                        <strong>Notatki:</strong>
                                    </label>
                                    <textarea class="form-control form-control-sm" id="notatki" name="notatki" rows="1" 
                                              placeholder="Dodaj notatki">{{ $zamowienie->notatki }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-save"></i> Zapisz
                                </button>
                            </form>

                            {{-- INFORMACJE O FAKTURZE - kompaktowe --}}
                            @if($zamowienie->faktura_uwagi || $zamowienie->faktura_odroczenie)
                                <div class="card mt-3">
                                    <div class="card-header bg-warning text-dark py-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-receipt"></i> INFORMACJE O FAKTURZE
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($zamowienie->faktura_uwagi)
                                            <div class="mb-1">
                                                <small class="text-danger">
                                                    <strong>Uwagi:</strong> {{ $zamowienie->faktura_uwagi }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->faktura_odroczenie)
                                            <div class="mb-1">
                                                <small class="text-danger">
                                                    <strong>Odroczenie:</strong> 
                                                    <span class="badge bg-danger">{{ $zamowienie->faktura_odroczenie }} dni</span>
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- DODATKOWE INFORMACJE - kompaktowe --}}
                            @if($zamowienie->data_zamowienia || $zamowienie->ip || $zamowienie->fb)
                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white py-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-info-circle"></i> DODATKOWE INFORMACJE
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($zamowienie->data_zamowienia)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>Data zamówienia:</strong> {{ \Carbon\Carbon::parse($zamowienie->data_zamowienia)->format('d.m.Y H:i') }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->ip)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>IP:</strong> {{ $zamowienie->ip }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->fb)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>FB:</strong> {{ $zamowienie->fb }}
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>



        </div>
    </div>

    {{-- JavaScript do kopiowania danych --}}
    <script>
        function copyOdbiorcaData() {
            const odbiorcaData = `ODBIORCA:
{{ $zamowienie->odb_nazwa ?? '—' }}
{{ $zamowienie->odb_adres ?? '—' }}
{{ $zamowienie->odb_kod ?? '—' }} {{ $zamowienie->odb_poczta ?? '—' }}
nowoczesna-edukacja.pl `;
            copyToClipboard(odbiorcaData, 'copyOdbiorcaData');
        }

        function copyNipNabywcy() {
            const nipData = `{{ preg_replace('/[^0-9]/', '', $zamowienie->nab_nip) }}`;
            copyToClipboard(nipData, 'copyNipNabywcy');
        }

        function copyUczestnikData() {
            const uczestnikData = `{{ $zamowienie->konto_imie_nazwisko ?? '—' }}`;
            copyToClipboard(uczestnikData, 'copyUczestnikData');
        }

        function copyEmailUczestnika() {
            const emailData = `{{ $zamowienie->konto_email ?? '—' }}`;
            copyToClipboard(emailData, 'copyEmailUczestnika');
        }

        function copyEmailFaktury() {
            const emailData = `{{ $zamowienie->zam_email ?? '—' }}`;
            copyToClipboard(emailData, 'copyEmailFaktury');
        }

        function copyToClipboard(text, functionName) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess(functionName);
                }).catch(function(err) {
                    console.error('Błąd kopiowania: ', err);
                    fallbackCopyTextToClipboard(text, functionName);
                });
            } else {
                fallbackCopyTextToClipboard(text, functionName);
            }
        }

        function showCopySuccess(functionName) {
            const button = document.querySelector(`button[onclick="${functionName}()"]`);
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> OK!';
                
                // Zapisz oryginalne klasy
                const originalClasses = button.className;
                button.className = 'btn btn-success btn-sm';
                
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.className = originalClasses;
                }, 1500);
            }
        }

        function fallbackCopyTextToClipboard(text, functionName) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(functionName);
                } else {
                    alert('Nie udało się skopiować danych. Spróbuj zaznaczyć i skopiować ręcznie.');
                }
            } catch (err) {
                console.error('Fallback: Nie udało się skopiować', err);
                alert('Nie udało się skopiować danych. Spróbuj zaznaczyć i skopiować ręcznie.');
            }
            
            document.body.removeChild(textArea);
        }
    </script>
</x-app-layout>

