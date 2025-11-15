<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Duplikaty zamówień') }} <span class="text-danger">(PNEADM)</span>
        </h2>
    </x-slot>

    {{-- Meta tagi do wyłączenia cache --}}
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

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

            {{-- Przyciski nawigacji --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="{{ route('form-orders.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Powrót do listy
                        </a>
                    </div>
                    <div>
                        <span class="badge bg-danger fs-6">
                            <i class="bi bi-files"></i> {{ $totalGroups }} grup duplikatów
                        </span>
                    </div>
                </div>
            </div>

            {{-- Filtry --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel"></i> Filtry
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="filterStatus" class="form-label">Status duplikatów:</label>
                            <select class="form-select" id="filterStatus" onchange="updateFilterDescription(); applyFilters();">
                                <option value="needs-action" selected>⚠️ Wymaga oznaczenia duplikatów</option>
                                <option value="multiple-invoices">🚨 UWAGA! za dużo faktur</option>
                                <option value="ready">✅ Gotowe do przetworzenia</option>
                                <option value="processed">✔️ Przetworzone duplikaty</option>
                                <option value="all">📋 Wszystkie duplikaty</option>
                            </select>
                            <div id="filterDescription" class="form-text mt-2"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="filterEmail" class="form-label">Email uczestnika:</label>
                            <input type="text" class="form-control" id="filterEmail" placeholder="Wpisz email..." oninput="debounceFilter()">
                        </div>
                        <div class="col-md-3">
                            <label for="filterProduct" class="form-label">ID szkolenia:</label>
                            <input type="text" class="form-control" id="filterProduct" placeholder="Wpisz ID szkolenia..." oninput="debounceFilter()">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Wyczyść filtry
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Informacje o scenariuszach --}}
            <div class="alert alert-info mb-4">
                <h6 class="alert-heading">
                    <i class="bi bi-info-circle"></i> Zarządzanie duplikatami zamówień
                </h6>
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="fw-bold mb-2 text-primary">📊 Status zamówienia:</p>
                        <strong>🥇 Z fakturą:</strong> Przetworzone, faktura wystawiona<br>
                        <strong>🥈 Aktywne:</strong> Bez faktury, nie zakończone<br>
                        <strong>🥉 Zakończone:</strong> Bez faktury, oznaczone jako duplikat<br>
                        <small class="text-muted mt-1 d-block">ℹ️ Notatki nie wpływają na priorytet - służą do opisu</small>
                    </div>
                    <div class="col-md-6">
                        <p class="fw-bold mb-2 text-primary">🔍 Dostępne filtry:</p>
                        <strong>⚠️ Wymaga akcji:</strong> Wymagają uporządkowania<br>
                        <strong>✅ Gotowe:</strong> Gotowe do wystawienia faktury<br>
                        <strong>✔️ Przetworzone:</strong> Faktura + duplikaty zakończone<br>
                        <strong>🚨 Za dużo faktur:</strong> Błąd - za dużo faktur!<br>
                        <strong>📋 Wszystkie:</strong> Pełna lista
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="fw-bold mb-2 text-success">🎯 Priorytet chronologiczny:</p>
                        <strong>Z fakturą:</strong> Starsze &gt; Nowsze<br>
                        <strong>Zakończone:</strong> Starsze &gt; Nowsze<br>
                        <strong class="text-success">Aktywne: Nowsze &gt; Starsze ✨</strong><br>
                        <small class="text-muted mt-1 d-block">💡 Klient mógł poprawić dane w kolejnym zgłoszeniu</small>
                    </div>
                    <div class="col-md-6">
                        <p class="fw-bold mb-2 text-primary">🛠️ Dostępne akcje:</p>
                        <strong>Zakończ:</strong> Oznacz jako duplikat<br>
                        <strong>Notatka:</strong> Dodaj opis (np. "Duplikat #123")<br>
                        <strong>Zachowaj to:</strong> Zachowaj, usuń resztę<br>
                        <strong>Usuń:</strong> Usuń zamówienie<br>
                        <strong>Szczegóły:</strong> Pokaż pełne dane
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-shield-check"></i>
                    <strong>Pełna kontrola:</strong> System sugeruje zamówienie <span class="badge bg-success">✅ ZALECANE</span>, 
                    ale ostateczną decyzję podejmujesz Ty. Wszystkie przyciski są dostępne dla każdego zamówienia.
                </div>
            </div>

            {{-- Statystyki --}}
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5 class="card-title">Grupy duplikatów</h5>
                            <h3 class="card-text">{{ $totalGroups }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Wszystkie zamówienia</h5>
                            <h3 class="card-text">{{ $totalOrders }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Zbędne duplikaty</h5>
                            <h3 class="card-text">{{ $totalDuplicates - $totalGroups }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Oszczędność</h5>
                            <h3 class="card-text">{{ round((($totalDuplicates - $totalGroups) / $totalDuplicates) * 100, 1) }}%</h3>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista duplikatów --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Grupy duplikatów (email + szkolenie)</h5>
                    <div class="text-muted">
                        Wyświetlanie {{ $duplicatesPaginated->firstItem() ?? 0 }}-{{ $duplicatesPaginated->lastItem() ?? 0 }} z {{ $duplicatesPaginated->total() }} grup
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($duplicatesPaginated as $duplicate)
                        <div class="card border-danger mb-4 duplicate-group" data-group-email="{{ $duplicate['email'] }}" data-group-product="{{ $duplicate['product_id'] }}">
                            <div class="card-header bg-danger text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">
                                            <i class="bi bi-files"></i> 
                                            {{ $duplicate['count'] }} duplikatów
                                        </h5>
                                        <div class="small">
                                            <strong>Email:</strong> {{ $duplicate['email'] }}<br>
                                            <strong>Szkolenie ID:</strong> {{ $duplicate['product_id'] }}
                                        </div>
                                    </div>
                                    <div>
                                        @php
                                            $recommended = $duplicate['recommended_order'];
                                            $hasInvoice = $recommended->has_invoice;
                                            $isActive = !$recommended->is_completed;
                                            $isMarkedDuplicate = $recommended->is_marked_as_duplicate;
                                        @endphp
                                        
                                        <button type="button" 
                                                class="btn btn-success btn-sm me-2" 
                                                onclick="keepRecommendedAndDeleteRest('{{ $duplicate['email'] }}', {{ $duplicate['product_id'] }}, {{ $recommended->id }})"
                                                title="@if($hasInvoice)Zachowaj zamówienie z fakturą i usuń resztę@elseif($isActive)Zachowaj aktywne zamówienie (najstarsze) i usuń resztę@elseif($isMarkedDuplicate)Zachowaj najstarsze zamówienie i usuń resztę@elseZachowaj zalecane zamówienie i usuń resztę@endif">
                                            <i class="bi bi-check-circle"></i> 
                                            @if($hasInvoice)
                                                Zachowaj z fakturą
                                            @elseif($isActive)
                                                Zachowaj aktywne
                                            @else
                                                Zachowaj zalecane
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="row">
                                    @foreach($duplicate['orders'] as $index => $order)
                                        @php
                                            $isRecommended = $order->id == $duplicate['recommended_order']->id;
                                            $isOldest = $order->id == $duplicate['oldest_order']->id;
                                            $isNewest = $order->id == $duplicate['newest_order']->id;
                                            $priority = $order->priority;
                                        @endphp
                                        <div class="col-md-6 mb-3">
                                            <div class="card duplicate-order @if($isRecommended) border-success border-3 @elseif($isOldest) border-info @else border-danger @endif">
                                                <div class="card-header @if($isRecommended) bg-success text-white @elseif($isOldest) bg-info text-white @else bg-light @endif">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-0">
                                                                <span class="badge bg-dark">#{{ $order->id }}</span>
                                                                @if($isRecommended)
                                                                    <span class="badge bg-success ms-1">
                                                                        <i class="bi bi-star-fill"></i> ZALECANE
                                                                    </span>
                                                                @elseif($isOldest)
                                                                    <span class="badge bg-info ms-1">NAJSTARSZE</span>
                                                                @elseif($isNewest)
                                                                    <span class="badge bg-warning text-dark ms-1">NAJNOWSZE</span>
                                                                @endif
                                                                <small class="text-muted ms-2">(priorytet: {{ $priority }})</small>
                                                            </h6>
                                                        </div>
                                                        <div>
                                                            <button type="button" 
                                                                    class="btn btn-primary btn-sm me-1 text-white" 
                                                                    onclick="showOrderDetails({{ $order->id }})"
                                                                    title="Zobacz pełne szczegóły zamówienia">
                                                                <i class="bi bi-eye"></i> Szczegóły
                                                            </button>
                                                            @if($isRecommended)
                                                                <button type="button" 
                                                                        class="btn btn-dark btn-sm me-1 text-white" 
                                                                        onclick="keepThisAndDeleteRest('{{ $duplicate['email'] }}', {{ $duplicate['product_id'] }}, {{ $order->id }})"
                                                                        title="Zachowaj to zamówienie i usuń resztę">
                                                                    <i class="bi bi-check-circle"></i> Zachowaj to
                                                                </button>
                                                            @endif
                                                            @php
                                                                // Przyciski pokazuj gdy: zamówienie nie ma faktury i nie jest zakończone
                                                                // (niezależnie od tego czy w grupie są inne zamówienia z fakturą)
                                                                $showActionButtons = !$order->has_invoice && !$order->is_completed;
                                                            @endphp
                                                            @if($showActionButtons)
                                                                <button type="button" 
                                                                        class="btn btn-warning btn-sm me-1 text-dark fw-bold" 
                                                                        onclick="markAsCompleted({{ $order->id }}, '{{ $duplicate['recommended_order']->id }}')"
                                                                        title="Oznacz jako zakończone (duplikat)">
                                                                    <i class="bi bi-check-square"></i> Zakończ
                                                                </button>
                                                                <button type="button" 
                                                                        class="btn btn-info btn-sm me-1 text-white" 
                                                                        onclick="editNotes({{ $order->id }}, '{{ addslashes($order->notes ?? '') }}')"
                                                                        title="Dodaj lub edytuj notatkę">
                                                                    <i class="bi bi-pencil"></i> Notatka
                                                                </button>
                                                            @endif
                                                            <button type="button" 
                                                                    class="btn btn-danger btn-sm text-white" 
                                                                    onclick="deleteDuplicate({{ $order->id }})"
                                                                    title="Usuń ten duplikat">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <strong>Uczestnik:</strong><br>
                                                            {{ $order->participant_name }}<br>
                                                            <small class="text-muted">{{ $order->participant_email }}</small>
                                                        </div>
                                                        <div class="col-6">
                                                            <strong>Szkolenie:</strong><br>
                                                            {{ $order->product_name }}<br>
                                                            <small class="text-muted">ID: {{ $order->publigo_product_id }}</small>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-6">
                                                            <strong>Data:</strong><br>
                                                            {{ $order->order_date ? $order->order_date->setTimezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}
                                                        </div>
                                                        <div class="col-6">
                                                            <strong>Status:</strong><br>
                                                            @if($order->has_invoice)
                                                                <span class="badge bg-success">FAKTURA</span>
                                                                <br><small class="text-success fw-bold">#{{ $order->invoice_number }}</small>
                                                            @elseif($order->is_new)
                                                                <span class="badge bg-warning text-dark">NOWE</span>
                                                            @elseif($order->is_completed)
                                                                <span class="badge bg-secondary">ZAKOŃCZONE</span>
                                                            @endif
                                                            @if($order->is_marked_as_duplicate)
                                                                <span class="badge bg-danger ms-1">DUPLIKAT</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-12">
                                                            <strong>Powód priorytetu:</strong><br>
                                                            <small class="text-muted" style="white-space: pre-line;">{{ $order->priority_reason }}</small>
                                                        </div>
                                                    </div>
                                                    @if($order->notes)
                                                        <div class="mt-2">
                                                            <strong>Notatki:</strong><br>
                                                            <small class="text-muted">{{ $order->notes }}</small>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-check-circle fs-1 text-success"></i>
                                <p class="mt-3 mb-0">Brak duplikatów!</p>
                                <small>Wszystkie zamówienia są unikalne</small>
                            </div>
                        </div>
                    @endforelse
                </div>
                    
                {{-- Paginacja --}}
                @if($duplicatesPaginated->hasPages())
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Strona {{ $duplicatesPaginated->currentPage() }} z {{ $duplicatesPaginated->lastPage() }}
                            </div>
                            <div>
                                {{ $duplicatesPaginated->appends(['per_page' => $perPage])->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- JavaScript dla usuwania duplikatów --}}
    <script>
        let currentDeleteOrderId = null;
        let currentKeepEmail = null;
        let currentKeepProductId = null;
        let currentKeepOrderId = null;
        let currentMarkCompletedOrderId = null;
        let currentEditNotesOrderId = null;
        let filterTimeout = null;

        function deleteDuplicate(orderId) {
            currentDeleteOrderId = orderId;
            document.getElementById('deleteDuplicateOrderId').textContent = '#' + orderId;
            const modal = new bootstrap.Modal(document.getElementById('deleteDuplicateModal'));
            modal.show();
        }


        function keepRecommendedAndDeleteRest(email, productId, keepOrderId) {
            currentKeepEmail = email;
            currentKeepProductId = productId;
            currentKeepOrderId = keepOrderId;
            document.getElementById('keepSelectedOrderId').textContent = '#' + keepOrderId;
            const modal = new bootstrap.Modal(document.getElementById('keepSelectedModal'));
            modal.show();
        }

        function keepThisAndDeleteRest(email, productId, keepOrderId) {
            currentKeepEmail = email;
            currentKeepProductId = productId;
            currentKeepOrderId = keepOrderId;
            document.getElementById('keepSelectedOrderId').textContent = '#' + keepOrderId;
            const modal = new bootstrap.Modal(document.getElementById('keepSelectedModal'));
            modal.show();
        }

        function markAsCompleted(orderId, mainOrderId) {
            currentMarkCompletedOrderId = orderId;
            document.getElementById('markCompletedOrderId').textContent = '#' + orderId;
            document.getElementById('suggestedNote').textContent = 'Duplikat #' + mainOrderId;
            document.getElementById('duplicateNote').value = 'Duplikat #' + mainOrderId;
            const modal = new bootstrap.Modal(document.getElementById('markCompletedModal'));
            modal.show();
        }

        function editNotes(orderId, currentNotes) {
            currentEditNotesOrderId = orderId;
            document.getElementById('editNotesOrderId').textContent = '#' + orderId;
            document.getElementById('orderNotes').value = currentNotes;
            const modal = new bootstrap.Modal(document.getElementById('editNotesModal'));
            modal.show();
        }

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const email = document.getElementById('filterEmail').value.toLowerCase();
            const product = document.getElementById('filterProduct').value;
            
            const groups = document.querySelectorAll('[data-group-email]');
            let visibleCount = 0;
            
            groups.forEach(group => {
                let show = true;
                
                // Filtruj po statusie
                if (status !== 'all') {
                    const orders = group.querySelectorAll('.duplicate-order');
                    let hasReady = false;
                    let needsAction = false;
                    let isProcessed = false;
                    let hasMultipleInvoices = false;
                    
                    // Sprawdź czy grupa jest "gotowa do przetworzenia", "wymaga akcji", "przetworzona" lub "za dużo faktur"
                    if (status === 'ready' || status === 'needs-action' || status === 'processed' || status === 'multiple-invoices') {
                        let activeCount = 0;
                        let completedCount = 0;
                        let mainCount = 0;
                        
                        orders.forEach(order => {
                            // Sprawdź czy ma fakturę - szukaj dokładnie tekstu "FAKTURA" w badge'ach
                            const badges = Array.from(order.querySelectorAll('.badge'));
                            const hasInvoice = badges.some(badge => badge.textContent.trim() === 'FAKTURA');
                            // Sprawdź czy jest zakończone - szukaj dokładnie tekstu "ZAKOŃCZONE"
                            const isCompleted = badges.some(badge => badge.textContent.trim() === 'ZAKOŃCZONE');
                            
                            if (hasInvoice) {
                                mainCount++;
                            } else if (isCompleted) {
                                completedCount++;
                            } else {
                                // Nie ma faktury i nie jest zakończone = aktywne
                                activeCount++;
                            }
                        });
                        
                        // Gotowe do przetworzenia = dokładnie 1 aktywne + przynajmniej 1 zakończone + brak głównych
                        hasReady = (activeCount === 1 && completedCount > 0 && mainCount === 0);
                        
                        // Wymaga akcji = więcej niż 1 aktywne (bez faktury) LUB ma fakturę ale są jeszcze aktywne duplikaty
                        needsAction = (activeCount > 1) || (mainCount > 0 && activeCount > 0);
                        
                        // Przetworzone = ma fakturę + brak aktywnych + wszystkie pozostałe zakończone
                        isProcessed = (mainCount > 0 && activeCount === 0 && completedCount > 0);
                        
                        // Za dużo faktur = więcej niż 1 zamówienie z fakturą w grupie
                        hasMultipleInvoices = (mainCount > 1);
                    }
                    
                    if (status === 'ready' && !hasReady) show = false;
                    if (status === 'needs-action' && !needsAction) show = false;
                    if (status === 'processed' && !isProcessed) show = false;
                    if (status === 'multiple-invoices' && !hasMultipleInvoices) show = false;
                }
                
                // Filtruj po emailu
                if (email && !group.getAttribute('data-group-email').toLowerCase().includes(email)) {
                    show = false;
                }
                
                // Filtruj po ID szkolenia
                if (product && !group.getAttribute('data-group-product').includes(product)) {
                    show = false;
                }
                
                if (show) {
                    group.style.display = 'block';
                    visibleCount++;
                } else {
                    group.style.display = 'none';
                }
            });
            
            // Zaktualizuj licznik
            const counter = document.querySelector('.badge.bg-danger.fs-6');
            if (counter) {
                counter.innerHTML = `<i class="bi bi-files"></i> ${visibleCount} grup duplikatów`;
            }
        }

        function debounceFilter() {
            // Opóźnij filtrowanie o 500ms po ostatnim wpisaniu znaku
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                applyFilters();
            }, 500);
        }

        function clearFilters() {
            document.getElementById('filterStatus').value = 'needs-action';
            document.getElementById('filterEmail').value = '';
            document.getElementById('filterProduct').value = '';
            updateFilterDescription();
            applyFilters();
        }

        function updateFilterCounts() {
            const groups = document.querySelectorAll('.duplicate-group');
            let needsActionCount = 0;
            let multipleInvoicesCount = 0;
            
            groups.forEach(group => {
                const orders = group.querySelectorAll('.duplicate-order');
                let activeCount = 0;
                let completedCount = 0;
                let mainCount = 0;
                
                orders.forEach(order => {
                    const badges = Array.from(order.querySelectorAll('.badge'));
                    const hasInvoice = badges.some(badge => badge.textContent.trim() === 'FAKTURA');
                    const isCompleted = badges.some(badge => badge.textContent.trim() === 'ZAKOŃCZONE');
                    
                    if (hasInvoice) {
                        mainCount++;
                    } else if (isCompleted) {
                        completedCount++;
                    } else {
                        activeCount++;
                    }
                });
                
                // Wymaga akcji = więcej niż 1 aktywne LUB ma fakturę ale są jeszcze aktywne duplikaty
                const needsAction = (activeCount > 1) || (mainCount > 0 && activeCount > 0);
                if (needsAction) needsActionCount++;
                
                // Za dużo faktur = więcej niż 1 zamówienie z fakturą w grupie
                const hasMultipleInvoices = (mainCount > 1);
                if (hasMultipleInvoices) multipleInvoicesCount++;
            });
            
            // Zaktualizuj tekst opcji
            const selectElement = document.getElementById('filterStatus');
            const needsActionOption = selectElement.querySelector('option[value="needs-action"]');
            const multipleInvoicesOption = selectElement.querySelector('option[value="multiple-invoices"]');
            
            if (needsActionOption) {
                needsActionOption.textContent = `⚠️ Wymaga oznaczenia duplikatów (${needsActionCount})`;
            }
            if (multipleInvoicesOption) {
                multipleInvoicesOption.textContent = `🚨 UWAGA! za dużo faktur (${multipleInvoicesCount})`;
            }
        }

        function updateFilterDescription() {
            const status = document.getElementById('filterStatus').value;
            const descriptionElement = document.getElementById('filterDescription');
            
            const descriptions = {
                'all': '<i class="bi bi-info-circle text-primary"></i> <strong>Pokazuje:</strong> Wszystkie grupy duplikatów bez filtrowania. <strong>Co zrobić:</strong> Przejrzyj i uporządkuj według potrzeb.',
                'needs-action': '<i class="bi bi-exclamation-triangle text-warning"></i> <strong>Pokazuje:</strong> Grupy dla tego samego uczestnika i szkolenia z więcej niż jednym zamówieniem gotowym do wprowadzenia lub z fakturą + nieoznaczone duplikaty. <strong>Co zrobić:</strong> Użyj przycisków <span class="badge bg-warning text-dark">Zakończ</span> i <span class="badge bg-info">Notatka</span> aby oznaczyć duplikaty - zostaw tylko jedno właściwe zamówienie do wystawienia faktury.',
                'multiple-invoices': '<i class="bi bi-exclamation-octagon text-danger"></i> <strong>Pokazuje:</strong> Grupy dla tego samego uczestnika i szkolenia z więcej niż jedną wystawioną fakturą - to jest błąd! <strong>Co zrobić:</strong> Anuluj niepotrzebne faktury w systemie księgowym, usuń numery faktur z duplikatów, oznacz jako zakończone (duplikat).',
                'ready': '<i class="bi bi-check-circle text-success"></i> <strong>Pokazuje:</strong> Grupy dla tego samego uczestnika i szkolenia z jednym zamówieniem gotowym do wprowadzenia + pozostałe zakończone (oznaczone jako duplikat). <strong>Co zrobić:</strong> Nic - są gotowe do wystawienia faktury.',
                'processed': '<i class="bi bi-check-all text-success"></i> <strong>Pokazuje:</strong> Grupy dla tego samego uczestnika i szkolenia z wystawioną fakturą + wszystkie duplikaty zakończone (oznaczone jako duplikat). <strong>Co zrobić:</strong> Nic - są już w pełni przetworzone.'
            };
            
            descriptionElement.innerHTML = descriptions[status] || '';
        }



        // Funkcje pomocnicze do wyświetlania komunikatów
        function showSuccessMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        function showErrorMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 8 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 8000);
        }


        function showOrderDetails(orderId) {
            // Pokaż modal
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            modal.show();
            
            // Ustaw link do pełnej strony
            document.getElementById('orderDetailsLink').href = `/form-orders/${orderId}`;
            
            // Załaduj szczegóły zamówienia
            fetch(`/form-orders/${orderId}`)
                .then(response => response.text())
                .then(html => {
                    // Wyciągnij tylko zawartość body z odpowiedzi
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const bodyContent = doc.querySelector('.container');
                    
                    if (bodyContent) {
                        // Usuń nagłówek i nawigację, zostaw tylko zawartość
                        const header = bodyContent.querySelector('x-slot[name="header"]');
                        if (header) header.remove();
                        
                        // Zastąp zawartość modala
                        document.getElementById('orderDetailsContent').innerHTML = bodyContent.innerHTML;
                    } else {
                        document.getElementById('orderDetailsContent').innerHTML = 
                            '<div class="alert alert-danger">Nie udało się załadować szczegółów zamówienia.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Wystąpił błąd podczas ładowania szczegółów zamówienia.</div>';
                });
        }

        // Inicjalizacja tooltipów Bootstrap i event listenerów
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Automatycznie zastosuj domyślny filtr "Wymaga oznaczenia duplikatów"
            updateFilterCounts();
            updateFilterDescription();
            applyFilters();

            // Event listenery dla modali
            const confirmDeleteDuplicate = document.getElementById('confirmDeleteDuplicate');
            const confirmKeepSelected = document.getElementById('confirmKeepSelected');
            const confirmMarkCompleted = document.getElementById('confirmMarkCompleted');
            const confirmEditNotes = document.getElementById('confirmEditNotes');

            if (confirmDeleteDuplicate) {
                confirmDeleteDuplicate.addEventListener('click', function() {
                    if (currentDeleteOrderId) {
                        fetch(`/form-orders/duplicates/${currentDeleteOrderId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json',
                            },
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessMessage(data.message);

                                // Jeśli w tej grupie nadal są duplikaty po usunięciu (przed usunięciem było > 1),
                                // zapisz wskazanie grupy do localStorage, żeby przewinąć po przeładowaniu.
                                if (data.remaining_duplicates > 1) {
                                    try {
                                        localStorage.setItem('scrollToDuplicateGroup', JSON.stringify({
                                            email: data.email,
                                            productId: String(data.product_id),
                                            ts: Date.now()
                                        }));
                                    } catch (e) {}
                                }
                                location.reload();
                            } else {
                                showErrorMessage('Błąd: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorMessage('Wystąpił błąd podczas usuwania duplikatu');
                        });
                    }
                });
            }


            if (confirmKeepSelected) {
                confirmKeepSelected.addEventListener('click', function() {
                    if (currentKeepEmail && currentKeepProductId && currentKeepOrderId) {
                        fetch(`/form-orders/duplicates/group/${encodeURIComponent(currentKeepEmail)}/${currentKeepProductId}/keep/${currentKeepOrderId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json',
                            },
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessMessage(data.message + '\n\n✅ Duplikaty zostały usunięte\n✅ Zachowane zamówienie jest gotowe do dalszego przetwarzania');
                                location.reload();
                            } else {
                                showErrorMessage('Błąd: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorMessage('Wystąpił błąd podczas usuwania duplikatów');
                        });
                    }
                });
            }

            if (confirmMarkCompleted) {
                confirmMarkCompleted.addEventListener('click', function() {
                    if (currentMarkCompletedOrderId) {
                        const notes = document.getElementById('duplicateNote').value;
                        fetch(`/form-orders/duplicates/${currentMarkCompletedOrderId}/mark-completed`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ notes: notes })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessMessage(data.message);
                                location.reload();
                            } else {
                                showErrorMessage('Błąd: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorMessage('Wystąpił błąd podczas oznaczania jako zakończone');
                        });
                    }
                });
            }

            if (confirmEditNotes) {
                confirmEditNotes.addEventListener('click', function() {
                    if (currentEditNotesOrderId) {
                        const notes = document.getElementById('orderNotes').value;
                        fetch(`/form-orders/duplicates/${currentEditNotesOrderId}/update-notes`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ notes: notes })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessMessage(data.message);
                                location.reload();
                            } else {
                                showErrorMessage('Błąd: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorMessage('Wystąpił błąd podczas zapisywania notatki');
                        });
                    }
                });
            }


            // Po przeładowaniu - przewiń do zapamiętanej grupy
            try {
                const scrollPrefRaw = localStorage.getItem('scrollToDuplicateGroup');
                if (scrollPrefRaw) {
                    const scrollPref = JSON.parse(scrollPrefRaw);
                    const sel = `[data-group-email="${scrollPref.email}"][data-group-product="${scrollPref.productId}"]`;
                    const groupElement = document.querySelector(sel);
                    if (groupElement) {
                        groupElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        groupElement.style.boxShadow = '0 0 20px rgba(220, 53, 69, 0.5)';
                        setTimeout(() => {
                            groupElement.style.boxShadow = '';
                        }, 3000);
                    }
                    localStorage.removeItem('scrollToDuplicateGroup');
                }
            } catch (e) {}
        });
    </script>

    {{-- Modal ze szczegółami zamówienia --}}
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="orderDetailsModalLabel">
                        <i class="bi bi-eye"></i> Szczegóły zamówienia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Ładowanie...</span>
                        </div>
                        <p class="mt-2">Ładowanie szczegółów zamówienia...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Zamknij
                    </button>
                    <a href="#" id="orderDetailsLink" class="btn btn-primary" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> Otwórz w nowej zakładce
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal potwierdzenia usunięcia pojedynczego duplikatu --}}
    <div class="modal fade" id="deleteDuplicateModal" tabindex="-1" aria-labelledby="deleteDuplicateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteDuplicateModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć duplikat <strong id="deleteDuplicateOrderId"></strong>?</p>
                    <p class="text-muted">To zamówienie zostanie oznaczone jako usunięte (soft delete).</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i>
                        <strong>Uwaga:</strong> Możesz usunąć nawet zalecane zamówienie, jeśli masz inne zdanie niż system.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteDuplicate">
                        <i class="bi bi-trash"></i> Usuń duplikat
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Modal potwierdzenia zachowania wybranego zamówienia --}}
    <div class="modal fade" id="keepSelectedModal" tabindex="-1" aria-labelledby="keepSelectedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="keepSelectedModalLabel">
                        <i class="bi bi-check-circle"></i> Potwierdzenie zachowania
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz zachować zamówienie <strong id="keepSelectedOrderId"></strong> i usunąć wszystkie pozostałe duplikaty?</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Informacja:</strong> To zamówienie zostało wybrane na podstawie inteligentnych priorytetów:
                        <ul class="mb-0 mt-2">
                            <li>Faktura > Aktywne > Zakończone bez faktury</li>
                            <li>Notatki są ignorowane - liczy się tylko status</li>
                            <li>Aktywne zawsze mają wyższy priorytet niż zakończone</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-success" id="confirmKeepSelected">
                        <i class="bi bi-check-circle"></i> Zachowaj to zamówienie
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal oznaczania jako zakończone --}}
    <div class="modal fade" id="markCompletedModal" tabindex="-1" aria-labelledby="markCompletedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="markCompletedModalLabel">
                        <i class="bi bi-check-square"></i> Oznacz jako zakończone
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz oznaczyć zamówienie <strong id="markCompletedOrderId"></strong> jako zakończone?</p>
                    <p class="text-muted">To zamówienie zostanie oznaczone jako duplikat i ukryte z głównej listy.</p>
                    
                    <div class="mb-3">
                        <label for="duplicateNote" class="form-label">
                            <strong>Notatka (opcjonalnie):</strong>
                        </label>
                        <textarea class="form-control" id="duplicateNote" rows="3" 
                                  placeholder="np. Duplikat #123 - główne zamówienie"></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i>
                            Sugerowana notatka: <span id="suggestedNote" class="text-primary fw-bold"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-warning" id="confirmMarkCompleted">
                        <i class="bi bi-check-square"></i> Oznacz jako zakończone
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal edycji notatek --}}
    <div class="modal fade" id="editNotesModal" tabindex="-1" aria-labelledby="editNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editNotesModalLabel">
                        <i class="bi bi-pencil"></i> Edytuj notatkę
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Edytuj notatkę dla zamówienia <strong id="editNotesOrderId"></strong>:</p>
                    
                    <div class="mb-3">
                        <label for="orderNotes" class="form-label">
                            <strong>Notatka:</strong>
                        </label>
                        <textarea class="form-control" id="orderNotes" rows="4" 
                                  placeholder="Wprowadź notatkę..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="button" class="btn btn-info" id="confirmEditNotes">
                        <i class="bi bi-save"></i> Zapisz notatkę
                    </button>
                </div>
            </div>
        </div>
    </div>


</x-app-layout>
