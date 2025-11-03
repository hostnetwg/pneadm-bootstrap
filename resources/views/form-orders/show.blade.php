<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły zamówienia') }} <span class="text-danger">(PNEADM)</span>
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Breadcrumb --}}
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('form-orders.index') }}">Zamówienia</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Zamówienie #{{ $zamowienie->id }}
                    </li>
                </ol>
            </nav>

            {{-- Przyciski akcji --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="@if($zamowienie->is_new) text-danger @elseif($zamowienie->status_completed == 1) text-secondary @else text-success @endif">Zamówienie #{{ $zamowienie->id }}</h2>
                <div class="d-flex align-items-center gap-3">
                    {{-- Checkbox do filtrowania tylko niewprowadzonych zamówień --}}
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterNewOnly" 
                               {{ request('filter_new') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterNewOnly">
                            <i class="bi bi-funnel"></i> Tylko niewprowadzone
                        </label>
                    </div>
                    
                    {{-- Pole input do filtrowania po ID szkolenia --}}
                    <div class="input-group" style="width: 200px;">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               id="courseIdFilter" 
                               placeholder="ID szkolenia Publigo"
                               value="{{ request('course_id') }}"
                               title="Wprowadź ID szkolenia (id_old) do filtrowania">
                    </div>
                    
                    <div class="btn-group me-2" role="group">
                        <a href="{{ $prevOrder ? route('form-orders.show', array_merge(['id' => $prevOrder->id], array_filter(['filter_new' => request('filter_new') ? '1' : null, 'course_id' => request('course_id')]))) : '#' }}" 
                           class="btn {{ $prevOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                           title="{{ $prevOrder ? 'Poprzednie zamówienie' : 'Brak poprzedniego zamówienia' }}"
                           @if(!$prevOrder) onclick="return false;" @endif
                           id="prevOrderBtn">
                            <i class="bi bi-chevron-left"></i> Poprzednie
                        </a>
                        <a href="{{ route('form-orders.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-list"></i> Lista
                        </a>
                        <a href="{{ $nextOrder ? route('form-orders.show', array_merge(['id' => $nextOrder->id], array_filter(['filter_new' => request('filter_new') ? '1' : null, 'course_id' => request('course_id')]))) : '#' }}" 
                           class="btn {{ $nextOrder ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}" 
                           title="{{ $nextOrder ? 'Następne zamówienie' : 'Brak następnego zamówienia' }}"
                           @if(!$nextOrder) onclick="return false;" @endif
                           id="nextOrderBtn">
                            Następne <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
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
            @if($zamowienie->is_new)
                <div class="text-center mb-3">
                    <small class="text-danger fw-bold">ZAMÓWIENIE OCZEKUJE NA WYSTAWIENIE FAKTURY!</small>
                </div>
            @endif

            {{-- SZKOLENIE - kompaktowe --}}
            <div class="card mb-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event"></i> {{ $zamowienie->product_name ?? '—' }}
                        @if($zamowienie->product_price)
                            <span class="badge bg-success ms-2 fs-6">
                                {{ number_format($zamowienie->product_price, 2) }} PLN
                            </span>
                        @endif
                        @if($zamowienie->publigo_product_id)
                            <span class="badge bg-info ms-2 fs-6">
                                Produkt Publigo ID: #{{ $zamowienie->publigo_product_id }}
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
{{ $zamowienie->buyer_name ?? '—' }}
{{ $zamowienie->buyer_address ?? '—' }}
{{ $zamowienie->buyer_postal_code ?? '—' }} {{ $zamowienie->buyer_city ?? '—' }}
@if($zamowienie->buyer_nip)NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip) }}@endif</div>
                            </div>

                            {{-- ODBIORCA --}}
                            <div class="mb-2">
                                <div class="border rounded p-2 bg-light" style="font-family: monospace; white-space: pre-line; user-select: text;"><strong>ODBIORCA:</strong>
{{ $zamowienie->recipient_name ?? '—' }}
{{ $zamowienie->recipient_address ?? '—' }}
{{ $zamowienie->recipient_postal_code ?? '—' }} {{ $zamowienie->recipient_city ?? '—' }}
nowoczesna-edukacja.pl </div>
                            </div>

                            {{-- Przyciski kopiowania --}}
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyOdbiorcaData()">
                                    <i class="bi bi-clipboard"></i> ODBIORCA
                                </button>
                                @if($zamowienie->buyer_nip)
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
                                <strong>{{ $zamowienie->participant_name ?? '—' }}</strong>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="copyUczestnikData()">
                                    <i class="bi bi-clipboard"></i> Uczestnik
                                </button>
                            </div>
                            @if($zamowienie->participant_email)
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        <i class="bi bi-envelope"></i> 
                                        <a href="mailto:{{ $zamowienie->participant_email }}" 
                                           class="text-decoration-none @if($zamowienie->participant_email == $zamowienie->orderer_email) bg-warning bg-opacity-25 px-1 rounded @endif"
                                           @if($zamowienie->participant_email == $zamowienie->orderer_email) title="Ten sam email co do faktury" @endif>
                                            {{ $zamowienie->participant_email }}
                                        </a>
                                    </small>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="copyEmailUczestnika()">
                                        <i class="bi bi-clipboard"></i> Email uczestnika
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Buttons PUBLIGO i iFirma --}}
                    <div class="mb-3 d-flex flex-column gap-2">
                        {{-- Button Dodaj zamówienie PUBLIGO --}}
                        @if(!empty($zamowienie->publigo_product_id) && !empty($zamowienie->publigo_price_id) && $zamowienie->publigo_sent != 1)
                            <button type="button" class="btn btn-primary w-100" id="publigoOrderBtn" onclick="createPubligoOrder({{ $zamowienie->id }})">
                                <i class="bi bi-plus-circle"></i> Dodaj zamówienie PUBLIGO
                            </button>
                        @endif
                        
                        {{-- Button Wystaw PRO-FORMA iFirma --}}
                        <div class="w-100">
                            <button type="button" class="btn btn-success w-100" id="ifirmaProFormaBtn" onclick="createIfirmaProForma({{ $zamowienie->id }})">
                                <i class="bi bi-receipt"></i> Wystaw PRO-FORMA iFirma
                            </button>
                            <div class="form-check mt-1" style="font-size: 0.875rem;">
                                <input class="form-check-input" type="checkbox" id="sendEmailCheckboxProforma">
                                <label class="form-check-label text-muted" for="sendEmailCheckboxProforma">
                                    <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                    @if(!empty($zamowienie->orderer_email))
                                        <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->participant_email)), {{ strtolower($zamowienie->participant_email) }}@endif)</small>
                                    @endif
                                </label>
                            </div>
                        </div>

                        {{-- Button Wystaw Fakturę iFirma --}}
                        <div class="w-100">
                            <button type="button" class="btn btn-primary w-100" id="ifirmaInvoiceBtn" onclick="createIfirmaInvoice({{ $zamowienie->id }})">
                                <i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma
                            </button>
                            <div class="form-check mt-1" style="font-size: 0.875rem;">
                                <input class="form-check-input" type="checkbox" id="sendEmailCheckboxInvoice">
                                <label class="form-check-label text-muted" for="sendEmailCheckboxInvoice">
                                    <i class="bi bi-envelope"></i> Wyślij automatycznie na e-mail
                                    @if(!empty($zamowienie->orderer_email))
                                        <small>({{ strtolower($zamowienie->orderer_email) }}@if(!empty($zamowienie->participant_email) && strtolower($zamowienie->orderer_email) !== strtolower($zamowienie->participant_email)), {{ strtolower($zamowienie->participant_email) }}@endif)</small>
                                    @endif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div id="publigoResult" class="mt-2"></div>
                    <div id="ifirmaResult" class="mt-2"></div>
                    
                    {{-- Informacja o statusie Publigo --}}
                    @if($zamowienie->publigo_sent == 1)
                        <div class="mb-3 mt-3">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 
                                <strong>Zamówienie zostało wysłane do Publigo</strong>
                                <small class="d-block text-muted mt-1">
                                    Data wysłania: {{ $zamowienie->publigo_sent_at ? $zamowienie->publigo_sent_at->format('d.m.Y H:i') : 'Nieznana' }}
                                </small>
                                
                                {{-- Przycisk resetowania dla administratorów --}}
                                @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="resetPubligoBtn" onclick="resetPubligoStatus({{ $zamowienie->id }})">
                                            <i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo
                                        </button>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-info-circle"></i> Użyj gdy zamówienie zostało usunięte z Publigo
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @elseif(empty($zamowienie->publigo_product_id) || empty($zamowienie->publigo_price_id))
                        <div class="mb-3 mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Brak danych produktu Publigo - zamówienie nie może być przesłane do Publigo
                            </small>
                        </div>
                    @endif
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
                            @if($zamowienie->orderer_email)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>
                                        <strong>Fakturę przesłać na:</strong>
                                        <br>
                                        <a href="mailto:{{ $zamowienie->orderer_email }}" 
                                           class="text-decoration-none @if($zamowienie->participant_email == $zamowienie->orderer_email) bg-warning bg-opacity-25 px-1 rounded @endif"
                                           @if($zamowienie->participant_email == $zamowienie->orderer_email) title="Ten sam email co uczestnika" @endif>
                                            <i class="bi bi-envelope"></i> {{ $zamowienie->orderer_email }}
                                        </a>
                                    </small>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="copyEmailFaktury()">
                                        <i class="bi bi-clipboard"></i> Email faktury
                                    </button>
                                </div>
                            @endif
                            @if($zamowienie->orderer_phone || $zamowienie->orderer_name)
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small>
                                        <i class="bi bi-telephone"></i> 
                                        <strong>KONTAKT</strong>
                                        @if($zamowienie->orderer_name)
                                            <span class="text-muted"> - {{ $zamowienie->orderer_name }}</span>
                                        @endif
                                        @if($zamowienie->orderer_phone)
                                            <br><strong>tel.</strong> 
                                            <a href="tel:{{ $zamowienie->orderer_phone }}" class="text-decoration-none">
                                                @php
                                                    $phone = preg_replace('/[^0-9]/', '', $zamowienie->orderer_phone);
                                                    if (strlen($phone) == 9) {
                                                        // Polskie numery 9-cyfrowe
                                                        echo '+48 ' . substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 3);
                                                    } elseif (strlen($phone) == 11 && substr($phone, 0, 2) == '48') {
                                                        // Polskie numery z prefiksem 48
                                                        echo '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8, 3);
                                                    } elseif (strlen($phone) >= 10 && strlen($phone) <= 15) {
                                                        // Numery międzynarodowe - dodaj + i formatuj z odstępami
                                                        $formatted = '+' . $phone;
                                                        // Dodaj spacje co 3 cyfry od końca (ale zachowaj prefiks kraju)
                                                        $formatted = preg_replace('/(\d{3})(?=\d)/', '$1 ', $formatted);
                                                        echo $formatted;
                                                    } else {
                                                        // Fallback - wyświetl oryginalny numer
                                                        echo $zamowienie->orderer_phone;
                                                    }
                                                @endphp
                                            </a>
                                        @endif
                                    </small>
                                </div>
                            @endif
                            
                            {{-- Formularz edycji - kompaktowy --}}
                            <form action="{{ route('form-orders.update', $zamowienie->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                {{-- Ukryte pole informujące że formularz jest ze strony szczegółów --}}
                                <input type="hidden" name="from_show_page" value="1">
                                {{-- Przekazujemy parametry filtrów --}}
                                @if(request('filter_new'))
                                    <input type="hidden" name="filter_new" value="{{ request('filter_new') }}">
                                @endif
                                @if(request('course_id'))
                                    <input type="hidden" name="course_id" value="{{ request('course_id') }}">
                                @endif
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="invoice_number" class="form-label small">
                                            <strong>Numer faktury:</strong>
                                        </label>
                                        <input type="text" class="form-control form-control-sm @if($zamowienie->is_new) border-danger bg-danger bg-opacity-10 @endif" 
                                               id="invoice_number" name="invoice_number" 
                                               value="{{ $zamowienie->invoice_number }}" 
                                               placeholder="Wprowadź numer faktury"
                                               @if($zamowienie->is_new) 
                                               style="border-width: 2px; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);" 
                                               @endif>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="status_completed" class="form-label small">
                                            <strong>Status:</strong>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="status_completed" name="status_completed" value="1" 
                                                   {{ $zamowienie->status_completed == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="status_completed">
                                                Zakończone
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label for="notes" class="form-label small">
                                        <strong>Notatki:</strong>
                                    </label>
                                    <textarea class="form-control form-control-sm" id="notes" name="notes" rows="1" 
                                              placeholder="Dodaj notatki">{{ $zamowienie->notes }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-save"></i> Zapisz
                                </button>
                            </form>

                            {{-- INFORMACJE O FAKTURZE - kompaktowe --}}
                            @if($zamowienie->invoice_notes || $zamowienie->invoice_payment_delay)
                                <div class="card mt-3">
                                    <div class="card-header bg-warning text-dark py-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-receipt"></i> INFORMACJE O FAKTURZE
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($zamowienie->invoice_notes)
                                            <div class="mb-1">
                                                <small class="text-danger">
                                                    <strong>Uwagi od zamawiającego:</strong> {{ $zamowienie->invoice_notes }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->invoice_payment_delay)
                                            <div class="mb-1">
                                                <small class="text-danger">
                                                    <strong>Odroczenie:</strong> 
                                                    <span class="badge bg-danger">{{ $zamowienie->invoice_payment_delay }} dni</span>
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            {{-- UWAGI DO FAKTURY (edytowalne dla API iFirma) --}}
                            <div class="card mt-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0">
                                        <i class="bi bi-pencil-square"></i> UWAGI DO FAKTURY (dla API iFirma)
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="mb-2">
                                        <label for="invoice_api_remarks" class="form-label small mb-1">
                                            <strong>Uwagi, które pojawią się na fakturze:</strong>
                                            <br>
                                            <small class="text-muted">Możesz edytować ten tekst przed wystawieniem faktury</small>
                                        </label>
                                        <textarea class="form-control form-control-sm" 
                                                  id="invoice_api_remarks" 
                                                  rows="4" 
                                                  placeholder="ODBIORCA:&#10;[dane odbiorcy]"
                                                  style="font-family: monospace; font-size: 12px;">@php
$recipientData = [];
if (!empty($zamowienie->recipient_name)) $recipientData[] = $zamowienie->recipient_name;
if (!empty($zamowienie->recipient_address)) $recipientData[] = $zamowienie->recipient_address;
if (!empty($zamowienie->recipient_postal_code) && !empty($zamowienie->recipient_city)) {
    $recipientData[] = $zamowienie->recipient_postal_code . ' ' . $zamowienie->recipient_city;
}

// Tylko dane odbiorcy - bez uwag od zamawiającego i bez pnedu.pl #ID (to dodaje backend)
$remarks = "ODBIORCA:\n";
if (!empty($recipientData)) {
    $remarks .= implode("\n", $recipientData);
}

echo $remarks;
@endphp</textarea>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> Ten tekst zostanie użyty jako "Uwagi" na fakturze. 
                                            <strong>Na końcu automatycznie dodamy: "pnedu.pl #{{ $zamowienie->id }}"</strong>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            {{-- DODATKOWE INFORMACJE - kompaktowe --}}
                            @if($zamowienie->order_date || $zamowienie->ip_address || $zamowienie->fb_source)
                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white py-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-info-circle"></i> DODATKOWE INFORMACJE
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        @if($zamowienie->order_date)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>Data zamówienia:</strong> {{ $zamowienie->order_date->format('d.m.Y H:i') }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->ip_address)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>IP:</strong> {{ $zamowienie->ip_address }}
                                                </small>
                                            </div>
                                        @endif
                                        @if($zamowienie->fb_source)
                                            <div class="mb-1">
                                                <small>
                                                    <strong>Źródło pozyskania:</strong> 
                                                    <span class="badge ms-1" 
                                                          style="background-color: {{ $zamowienie->marketingCampaign && $zamowienie->marketingCampaign->sourceType ? $zamowienie->marketingCampaign->sourceType->color : '#28a745' }}; color: white;"
                                                          title="{{ $zamowienie->marketingCampaign ? $zamowienie->marketingCampaign->name . ' (' . ($zamowienie->marketingCampaign->sourceType->name ?? 'Nieznany typ') . ')' : 'Źródło: ' . $zamowienie->fb_source }}"
                                                          data-bs-toggle="tooltip" 
                                                          data-bs-placement="top">
                                                        {{ $zamowienie->fb_source }}
                                                    </span>
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

            {{-- Przyciski akcji na dole strony --}}
            <div class="d-flex justify-content-end mt-4 mb-4">
                <div class="btn-group" role="group">
                    <a href="{{ route('form-orders.edit', $zamowienie->id) }}" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edytuj
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Usuń
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- JavaScript do kopiowania danych --}}
    <script>
        function copyOdbiorcaData() {
            const odbiorcaData = `ODBIORCA:
{{ $zamowienie->recipient_name ?? '—' }}
{{ $zamowienie->recipient_address ?? '—' }}
{{ $zamowienie->recipient_postal_code ?? '—' }} {{ $zamowienie->recipient_city ?? '—' }}
nowoczesna-edukacja.pl `;
            copyToClipboard(odbiorcaData, 'copyOdbiorcaData');
        }

        function copyNipNabywcy() {
            const nipData = `{{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip) }}`;
            copyToClipboard(nipData, 'copyNipNabywcy');
        }

        function copyUczestnikData() {
            const uczestnikData = `{{ $zamowienie->participant_name ?? '—' }}`;
            copyToClipboard(uczestnikData, 'copyUczestnikData');
        }

        function copyEmailUczestnika() {
            const emailData = `{{ $zamowienie->participant_email ?? '—' }}`;
            copyToClipboard(emailData, 'copyEmailUczestnika');
        }

        function copyEmailFaktury() {
            const emailData = `{{ $zamowienie->orderer_email ?? '—' }}`;
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

        // Funkcja do tworzenia zamówienia w Publigo
        function createPubligoOrder(orderId) {
            const button = document.getElementById('publigoOrderBtn');
            const resultDiv = document.getElementById('publigoResult');
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Wysłanie zapytania AJAX
            fetch(`/form-orders/${orderId}/publigo/create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sukces
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sukces!</strong> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showPubligoDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.publigoResponseData = data;
                } else {
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.publigo_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showPubligoDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.publigoResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd połączenia:</strong> Wystąpił błąd podczas komunikacji z serwerem.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-plus-circle"></i> Dodaj zamówienie PUBLIGO';
            });
        }

        // Funkcja do wystawiania faktury pro forma w iFirma
        function createIfirmaProForma(orderId) {
            const button = document.getElementById('ifirmaProFormaBtn');
            const resultDiv = document.getElementById('ifirmaResult');
            
            // Pobierz edytowalne uwagi do faktury
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';
            
            // Pobierz stan checkboxa "Wyślij automatycznie na e-mail"
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxProforma');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Wysłanie zapytania AJAX z niestandardowymi uwagami i opcją wysyłki e-mail
            fetch(`/form-orders/${orderId}/ifirma/proforma`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    custom_remarks: customRemarks,
                    send_email: sendEmail
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sukces
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sukces!</strong> ${data.message}
                            ${data.invoice_number ? `<br><small>Numer faktury: <strong>${data.invoice_number}</strong></small>` : ''}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Odśwież stronę
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                    
                    // Automatyczne wypełnienie pola "Numer faktury" jeśli jest dostępny
                    if (data.invoice_number) {
                        const invoiceNumberInput = document.getElementById('invoice_number');
                        if (invoiceNumberInput) {
                            invoiceNumberInput.value = data.invoice_number;
                            
                            // Wizualny efekt - podświetlenie pola na zielono na moment
                            invoiceNumberInput.style.transition = 'background-color 0.3s';
                            invoiceNumberInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                invoiceNumberInput.style.backgroundColor = '';
                            }, 2000);
                        }
                    }
                    
                    // NIE odświeżamy strony automatycznie - użytkownik może zobaczyć szczegóły
                    // Jeśli użytkownik chce, może odświeżyć ręcznie
                } else {
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.ifirma_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd połączenia:</strong> Wystąpił błąd podczas komunikacji z serwerem.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-receipt"></i> Wystaw PRO-FORMA iFirma';
            });
        }

        // Funkcja do wystawiania zwykłej faktury w iFirma
        function createIfirmaInvoice(orderId) {
            const button = document.getElementById('ifirmaInvoiceBtn');
            const resultDiv = document.getElementById('ifirmaResult');
            
            // Pobierz edytowalne uwagi do faktury
            const invoiceRemarksTextarea = document.getElementById('invoice_api_remarks');
            const customRemarks = invoiceRemarksTextarea ? invoiceRemarksTextarea.value.trim() : '';
            
            // Pobierz stan checkboxa "Wyślij automatycznie na e-mail"
            const sendEmailCheckbox = document.getElementById('sendEmailCheckboxInvoice');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Przetwarzanie...';
            
            // Wyczyść poprzednie komunikaty
            resultDiv.innerHTML = '';
            
            // Wysłanie zapytania AJAX z niestandardowymi uwagami i opcją wysyłki e-mail
            fetch(`/form-orders/${orderId}/ifirma/invoice`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    custom_remarks: customRemarks,
                    send_email: sendEmail
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sukces
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sukces!</strong> ${data.message}
                            ${data.invoice_number ? `<br><small>Numer faktury: <strong>${data.invoice_number}</strong></small>` : ''}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showIfirmaDetails()">
                                <i class="bi bi-info-circle"></i> Pokaż szczegóły odpowiedzi
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Odśwież stronę
                            </button>
                        </div>
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                    
                    // Automatyczne wypełnienie pola "Numer faktury" jeśli jest dostępny
                    if (data.invoice_number) {
                        const invoiceNumberInput = document.getElementById('invoice_number');
                        if (invoiceNumberInput) {
                            invoiceNumberInput.value = data.invoice_number;
                            
                            // Wizualny efekt - podświetlenie pola na zielono na moment
                            invoiceNumberInput.style.transition = 'background-color 0.3s';
                            invoiceNumberInput.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                invoiceNumberInput.style.backgroundColor = '';
                            }, 2000);
                        }
                    }
                    
                } else {
                    // Błąd
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Błąd:</strong> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        ${data.ifirma_response ? `
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="showIfirmaDetails()">
                                    <i class="bi bi-info-circle"></i> Pokaż szczegóły błędu
                                </button>
                            </div>
                        ` : ''}
                    `;
                    
                    // Przechowanie danych do wyświetlenia szczegółów
                    window.ifirmaResponseData = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Błąd połączenia:</strong> Wystąpił błąd podczas komunikacji z serwerem.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-file-earmark-text"></i> Wystaw Fakturę iFirma';
            });
        }

        // Funkcja do wyświetlania szczegółów odpowiedzi iFirma
        function showIfirmaDetails() {
            if (!window.ifirmaResponseData) return;
            
            const data = window.ifirmaResponseData;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Szczegóły odpowiedzi iFirma
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-info-circle"></i> Informacje:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Status:</strong> <span class="badge ${data.success ? 'bg-success' : 'bg-danger'}">${data.success ? 'Sukces' : 'Błąd'}</span></li>
                                    ${data.invoice_number ? `<li><strong>Numer faktury:</strong> <code>${data.invoice_number}</code></li>` : ''}
                                    ${data.created_at ? `<li><strong>Utworzono:</strong> ${data.created_at}</li>` : ''}
                                    ${data.status_code ? `<li><strong>Kod HTTP:</strong> <span class="badge ${data.status_code === 200 ? 'bg-success' : 'bg-danger'}">${data.status_code}</span></li>` : ''}
                                </ul>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-send"></i> Wysłane dane do API:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 400px; overflow-y: auto;">${JSON.stringify(data.invoice_data, null, 2)}</pre>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-reply"></i> Pełna odpowiedź z iFirma:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 400px; overflow-y: auto;">${JSON.stringify(data.ifirma_response, null, 2)}</pre>
                                </div>
                            </div>
                            ${data.error ? `
                                <div class="alert alert-danger mt-3">
                                    <strong>Błąd:</strong> ${data.error}
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Usuń modal z DOM po zamknięciu
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.removeChild(modal);
            });
        }

        // Funkcja do wyświetlania szczegółów odpowiedzi Publigo
        function showPubligoDetails() {
            if (!window.publigoResponseData) return;
            
            const data = window.publigoResponseData;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Szczegóły odpowiedzi Publigo
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-send"></i> Wysłane dane:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(data.order_data, null, 2)}</pre>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-reply"></i> Odpowiedź Publigo:</h6>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(data.publigo_response, null, 2)}</pre>
                                </div>
                            </div>
                            ${data.http_code ? `
                                <div class="mt-3">
                                    <h6><i class="bi bi-code"></i> Kod HTTP:</h6>
                                    <span class="badge ${data.http_code === 200 ? 'bg-success' : 'bg-danger'}">${data.http_code}</span>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Usuń modal z DOM po zamknięciu
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.removeChild(modal);
            });
        }

        // Funkcja do resetowania statusu Publigo (tylko dla administratorów)
        function resetPubligoStatus(orderId) {
            if (!confirm('Czy na pewno chcesz zresetować status Publigo dla tego zamówienia?\n\nTo pozwoli na ponowne wysłanie zamówienia do Publigo.')) {
                return;
            }

            const button = document.getElementById('resetPubligoBtn');
            const resultDiv = document.getElementById('publigoResult');
            
            // Zmiana stanu przycisku
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Resetowanie...';
            
            // Wysłanie zapytania AJAX
            fetch(`/form-orders/${orderId}/publigo/reset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sukces - przeładowanie strony aby pokazać przycisk "Dodaj zamówienie PUBLIGO"
                    location.reload();
                } else {
                    // Błąd
                    alert('Błąd: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas resetowania statusu.');
            })
            .finally(() => {
                // Przywrócenie stanu przycisku
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo';
            });
        }

        // Obsługa checkboxa filtrowania i pola ID szkolenia
        document.addEventListener('DOMContentLoaded', function() {
            const filterCheckbox = document.getElementById('filterNewOnly');
            const courseIdInput = document.getElementById('courseIdFilter');
            const prevOrderBtn = document.getElementById('prevOrderBtn');
            const nextOrderBtn = document.getElementById('nextOrderBtn');
            
            // Przechowujemy oryginalne linki
            const originalPrevHref = prevOrderBtn.href;
            const originalNextHref = nextOrderBtn.href;
            
            filterCheckbox.addEventListener('change', function() {
                const currentOrderId = {{ $zamowienie->id }};
                const filterNew = this.checked ? '1' : '';
                
                // Aktualizujemy linki nawigacyjne
                if (filterNew) {
                    // Dodajemy parametr filter_new=1
                    const prevUrl = new URL(originalPrevHref);
                    const nextUrl = new URL(originalNextHref);
                    
                    prevUrl.searchParams.set('filter_new', '1');
                    nextUrl.searchParams.set('filter_new', '1');
                    
                    prevOrderBtn.href = prevUrl.toString();
                    nextOrderBtn.href = nextUrl.toString();
                } else {
                    // Usuwamy parametr filter_new
                    const prevUrl = new URL(originalPrevHref);
                    const nextUrl = new URL(originalNextHref);
                    
                    prevUrl.searchParams.delete('filter_new');
                    nextUrl.searchParams.delete('filter_new');
                    
                    prevOrderBtn.href = prevUrl.toString();
                    nextOrderBtn.href = nextUrl.toString();
                }
                
                // Przeładowujemy stronę z nowym filtrem
                const currentUrl = new URL(window.location);
                if (filterNew) {
                    currentUrl.searchParams.set('filter_new', '1');
                } else {
                    currentUrl.searchParams.delete('filter_new');
                }
                window.location.href = currentUrl.toString();
            });
            
            // Obsługa pola ID szkolenia
            courseIdInput.addEventListener('input', function() {
                const courseId = this.value.trim();
                const currentUrl = new URL(window.location);
                
                if (courseId) {
                    currentUrl.searchParams.set('course_id', courseId);
                } else {
                    currentUrl.searchParams.delete('course_id');
                }
                
                // Przeładowujemy stronę z nowym filtrem
                window.location.href = currentUrl.toString();
            });
        });

        // Inicjalizacja tooltipów Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Funkcja do zapamiętywania stanu checkboxów e-mail w bazie danych (per użytkownik)
        async function initializeEmailCheckboxes() {
            const proformaCheckbox = document.getElementById('sendEmailCheckboxProforma');
            const invoiceCheckbox = document.getElementById('sendEmailCheckboxInvoice');
            
            // Klucze preferencji
            const PROFORMA_KEY = 'ifirma_send_email_proforma';
            const INVOICE_KEY = 'ifirma_send_email_invoice';
            
            // Funkcja do pobierania preferencji z serwera
            async function loadPreferences() {
                try {
                    const response = await fetch('/api/user/preferences', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        return data.preferences || {};
                    }
                } catch (error) {
                    console.error('Error loading preferences:', error);
                }
                return {};
            }
            
            // Funkcja do zapisywania preferencji na serwerze
            async function savePreference(key, value) {
                try {
                    await fetch('/api/user/preferences', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ key, value })
                    });
                } catch (error) {
                    console.error('Error saving preference:', error);
                }
            }
            
            // Załaduj preferencje z serwera
            const preferences = await loadPreferences();
            
            // Przywróć zapisany stan dla PRO-FORMA
            if (proformaCheckbox) {
                if (preferences[PROFORMA_KEY] !== undefined) {
                    proformaCheckbox.checked = preferences[PROFORMA_KEY];
                }
                
                // Zapisz stan przy każdej zmianie
                proformaCheckbox.addEventListener('change', function() {
                    savePreference(PROFORMA_KEY, this.checked);
                });
            }
            
            // Przywróć zapisany stan dla Faktury
            if (invoiceCheckbox) {
                if (preferences[INVOICE_KEY] !== undefined) {
                    invoiceCheckbox.checked = preferences[INVOICE_KEY];
                }
                
                // Zapisz stan przy każdej zmianie
                invoiceCheckbox.addEventListener('change', function() {
                    savePreference(INVOICE_KEY, this.checked);
                });
            }
        }
        
        // Wywołaj inicjalizację po załadowaniu DOM
        document.addEventListener('DOMContentLoaded', initializeEmailCheckboxes);
    </script>

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
                    <p>Czy na pewno chcesz usunąć zamówienie <strong>#{{ $zamowienie->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły zamówienia:</h6>
                        <ul class="mb-0">
                            <li><strong>Uczestnik:</strong> {{ $zamowienie->participant_name }}</li>
                            <li><strong>Email:</strong> {{ $zamowienie->participant_email }}</li>
                            <li><strong>Szkolenie:</strong> {{ $zamowienie->product_name }}</li>
                            <li><strong>Data:</strong> {{ $zamowienie->order_date ? $zamowienie->order_date->format('d.m.Y H:i') : '—' }}</li>
                            <li><strong>Status:</strong> {{ $zamowienie->is_new ? 'Niewprowadzone' : 'Wprowadzone' }}</li>
                            <li><strong>Numer faktury:</strong> {{ $zamowienie->invoice_number ?: 'Brak' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Zamówienie zostanie przeniesione do kosza (soft delete) i będzie można je przywrócić.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('form-orders.destroy', $zamowienie->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń zamówienie
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


