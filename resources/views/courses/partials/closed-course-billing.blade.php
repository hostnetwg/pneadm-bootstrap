@php
    use App\Services\CourseFormOrderBillingService;

    $billingStatus = $billingStatus ?? CourseFormOrderBillingService::STATUS_NOT_APPLICABLE;
    $courseFormOrders = $courseFormOrders ?? collect();
    $activeVariants = CourseFormOrderBillingService::activePriceVariantsForLinks($course);
    $pneduBase = CourseFormOrderBillingService::pneduBaseUrl();
@endphp

<div class="card mb-4" id="closed-course-billing">
    <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">
            <i class="fas fa-file-invoice-dollar"></i> Rozliczenie — formularz zamówienia (PNEDU)
        </h5>
        @if($billingStatus !== CourseFormOrderBillingService::STATUS_NOT_APPLICABLE)
            <span class="badge {{ CourseFormOrderBillingService::statusBadgeClass($billingStatus) }}">
                {{ CourseFormOrderBillingService::statusLabel($billingStatus) }}
            </span>
        @endif
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Szkolenie zamknięte: zamówienie w <code>form_orders</code> to płatnik / kontakt (dyrektor, sekretariat).
            Faktura i automatyzacje (iFirma, ClickMeeting) idą z zamówienia.
            Nauczyciele rejestrują się osobno — sekcja
            <a href="{{ ($context ?? 'show') === 'edit' ? '#certificate-registration' : route('courses.edit', [$course->id, 'filter_preserve' => 1]) . '#certificate-registration' }}">Rejestracja zaświadczenia</a>.
        </p>

        @if($billingStatus === CourseFormOrderBillingService::STATUS_NO_ORDERS)
            <div class="alert alert-warning mb-3">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Brak zamówienia</strong> — po kontakcie z dyrektorem załóż zamówienie ręcznie lub wyślij link do formularza.
            </div>
        @elseif($billingStatus === CourseFormOrderBillingService::STATUS_NO_INVOICE)
            <div class="alert alert-danger mb-3">
                <i class="bi bi-exclamation-octagon"></i>
                <strong>Brak wystawionej faktury</strong> — jest zamówienie, ale bez numeru FV. Wystaw fakturę z poziomu zamówienia (iFirma).
            </div>
        @elseif($billingStatus === CourseFormOrderBillingService::STATUS_PARTIAL)
            <div class="alert alert-warning mb-3">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Faktura częściowa</strong> — część zamówień ma numer FV, część nie (np. rozbicie na dwie szkoły).
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('form-orders.create', ['course_id' => $course->id]) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Dodaj zamówienie w panelu
            </a>
            <a href="{{ route('form-orders.index', ['course_id' => $course->id]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Wszystkie zamówienia tego kursu
            </a>
        </div>

        <h6 class="fw-semibold">Link — nowe zamówienie (dyrektor wypełnia sam)</h6>
        @if($activeVariants->isEmpty())
            <p class="text-danger small mb-2">
                Brak aktywnego wariantu cenowego — dodaj wariant w sekcji „Warianty cenowe”, inaczej formularz na PNEDU może nie mieć ceny.
            </p>
        @elseif($activeVariants->count() === 1)
            @php $newUrl = CourseFormOrderBillingService::newOrderFormUrl($course, (int) $activeVariants->first()->id); @endphp
            <div class="input-group mb-3">
                <input type="text" class="form-control font-monospace small" id="pnedu-new-order-url-single" value="{{ $newUrl }}" readonly>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="navigator.clipboard.writeText(document.getElementById('pnedu-new-order-url-single').value); this.textContent='Skopiowano!'; setTimeout(() => this.textContent='Kopiuj', 2000);">
                    Kopiuj
                </button>
                <a href="{{ $newUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Otwórz</a>
            </div>
        @else
            <p class="small text-muted">Wiele wariantów — wyślij link z właściwym wariantem cenowym:</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Wariant</th>
                            <th>Cena</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeVariants as $variant)
                            @php
                                $newUrl = CourseFormOrderBillingService::newOrderFormUrl($course, (int) $variant->id);
                                $inputId = 'pnedu-new-order-url-v'.$variant->id;
                            @endphp
                            <tr>
                                <td>{{ $variant->name }}</td>
                                <td>{{ number_format($variant->getCurrentPrice(), 2, ',', ' ') }} PLN</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control font-monospace" id="{{ $inputId }}" value="{{ $newUrl }}" readonly>
                                        <button type="button" class="btn btn-outline-secondary"
                                                onclick="navigator.clipboard.writeText(document.getElementById('{{ $inputId }}').value); this.textContent='OK'; setTimeout(() => this.textContent='Kopiuj', 2000);">
                                            Kopiuj
                                        </button>
                                        <a href="{{ $newUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Otwórz</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h6 class="fw-semibold mt-3">Zamówienia powiązane z tym szkoleniem</h6>
        @if($courseFormOrders->isEmpty())
            <p class="text-muted small mb-0">Brak zamówień w bazie.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nabywca / zamawiający</th>
                            <th>Kwota</th>
                            <th>Faktura</th>
                            <th>Link edycji (PNEDU)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($courseFormOrders as $order)
                            @php
                                $hasInv = CourseFormOrderBillingService::hasMeaningfulInvoice($order->invoice_number);
                                $editUrl = ($order->ident && $pneduBase !== '')
                                    ? CourseFormOrderBillingService::editOrderFormUrl($course, $order->ident)
                                    : null;
                                $editInputId = 'pnedu-edit-order-'.$order->id;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('form-orders.show', $order->id) }}">#{{ $order->id }}</a>
                                </td>
                                <td>
                                    <div>{{ $order->buyer_name ?: $order->orderer_name ?: '—' }}</div>
                                    @if($order->buyer_nip)
                                        <small class="text-muted">NIP: {{ $order->buyer_nip }}</small>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $order->product_price, 2, ',', ' ') }} PLN</td>
                                <td>
                                    @if($hasInv)
                                        <span class="badge bg-success">{{ $order->invoice_number }}</span>
                                    @else
                                        <span class="badge bg-danger">Brak FV</span>
                                    @endif
                                </td>
                                <td>
                                    @if($editUrl)
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control font-monospace" id="{{ $editInputId }}" value="{{ $editUrl }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary"
                                                    onclick="navigator.clipboard.writeText(document.getElementById('{{ $editInputId }}').value); this.textContent='OK'; setTimeout(() => this.textContent='Kopiuj', 2000);">
                                                Kopiuj
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">Panel</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
