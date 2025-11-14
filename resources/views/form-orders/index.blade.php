<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Nowe zamówienia') }} <span class="text-danger">(PNEADM)</span>
        </h2>
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

            {{-- Przyciski akcji i filtrów --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group" role="group">
                        <a href="{{ route('form-orders.index') }}" 
                           class="btn {{ $filter === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-list"></i> Wszystkie
                        </a>
                        <a href="{{ route('form-orders.index', ['filter' => 'new']) }}" 
                           class="btn {{ $filter === 'new' ? 'btn-warning' : 'btn-outline-warning' }}">
                            <i class="bi bi-exclamation-triangle"></i> NOWE 
                            <span class="badge bg-warning text-dark ms-1">{{ $newCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('form-orders.index', ['filter' => 'archival']) }}" 
                           class="btn {{ $filter === 'archival' ? 'btn-success' : 'btn-outline-success' }}">
                            <i class="bi bi-archive"></i> Archiwalne 
                            <span class="badge bg-success text-white ms-1">{{ $archivalCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('form-orders.duplicates') }}?v={{ time() }}" 
                           class="btn btn-danger @if($urgentDuplicatesCount > 0) btn-pulse @endif">
                            <i class="bi bi-files"></i> DUPLIKATY 
                            @if($urgentDuplicatesCount > 0)
                                <span class="badge bg-white text-danger fw-bold">({{ $urgentDuplicatesCount }})</span>
                            @endif
                        </a>
                    </div>
                    <div>
                        <a href="{{ route('form-orders.create') }}" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Dodaj nowe zamówienie
                        </a>
                    </div>
                </div>
                
                @if($filter === '')
                    <div class="mt-2">
                        <span class="badge bg-primary text-white">
                            <i class="bi bi-info-circle"></i> 
                            Pokazuję wszystkie zamówienia
                        </span>
                    </div>
                @endif
                
                @if($filter === 'new')
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-info-circle"></i> 
                            Pokazuję tylko niezakończone zamówienia bez numeru faktury
                        </span>
                    </div>
                @endif
                
                @if($filter === 'archival')
                    <div class="mt-2">
                        <span class="badge bg-success text-white">
                            <i class="bi bi-info-circle"></i> 
                            Pokazuję nieprzetworzone zamówienia (bez numeru faktury i nieoznaczone jako zakończone) dla zakończonych szkoleń
                        </span>
                    </div>
                @endif
            </div>

            {{-- Wyszukiwanie --}}
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('form-orders.index') }}" class="row g-3">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Wyszukaj:</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   placeholder="Imię, email, produkt, numer faktury, notatki, ID, Publigo ID...">
                        </div>
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">Rekordów na stronę:</label>
                            <select id="per_page" name="per_page" class="form-select">
                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                                <option value="all" {{ $perPage == 'all' ? 'selected' : '' }}>Wszystkie</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Szukaj
                                </button>
                                @if($search)
                                    <a href="{{ route('form-orders.index', ['filter' => $filter]) }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Wyczyść
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Statystyki --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center">
                        <strong>Wszystkie zamówienia:</strong> {{ number_format($stats['total'], 0, ',', ' ') }} | 
                        <strong>Nowe:</strong> {{ $stats['new'] }} | 
                        <strong>Wczoraj:</strong> {{ $stats['yesterday'] }} | 
                        <strong>Dzisiaj:</strong> {{ $stats['today'] }} | 
                        <strong>Archiwalne:</strong> {{ $stats['archival'] }} | 
                        <strong>Wartość sprzedaży:</strong> {{ number_format($stats['sales_value'], 0, ',', ' ') }} zł | 
                        <strong>Średnia cena:</strong> {{ number_format($stats['avg_price'], 2, ',', ' ') }} zł
                    </div>
                </div>
            </div>

            {{-- ======================  Lista zamówień w stylu dokumentu  ====================== --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Zamówienia z formularza (baza PNEADM)</h5>
                    <div class="text-muted">
                        Wyświetlanie {{ $zamowienia->firstItem() ?? 0 }}-{{ $zamowienia->lastItem() ?? 0 }} z {{ $zamowienia->total() }} rekordów
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($zamowienia as $zamowienie)
                        @php
                            $isNew = $zamowienie->is_new;
                            $isCompleted = $zamowienie->status_completed == 1;
                            $hasInvoice = $zamowienie->has_invoice;
                            $isDuplicate = isset($duplicateInfo[$zamowienie->id]) && $duplicateInfo[$zamowienie->id]['is_duplicate'];
                            $duplicateCount = $isDuplicate ? $duplicateInfo[$zamowienie->id]['count'] : 0;
                        @endphp
                        
                        <div class="card shadow-sm mb-4 @if($isDuplicate) border-danger @elseif($isNew) border-warning @elseif($isCompleted) border-secondary @else border-primary @endif">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">
                                            <span class="badge bg-dark fs-6">ID: #{{ $zamowienie->id }}</span>
                                            @if($zamowienie->publigo_product_id)
                                                <span class="badge bg-info fs-6 ms-2">Produkt Publigo ID: #{{ $zamowienie->publigo_product_id }}</span>
                                            @endif
                                            @if($zamowienie->fb_source)
                                                <span class="badge fs-6 ms-2" 
                                                      style="background-color: {{ $zamowienie->marketingCampaign && $zamowienie->marketingCampaign->sourceType ? $zamowienie->marketingCampaign->sourceType->color : '#28a745' }}; color: white;"
                                                      title="{{ $zamowienie->marketingCampaign ? $zamowienie->marketingCampaign->name . ' (' . ($zamowienie->marketingCampaign->sourceType->name ?? 'Nieznany typ') . ')' : 'Źródło: ' . $zamowienie->fb_source }}"
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top">
                                                    Źródło: {{ $zamowienie->fb_source }}
                                                </span>
                                            @endif
                                            @if($isDuplicate)
                                                <span class="badge bg-danger ms-2" 
                                                      title="Duplikat: {{ $duplicateCount }} zamówień dla tego samego emaila i szkolenia"
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top">
                                                    <i class="bi bi-files"></i> DUPLIKAT ({{ $duplicateCount }})
                                                </span>
                                            @elseif($isNew)
                                                <span class="badge bg-warning text-dark ms-2">
                                                    <i class="bi bi-exclamation-triangle"></i> NOWE
                                                </span>
                                            @elseif($isCompleted)
                                                <span class="badge bg-secondary ms-2">
                                                    <i class="bi bi-check-circle"></i> ZAKOŃCZONE
                                                </span>
                                            @elseif($hasInvoice)
                                                <span class="badge bg-success ms-2" title="Numer faktury: {{ $zamowienie->invoice_number }}">
                                                    <i class="bi bi-receipt"></i> FAKTURA {{ $zamowienie->invoice_number }}
                                                </span>
                                            @endif
                                        </h5>
                                        @if($zamowienie->order_date)
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-event"></i> {{ $zamowienie->order_date->format('d.m.Y H:i') }}
                                            </small>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        <a href="{{ route('form-orders.show', $zamowienie->id) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Szczegóły zamówienia">
                                            <i class="bi bi-eye"></i> Szczegóły
                                        </a>
                                        @if($isDuplicate)
                                            <a href="{{ route('form-orders.duplicates') }}" 
                                               class="btn btn-sm btn-outline-danger ms-1" 
                                               title="Zobacz duplikaty">
                                                <i class="bi bi-files"></i> Duplikaty
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                {{-- SZKOLENIE --}}
                                <div class="mb-4">
                                    <h6 class="text-primary fw-bold mb-2">
                                        <i class="bi bi-calendar-event"></i> SZKOLENIE
                                    </h6>
                                    <div class="fs-5 fw-semibold text-dark">{{ $zamowienie->product_name ?? '—' }}</div>
                                    @if($zamowienie->product_price)
                                        <div class="text-success fw-bold fs-6">
                                            {{ number_format($zamowienie->product_price, 2) }} PLN
                                        </div>
                                    @endif
                                </div>

                                {{-- Telefon --}}
                                @if($zamowienie->orderer_phone || $zamowienie->orderer_name) {{-- Sprawdzamy oba, bo może być tylko nazwa --}}
                                    <div class="mb-3">
                                        <h6 class="text-dark fw-bold mb-1">
                                            <i class="bi bi-telephone"></i> KONTAKT
                                            @if($zamowienie->orderer_name)
                                                <span class="text-muted"> - {{ $zamowienie->orderer_name }}</span>
                                            @endif
                                        </h6>
                                        @if($zamowienie->orderer_phone)
                                            <div class="text-dark">
                                                <strong>tel.</strong> 
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
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <div class="row">
                                    {{-- NABYWCA --}}
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-primary fw-bold mb-2">
                                            <i class="bi bi-building"></i> NABYWCA
                                        </h6>
                                        <div class="text-dark">
                                            <div class="fw-semibold">{{ $zamowienie->buyer_name ?? '—' }}</div>
                                            <div>{{ $zamowienie->buyer_address ?? '—' }}</div>
                                            <div>{{ $zamowienie->buyer_postal_code ?? '—' }} {{ $zamowienie->buyer_city ?? '—' }}</div>
                                            @if($zamowienie->buyer_nip)
                                                <div class="text-primary fw-semibold">NIP: {{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip) }}</div>
                                            @endif
                                        </div>
                                        <div class="mt-2">
                                            @if($zamowienie->buyer_nip)
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyNipNabywcy_{{ $zamowienie->id }}(this)">
                                                    <i class="bi bi-clipboard"></i> NIP
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ODBIORCA --}}
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-success fw-bold mb-2">
                                            <i class="bi bi-geo-alt"></i> ODBIORCA
                                        </h6>
                                        <div class="text-dark">
                                            <div class="fw-semibold">{{ $zamowienie->recipient_name ?? '—' }}</div>
                                            <div>{{ $zamowienie->recipient_address ?? '—' }}</div>
                                            <div>{{ $zamowienie->recipient_postal_code ?? '—' }} {{ $zamowienie->recipient_city ?? '—' }}</div>
                                            <div class="text-primary fw-semibold">nowoczesna-edukacja.pl</div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyOdbiorcaData_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> ODBIORCA
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- FAKTURA --}}
                                <div class="mb-3">
                                    <h6 class="text-info fw-bold mb-2">
                                        <i class="bi bi-receipt"></i> FAKTURA
                                    </h6>
                                    
                                    {{-- Informacje o fakturze --}}
                                    @if($hasInvoice)
                                        <div class="text-dark mb-1">
                                            <span class="fw-semibold">Nr faktury:</span> {{ $zamowienie->invoice_number }}
                                        </div>
                                    @else
                                        <div class="text-warning mb-1">
                                            <i class="bi bi-clock"></i> Oczekuje na wystawienie faktury
                                        </div>
                                    @endif
                                    @if($zamowienie->orderer_email)
                                        <div class="text-dark">
                                            <span class="fw-semibold">Email:</span> 
                                            <a href="mailto:{{ $zamowienie->orderer_email }}" class="text-primary text-decoration-none">
                                                {{ $zamowienie->orderer_email }}
                                            </a>
                                        </div>
                                        <div class="mt-1">
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="copyEmailFaktury_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> Email faktury
                                            </button>
                                        </div>
                                    @endif
                                    @if($zamowienie->invoice_notes)
                                        <div class="text-danger mt-1">
                                            <span class="fw-semibold">Uwagi:</span> {{ $zamowienie->invoice_notes }}
                                        </div>
                                    @endif
                                    @if($zamowienie->invoice_payment_delay)
                                        <div class="text-danger">
                                            <span class="fw-semibold">Odroczenie:</span> {{ $zamowienie->invoice_payment_delay }} dni
                                        </div>
                                    @endif
                                </div>

                                {{-- UCZESTNIK --}}
                                <div class="mb-3">
                                    <h6 class="text-dark fw-bold mb-2">
                                        <i class="bi bi-person"></i> UCZESTNIK
                                    </h6>
                                    <div class="text-dark">
                                        <div class="fw-semibold">{{ $zamowienie->participant_name ?? '—' }}</div>
                                        @if($zamowienie->participant_email)
                                            <a href="mailto:{{ $zamowienie->participant_email }}" class="text-primary text-decoration-none">
                                                {{ $zamowienie->participant_email }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="copyUczestnik_{{ $zamowienie->id }}(this)">
                                            <i class="bi bi-clipboard"></i> Uczestnik
                                        </button>
                                        @if($zamowienie->participant_email)
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="copyEmailUczestnika_{{ $zamowienie->id }}(this)">
                                                <i class="bi bi-clipboard"></i> Email uczestnika
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Notatki --}}
                                @if($zamowienie->notes)
                                    <div class="mb-3">
                                        <h6 class="text-secondary fw-bold mb-2">
                                            <i class="bi bi-sticky"></i> NOTATKI
                                        </h6>
                                        <div class="text-dark">{{ $zamowienie->notes }}</div>
                                    </div>
                                @endif

                                {{-- Formularz edycji na końcu karty --}}
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="text-dark fw-bold mb-3">
                                        <i class="bi bi-pencil"></i> EDYCJA ZAMÓWIENIA
                                    </h6>
                                    <form method="POST" action="{{ route('form-orders.update', $zamowienie->id) }}" class="mb-3">
                                        @csrf
                                        @method('PUT')
                                        {{-- Ukryte pola dla zachowania parametrów URL --}}
                                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                        <input type="hidden" name="page" value="{{ request()->get('page', 1) }}">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label for="invoice_number_{{ $zamowienie->id }}" class="form-label small">Nr faktury:</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm @if($isNew) border-danger bg-danger bg-opacity-10 @endif"
                                                       id="invoice_number_{{ $zamowienie->id }}" 
                                                       name="invoice_number"
                                                       value="{{ $zamowienie->invoice_number }}"
                                                       placeholder="Wprowadź numer faktury"
                                                       @if($isNew)
                                                       style="border-width: 2px; box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);"
                                                       @endif>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Status:</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="status_completed_{{ $zamowienie->id }}" name="status_completed" value="1" {{ $zamowienie->status_completed == 1 ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="status_completed_{{ $zamowienie->id }}">
                                                        Zakończone
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-12">
                                                <label for="notes_{{ $zamowienie->id }}" class="form-label small">Notatki:</label>
                                                <textarea class="form-control form-control-sm" 
                                                          id="notes_{{ $zamowienie->id }}" 
                                                          name="notes" 
                                                          rows="2" 
                                                          placeholder="Dodaj notatki...">{{ $zamowienie->notes }}</textarea>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-flex justify-content-between">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-circle"></i> Zapisz zmiany
                                            </button>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('form-orders.edit', $zamowienie->id) }}" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Edytuj zamówienie">
                                                    <i class="bi bi-pencil"></i> Edytuj
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        title="Usuń zamówienie"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal{{ $zamowienie->id }}">
                                                    <i class="bi bi-trash"></i> USUŃ
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Modal potwierdzenia usunięcia --}}
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
                                        <p>Czy na pewno chcesz usunąć zamówienie <strong>#{{ $zamowienie->id }}</strong>?</p>
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="mb-2">Szczegóły zamówienia:</h6>
                                            <ul class="mb-0">
                                                <li><strong>Uczestnik:</strong> {{ $zamowienie->participant_name }}</li>
                                                <li><strong>Email:</strong> {{ $zamowienie->participant_email }}</li>
                                                <li><strong>Szkolenie:</strong> {{ $zamowienie->product_name }}</li>
                                                <li><strong>Data:</strong> {{ $zamowienie->order_date ? $zamowienie->order_date->format('d.m.Y H:i') : '—' }}</li>
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
                                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                                            <input type="hidden" name="search" value="{{ $search }}">
                                            <input type="hidden" name="filter" value="{{ $filter }}">
                                            <input type="hidden" name="page" value="{{ request()->get('page', 1) }}">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Usuń zamówienie
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-3 mb-0">Brak zamówień do wyświetlenia.</p>
                                <small>Dostosuj filtry lub wyszukiwanie</small>
                            </div>
                        </div>
                    @endforelse
                </div>
                    
                    {{-- Paginacja --}}
                    @if($zamowienia->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Strona {{ $zamowienia->currentPage() }} z {{ $zamowienia->lastPage() }}
                                </div>
                                <div>
                                    {{ $zamowienia->appends(['per_page' => $perPage, 'search' => $search, 'filter' => $filter])->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- JavaScript dla kopiowania danych --}}
    <script>
        // Funkcje pomocnicze
        function copyToClipboard(text, clickedButton) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess(clickedButton);
                }).catch(() => {
                    fallbackCopyTextToClipboard(text, clickedButton);
                });
            } else {
                fallbackCopyTextToClipboard(text, clickedButton);
            }
        }

        function showCopySuccess(clickedButton) {
            const originalText = clickedButton.innerHTML;
            clickedButton.innerHTML = '<i class="bi bi-check"></i> Skopiowano!';
            clickedButton.classList.remove('btn-outline-primary', 'btn-outline-secondary', 'btn-outline-info', 'btn-outline-warning');
            clickedButton.classList.add('btn-success');
            
            setTimeout(() => {
                clickedButton.innerHTML = originalText;
                clickedButton.classList.remove('btn-success');
                if (originalText.includes('NIP')) {
                    clickedButton.classList.add('btn-outline-secondary');
                } else if (originalText.includes('ODBIORCA')) {
                    clickedButton.classList.add('btn-outline-primary');
                } else if (originalText.includes('Email faktury')) {
                    clickedButton.classList.add('btn-outline-warning');
                } else {
                    clickedButton.classList.add('btn-outline-info');
                }
            }, 2000);
        }

        function fallbackCopyTextToClipboard(text, clickedButton) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess(clickedButton);
            } catch (err) {
                console.error('Fallback: Nie udało się skopiować', err);
            }
            
            document.body.removeChild(textArea);
        }

        // Funkcje kopiowania dla każdego zamówienia
        @foreach($zamowienia as $zamowienie)
            function copyOdbiorcaData_{{ $zamowienie->id }}(button) {
                const odbiorcaData = `ODBIORCA: {{ $zamowienie->recipient_name ?? '' }}
{{ $zamowienie->recipient_address ?? '' }}
{{ $zamowienie->recipient_postal_code ?? '' }} {{ $zamowienie->recipient_city ?? '' }}
nowoczesna-edukacja.pl `;
                copyToClipboard(odbiorcaData, button);
            }

            function copyNipNabywcy_{{ $zamowienie->id }}(button) {
                const nip = '{{ preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip ?? '') }}';
                copyToClipboard(nip, button);
            }

            function copyUczestnik_{{ $zamowienie->id }}(button) {
                const uczestnik = '{{ $zamowienie->participant_name ?? '' }}';
                copyToClipboard(uczestnik, button);
            }

            function copyEmailUczestnika_{{ $zamowienie->id }}(button) {
                const email = '{{ $zamowienie->participant_email ?? '' }}';
                copyToClipboard(email, button);
            }

            function copyEmailFaktury_{{ $zamowienie->id }}(button) {
                const email = '{{ $zamowienie->orderer_email ?? '' }}';
                copyToClipboard(email, button);
            }
        @endforeach

        // Inicjalizacja tooltipów Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

</x-app-layout>
