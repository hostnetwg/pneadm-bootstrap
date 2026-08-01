<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Import #{{ $import->id }}</h2>
            <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-sm btn-outline-secondary">Lista importów</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="row g-2 small">
                        <div class="col-md-4"><span class="text-muted">Plik:</span> {{ $import->original_filename }}</div>
                        <div class="col-md-3">
                            <span class="text-muted">Okres:</span>
                            {{ $import->period_from?->format('Y-m-d') ?? '—' }} → {{ $import->period_to?->format('Y-m-d') ?? '—' }}
                        </div>
                        <div class="col-md-2"><span class="text-muted">Wpływy:</span> {{ $import->rows_incoming }}</div>
                        <div class="col-md-3"><span class="text-muted">Wgrał:</span> {{ $import->uploader?->name ?? '—' }} {{ $import->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <form method="POST" action="{{ route('accounting.bank-imports.rematch', $import) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">Przelicz sugestie</button>
                        </form>
                        <span class="small text-muted align-self-center">Po zmianie reguł dopasowania (np. nazwisko nabywcy). Zaakceptowane pozostają bez zmian.</span>
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        Akceptacja dopasowania zapisuje działanie na sprawie lokalnie. Przy zgodnej kwocie możesz też
                        zarejestrować wpłatę w iFirma albo sprawdzić w podglądzie, czy faktura jest już tam opłacona.
                        Dopasowanie powstaje z <strong>treści tytułu/opisu przelewu</strong>
                        (numer FV, KSeF, #ID zamówienia, NIP, albo imię i nazwisko nabywcy gdy FV bez NIP)
                        oraz porównania <strong>kwoty</strong> z zamówieniem/sprawą — nie z e-maila ani adresu.
                        Porównanie nazwiska ignoruje wielkość liter (<code>Jan Kowalski</code> = <code>JAN KOWALSKI</code>).
                        Ikona <i class="bi bi-eye"></i> otwiera porównanie przelewu z zamówieniem.
                    </p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @php
                    $filters = [
                        'unmatched' => [
                            'label' => 'Do przeglądu ('.$counts['unmatched'].')',
                            'title' => null,
                        ],
                        'unlinked' => [
                            'label' => 'Bez powiązania ('.$counts['unlinked'].')',
                            'title' => '<div class="text-start"><strong>Bez powiązania</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>brak aktywnej sugestii dla przelewu</li>'
                                .'<li>albo wszystkie sugestie zostały odrzucone</li>'
                                .'<li>nie obejmuje zaakceptowanych ani ignorowanych</li>'
                                .'</ul></div>',
                        ],
                        'high' => [
                            'label' => 'High ('.$counts['high'].')',
                            'title' => '<div class="text-start"><strong>Wysoka pewność</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>numer FV z tytułu przelewu = FV w systemie <em>oraz</em> kwota się zgadza</li>'
                                .'<li>albo numer KSeF z tytułu = zamówienie <em>oraz</em> kwota się zgadza</li>'
                                .'<li class="text-warning-emphasis">nie High, gdy KSeF w tytule ≠ KSeF zamówienia albo nadawca nie pasuje do nabywcy/odbiorcy</li>'
                                .'</ul></div>',
                        ],
                        'medium' => [
                            'label' => 'Medium ('.$counts['medium'].')',
                            'title' => '<div class="text-start"><strong>Średnia pewność</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>numer FV lub KSeF znaleziony, ale kwota się nie zgadza</li>'
                                .'<li>albo w tytule jest #ID / zamówienie ID</li>'
                                .'<li>albo imię i nazwisko nabywcy (tylko FV bez NIP) w tytule <em>oraz</em> kwota się zgadza</li>'
                                .'<li>albo FV+kwota OK, ale nadawca ≠ nabywca/odbiorca (możliwy błędny numer FV)</li>'
                                .'</ul></div>',
                        ],
                        'low' => [
                            'label' => 'Low ('.$counts['low'].')',
                            'title' => '<div class="text-start"><strong>Niska pewność</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>NIP z tytułu przelewu + zgodna kwota z zamówieniem</li>'
                                .'<li>albo konflikt: KSeF w tytule przelewu ≠ KSeF na zamówieniu (mimo zgodnego FV)</li>'
                                .'<li>tylko sugestia do ręcznej weryfikacji</li>'
                                .'</ul></div>',
                        ],
                        'accepted' => [
                            'label' => 'Zaakceptowane ('.$counts['accepted'].')',
                            'title' => null,
                        ],
                        'ignored' => [
                            'label' => 'Ignorowane ('.$counts['ignored'].')',
                            'title' => null,
                        ],
                        'all' => [
                            'label' => 'Wszystkie wpływy',
                            'title' => null,
                        ],
                    ];
                @endphp
                @foreach($filters as $key => $item)
                    <a href="{{ route('accounting.bank-imports.show', ['bankImport' => $import, 'filter' => $key]) }}"
                       class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
                       @if($item['title'])
                           data-bs-toggle="tooltip"
                           data-bs-placement="bottom"
                           data-bs-html="true"
                           data-bs-custom-class="bank-import-filter-tooltip"
                           title="{{ $item['title'] }}"
                       @endif
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 2.5rem;"></th>
                                    <th style="min-width: 6rem;">Data</th>
                                    <th style="min-width: 6rem;">Kwota</th>
                                    <th>Opis / sugestia</th>
                                    <th style="min-width: 14rem;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $tx)
                                    @php
                                        $suggested = $tx->matches->where('status', \App\Models\BankTransactionMatch::STATUS_SUGGESTED);
                                        $accepted = $tx->matches->firstWhere('status', \App\Models\BankTransactionMatch::STATUS_ACCEPTED);
                                        $best = $accepted ?: $suggested->sortBy(fn ($m) => match ($m->confidence) {
                                            'high' => 0, 'medium' => 1, default => 2,
                                        })->first();
                                        $order = $best?->formOrder;
                                        $course = $order?->course;
                                        $courseTitle = $course
                                            ? $course->plainTitle((string) ($order->product_name ?: 'Szkolenie'))
                                            : (string) ($order?->product_name ?: '—');
                                        $courseDate = $course?->start_date
                                            ? $course->start_date->timezone(config('app.timezone'))->format('d.m.Y H:i')
                                            : null;
                                        $courseInstructor = trim(
                                            ($course?->instructor?->first_name ?? '').' '.($course?->instructor?->last_name ?? '')
                                        );
                                        $productLabel = $courseTitle;
                                        if ($courseDate) {
                                            $productLabel .= ' ('.$courseDate.')';
                                        }
                                        if ($courseInstructor !== '') {
                                            $productLabel .= ' — '.$courseInstructor;
                                        }
                                        $courseId = $course?->id;
                                        $descParts = app(\App\Services\Bank\MbankStatementParser::class)
                                            ->splitDescriptionParts((string) $tx->description);
                                        $titleExtracted = app(\App\Services\Bank\PaymentTitleExtractor::class)
                                            ->extract((string) $tx->description);
                                        $ksefFromTitle = $titleExtracted['ksef_numbers'] !== []
                                            ? implode(', ', $titleExtracted['ksef_numbers'])
                                            : '—';
                                        $invoiceFromTitle = $titleExtracted['invoice_numbers'] !== []
                                            ? implode(', ', $titleExtracted['invoice_numbers'])
                                            : '—';
                                        $preview = [
                                            'tx' => [
                                                'id' => $tx->id,
                                                'date' => $tx->operation_date?->format('Y-m-d') ?? '—',
                                                'amount' => number_format((float) $tx->amount, 2, ',', ' ').' '.$tx->currency,
                                                'category' => $tx->category ?: '—',
                                                'account' => $tx->account_label ?: '—',
                                                'counterparty' => $tx->counterparty_account ?: ($descParts['counterparty_account'] ?: '—'),
                                                'transfer_type' => $descParts['transfer_type'] ?: '—',
                                                'sender_estimate' => $descParts['sender_estimate'] ?: '—',
                                                'title_estimate' => $descParts['title_estimate'] ?: '—',
                                                'invoice_from_title' => $invoiceFromTitle,
                                                'ksef_from_title' => $ksefFromTitle,
                                                'description' => $tx->description,
                                            ],
                                            'match' => $best ? [
                                                'status' => $best->statusLabel(),
                                                'confidence' => $best->confidenceLabel(),
                                                'confidence_class' => $best->confidenceBadgeClass(),
                                                'reasons' => $best->reasonLabels(),
                                                'reason_codes' => array_values($best->match_reasons ?? []),
                                                'debt_case_id' => $best->debt_case_id,
                                                'debt_case_url' => $best->debt_case_id
                                                    ? route('accounting.collections.show', $best->debt_case_id)
                                                    : null,
                                            ] : null,
                                            'order' => $order ? [
                                                'id' => $order->id,
                                                'url' => route('form-orders.show', $order->id),
                                                'invoice' => $order->invoice_number ?: '—',
                                                'ksef' => $order->ksef_number ?: '—',
                                                'amount' => $order->product_price !== null
                                                    ? number_format((float) $order->product_price, 2, ',', ' ').' PLN'
                                                    : '—',
                                                'product' => $productLabel !== '' ? $productLabel : '—',
                                                'course_id' => $courseId,
                                                'course_url' => $courseId ? route('courses.show', $courseId) : null,
                                                'buyer_name' => $order->buyer_name ?: '—',
                                                'buyer_nip' => $order->buyer_nip ?: 'brak NIP',
                                                'buyer_address' => trim(implode(', ', array_filter([
                                                    $order->buyer_address,
                                                    trim(($order->buyer_postal_code ?? '').' '.($order->buyer_city ?? '')),
                                                ]))) ?: '—',
                                                'recipient_name' => $order->recipient_name ?: '—',
                                                'recipient_nip' => $order->recipient_nip ?: 'brak NIP',
                                                'recipient_address' => trim(implode(', ', array_filter([
                                                    $order->recipient_address,
                                                    trim(($order->recipient_postal_code ?? '').' '.($order->recipient_city ?? '')),
                                                ]))) ?: '—',
                                                'participant_name' => trim((string) ($order->display_participant_name ?? '')) ?: '—',
                                                'participant_email' => trim((string) ($order->display_participant_email ?? '')) ?: '',
                                                'orderer_name' => trim((string) ($order->orderer_name ?? '')) ?: '—',
                                                'orderer_email' => trim((string) ($order->orderer_email ?? '')) ?: '',
                                                'order_date' => $order->order_date
                                                    ? (\Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d'))
                                                    : '—',
                                            ] : null,
                                        ];
                                        $suggestBest = ($suggested->isNotEmpty() && ! $accepted)
                                            ? $suggested->sortBy(fn ($m) => match ($m->confidence) {
                                                'high' => 0, 'medium' => 1, default => 2,
                                            })->first()
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary border-0 px-1 bank-tx-preview-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#bankTxPreviewModal"
                                                    data-tx-id="{{ $tx->id }}"
                                                    data-tx-amount="{{ number_format((float) $tx->amount, 2, '.', '') }}"
                                                    data-link-url="{{ route('accounting.bank-imports.transactions.link-case', [$import, $tx]) }}"
                                                    data-preview="{{ json_encode($preview, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
                                                    @if($suggestBest)
                                                        data-can-act="match"
                                                        data-accept-url="{{ route('accounting.bank-imports.matches.accept', [$import, $suggestBest]) }}"
                                                        data-ifirma-status-url="{{ route('accounting.bank-imports.matches.ifirma-status', [$import, $suggestBest]) }}"
                                                        data-reject-url="{{ route('accounting.bank-imports.matches.reject', [$import, $suggestBest]) }}"
                                                        data-ignore-url="{{ route('accounting.bank-imports.matches.ignore', [$import, $suggestBest]) }}"
                                                        @if(in_array('amount_mismatch', $suggestBest->match_reasons ?? [], true)) data-amount-mismatch="1" @endif
                                                    @elseif(! $accepted)
                                                        data-can-act="ignore-tx"
                                                        data-ignore-url="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}"
                                                    @else
                                                        data-can-act="0"
                                                        data-register-ifirma-url="{{ route('accounting.bank-imports.matches.register-ifirma-payment', [$import, $accepted]) }}"
                                                    @endif
                                                    title="Podgląd przelewu i zamówienia"
                                                    aria-label="Podgląd przelewu i zamówienia">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                        <td class="small">{{ $tx->operation_date?->format('Y-m-d') }}</td>
                                        <td class="fw-semibold text-nowrap">
                                            {{ number_format((float) $tx->amount, 2, ',', ' ') }} {{ $tx->currency }}
                                        </td>
                                        <td>
                                            <div class="small text-break" style="max-width: 42rem;">{{ \Illuminate\Support\Str::limit($tx->description, 220) }}</div>
                                            @if($accepted)
                                                <div class="mt-1">
                                                    <span class="badge text-bg-success">Zaakceptowane</span>
                                                    @if($accepted->debtCase)
                                                        <a href="{{ route('accounting.collections.show', $accepted->debtCase) }}">Sprawa #{{ $accepted->debt_case_id }}</a>
                                                    @endif
                                                    @if($accepted->form_order_id)
                                                        <span class="text-muted">·</span>
                                                        <a href="{{ route('form-orders.show', $accepted->form_order_id) }}">Zam. #{{ $accepted->form_order_id }}</a>
                                                    @endif
                                                </div>
                                            @elseif($best)
                                                <div class="mt-1 d-flex flex-wrap align-items-center gap-2">
                                                    <span class="badge {{ $best->confidenceBadgeClass() }}">{{ $best->confidenceLabel() }}</span>
                                                    @if($best->form_order_id)
                                                        <a href="{{ route('form-orders.show', $best->form_order_id) }}">Zam. #{{ $best->form_order_id }}</a>
                                                        @if($best->formOrder?->invoice_number)
                                                            <span class="text-muted">FV w systemie: {{ $best->formOrder->invoice_number }}</span>
                                                        @endif
                                                    @endif
                                                    @if($best->debt_case_id)
                                                        <a href="{{ route('accounting.collections.show', $best->debt_case_id) }}">Sprawa #{{ $best->debt_case_id }}</a>
                                                    @elseif($best->form_order_id)
                                                        <span class="badge text-bg-light border">Utworzy sprawę przy akceptacji</span>
                                                    @endif
                                                    @if($suggested->count() > 1)
                                                        <span class="badge text-bg-warning">Do ręcznej weryfikacji ({{ $suggested->count() }})</span>
                                                    @endif
                                                </div>
                                                @if(count($best->reasonLabels()))
                                                    <div class="mt-1 small">
                                                        <span class="text-muted">Podstawa dopasowania:</span>
                                                        <ul class="mb-0 ps-3">
                                                            @foreach($best->reasonLabels() as $reasonLabel)
                                                                <li>{{ $reasonLabel }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="mt-1 small text-muted">Brak sugestii</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @if($suggestBest)
                                                    <form method="POST"
                                                          action="{{ route('accounting.bank-imports.matches.accept', [$import, $suggestBest]) }}"
                                                          class="bank-import-accept-form"
                                                          @if(in_array('amount_mismatch', $suggestBest->match_reasons ?? [], true)) data-amount-mismatch="1" @endif>
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">
                                                        <input type="hidden" name="ifirma_already_paid" value="0" class="bank-import-ifirma-already-paid">
                                                        <button type="submit" class="btn btn-sm btn-success">Akceptuj</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('accounting.bank-imports.matches.reject', [$import, $suggestBest]) }}">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Odrzuć</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('accounting.bank-imports.matches.ignore', [$import, $suggestBest]) }}">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Ignoruj</button>
                                                    </form>
                                                @elseif(! $accepted)
                                                    <form method="POST" action="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Ignoruj transakcję</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Brak transakcji dla tego filtra.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($transactions->hasPages())
                    <div class="card-footer">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankTxPreviewModal" tabindex="-1" aria-labelledby="bankTxPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable bank-tx-preview-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bankTxPreviewModalLabel">Podgląd dopasowania</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="border rounded h-100 p-3">
                                <h6 class="fw-semibold mb-3">Przelew z wyciągu</h6>
                                <dl class="row small mb-0" id="bankTxPreviewTransfer">
                                    <dt class="col-sm-4 text-muted">Data</dt><dd class="col-sm-8" data-field="date">—</dd>
                                    <dt class="col-sm-4 text-muted">Kwota</dt><dd class="col-sm-8 fw-semibold" data-field="amount">—</dd>
                                    <dt class="col-sm-4 text-muted">Kategoria</dt><dd class="col-sm-8" data-field="category">—</dd>
                                    <dt class="col-sm-4 text-muted">Rachunek PNE</dt><dd class="col-sm-8" data-field="account">—</dd>
                                    <dt class="col-sm-4 text-muted">Rachunek nadawcy</dt><dd class="col-sm-8" data-field="counterparty">—</dd>
                                    <dt class="col-sm-4 text-muted">Typ przelewu</dt><dd class="col-sm-8" data-field="transfer_type">—</dd>
                                    <dt class="col-sm-4 text-muted">Nadawca <span class="fw-normal">(szacunek)</span></dt><dd class="col-sm-8 text-break" data-field="sender_estimate">—</dd>
                                    <dt class="col-sm-4 text-muted">Tytuł <span class="fw-normal">(szacunek)</span></dt><dd class="col-sm-8 text-break" data-field="title_estimate">—</dd>
                                    <dt class="col-sm-4 text-muted">FV <span class="fw-normal">(z opisu)</span></dt><dd class="col-sm-8 text-break" data-field="invoice_from_title">—</dd>
                                    <dt class="col-sm-4 text-muted">KSeF <span class="fw-normal">(z tytułu)</span></dt><dd class="col-sm-8 text-break" data-field="ksef_from_title">—</dd>
                                    <dt class="col-sm-4 text-muted">Opis surowy</dt><dd class="col-sm-8 text-break text-muted" data-field="description">—</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded h-100 p-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <h6 class="fw-semibold mb-0">Zamówienie / sugestia</h6>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary d-none"
                                            id="bankTxPreviewClearOrderBtn"
                                            title="Przywróć oryginalną sugestię przelewu">
                                        Wyczyść
                                    </button>
                                </div>
                                <div id="bankTxPreviewNoOrder" class="small text-muted d-none">Brak powiązanego zamówienia w sugestii.</div>
                                <div id="bankTxPreviewOrderBlock">
                                    <div class="mb-2" id="bankTxPreviewMatchMeta"></div>
                                    <dl class="row small mb-0" id="bankTxPreviewOrder">
                                        <dt class="col-sm-4 text-muted">Zamówienie</dt><dd class="col-sm-8" data-field="id">—</dd>
                                        <dt class="col-sm-4 text-muted">Faktura</dt><dd class="col-sm-8" data-field="invoice">—</dd>
                                        <dt class="col-sm-4 text-muted">KSeF</dt><dd class="col-sm-8" data-field="ksef">—</dd>
                                        <dt class="col-sm-4 text-muted">Kwota FV</dt><dd class="col-sm-8 fw-semibold" data-field="amount">—</dd>
                                        <dt class="col-sm-4 text-muted">Szkolenie</dt><dd class="col-sm-8" data-field="product">—</dd>
                                        <dt class="col-sm-4 text-muted">Data zamówienia</dt><dd class="col-sm-8" data-field="order_date">—</dd>
                                        <dt class="col-sm-4 text-muted fw-bold">Nabywca</dt><dd class="col-sm-8 fw-semibold" data-field="buyer_name">—</dd>
                                        <dt class="col-sm-4 text-muted">NIP nabywcy</dt><dd class="col-sm-8" data-field="buyer_nip">—</dd>
                                        <dt class="col-sm-4 text-muted">Adres nabywcy</dt><dd class="col-sm-8" data-field="buyer_address">—</dd>
                                        <dt class="col-sm-4 text-muted fw-bold">Odbiorca</dt><dd class="col-sm-8 fw-semibold" data-field="recipient_name">—</dd>
                                        <dt class="col-sm-4 text-muted">NIP odbiorcy</dt><dd class="col-sm-8" data-field="recipient_nip">—</dd>
                                        <dt class="col-sm-4 text-muted">Adres odbiorcy</dt><dd class="col-sm-8" data-field="recipient_address">—</dd>
                                        <dt class="col-sm-4 text-muted">Uczestnik</dt><dd class="col-sm-8" data-field="participant_name">—</dd>
                                        <dt class="col-sm-4 text-muted">Zamawiający</dt><dd class="col-sm-8" data-field="orderer_name">—</dd>
                                    </dl>
                                    <div class="mt-3 small" id="bankTxPreviewReasons"></div>
                                    <div class="mt-2 d-flex flex-wrap gap-2" id="bankTxPreviewLinks"></div>
                                    <div class="mt-3 d-none" id="bankTxPreviewIfirmaStatus"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3 d-none" id="bankTxManualLinkPanel">
                        <h6 class="fw-semibold mb-2">Powiąż ręcznie ze sprawą lub zamówieniem</h6>
                        <p class="small text-muted mb-2">
                            Szukaj po FV, KSeF, ID sprawy/zamówienia, NIP, nazwie, adresie/mieście nabywcy lub odbiorcy albo e-mailu.
                            Wielkość liter nie ma znaczenia.
                            Najpierw pokazujemy <strong>niezamknięte sprawy</strong>, potem zamówienia <strong>bez aktywnej sprawy</strong>
                            (np. gdy wpłatę oznaczono wcześniej tylko w iFirma).
                            Kliknij <strong>oko</strong>, żeby zobaczyć dane w prawej kolumnie i dopiero stamtąd powiązać.
                        </p>
                        <div class="input-group input-group-sm mb-2" style="max-width: 36rem;">
                            <input type="text"
                                   id="bankTxManualLookupInput"
                                   class="form-control"
                                   placeholder="np. Kurzętnik, 343/7/2026, NIP…"
                                   maxlength="128"
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="bankTxManualLookupBtn">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                        </div>
                        <div class="form-text mb-2" id="bankTxManualLookupStatus"></div>
                        <div id="bankTxManualLookupResults"></div>
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2 justify-content-between">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="bankTxPreviewPrevBtn">
                            <i class="bi bi-chevron-left"></i> Poprzedni
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="bankTxPreviewNextBtn">
                            Następny <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2" id="bankTxPreviewActionButtons">
                        <form method="POST" id="bankTxPreviewAcceptForm" class="d-none bank-import-accept-form">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">
                            <input type="hidden" name="ifirma_already_paid" value="0" class="bank-import-ifirma-already-paid">
                            <button type="submit" class="btn btn-success">Akceptuj</button>
                        </form>
                        <form method="POST" id="bankTxPreviewRejectForm" class="d-none">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <button type="submit" class="btn btn-outline-danger">Odrzuć</button>
                        </form>
                        <form method="POST" id="bankTxPreviewIgnoreForm" class="d-none">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <button type="submit" class="btn btn-outline-secondary" id="bankTxPreviewIgnoreBtn">Ignoruj</button>
                        </form>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportManualLinkConfirmModal" tabindex="-1" aria-labelledby="bankImportManualLinkConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankImportManualLinkConfirmForm">
                    @csrf
                    <input type="hidden" name="debt_case_id" value="" id="bankImportManualLinkCaseId">
                    <input type="hidden" name="form_order_id" value="" id="bankImportManualLinkOrderId">
                    <input type="hidden" name="register_ifirma_payment" value="0" id="bankImportManualLinkRegisterIfirma">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                    <input type="hidden" name="preview" value="" id="bankImportManualLinkPreview">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bankImportManualLinkConfirmModalLabel">Potwierdź ręczne powiązanie</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="bankImportManualLinkSummary">—</p>
                        <div class="alert alert-info small mb-0 d-none" id="bankImportManualLinkIfirmaInfo">
                            Po lokalnym powiązaniu system spróbuje zarejestrować wpłatę w iFirma i odświeżyć status sprawy.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary" id="bankImportManualLinkSubmit">Powiąż lokalnie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportAcceptWarnModal" tabindex="-1" aria-labelledby="bankImportAcceptWarnModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-bg-warning">
                    <h5 class="modal-title" id="bankImportAcceptWarnModalLabel">Uwaga: kwota przelewu ≠ kwota FV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3" role="alert">
                        Kwota z wyciągu różni się od kwoty wskazanej faktury/zamówienia.
                    </div>
                    <p class="mb-2">Po akceptacji:</p>
                    <ul class="mb-3">
                        <li>przelew zostanie powiązany <strong>tylko z jedną</strong> fakturą/sprawą,</li>
                        <li>inne sugestie dla tego przelewu zostaną odrzucone,</li>
                        <li>przelew zniknie z kolejki „Do przeglądu” / Medium / High,</li>
                        <li>nie da się potem łatwo podpiąć tego samego przelewu do kolejnych FV (np. przy jednym przelewie za kilka faktur).</li>
                    </ul>
                    <p class="mb-0 small text-muted">
                        Przy różnicy kwoty <strong>nie</strong> rejestrujemy wpłaty w iFirma. Jeśli w tytule widać kilka numerów FV albo kwota jest sumą kilku faktur — lepiej najpierw wyjaśnić, zamiast akceptować.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-warning" id="bankImportAcceptWarnConfirmBtn">Akceptuj tylko lokalnie</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportAcceptIfirmaModal" tabindex="-1" aria-labelledby="bankImportAcceptIfirmaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-bg-success">
                    <h5 class="modal-title" id="bankImportAcceptIfirmaModalLabel">Akceptacja dopasowania</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Kwota przelewu zgadza się z kwotą FV/zamówienia.</p>
                    <p class="mb-3">Możesz od razu <strong>zarejestrować wpłatę w iFirma</strong> (API) i odświeżyć status na sprawie — albo zaakceptować tylko lokalnie w windykacji.</p>
                    <div class="alert alert-light border small mb-0" role="alert">
                        Rejestracja w iFirma działa dla faktur krajowych. Wymaga odnalezienia faktury w iFirma (ID lub numer FV).
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-outline-success" id="bankImportAcceptLocalOnlyBtn">Tylko lokalnie</button>
                    <button type="button" class="btn btn-success" id="bankImportAcceptIfirmaConfirmBtn">Akceptuj + wpłata w iFirma</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tooltip.bank-import-filter-tooltip .tooltip-inner {
            max-width: 22rem;
            text-align: left;
        }
        .bank-tx-preview-dialog {
            --bs-modal-width: min(1320px, 96vw);
        }
        #bankTxPreviewModal dd.bank-match-ok,
        #bankTxPreviewModal mark.bank-match-ok {
            background: #d1e7dd;
            border-radius: 0.25rem;
            padding: 0.15rem 0.35rem;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
        #bankTxPreviewModal dd.bank-match-warn,
        #bankTxPreviewModal mark.bank-match-warn {
            background: #fff3cd;
            border-radius: 0.25rem;
            padding: 0.15rem 0.35rem;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
        #bankTxPreviewModal mark.bank-match-ok,
        #bankTxPreviewModal mark.bank-match-warn {
            color: inherit;
        }
        #bankTxPreviewModal dt.bank-match-ok-label {
            color: #0f5132 !important;
            font-weight: 600;
        }
        #bankTxPreviewModal dt.bank-match-warn-label {
            color: #664d03 !important;
            font-weight: 600;
        }
        #bankImportAcceptWarnModal,
        #bankImportAcceptIfirmaModal {
            z-index: 1065;
        }
        .bank-manual-peek-btn.is-active {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .bank-manual-peek-btn.is-active:hover,
        .bank-manual-peek-btn.is-active:focus {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });

            var acceptWarnModalEl = document.getElementById('bankImportAcceptWarnModal');
            var acceptWarnModal = acceptWarnModalEl ? bootstrap.Modal.getOrCreateInstance(acceptWarnModalEl) : null;
            var acceptIfirmaModalEl = document.getElementById('bankImportAcceptIfirmaModal');
            var acceptIfirmaModal = acceptIfirmaModalEl ? bootstrap.Modal.getOrCreateInstance(acceptIfirmaModalEl) : null;
            var pendingAcceptForm = null;

            function setRegisterIfirma(form, value) {
                var input = form.querySelector('.bank-import-register-ifirma');
                if (input) {
                    input.value = value ? '1' : '0';
                }
            }

            function setIfirmaAlreadyPaid(form, value) {
                var input = form.querySelector('.bank-import-ifirma-already-paid');
                if (input) {
                    input.value = value ? '1' : '0';
                }
            }

            function submitAcceptForm(form) {
                form.setAttribute('data-accept-confirmed', '1');
                form.requestSubmit();
            }

            document.querySelectorAll('.bank-import-accept-form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (form.getAttribute('data-accept-confirmed') === '1') {
                        form.removeAttribute('data-accept-confirmed');
                        return;
                    }
                    e.preventDefault();
                    pendingAcceptForm = form;
                    if (form.getAttribute('data-amount-mismatch') === '1') {
                        setRegisterIfirma(form, false);
                        setIfirmaAlreadyPaid(form, false);
                        if (acceptWarnModal) {
                            acceptWarnModal.show();
                        }
                        return;
                    }
                    setRegisterIfirma(form, false);
                    setIfirmaAlreadyPaid(form, false);
                    if (acceptIfirmaModal) {
                        acceptIfirmaModal.show();
                    }
                });
            });

            var acceptWarnConfirmBtn = document.getElementById('bankImportAcceptWarnConfirmBtn');
            if (acceptWarnConfirmBtn) {
                acceptWarnConfirmBtn.addEventListener('click', function () {
                    if (!pendingAcceptForm) {
                        return;
                    }
                    var form = pendingAcceptForm;
                    pendingAcceptForm = null;
                    setRegisterIfirma(form, false);
                    setIfirmaAlreadyPaid(form, false);
                    if (acceptWarnModal) {
                        acceptWarnModal.hide();
                    }
                    submitAcceptForm(form);
                });
            }

            var acceptLocalOnlyBtn = document.getElementById('bankImportAcceptLocalOnlyBtn');
            if (acceptLocalOnlyBtn) {
                acceptLocalOnlyBtn.addEventListener('click', function () {
                    if (!pendingAcceptForm) {
                        return;
                    }
                    var form = pendingAcceptForm;
                    pendingAcceptForm = null;
                    setRegisterIfirma(form, false);
                    setIfirmaAlreadyPaid(form, false);
                    if (acceptIfirmaModal) {
                        acceptIfirmaModal.hide();
                    }
                    submitAcceptForm(form);
                });
            }

            var acceptIfirmaConfirmBtn = document.getElementById('bankImportAcceptIfirmaConfirmBtn');
            if (acceptIfirmaConfirmBtn) {
                acceptIfirmaConfirmBtn.addEventListener('click', function () {
                    if (!pendingAcceptForm) {
                        return;
                    }
                    var form = pendingAcceptForm;
                    pendingAcceptForm = null;
                    setRegisterIfirma(form, true);
                    setIfirmaAlreadyPaid(form, false);
                    if (acceptIfirmaModal) {
                        acceptIfirmaModal.hide();
                    }
                    submitAcceptForm(form);
                });
            }

            function esc(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function nameWithEmailTooltip(name, email) {
                var label = name || '—';
                if (!email) {
                    return esc(label);
                }
                return '<span class="text-decoration-underline" style="text-decoration-style: dotted; cursor: help;"'
                    + ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + esc(email) + '">'
                    + esc(label) + '</span>';
            }

            function disposeTooltips(root) {
                if (!root) return;
                root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    var instance = bootstrap.Tooltip.getInstance(el);
                    if (instance) instance.dispose();
                });
            }

            function initTooltips(root) {
                if (!root) return;
                root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }

            function formatMoney(value) {
                if (value === null || value === undefined || value === '') return '—';
                var number = Number(value);
                if (Number.isNaN(number)) return esc(value);
                return number.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł';
            }

            function resetIfirmaStatusPanel() {
                var panel = document.getElementById('bankTxPreviewIfirmaStatus');
                if (!panel) return;
                panel.classList.add('d-none');
                panel.innerHTML = '';
            }

            function renderIfirmaStatusPanel(data) {
                var panel = document.getElementById('bankTxPreviewIfirmaStatus');
                if (!panel) return;

                var status = data.status || '';
                var isPaid = status === 'oplacone';
                var alertClass = isPaid ? 'alert-success' : (status === 'unknown' ? 'alert-secondary' : 'alert-warning');
                var html = '<div class="alert ' + alertClass + ' mb-0 small" role="alert">'
                    + '<div class="fw-semibold mb-1">Status iFirma: ' + esc(data.status_label || '—') + '</div>'
                    + '<div>Zapłacono: ' + formatMoney(data.paid_amount) + ' / brutto: ' + formatMoney(data.gross_amount) + '</div>'
                    + '<div>Faktura: ' + esc(data.invoice_number || '—') + ' · źródło: ' + esc(data.source || '—') + '</div>';

                if (isPaid && data.can_accept_as_paid) {
                    html += '<div class="mt-2">'
                        + '<button type="button" class="btn btn-sm btn-success" id="bankTxPreviewAcceptIfirmaPaidBtn">'
                        + 'Zaakceptuj jako opłacone w iFirma'
                        + '</button>'
                        + '<div class="text-muted mt-1">Powiąże przelew lokalnie, bez rejestracji nowej wpłaty w iFirma.</div>'
                        + '</div>';
                }

                html += '</div>';
                panel.innerHTML = html;
                panel.classList.remove('d-none');

                var acceptPaidBtn = document.getElementById('bankTxPreviewAcceptIfirmaPaidBtn');
                if (acceptPaidBtn) {
                    acceptPaidBtn.addEventListener('click', function () {
                        var form = document.getElementById('bankTxPreviewAcceptForm');
                        if (!form || form.classList.contains('d-none')) return;
                        setRegisterIfirma(form, false);
                        setIfirmaAlreadyPaid(form, true);
                        submitAcceptForm(form);
                    });
                }
            }

            function checkIfirmaStatus(url, button) {
                if (!url) return;

                resetIfirmaStatusPanel();
                var originalText = button ? button.textContent : '';
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Sprawdzam...';
                }

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({}),
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) {
                                throw data;
                            }
                            return data;
                        });
                    })
                    .then(renderIfirmaStatusPanel)
                    .catch(function (error) {
                        var panel = document.getElementById('bankTxPreviewIfirmaStatus');
                        if (panel) {
                            panel.innerHTML = '<div class="alert alert-danger mb-0 small" role="alert">'
                                + esc(error && error.message ? error.message : 'Nie udało się pobrać statusu z iFirma.')
                                + '</div>';
                            panel.classList.remove('d-none');
                        }
                    })
                    .finally(function () {
                        if (button) {
                            button.disabled = false;
                            button.textContent = originalText || 'Sprawdź status z iFirma';
                        }
                    });
            }

            function fillDl(root, data) {
                if (!root || !data) return;
                disposeTooltips(root);
                root.querySelectorAll('[data-field]').forEach(function (el) {
                    var key = el.getAttribute('data-field');
                    var value = data[key];
                    if (key === 'id' && data.url) {
                        el.innerHTML = '<a href="' + esc(data.url) + '" target="_blank" rel="noopener">#' + esc(data.id) + '</a>';
                    } else if (key === 'product' && data.course_id && data.course_url) {
                        el.innerHTML = '<a href="' + esc(data.course_url) + '" target="_blank" rel="noopener">#' + esc(data.course_id) + '</a> '
                            + esc(value || '—');
                    } else if (key === 'participant_name') {
                        el.innerHTML = nameWithEmailTooltip(value, data.participant_email);
                    } else if (key === 'orderer_name') {
                        el.innerHTML = nameWithEmailTooltip(value, data.orderer_email);
                    } else if (key === 'description') {
                        el.textContent = value || '—';
                    } else {
                        el.textContent = value || '—';
                    }
                });
                initTooltips(root);
            }

            function clearMatchHighlights(modal) {
                if (!modal) return;
                modal.querySelectorAll('dd.bank-match-ok, dd.bank-match-warn, dt.bank-match-ok-label, dt.bank-match-warn-label')
                    .forEach(function (el) {
                        el.classList.remove('bank-match-ok', 'bank-match-warn', 'bank-match-ok-label', 'bank-match-warn-label');
                    });
            }

            function highlightField(root, field, kind) {
                if (!root) return;
                var dd = root.querySelector('[data-field="' + field + '"]');
                if (!dd) return;
                dd.classList.add(kind === 'warn' ? 'bank-match-warn' : 'bank-match-ok');
                var dt = dd.previousElementSibling;
                if (dt && dt.tagName === 'DT') {
                    dt.classList.add(kind === 'warn' ? 'bank-match-warn-label' : 'bank-match-ok-label');
                }
            }

            function escapeRegExp(value) {
                return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function highlightFragmentsInText(el, fragments, kind) {
                if (!el || !fragments || !fragments.length) return;
                var text = el.textContent || '';
                if (!text) return;

                var unique = [];
                fragments.forEach(function (f) {
                    f = String(f || '').trim();
                    if (!f || f === '—') return;
                    if (unique.some(function (u) { return u.toLowerCase() === f.toLowerCase(); })) return;
                    unique.push(f);
                });
                if (!unique.length) return;

                unique.sort(function (a, b) { return b.length - a.length; });

                var pattern = unique.map(escapeRegExp).join('|');
                if (!pattern) return;

                var re = new RegExp('(' + pattern + ')', 'gi');
                var css = kind === 'warn' ? 'bank-match-warn' : 'bank-match-ok';
                el.innerHTML = esc(text).replace(re, function (match) {
                    return '<mark class="' + css + ' px-0">' + esc(match) + '</mark>';
                });

                if (el.querySelector('mark')) {
                    el.classList.remove('text-muted');
                    var dt = el.previousElementSibling;
                    if (dt && dt.tagName === 'DT') {
                        dt.classList.add(kind === 'warn' ? 'bank-match-warn-label' : 'bank-match-ok-label');
                    }
                }
            }

            function applyMatchHighlights(modal, reasonCodes, txData) {
                clearMatchHighlights(modal);
                if (!reasonCodes || !reasonCodes.length) return;

                var txRoot = document.getElementById('bankTxPreviewTransfer');
                var orderRoot = document.getElementById('bankTxPreviewOrder');
                var codes = reasonCodes.map(String);
                var rawFragments = [];
                var rawKind = 'ok';

                codes.forEach(function (code) {
                    if (code === 'amount_match') {
                        highlightField(txRoot, 'amount', 'ok');
                        highlightField(orderRoot, 'amount', 'ok');
                        if (txData && txData.amount) {
                            // np. "365,00 PLN" → spróbuj też "365,00"
                            var amountCore = String(txData.amount).replace(/\s*PLN\s*$/i, '').trim();
                            rawFragments.push(amountCore);
                            rawFragments.push(amountCore.replace(/\s/g, ''));
                        }
                        return;
                    }
                    if (code === 'amount_mismatch') {
                        highlightField(txRoot, 'amount', 'warn');
                        highlightField(orderRoot, 'amount', 'warn');
                        rawKind = 'warn';
                        return;
                    }
                    if (code.indexOf('invoice_number:') === 0 || code.indexOf('debt_case_invoice_number:') === 0) {
                        highlightField(txRoot, 'invoice_from_title', 'ok');
                        highlightField(orderRoot, 'invoice', 'ok');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        return;
                    }
                    if (code.indexOf('ksef_number:') === 0) {
                        highlightField(txRoot, 'ksef_from_title', 'ok');
                        highlightField(orderRoot, 'ksef', 'ok');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        return;
                    }
                    if (code.indexOf('order_id:') === 0) {
                        highlightField(txRoot, 'title_estimate', 'ok');
                        highlightField(orderRoot, 'id', 'ok');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        rawFragments.push('#' + code.split(':').slice(1).join(':'));
                        return;
                    }
                    if (code.indexOf('nip:') === 0) {
                        highlightField(txRoot, 'title_estimate', 'ok');
                        highlightField(orderRoot, 'buyer_nip', 'ok');
                        highlightField(orderRoot, 'recipient_nip', 'ok');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        return;
                    }
                    if (code.indexOf('buyer_name:') === 0) {
                        highlightField(txRoot, 'sender_estimate', 'ok');
                        highlightField(orderRoot, 'buyer_name', 'ok');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        return;
                    }
                    if (code.indexOf('ksef_mismatch:') === 0) {
                        highlightField(txRoot, 'ksef_from_title', 'warn');
                        highlightField(orderRoot, 'ksef', 'warn');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        rawKind = 'warn';
                        return;
                    }
                    if (code.indexOf('invoice_number_mismatch:') === 0) {
                        highlightField(txRoot, 'invoice_from_title', 'warn');
                        highlightField(orderRoot, 'invoice', 'warn');
                        rawFragments.push(code.split(':').slice(1).join(':'));
                        rawKind = 'warn';
                        return;
                    }
                    if (code === 'party_name_mismatch') {
                        highlightField(txRoot, 'sender_estimate', 'warn');
                        highlightField(orderRoot, 'buyer_name', 'warn');
                        highlightField(orderRoot, 'recipient_name', 'warn');
                    }
                });

                var descEl = txRoot ? txRoot.querySelector('[data-field="description"]') : null;
                highlightFragmentsInText(descEl, rawFragments, rawKind);
            }

            var modalEl = document.getElementById('bankTxPreviewModal');
            if (!modalEl) return;

            var currentPreviewBtn = null;
            var lookupCasesUrl = @json(route('accounting.bank-imports.lookup-cases'));
            var lookupOrderPreviewUrl = @json(route('accounting.bank-imports.lookup-order-preview'));
            var csrfToken = @json(csrf_token());
            var originalOrderSnapshot = null;
            var peekedOrderId = null;
            var peekedLinkContext = null;
            var previewButtons = function () {
                return Array.prototype.slice.call(document.querySelectorAll('.bank-tx-preview-btn'));
            };

            function setManualLinkPanelVisible(visible) {
                var panel = document.getElementById('bankTxManualLinkPanel');
                if (!panel) return;
                panel.classList.toggle('d-none', !visible);
                if (!visible) {
                    var results = document.getElementById('bankTxManualLookupResults');
                    var status = document.getElementById('bankTxManualLookupStatus');
                    if (results) results.innerHTML = '';
                    if (status) status.textContent = '';
                    clearPeekState(true);
                }
            }

            function setClearPeekBtnVisible(visible) {
                var btn = document.getElementById('bankTxPreviewClearOrderBtn');
                if (btn) btn.classList.toggle('d-none', !visible);
            }

            function setActivePeekEye(orderId) {
                document.querySelectorAll('.bank-manual-peek-btn').forEach(function (btn) {
                    var active = orderId && String(btn.getAttribute('data-peek-order-id')) === String(orderId);
                    btn.classList.toggle('is-active', !!active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            function clearPeekState(restoreOriginal) {
                peekedOrderId = null;
                peekedLinkContext = null;
                setActivePeekEye(null);
                setClearPeekBtnVisible(false);
                if (restoreOriginal && originalOrderSnapshot) {
                    renderOrderPanelFromSnapshot(originalOrderSnapshot, false);
                }
            }

            function renderOrderPanelFromSnapshot(snapshot, isPeek) {
                var noOrder = document.getElementById('bankTxPreviewNoOrder');
                var orderBlock = document.getElementById('bankTxPreviewOrderBlock');
                var matchMeta = document.getElementById('bankTxPreviewMatchMeta');
                var reasonsEl = document.getElementById('bankTxPreviewReasons');
                var linksEl = document.getElementById('bankTxPreviewLinks');
                var txId = snapshot && snapshot.txId ? snapshot.txId : '';
                var order = snapshot ? snapshot.order : null;
                var match = snapshot ? snapshot.match : null;

                clearMatchHighlights(modalEl);
                resetIfirmaStatusPanel();

                if (!order) {
                    noOrder.classList.remove('d-none');
                    orderBlock.classList.add('d-none');
                    document.getElementById('bankTxPreviewModalLabel').textContent =
                        'Podgląd przelewu #' + (txId || '');
                    matchMeta.innerHTML = '';
                    reasonsEl.innerHTML = '';
                    linksEl.innerHTML = '';
                    setClearPeekBtnVisible(!!isPeek);
                    return;
                }

                noOrder.classList.add('d-none');
                orderBlock.classList.remove('d-none');
                fillDl(document.getElementById('bankTxPreviewOrder'), order);

                if (isPeek) {
                    document.getElementById('bankTxPreviewModalLabel').textContent =
                        'Podgląd przelewu #' + (txId || '') + ' · kandydat zam. #' + order.id;
                    matchMeta.innerHTML = '<span class="badge text-bg-primary">Podgląd kandydata</span>';
                    reasonsEl.innerHTML = '';
                    var peekLinks = [];
                    var ctx = peekedLinkContext || {};
                    var openLabel = ctx.kind === 'case' ? 'Sprawa' : 'Zamówienie';
                    var openUrl = ctx.itemUrl || order.url || '';
                    if (openUrl) {
                        peekLinks.push('<a class="btn btn-sm btn-outline-secondary" href="' + esc(openUrl) + '" target="_blank" rel="noopener">'
                            + esc(openLabel) + '</a>');
                    }
                    var linkAttrs = ' data-case-id="' + esc(String(ctx.caseId || '')) + '"'
                        + ' data-order-id="' + esc(String(ctx.orderId || '')) + '"'
                        + ' data-summary="' + esc(String(ctx.summary || ('zam. #' + order.id))) + '"';
                    peekLinks.push('<button type="button" class="btn btn-sm btn-outline-primary bank-manual-link-btn"'
                        + linkAttrs
                        + ' data-register-ifirma="0">Powiąż lokalnie</button>');
                    if (ctx.amountMatches) {
                        peekLinks.push('<button type="button" class="btn btn-sm btn-success bank-manual-link-btn"'
                            + linkAttrs
                            + ' data-register-ifirma="1">+ wpłata iFirma</button>');
                    } else {
                        reasonsEl.innerHTML = '<div class="text-warning">Kwota różni się od przelewu — dostępne tylko powiązanie lokalne.</div>';
                    }
                    linksEl.innerHTML = peekLinks.join(' ');
                    setClearPeekBtnVisible(true);
                    return;
                }

                document.getElementById('bankTxPreviewModalLabel').textContent =
                    'Podgląd: przelew #' + (txId || '') + ' ↔ zam. #' + order.id;

                if (match) {
                    matchMeta.innerHTML =
                        '<span class="badge ' + esc(match.confidence_class) + ' me-1">' + esc(match.confidence) + '</span>' +
                        '<span class="badge text-bg-light border">' + esc(match.status) + '</span>';
                } else {
                    matchMeta.innerHTML = '';
                }

                if (match && match.reasons && match.reasons.length) {
                    reasonsEl.innerHTML =
                        '<div class="text-muted mb-1">Podstawa dopasowania:</div><ul class="mb-0 ps-3">' +
                        match.reasons.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') +
                        '</ul>';
                } else {
                    reasonsEl.innerHTML = '';
                }

                var links = [];
                if (order.url) {
                    links.push('<a class="btn btn-sm btn-outline-primary" href="' + esc(order.url) + '" target="_blank" rel="noopener">Otwórz zamówienie</a>');
                }
                if (match && match.debt_case_url) {
                    links.push('<a class="btn btn-sm btn-outline-secondary" href="' + esc(match.debt_case_url) + '" target="_blank" rel="noopener">Otwórz sprawę #' + esc(match.debt_case_id) + '</a>');
                }
                var ifirmaStatusUrl = currentPreviewBtn ? (currentPreviewBtn.getAttribute('data-ifirma-status-url') || '') : '';
                if (ifirmaStatusUrl && currentPreviewBtn && currentPreviewBtn.getAttribute('data-can-act') === 'match') {
                    links.push('<button type="button" class="btn btn-sm btn-outline-success" id="bankTxPreviewIfirmaStatusBtn">Sprawdź status z iFirma</button>');
                }
                var registerIfirmaUrl = currentPreviewBtn ? (currentPreviewBtn.getAttribute('data-register-ifirma-url') || '') : '';
                if (registerIfirmaUrl) {
                    links.push('<form method="POST" action="' + esc(registerIfirmaUrl) + '" class="d-inline">'
                        + '<input type="hidden" name="_token" value="' + esc(csrfToken) + '">'
                        + '<input type="hidden" name="filter" value="{{ $filter }}">'
                        + '<input type="hidden" name="preview" value="' + esc(txId || '') + '">'
                        + '<button type="submit" class="btn btn-sm btn-outline-success">Zarejestruj wpłatę iFirma</button>'
                        + '</form>');
                }
                linksEl.innerHTML = links.join(' ');
                var ifirmaStatusBtn = document.getElementById('bankTxPreviewIfirmaStatusBtn');
                if (ifirmaStatusBtn) {
                    ifirmaStatusBtn.addEventListener('click', function () {
                        checkIfirmaStatus(ifirmaStatusUrl, ifirmaStatusBtn);
                    });
                }

                applyMatchHighlights(modalEl, match ? match.reason_codes : [], snapshot.txData || {});
                setClearPeekBtnVisible(false);
            }

            function amountsClose(a, b) {
                return Math.abs(Number(a) - Number(b)) <= 0.01;
            }

            function renderManualLookupResults(payload, txAmount) {
                var root = document.getElementById('bankTxManualLookupResults');
                if (!root) return;

                var cases = payload.cases || [];
                var orders = payload.orders || [];
                if (!cases.length && !orders.length) {
                    root.innerHTML = '<div class="small text-muted">Brak spraw ani zamówień dla tego zapytania.</div>';
                    return;
                }

                function rowHtml(item, kind) {
                    var amountMatches = amountsClose(txAmount, item.amount_gross || 0);
                    var courseDateBit = item.course_date ? ('szkol. ' + item.course_date) : '';
                    var summary = kind === 'case'
                        ? ('Sprawa #' + item.id
                            + (item.invoice_number ? ' · FV ' + item.invoice_number : '')
                            + (item.order_id ? ' · zam. #' + item.order_id : '')
                            + (item.order_date ? ' · zam. ' + item.order_date : '')
                            + ' · ' + Number(item.amount_gross || 0).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł')
                        : ('Zamówienie #' + item.id
                            + (item.order_date ? ' · ' + item.order_date : '')
                            + (item.invoice_number ? ' · FV ' + item.invoice_number : '')
                            + ' · ' + Number(item.amount_gross || 0).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł'
                            + ' · bez aktywnej sprawy');
                    var meta = [
                        kind === 'case' ? (item.status_label || '') : 'Utworzy sprawę przy powiązaniu',
                        courseDateBit,
                        item.product_name || '',
                        item.buyer_name || '',
                        item.recipient_name || ''
                    ].filter(Boolean).join(' · ');

                    var peekOrderId = kind === 'case' ? item.order_id : item.id;
                    var eyeHtml = peekOrderId
                        ? ('<button type="button" class="btn btn-sm btn-outline-secondary bank-manual-peek-btn flex-shrink-0'
                            + (peekedOrderId && String(peekedOrderId) === String(peekOrderId) ? ' is-active' : '')
                            + '" data-peek-order-id="' + esc(String(peekOrderId)) + '"'
                            + ' data-kind="' + esc(kind) + '"'
                            + ' data-case-id="' + esc(kind === 'case' ? String(item.id) : '') + '"'
                            + ' data-order-id="' + esc(kind === 'order' ? String(item.id) : '') + '"'
                            + ' data-item-url="' + esc(item.url || '') + '"'
                            + ' data-summary="' + esc(summary) + '"'
                            + ' data-amount-matches="' + (amountMatches ? '1' : '0') + '"'
                            + ' title="Podgląd w prawej kolumnie — potem powiąż"'
                            + ' aria-label="Podgląd zamówienia #' + esc(String(peekOrderId)) + '"'
                            + ' aria-pressed="' + (peekedOrderId && String(peekedOrderId) === String(peekOrderId) ? 'true' : 'false') + '">'
                            + '<i class="bi bi-eye"></i></button>')
                        : '<span class="text-muted small flex-shrink-0" title="Brak zamówienia do podglądu">—</span>';

                    return '<div class="border rounded p-2 mb-2">'
                        + '<div class="d-flex gap-2 align-items-start">'
                        + eyeHtml
                        + '<div class="small flex-grow-1">'
                        + '<div class="fw-semibold">' + esc(summary) + '</div>'
                        + '<div class="text-muted">' + esc(meta) + '</div>'
                        + (!amountMatches ? '<div class="text-warning">Kwota różni się od przelewu</div>' : '')
                        + '</div></div></div>';
                }

                var html = '';
                if (cases.length) {
                    html += '<div class="small text-muted mb-1">Sprawy niezamknięte</div>';
                    html += cases.map(function (c) { return rowHtml(c, 'case'); }).join('');
                }
                if (orders.length) {
                    html += '<div class="small text-muted mb-1' + (cases.length ? ' mt-2' : '') + '">Zamówienia bez aktywnej sprawy</div>';
                    html += orders.map(function (o) { return rowHtml(o, 'order'); }).join('');
                }
                root.innerHTML = html;
            }

            async function peekOrderInRightColumn(orderId, eyeBtn) {
                if (!orderId) return;
                if (eyeBtn) {
                    eyeBtn.disabled = true;
                }
                try {
                    var response = await fetch(
                        lookupOrderPreviewUrl + '?form_order_id=' + encodeURIComponent(orderId),
                        { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                    );
                    var payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Nie udało się wczytać zamówienia');
                    }
                    peekedOrderId = orderId;
                    peekedLinkContext = {
                        kind: eyeBtn ? (eyeBtn.getAttribute('data-kind') || 'order') : 'order',
                        caseId: eyeBtn ? (eyeBtn.getAttribute('data-case-id') || '') : '',
                        orderId: eyeBtn ? (eyeBtn.getAttribute('data-order-id') || '') : '',
                        itemUrl: eyeBtn ? (eyeBtn.getAttribute('data-item-url') || '') : '',
                        summary: eyeBtn ? (eyeBtn.getAttribute('data-summary') || '') : '',
                        amountMatches: eyeBtn ? eyeBtn.getAttribute('data-amount-matches') === '1' : false
                    };
                    setActivePeekEye(orderId);
                    renderOrderPanelFromSnapshot({
                        order: payload.order,
                        match: null,
                        txId: originalOrderSnapshot && originalOrderSnapshot.txId
                            ? originalOrderSnapshot.txId
                            : (currentPreviewBtn ? currentPreviewBtn.getAttribute('data-tx-id') : ''),
                        txData: originalOrderSnapshot ? originalOrderSnapshot.txData : {}
                    }, true);
                } catch (e) {
                    var status = document.getElementById('bankTxManualLookupStatus');
                    if (status) status.textContent = e.message || 'Nie udało się wczytać podglądu.';
                } finally {
                    if (eyeBtn) eyeBtn.disabled = false;
                }
            }

            async function runManualCaseLookup() {
                var input = document.getElementById('bankTxManualLookupInput');
                var status = document.getElementById('bankTxManualLookupStatus');
                var q = (input && input.value ? input.value : '').trim();
                if (q.length < 1) {
                    if (status) status.textContent = 'Wpisz frazę wyszukiwania.';
                    return;
                }
                if (q.length < 2 && !/^\d+$/.test(q)) {
                    if (status) status.textContent = 'Wpisz co najmniej 2 znaki (albo samo ID).';
                    return;
                }
                if (!currentPreviewBtn) return;

                if (status) status.textContent = 'Szukam…';
                try {
                    var response = await fetch(lookupCasesUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    var payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Błąd wyszukiwania');
                    }
                    var txAmount = parseFloat(currentPreviewBtn.getAttribute('data-tx-amount') || '0');
                    renderManualLookupResults(payload, txAmount);
                    var total = (payload.cases || []).length + (payload.orders || []).length;
                    if (status) {
                        status.textContent = total ? ('Znaleziono: ' + total) : 'Brak wyników';
                    }
                } catch (e) {
                    if (status) status.textContent = e.message || 'Nie udało się wyszukać.';
                }
            }

            function setFormPreviewTargets(nextTxId) {
                ['bankTxPreviewAcceptForm', 'bankTxPreviewRejectForm', 'bankTxPreviewIgnoreForm'].forEach(function (id) {
                    var form = document.getElementById(id);
                    if (!form) return;
                    var input = form.querySelector('input[name="preview"]');
                    if (input) input.value = nextTxId || '';
                });
            }

            function syncActionForms(btn) {
                var canAct = btn.getAttribute('data-can-act') || '0';
                var acceptForm = document.getElementById('bankTxPreviewAcceptForm');
                var rejectForm = document.getElementById('bankTxPreviewRejectForm');
                var ignoreForm = document.getElementById('bankTxPreviewIgnoreForm');
                var ignoreBtn = document.getElementById('bankTxPreviewIgnoreBtn');
                var buttons = previewButtons();
                var idx = buttons.indexOf(btn);
                var nextBtn = idx >= 0 && idx < buttons.length - 1 ? buttons[idx + 1] : null;
                var nextTxId = nextBtn ? (nextBtn.getAttribute('data-tx-id') || '') : '';

                setFormPreviewTargets(nextTxId);

                if (canAct === 'match') {
                    acceptForm.classList.remove('d-none');
                    rejectForm.classList.remove('d-none');
                    ignoreForm.classList.remove('d-none');
                    acceptForm.setAttribute('action', btn.getAttribute('data-accept-url') || '');
                    rejectForm.setAttribute('action', btn.getAttribute('data-reject-url') || '');
                    ignoreForm.setAttribute('action', btn.getAttribute('data-ignore-url') || '');
                    if (btn.getAttribute('data-amount-mismatch') === '1') {
                        acceptForm.setAttribute('data-amount-mismatch', '1');
                    } else {
                        acceptForm.removeAttribute('data-amount-mismatch');
                    }
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    ignoreBtn.textContent = 'Ignoruj';
                    setManualLinkPanelVisible(true);
                } else if (canAct === 'ignore-tx') {
                    acceptForm.classList.add('d-none');
                    rejectForm.classList.add('d-none');
                    ignoreForm.classList.remove('d-none');
                    ignoreForm.setAttribute('action', btn.getAttribute('data-ignore-url') || '');
                    acceptForm.removeAttribute('data-amount-mismatch');
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    ignoreBtn.textContent = 'Ignoruj transakcję';
                    setManualLinkPanelVisible(true);
                } else {
                    acceptForm.classList.add('d-none');
                    rejectForm.classList.add('d-none');
                    ignoreForm.classList.add('d-none');
                    acceptForm.removeAttribute('data-amount-mismatch');
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    setManualLinkPanelVisible(false);
                }

                var prevBtn = document.getElementById('bankTxPreviewPrevBtn');
                var nextNavBtn = document.getElementById('bankTxPreviewNextBtn');
                prevBtn.disabled = idx <= 0;
                nextNavBtn.disabled = idx < 0 || idx >= buttons.length - 1;
            }

            function loadPreviewFromButton(btn) {
                if (!btn) return;
                currentPreviewBtn = btn;

                var preview;
                try {
                    preview = JSON.parse(btn.getAttribute('data-preview') || '{}');
                } catch (e) {
                    preview = {};
                }

                originalOrderSnapshot = {
                    order: preview.order || null,
                    match: preview.match || null,
                    txId: preview.tx && preview.tx.id ? preview.tx.id : (btn.getAttribute('data-tx-id') || ''),
                    txData: preview.tx || {}
                };
                peekedOrderId = null;
                peekedLinkContext = null;
                setActivePeekEye(null);

                clearMatchHighlights(modalEl);
                fillDl(document.getElementById('bankTxPreviewTransfer'), preview.tx || {});

                var lookupResults = document.getElementById('bankTxManualLookupResults');
                var lookupStatus = document.getElementById('bankTxManualLookupStatus');
                if (lookupResults) lookupResults.innerHTML = '';
                if (lookupStatus) lookupStatus.textContent = '';

                renderOrderPanelFromSnapshot(originalOrderSnapshot, false);
                syncActionForms(btn);
            }

            modalEl.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button || !button.classList.contains('bank-tx-preview-btn')) {
                    if (currentPreviewBtn) {
                        loadPreviewFromButton(currentPreviewBtn);
                    }
                    return;
                }
                loadPreviewFromButton(button);
            });

            document.getElementById('bankTxPreviewPrevBtn').addEventListener('click', function () {
                var buttons = previewButtons();
                var idx = buttons.indexOf(currentPreviewBtn);
                if (idx > 0) loadPreviewFromButton(buttons[idx - 1]);
            });

            document.getElementById('bankTxPreviewNextBtn').addEventListener('click', function () {
                var buttons = previewButtons();
                var idx = buttons.indexOf(currentPreviewBtn);
                if (idx >= 0 && idx < buttons.length - 1) loadPreviewFromButton(buttons[idx + 1]);
            });

            var lookupBtn = document.getElementById('bankTxManualLookupBtn');
            var lookupInput = document.getElementById('bankTxManualLookupInput');
            if (lookupBtn) {
                lookupBtn.addEventListener('click', runManualCaseLookup);
            }
            if (lookupInput) {
                lookupInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        runManualCaseLookup();
                    }
                });
            }

            var manualConfirmModalEl = document.getElementById('bankImportManualLinkConfirmModal');
            var manualConfirmForm = document.getElementById('bankImportManualLinkConfirmForm');
            var manualCaseIdInput = document.getElementById('bankImportManualLinkCaseId');
            var manualOrderIdInput = document.getElementById('bankImportManualLinkOrderId');
            var manualRegisterInput = document.getElementById('bankImportManualLinkRegisterIfirma');
            var manualPreviewInput = document.getElementById('bankImportManualLinkPreview');
            var manualSummaryEl = document.getElementById('bankImportManualLinkSummary');
            var manualIfirmaInfo = document.getElementById('bankImportManualLinkIfirmaInfo');
            var manualSubmitBtn = document.getElementById('bankImportManualLinkSubmit');
            var manualConfirmModal = manualConfirmModalEl && window.bootstrap
                ? new window.bootstrap.Modal(manualConfirmModalEl)
                : null;

            function openManualLinkConfirm(btn) {
                if (!btn || !currentPreviewBtn || !manualConfirmForm || !manualConfirmModal) return;

                var registerIfirma = btn.getAttribute('data-register-ifirma') === '1';
                manualConfirmForm.setAttribute('action', currentPreviewBtn.getAttribute('data-link-url') || '');
                if (manualCaseIdInput) manualCaseIdInput.value = btn.getAttribute('data-case-id') || '';
                if (manualOrderIdInput) manualOrderIdInput.value = btn.getAttribute('data-order-id') || '';
                if (manualRegisterInput) manualRegisterInput.value = registerIfirma ? '1' : '0';
                if (manualPreviewInput) {
                    var buttons = previewButtons();
                    var idx = buttons.indexOf(currentPreviewBtn);
                    var nextBtn = idx >= 0 && idx < buttons.length - 1 ? buttons[idx + 1] : null;
                    manualPreviewInput.value = nextBtn ? (nextBtn.getAttribute('data-tx-id') || '') : '';
                }
                if (manualSummaryEl) {
                    manualSummaryEl.textContent = 'Powiązać przelew #'
                        + (currentPreviewBtn.getAttribute('data-tx-id') || '')
                        + ' z: '
                        + (btn.getAttribute('data-summary') || 'wybranym rekordem');
                }
                if (manualIfirmaInfo) manualIfirmaInfo.classList.toggle('d-none', !registerIfirma);
                if (manualSubmitBtn) {
                    manualSubmitBtn.textContent = registerIfirma ? 'Powiąż + wpłata iFirma' : 'Powiąż lokalnie';
                    manualSubmitBtn.className = registerIfirma ? 'btn btn-success' : 'btn btn-primary';
                }
                manualConfirmModal.show();
            }

            document.getElementById('bankTxManualLookupResults')?.addEventListener('click', function (event) {
                var peekBtn = event.target.closest('.bank-manual-peek-btn');
                if (peekBtn) {
                    event.preventDefault();
                    peekOrderInRightColumn(peekBtn.getAttribute('data-peek-order-id'), peekBtn);
                }
            });

            document.getElementById('bankTxPreviewLinks')?.addEventListener('click', function (event) {
                var btn = event.target.closest('.bank-manual-link-btn');
                if (btn) {
                    event.preventDefault();
                    openManualLinkConfirm(btn);
                }
            });

            var clearPeekBtn = document.getElementById('bankTxPreviewClearOrderBtn');
            if (clearPeekBtn) {
                clearPeekBtn.addEventListener('click', function () {
                    clearPeekState(true);
                });
            }

            var autoPreviewId = new URLSearchParams(window.location.search).get('preview');
            if (autoPreviewId) {
                var autoBtn = document.querySelector('.bank-tx-preview-btn[data-tx-id="' + autoPreviewId + '"]');
                if (autoBtn) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show(autoBtn);
                }
            }
        });
    </script>
</x-app-layout>
