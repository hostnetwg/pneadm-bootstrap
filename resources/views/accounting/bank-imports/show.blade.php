<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Import #{{ $import->id }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @if($import->canBeDeleted())
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#bankImportDeleteFromShowModal">
                        Usuń import
                    </button>
                @endif
                <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-sm btn-outline-secondary">Lista importów</a>
            </div>
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
                        <div class="col-md-3">
                            <span class="text-muted">Przegląd:</span>
                            @if(($counts['unmatched'] ?? 0) === 0)
                                <span class="badge text-bg-success">Przejrzany</span>
                            @else
                                <span class="badge text-bg-warning text-dark">Do przeglądu: {{ $counts['unmatched'] }}</span>
                            @endif
                        </div>
                        <div class="col-md-4"><span class="text-muted">Wgrał:</span> {{ $import->uploader?->name ?? '—' }} {{ $import->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <form method="POST" action="{{ route('accounting.bank-imports.rematch', $import) }}" data-loading-submit data-loading-text="Przeliczam…">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary" data-loading-text="Przeliczam…">Przelicz sugestie</button>
                        </form>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#bankImportIgnorePayNowModal"
                                @disabled(($payNowIgnorableCount ?? 0) < 1)>
                            Ignoruj wypłaty PayNow
                            @if(($payNowIgnorableCount ?? 0) > 0)
                                ({{ $payNowIgnorableCount }})
                            @endif
                        </button>
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
                        'paynow' => [
                            'label' => 'PayNow ('.$counts['paynow'].')',
                            'title' => '<div class="text-start"><strong>PayNow</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>wypłaty rozliczeniowe bramki PayNow (mElements / WYPŁATA ŚRODKÓW NR PON-…)</li>'
                                .'<li>oznaczone przyciskiem „Ignoruj wypłaty PayNow”</li>'
                                .'<li>nie obejmuje przelewów klientów bez FV/KSeF</li>'
                                .'</ul></div>',
                        ],
                        'ignored' => [
                            'label' => 'Ignorowane ('.$counts['ignored'].')',
                            'title' => '<div class="text-start"><strong>Ignorowane</strong><ul class="mb-0 ps-3 mt-1">'
                                .'<li>ręcznie zignorowane wpływy spoza PayNow</li>'
                                .'<li>wypłaty PayNow są w osobnej zakładce</li>'
                                .'</ul></div>',
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
                                        $acceptedMatches = $tx->acceptedMatches();
                                        $accepted = $acceptedMatches->first();
                                        $remainingAmount = $tx->remainingAllocatableAmount();
                                        $canAddSplit = $tx->canAcceptAdditionalLink();
                                        $packageSuggested = $suggested
                                            ->filter(
                                                fn ($m) => in_array('multi_invoice_sum_match', $m->match_reasons ?? [], true)
                                            )
                                            ->sortBy(fn ($m) => [
                                                (string) ($m->formOrder?->invoice_number ?? ''),
                                                (int) $m->id,
                                            ])
                                            ->values();
                                        $packageSum = round((float) $packageSuggested->sum(
                                            fn ($m) => (float) ($m->formOrder?->product_price
                                                ?? $m->debtCase?->amount_gross
                                                ?? 0)
                                        ), 2);
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
                                                'remaining' => number_format($remainingAmount, 2, ',', ' ').' '.$tx->currency,
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
                                                'allocated_amount' => $best->allocated_amount !== null
                                                    ? number_format((float) $best->allocated_amount, 2, ',', ' ').' '.$tx->currency
                                                    : null,
                                            ] : null,
                                            'allocations' => $acceptedMatches->map(function ($m) use ($tx, $import) {
                                                return [
                                                    'match_id' => $m->id,
                                                    'debt_case_id' => $m->debt_case_id,
                                                    'debt_case_url' => $m->debt_case_id
                                                        ? route('accounting.collections.show', $m->debt_case_id)
                                                        : null,
                                                    'form_order_id' => $m->form_order_id,
                                                    'form_order_url' => $m->form_order_id
                                                        ? route('form-orders.show', $m->form_order_id)
                                                        : null,
                                                    'invoice' => $m->formOrder?->invoice_number
                                                        ?: ($m->debtCase?->invoice_number ?: '—'),
                                                    'allocated' => number_format(
                                                        (float) ($m->allocated_amount ?? $tx->amount),
                                                        2,
                                                        ',',
                                                        ' '
                                                    ).' '.$tx->currency,
                                                    'unlink_url' => route('accounting.bank-imports.matches.unlink', [$import, $m]),
                                                    'register_ifirma_url' => route(
                                                        'accounting.bank-imports.matches.register-ifirma-payment',
                                                        [$import, $m]
                                                    ),
                                                    'ifirma_status_url' => route(
                                                        'accounting.bank-imports.matches.ifirma-status',
                                                        [$import, $m]
                                                    ),
                                                    'match' => [
                                                        'status' => $m->statusLabel(),
                                                        'confidence' => $m->confidenceLabel(),
                                                        'confidence_class' => $m->confidenceBadgeClass(),
                                                        'reasons' => $m->reasonLabels(),
                                                        'reason_codes' => array_values($m->match_reasons ?? []),
                                                        'debt_case_id' => $m->debt_case_id,
                                                        'debt_case_url' => $m->debt_case_id
                                                            ? route('accounting.collections.show', $m->debt_case_id)
                                                            : null,
                                                    ],
                                                ];
                                            })->values()->all(),
                                            'remaining' => [
                                                'amount' => $remainingAmount,
                                                'formatted' => number_format($remainingAmount, 2, ',', ' ').' '.$tx->currency,
                                                'can_add' => $canAddSplit,
                                            ],
                                            'package' => $packageSuggested->count() >= 2 ? [
                                                'count' => $packageSuggested->count(),
                                                'accept_url' => route('accounting.bank-imports.transactions.accept-package', [$import, $tx]),
                                                'sum_formatted' => number_format($packageSum, 2, ',', ' ').' '.$tx->currency,
                                                'transfer_formatted' => number_format((float) $tx->amount, 2, ',', ' ').' '.$tx->currency,
                                                'items' => $packageSuggested->map(function ($m) use ($tx, $import) {
                                                    $fo = $m->formOrder;
                                                    $amt = (float) ($fo?->product_price ?? $m->debtCase?->amount_gross ?? 0);

                                                    return [
                                                        'match_id' => $m->id,
                                                        'form_order_id' => $m->form_order_id,
                                                        'form_order_url' => $m->form_order_id
                                                            ? route('form-orders.show', $m->form_order_id)
                                                            : null,
                                                        'debt_case_id' => $m->debt_case_id,
                                                        'debt_case_url' => $m->debt_case_id
                                                            ? route('accounting.collections.show', $m->debt_case_id)
                                                            : null,
                                                        'invoice' => $fo?->invoice_number
                                                            ?: ($m->debtCase?->invoice_number ?: '—'),
                                                        'amount' => number_format($amt, 2, ',', ' ').' '.$tx->currency,
                                                        'accept_url' => route('accounting.bank-imports.matches.accept', [$import, $m]),
                                                    ];
                                                })->values()->all(),
                                            ] : null,
                                            'order' => $order ? [
                                                'id' => $order->id,
                                                'url' => route('form-orders.show', $order->id),
                                                'invoice' => $order->invoice_number ?: '—',
                                                'invoice_issue_date' => $order->invoice_issue_date?->format('Y-m-d'),
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
                                        $suggestBest = ($suggested->isNotEmpty() && $canAddSplit)
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
                                                    data-preview="{{ json_encode($preview, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' }}"
                                                    @if($packageSuggested->count() >= 2 && $canAddSplit)
                                                        data-can-act="package"
                                                        data-ignore-url="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}"
                                                    @elseif($suggestBest)
                                                        data-can-act="match"
                                                        data-accept-url="{{ route('accounting.bank-imports.matches.accept', [$import, $suggestBest]) }}"
                                                        data-ifirma-status-url="{{ route('accounting.bank-imports.matches.ifirma-status', [$import, $suggestBest]) }}"
                                                        data-reject-url="{{ route('accounting.bank-imports.matches.reject', [$import, $suggestBest]) }}"
                                                        data-ignore-url="{{ route('accounting.bank-imports.matches.ignore', [$import, $suggestBest]) }}"
                                                        @if(in_array('amount_mismatch', $suggestBest->match_reasons ?? [], true) || $acceptedMatches->isNotEmpty())
                                                            data-amount-mismatch="1"
                                                        @endif
                                                    @elseif($canAddSplit)
                                                        data-can-act="ignore-tx"
                                                        data-ignore-url="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}"
                                                    @elseif($acceptedMatches->isNotEmpty())
                                                        data-can-act="accepted"
                                                        data-unlink-url="{{ route('accounting.bank-imports.matches.unlink', [$import, $accepted]) }}"
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
                                            @if($acceptedMatches->isNotEmpty())
                                                <div class="mt-1">
                                                    <span class="badge text-bg-success">
                                                        Zaakceptowane{{ $acceptedMatches->count() > 1 ? ' ('.$acceptedMatches->count().')' : '' }}
                                                    </span>
                                                    @if($remainingAmount > 0.01)
                                                        <span class="badge text-bg-warning">Wolne {{ number_format($remainingAmount, 2, ',', ' ') }} {{ $tx->currency }}</span>
                                                    @endif
                                                    <ul class="mb-0 ps-3 small">
                                                        @foreach($acceptedMatches as $acc)
                                                            <li>
                                                                @if($acc->allocated_amount !== null)
                                                                    {{ number_format((float) $acc->allocated_amount, 2, ',', ' ') }} {{ $tx->currency }}
                                                                    <span class="text-muted">→</span>
                                                                @endif
                                                                @if($acc->debt_case_id)
                                                                    <a href="{{ route('accounting.collections.show', $acc->debt_case_id) }}">Sprawa #{{ $acc->debt_case_id }}</a>
                                                                @endif
                                                                @if($acc->form_order_id)
                                                                    <span class="text-muted">·</span>
                                                                    <a href="{{ route('form-orders.show', $acc->form_order_id) }}">Zam. #{{ $acc->form_order_id }}</a>
                                                                    @if($acc->formOrder?->invoice_number)
                                                                        <span class="text-muted">({{ $acc->formOrder->invoice_number }})</span>
                                                                    @endif
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            @if($suggestBest)
                                                @if($packageSuggested->count() >= 2)
                                                    <div class="mt-1">
                                                        <span class="badge text-bg-info">Pakiet podziału ({{ $packageSuggested->count() }} FV)</span>
                                                        <span class="badge text-bg-light border">
                                                            Suma {{ number_format($packageSum, 2, ',', ' ') }} = przelew {{ number_format((float) $tx->amount, 2, ',', ' ') }} {{ $tx->currency }}
                                                        </span>
                                                    </div>
                                                    <ul class="mb-0 mt-1 ps-3 small">
                                                        @foreach($packageSuggested as $pkgMatch)
                                                            @php
                                                                $pkgOrder = $pkgMatch->formOrder;
                                                                $pkgAmt = (float) ($pkgOrder?->product_price ?? $pkgMatch->debtCase?->amount_gross ?? 0);
                                                            @endphp
                                                            <li>
                                                                <span class="fw-semibold">{{ $pkgOrder?->invoice_number ?: ($pkgMatch->debtCase?->invoice_number ?: 'FV —') }}</span>
                                                                <span class="text-muted">·</span>
                                                                {{ number_format($pkgAmt, 2, ',', ' ') }} {{ $tx->currency }}
                                                                @if($pkgMatch->form_order_id)
                                                                    <span class="text-muted">·</span>
                                                                    <a href="{{ route('form-orders.show', $pkgMatch->form_order_id) }}">Zam. #{{ $pkgMatch->form_order_id }}</a>
                                                                @endif
                                                                @if($pkgMatch->debt_case_id)
                                                                    <span class="text-muted">·</span>
                                                                    <a href="{{ route('accounting.collections.show', $pkgMatch->debt_case_id) }}">Sprawa #{{ $pkgMatch->debt_case_id }}</a>
                                                                @else
                                                                    <span class="badge text-bg-light border">utworzy sprawę</span>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    <div class="mt-1 small text-muted">
                                                        Podstawa: suma kwot FV z tytułu ≈ kwota przelewu (podział).
                                                    </div>
                                                @else
                                                    <div class="mt-1 d-flex flex-wrap align-items-center gap-2">
                                                        <span class="badge {{ $suggestBest->confidenceBadgeClass() }}">{{ $suggestBest->confidenceLabel() }}</span>
                                                        @if($suggestBest->form_order_id)
                                                            <a href="{{ route('form-orders.show', $suggestBest->form_order_id) }}">Zam. #{{ $suggestBest->form_order_id }}</a>
                                                            @if($suggestBest->formOrder?->invoice_number)
                                                                <span class="text-muted">FV w systemie: {{ $suggestBest->formOrder->invoice_number }}</span>
                                                            @endif
                                                        @endif
                                                        @if($suggestBest->debt_case_id)
                                                            <a href="{{ route('accounting.collections.show', $suggestBest->debt_case_id) }}">Sprawa #{{ $suggestBest->debt_case_id }}</a>
                                                        @elseif($suggestBest->form_order_id)
                                                            <span class="badge text-bg-light border">Utworzy sprawę przy akceptacji</span>
                                                        @endif
                                                        @if($suggested->count() > 1)
                                                            <span class="badge text-bg-warning">Do ręcznej weryfikacji ({{ $suggested->count() }})</span>
                                                        @endif
                                                    </div>
                                                    @if(count($suggestBest->reasonLabels()))
                                                        <div class="mt-1 small">
                                                            <span class="text-muted">Podstawa dopasowania:</span>
                                                            <ul class="mb-0 ps-3">
                                                                @foreach($suggestBest->reasonLabels() as $reasonLabel)
                                                                    <li>{{ $reasonLabel }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif
                                                @endif
                                            @elseif($acceptedMatches->isEmpty())
                                                <div class="mt-1 small text-muted">Brak sugestii</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @if($packageSuggested->count() >= 2 && $canAddSplit)
                                                    <form method="POST"
                                                          action="{{ route('accounting.bank-imports.transactions.accept-package', [$import, $tx]) }}"
                                                          class="bank-import-package-form"
                                                          data-loading-submit
                                                          data-loading-text="Akceptuję pakiet…"
                                                          data-package-count="{{ $packageSuggested->count() }}">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">
                                                        <button type="submit" class="btn btn-sm btn-primary" data-loading-text="Akceptuję pakiet…">
                                                            Akceptuj pakiet ({{ $packageSuggested->count() }})
                                                        </button>
                                                    </form>
                                                @endif
                                                @if($suggestBest && $packageSuggested->count() < 2)
                                                    <form method="POST"
                                                          action="{{ route('accounting.bank-imports.matches.accept', [$import, $suggestBest]) }}"
                                                          class="bank-import-accept-form"
                                                          data-loading-submit
                                                          data-loading-text="Akceptuję…"
                                                          @if(in_array('amount_mismatch', $suggestBest->match_reasons ?? [], true) || $acceptedMatches->isNotEmpty())
                                                              data-amount-mismatch="1"
                                                          @endif>
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">
                                                        <input type="hidden" name="ifirma_already_paid" value="0" class="bank-import-ifirma-already-paid">
                                                        <button type="submit" class="btn btn-sm btn-success" data-loading-text="Akceptuję…">
                                                            {{ $acceptedMatches->isNotEmpty() ? 'Dodaj do podziału' : 'Akceptuj' }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('accounting.bank-imports.matches.reject', [$import, $suggestBest]) }}" data-loading-submit data-loading-text="Odrzucam…">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-loading-text="Odrzucam…">Odrzuć</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('accounting.bank-imports.matches.ignore', [$import, $suggestBest]) }}" data-loading-submit data-loading-text="Ignoruję…">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-text="Ignoruję…">Ignoruj</button>
                                                    </form>
                                                @elseif($suggestBest && $packageSuggested->count() >= 2)
                                                    {{-- Pakiet: główna akcja to „Akceptuj pakiet”; pojedyncze FV w podglądzie --}}
                                                    <form method="POST" action="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}" data-loading-submit data-loading-text="Ignoruję…">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-text="Ignoruję…">Ignoruj</button>
                                                    </form>
                                                @elseif($canAddSplit)
                                                    <form method="POST" action="{{ route('accounting.bank-imports.transactions.ignore', [$import, $tx]) }}" data-loading-submit data-loading-text="Ignoruję…">
                                                        @csrf
                                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-text="Ignoruję…">Ignoruj transakcję</button>
                                                    </form>
                                                @elseif($acceptedMatches->isNotEmpty())
                                                    @foreach($acceptedMatches as $acc)
                                                        <form method="POST"
                                                              action="{{ route('accounting.bank-imports.matches.register-ifirma-payment', [$import, $acc]) }}"
                                                              class="d-inline"
                                                              data-loading-submit
                                                              data-loading-text="Rejestruję…">
                                                            @csrf
                                                            <input type="hidden" name="filter" value="{{ $filter }}">
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-outline-success"
                                                                    data-loading-text="Rejestruję…"
                                                                    title="Zarejestruj wpłatę w iFirma dla tej alokacji{{ $acc->formOrder?->invoice_number ? ' (FV '.$acc->formOrder->invoice_number.')' : ($acc->debt_case_id ? ' (sprawa #'.$acc->debt_case_id.')' : '') }}">
                                                                @php
                                                                    $ifirmaBtnSuffix = '';
                                                                    if ($acceptedMatches->count() > 1) {
                                                                        $ifirmaBtnSuffix = $acc->formOrder?->invoice_number
                                                                            ? ' · FV '.$acc->formOrder->invoice_number
                                                                            : ($acc->debt_case_id ? ' · sprawa #'.$acc->debt_case_id : '');
                                                                    }
                                                                @endphp
                                                                Wpłata iFirma{{ $ifirmaBtnSuffix }}
                                                            </button>
                                                        </form>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#bankImportUnlinkModal"
                                                                data-unlink-url="{{ route('accounting.bank-imports.matches.unlink', [$import, $acc]) }}"
                                                                data-unlink-summary="{{ number_format((float) ($acc->allocated_amount ?? $tx->amount), 2, ',', ' ').' '.$tx->currency.' · '.($tx->operation_date?->format('Y-m-d') ?? '—') }}{{ $acc->debt_case_id ? ' · sprawa #'.$acc->debt_case_id : '' }}{{ $acc->formOrder?->invoice_number ? ' · FV '.$acc->formOrder->invoice_number : '' }}">
                                                            Cofnij{{ $acceptedMatches->count() > 1 ? ' #'.$acc->debt_case_id : ' przypisanie' }}
                                                        </button>
                                                    @endforeach
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

    <div class="modal fade" id="bankImportIgnorePayNowModal" tabindex="-1" aria-labelledby="bankImportIgnorePayNowModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bankImportIgnorePayNowModalLabel">Ignoruj wypłaty PayNow</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Oznacz jako ignorowane <strong>{{ (int) ($payNowIgnorableCount ?? 0) }}</strong>
                        {{ (int) ($payNowIgnorableCount ?? 0) === 1 ? 'wpływ' : 'wpływów' }}
                        rozpoznanych jako <strong>rozliczeniowa wypłata bramki PayNow</strong>
                        (mElements / „WYPŁATA ŚRODKÓW NR PON-…”).
                    </p>
                    <div class="alert alert-warning py-2 small mb-0">
                        Ta akcja <strong>nie</strong> dotyczy przelewów klientów bez numeru FV/KSeF —
                        takie pozycje zostają w kolejce (powiązanie po nazwie szkoły, adresie itd.).
                        Zaakceptowane powiązania nie są ruszane. Po potwierdzeniu pozycje trafią do zakładki <strong>PayNow</strong>
                        (nie do ogólnego „Ignorowane”).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <form method="POST" action="{{ route('accounting.bank-imports.ignore-paynow-payouts', $import) }}" data-loading-submit data-loading-text="Ignoruję…">
                        @csrf
                        <button type="submit" class="btn btn-secondary" data-loading-text="Ignoruję…">Ignoruj wypłaty PayNow</button>
                    </form>
                </div>
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
                                    <dt class="col-sm-4 text-muted">Wolne</dt><dd class="col-sm-8" data-field="remaining">—</dd>
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
                            Szukaj po FV, KSeF, ID sprawy/zamówienia, NIP, nazwie, adresie/mieście nabywcy lub odbiorcy, e-mailu
                            albo notatkach zamówienia (w tym numerze anulowanej FV).
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
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="bankTxManualLookupClearBtn"
                                    title="Wyczyść pole wyszukiwania"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Wyczyść pole wyszukiwania"
                                    aria-label="Wyczyść pole wyszukiwania">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="bankTxManualLookupBtn">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value="1"
                                   id="bankTxManualLookupExact">
                            <label class="form-check-label small" for="bankTxManualLookupExact">
                                Szukaj dokładnie wpisanego numeru (bez dopasowania fragmentu)
                            </label>
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
                        <form method="POST" id="bankTxPreviewAcceptForm" class="d-none bank-import-accept-form" data-loading-submit data-loading-text="Akceptuję…">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">
                            <input type="hidden" name="ifirma_already_paid" value="0" class="bank-import-ifirma-already-paid">
                            <button type="submit" class="btn btn-success" data-loading-text="Akceptuję…">Akceptuj</button>
                        </form>
                        <form method="POST" id="bankTxPreviewRejectForm" class="d-none" data-loading-submit data-loading-text="Odrzucam…">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <button type="submit" class="btn btn-outline-danger" data-loading-text="Odrzucam…">Odrzuć</button>
                        </form>
                        <form method="POST" id="bankTxPreviewIgnoreForm" class="d-none" data-loading-submit data-loading-text="Ignoruję…">
                            @csrf
                            <input type="hidden" name="filter" value="{{ $filter }}">
                            <input type="hidden" name="preview" value="">
                            <button type="submit" class="btn btn-outline-secondary" id="bankTxPreviewIgnoreBtn" data-loading-text="Ignoruję…">Ignoruj</button>
                        </form>
                        <button type="button"
                                class="btn btn-outline-danger d-none"
                                id="bankTxPreviewUnlinkBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#bankImportUnlinkModal"
                                data-unlink-url=""
                                data-unlink-summary="">
                            Cofnij przypisanie
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportUnlinkModal" tabindex="-1" aria-labelledby="bankImportUnlinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankImportUnlinkForm" data-loading-submit data-loading-text="Cofam…">
                    @csrf
                    <input type="hidden" name="filter" value="{{ $filter }}">
                    <div class="modal-header text-bg-danger">
                        <h5 class="modal-title" id="bankImportUnlinkModalLabel">Cofnij przypisanie przelewu</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Odpiąć zaakceptowane powiązanie przelewu ze sprawą / fakturą?</p>
                        <div class="border rounded p-2 bg-light small mb-3" id="bankImportUnlinkSummary">—</div>
                        <div class="alert alert-warning small mb-0">
                            <ul class="mb-0 ps-3">
                                <li>Przelew wróci do kolejki nieprzypisanych wpływów.</li>
                                <li>Jeśli sprawa jest zamknięta — zostanie <strong>otwarta ponownie</strong>.</li>
                                <li>System <strong>spróbuje usunąć wpłatę w iFirma</strong>. Gdy API nie pozwoli, popraw status ręcznie w panelu iFirma.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger" data-loading-text="Cofam…">Cofnij przypisanie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportManualLinkConfirmModal" tabindex="-1" aria-labelledby="bankImportManualLinkConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankImportManualLinkConfirmForm" data-loading-submit data-loading-text="Powiązuję…">
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
                        <button type="submit" class="btn btn-primary" id="bankImportManualLinkSubmit" data-loading-text="Powiązuję…">Powiąż lokalnie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportAcceptWarnModal" tabindex="-1" aria-labelledby="bankImportAcceptWarnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-bg-warning">
                    <h5 class="modal-title" id="bankImportAcceptWarnModalLabel">Uwaga: kwota przelewu ≠ kwota FV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3" role="alert">
                        Kwota z wyciągu różni się od kwoty wskazanej faktury/zamówienia.
                    </div>
                    <p class="mb-2">Po akceptacji lokalnej:</p>
                    <ul class="mb-3">
                        <li>do tej FV/sprawy zostanie przypisana <strong>kwota FV</strong> (albo wolna reszta przelewu),</li>
                        <li>pozostała kwota przelewu zostaje wolna — możesz dodać kolejne FV (podział),</li>
                        <li>inne sugestie <strong>tej samej</strong> FV zostaną odrzucone; sugestie innych FV zostają,</li>
                        <li>przy różnicy kwoty <strong>nie</strong> rejestrujemy wpłaty w iFirma (MVP podziału).</li>
                    </ul>
                    <p class="mb-0 small text-muted">
                        Gdy w tytule widać kilka numerów FV i ich suma ≈ przelew — użyj <strong>Akceptuj pakiet</strong>
                        (lokalnie albo z wpłatami w iFirma).
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-warning" id="bankImportAcceptWarnConfirmBtn" data-loading-text="Akceptuję…">Akceptuj tylko lokalnie</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportAcceptPackageModal" tabindex="-1" aria-labelledby="bankImportAcceptPackageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-bg-primary">
                    <h5 class="modal-title" id="bankImportAcceptPackageModalLabel">Akceptacja pakietu podziału</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Suma kwot FV z tytułu zgadza się z kwotą przelewu.
                        Zostanie lokalnie powiązanych <strong id="bankImportAcceptPackageCount">kilka</strong> faktur/spraw
                        (po kwocie każdej FV) — lista FV jest widoczna w wierszu / podglądzie przelewu.
                    </p>
                    <p class="mb-3">
                        Możesz od razu <strong>zarejestrować wpłatę w iFirma dla każdej FV</strong> (po alokowanej kwocie)
                        i odświeżyć status spraw — albo zaakceptować pakiet tylko lokalnie.
                    </p>
                    <div class="alert alert-light border small mb-0" role="alert">
                        Rejestracja w iFirma działa dla faktur krajowych. Przy błędzie jednej FV pozostałe lokalne powiązania zostają —
                        komunikat ostrzeże, które wpłaty nie przeszły.
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="button" class="btn btn-outline-primary" id="bankImportAcceptPackageLocalBtn" data-loading-text="Akceptuję…">Tylko lokalnie</button>
                    <button type="button" class="btn btn-primary" id="bankImportAcceptPackageIfirmaBtn" data-loading-text="Rejestruję wpłaty…">Pakiet + wpłaty w iFirma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportAcceptIfirmaModal" tabindex="-1" aria-labelledby="bankImportAcceptIfirmaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
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
                    <button type="button" class="btn btn-outline-success" id="bankImportAcceptLocalOnlyBtn" data-loading-text="Akceptuję…">Tylko lokalnie</button>
                    <button type="button" class="btn btn-success" id="bankImportAcceptIfirmaConfirmBtn" data-loading-text="Rejestruję wpłatę…">Akceptuj + wpłata w iFirma</button>
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
        #bankImportAcceptIfirmaModal,
        #bankImportAcceptPackageModal {
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
            var acceptPackageModalEl = document.getElementById('bankImportAcceptPackageModal');
            var acceptPackageModal = acceptPackageModalEl ? bootstrap.Modal.getOrCreateInstance(acceptPackageModalEl) : null;
            var pendingAcceptForm = null;
            var pendingPackageForm = null;

            function setRegisterIfirma(form, value) {
                var input = form.querySelector('.bank-import-register-ifirma');
                if (input) {
                    input.value = value ? '1' : '0';
                }
            }

            function submitPackageForm(form, triggerBtn, withIfirma) {
                setRegisterIfirma(form, !!withIfirma);
                form.setAttribute('data-package-confirmed', '1');
                if (window.PneButtonLoading && window.PneButtonLoading.setButtonLoading) {
                    if (triggerBtn) {
                        window.PneButtonLoading.setButtonLoading(
                            triggerBtn,
                            true,
                            triggerBtn.getAttribute('data-loading-text') || 'Akceptuję pakiet…'
                        );
                    }
                    var formBtn = form.querySelector('button[type="submit"]');
                    if (formBtn) {
                        window.PneButtonLoading.setButtonLoading(
                            formBtn,
                            true,
                            formBtn.getAttribute('data-loading-text') || 'Akceptuję pakiet…'
                        );
                    }
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }

            document.querySelectorAll('.bank-import-package-form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (form.getAttribute('data-package-confirmed') === '1') {
                        form.removeAttribute('data-package-confirmed');
                        return;
                    }
                    e.preventDefault();
                    pendingPackageForm = form;
                    var countEl = document.getElementById('bankImportAcceptPackageCount');
                    if (countEl) {
                        countEl.textContent = form.getAttribute('data-package-count') || 'kilka';
                    }
                    setRegisterIfirma(form, false);
                    if (acceptPackageModal) {
                        acceptPackageModal.show();
                    }
                });
            });

            var packageLocalBtn = document.getElementById('bankImportAcceptPackageLocalBtn');
            if (packageLocalBtn) {
                packageLocalBtn.addEventListener('click', function () {
                    if (!pendingPackageForm) {
                        return;
                    }
                    var form = pendingPackageForm;
                    pendingPackageForm = null;
                    if (acceptPackageModal) {
                        acceptPackageModal.hide();
                    }
                    submitPackageForm(form, packageLocalBtn, false);
                });
            }

            var packageIfirmaBtn = document.getElementById('bankImportAcceptPackageIfirmaBtn');
            if (packageIfirmaBtn) {
                packageIfirmaBtn.addEventListener('click', function () {
                    if (!pendingPackageForm) {
                        return;
                    }
                    var form = pendingPackageForm;
                    pendingPackageForm = null;
                    if (acceptPackageModal) {
                        acceptPackageModal.hide();
                    }
                    submitPackageForm(form, packageIfirmaBtn, true);
                });
            }

            function setIfirmaAlreadyPaid(form, value) {
                var input = form.querySelector('.bank-import-ifirma-already-paid');
                if (input) {
                    input.value = value ? '1' : '0';
                }
            }

            function submitAcceptForm(form, triggerBtn) {
                form.setAttribute('data-accept-confirmed', '1');
                if (window.PneButtonLoading && window.PneButtonLoading.setButtonLoading) {
                    if (triggerBtn) {
                        window.PneButtonLoading.setButtonLoading(triggerBtn, true, triggerBtn.getAttribute('data-loading-text') || 'Akceptuję…');
                    }
                    var formBtn = form.querySelector('button[type="submit"]');
                    if (formBtn) {
                        window.PneButtonLoading.setButtonLoading(formBtn, true, formBtn.getAttribute('data-loading-text') || 'Akceptuję…');
                    }
                }
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
                    // Pojedyncza FV z pakietu: alokacja = kwota FV → ten sam wybór lokalnie / iFirma
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
                    submitAcceptForm(form, acceptWarnConfirmBtn);
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
                    submitAcceptForm(form, acceptLocalOnlyBtn);
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
                    submitAcceptForm(form, acceptIfirmaConfirmBtn);
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

            function polishDaysWord(days) {
                var n = Math.abs(Number(days) || 0);
                if (n === 1) return 'dzień';
                var mod10 = n % 10;
                var mod100 = n % 100;
                if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
                    return 'dni';
                }
                return 'dni';
            }

            function renderIfirmaStatusPanel(data) {
                var panel = document.getElementById('bankTxPreviewIfirmaStatus');
                if (!panel) return;

                var status = data.status || '';
                var isPaid = status === 'oplacone';
                var daysOverdue = Number(data.days_overdue || 0);
                var alertClass = isPaid
                    ? 'alert-success'
                    : (daysOverdue > 0 ? 'alert-danger' : (status === 'unknown' ? 'alert-secondary' : 'alert-warning'));
                var invoiceLine = 'Faktura: ' + esc(data.invoice_number || '—');
                if (data.issue_date) {
                    invoiceLine += ' · wyst. ' + esc(data.issue_date);
                }
                invoiceLine += ' · źródło: ' + esc(data.source || '—');

                var html = '<div class="alert ' + alertClass + ' mb-0 small" role="alert">'
                    + '<div class="fw-semibold mb-1">Status iFirma: ' + esc(data.status_label || '—') + '</div>'
                    + '<div>Zapłacono: ' + formatMoney(data.paid_amount) + ' / brutto: ' + formatMoney(data.gross_amount) + '</div>'
                    + '<div>' + invoiceLine + '</div>';

                if (!isPaid && daysOverdue > 0) {
                    html += '<div class="fw-semibold mt-2">'
                        + 'Po terminie o ' + esc(String(daysOverdue)) + ' ' + polishDaysWord(daysOverdue)
                        + (data.due_date ? ' (termin: ' + esc(data.due_date) + ')' : '')
                        + '.'
                        + '</div>';
                } else if (!isPaid && data.due_date) {
                    html += '<div class="mt-1 text-muted">Termin płatności: ' + esc(data.due_date) + '</div>';
                }

                if (data.debt_case && data.debt_case.id && data.debt_case.url) {
                    html += '<div class="mt-2">'
                        + 'Sprawa windykacyjna: '
                        + '<a href="' + esc(data.debt_case.url) + '" target="_blank" rel="noopener" class="fw-semibold">'
                        + '#' + esc(String(data.debt_case.id))
                        + '</a>'
                        + (data.debt_case.status_label ? ' · ' + esc(data.debt_case.status_label) : '')
                        + '</div>';
                }

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
                        submitAcceptForm(form, acceptPaidBtn);
                    });
                }
            }

            function checkIfirmaStatus(url, button, payload) {
                if (!url) return;

                resetIfirmaStatusPanel();
                if (window.PneButtonLoading && window.PneButtonLoading.setButtonLoading) {
                    window.PneButtonLoading.setButtonLoading(button, true, 'Sprawdzam…');
                } else if (button) {
                    button.disabled = true;
                    button.textContent = 'Sprawdzam…';
                }

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(payload || {}),
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
                        if (window.PneButtonLoading && window.PneButtonLoading.setButtonLoading) {
                            window.PneButtonLoading.setButtonLoading(button, false);
                        } else if (button) {
                            button.disabled = false;
                            button.textContent = 'Sprawdź status z iFirma';
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
                    } else if (key === 'invoice') {
                        var invoiceText = value || '—';
                        if (data.invoice_issue_date) {
                            invoiceText += ' · wyst. ' + data.invoice_issue_date;
                        }
                        el.textContent = invoiceText;
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
                modal.querySelectorAll('#bankTxPreviewTransfer [data-field="description"], #bankTxPreviewTransfer [data-field="invoice_from_title"]')
                    .forEach(function (el) {
                        if (el.querySelector('mark')) {
                            el.textContent = el.textContent || '';
                        }
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

            function normalizeInvoiceHint(value) {
                var raw = String(value || '').trim();
                if (!raw || raw === '—') return null;
                var primary = raw.split(/\s*[·|]\s*|\s+-\s+/)[0].trim();
                return primary || null;
            }

            /**
             * Podświetlenia zgodności przelew ↔ zamówienie.
             * options.orderInvoice — FV z prawej kolumny (gdy brak invoice_number: w reasons).
             */
            function applyMatchHighlights(modal, reasonCodes, txData, options) {
                clearMatchHighlights(modal);
                if (!reasonCodes || !reasonCodes.length) return;

                options = options || {};
                var txRoot = document.getElementById('bankTxPreviewTransfer');
                var orderRoot = document.getElementById('bankTxPreviewOrder');
                var codes = reasonCodes.map(String);
                var isSplit = codes.indexOf('split_allocation') !== -1
                    || codes.indexOf('multi_invoice_sum_match') !== -1;
                var rawFragments = [];
                var rawKind = 'ok';
                var invoiceNumbers = [];

                var hasAmountMismatch = codes.indexOf('amount_mismatch') !== -1;
                var hasAmountMatch = codes.indexOf('amount_match') !== -1;

                codes.forEach(function (code) {
                    if (code === 'amount_match' || code === 'amount_mismatch') {
                        // Kwoty obsługujemy po pętli (jedna logika: 1:1 vs podział).
                        return;
                    }
                    if (code.indexOf('invoice_number:') === 0 || code.indexOf('debt_case_invoice_number:') === 0) {
                        invoiceNumbers.push(code.split(':').slice(1).join(':'));
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

                // Kwoty:
                // - 1:1 + amount_match → zielone obie strony (przelew = FV)
                // - podział / pakiet multi-FV → zielona tylko kwota FV (730 ≠ 365 — nie podświetlaj przelewu)
                // - amount_mismatch → ostrzeżenie na kwocie FV (przy podziale nie na pełnym przelewie)
                if (hasAmountMismatch) {
                    highlightField(orderRoot, 'amount', 'warn');
                    if (! isSplit) {
                        highlightField(txRoot, 'amount', 'warn');
                    }
                    rawKind = 'warn';
                } else if (hasAmountMatch || isSplit) {
                    highlightField(orderRoot, 'amount', 'ok');
                    if (! isSplit && hasAmountMatch) {
                        highlightField(txRoot, 'amount', 'ok');
                        if (txData && txData.amount) {
                            var amountCore = String(txData.amount).replace(/\s*PLN\s*$/i, '').trim();
                            rawFragments.push(amountCore);
                            rawFragments.push(amountCore.replace(/\s/g, ''));
                        }
                    }
                }

                var orderInvoice = normalizeInvoiceHint(options.orderInvoice);
                var hasInvoiceMismatch = codes.some(function (c) {
                    return c.indexOf('invoice_number_mismatch:') === 0;
                });
                if (orderInvoice && !hasInvoiceMismatch) {
                    var hasOrderInvoice = invoiceNumbers.some(function (n) {
                        return String(n).replace(/\s+/g, '').toLowerCase()
                            === orderInvoice.replace(/\s+/g, '').toLowerCase();
                    });
                    if (!hasOrderInvoice) {
                        invoiceNumbers.push(orderInvoice);
                    }
                }

                invoiceNumbers = invoiceNumbers
                    .map(normalizeInvoiceHint)
                    .filter(Boolean);

                if (invoiceNumbers.length && !hasInvoiceMismatch) {
                    highlightField(txRoot, 'invoice_from_title', 'ok');
                    highlightField(orderRoot, 'invoice', 'ok');
                    var invoiceFromTitleEl = txRoot
                        ? txRoot.querySelector('[data-field="invoice_from_title"]')
                        : null;
                    highlightFragmentsInText(invoiceFromTitleEl, invoiceNumbers, 'ok');
                    invoiceNumbers.forEach(function (n) {
                        rawFragments.push(n);
                    });
                }

                var descEl = txRoot ? txRoot.querySelector('[data-field="description"]') : null;
                highlightFragmentsInText(descEl, rawFragments, rawKind);
            }

            var modalEl = document.getElementById('bankTxPreviewModal');
            if (!modalEl) return;

            var currentPreviewBtn = null;
            var lookupCasesUrl = @json(route('accounting.bank-imports.lookup-cases'));
            var lookupOrderPreviewUrl = @json(route('accounting.bank-imports.lookup-order-preview'));
            var lookupIfirmaStatusUrl = @json(route('accounting.bank-imports.lookup-ifirma-status'));
            var csrfToken = @json(csrf_token());
            var originalOrderSnapshot = null;
            var peekedOrderId = null;
            var peekedLinkContext = null;
            var activeAllocationContext = null;
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
                    var peekOrderId = ctx.orderId || order.id;
                    if (peekOrderId || ctx.caseId) {
                        peekLinks.push('<button type="button" class="btn btn-sm btn-outline-success" id="bankTxPreviewIfirmaStatusBtn">Sprawdź status z iFirma</button>');
                    }
                    linksEl.innerHTML = peekLinks.join(' ');
                    var peekIfirmaStatusBtn = document.getElementById('bankTxPreviewIfirmaStatusBtn');
                    if (peekIfirmaStatusBtn) {
                        peekIfirmaStatusBtn.addEventListener('click', function () {
                            var body = {};
                            if (peekOrderId) body.form_order_id = Number(peekOrderId);
                            if (ctx.caseId) body.debt_case_id = Number(ctx.caseId);
                            checkIfirmaStatus(lookupIfirmaStatusUrl, peekIfirmaStatusBtn, body);
                        });
                    }
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
                var ifirmaStatusUrl = (activeAllocationContext && activeAllocationContext.ifirma_status_url)
                    ? activeAllocationContext.ifirma_status_url
                    : (currentPreviewBtn ? (currentPreviewBtn.getAttribute('data-ifirma-status-url') || '') : '');
                if (ifirmaStatusUrl && (
                    (currentPreviewBtn && currentPreviewBtn.getAttribute('data-can-act') === 'match')
                    || (activeAllocationContext && activeAllocationContext.ifirma_status_url)
                )) {
                    links.push('<button type="button" class="btn btn-sm btn-outline-success" id="bankTxPreviewIfirmaStatusBtn">Sprawdź status z iFirma</button>');
                }
                var registerIfirmaUrl = (activeAllocationContext && activeAllocationContext.register_ifirma_url)
                    ? activeAllocationContext.register_ifirma_url
                    : (currentPreviewBtn ? (currentPreviewBtn.getAttribute('data-register-ifirma-url') || '') : '');
                if (registerIfirmaUrl) {
                    links.push('<form method="POST" action="' + esc(registerIfirmaUrl) + '" class="d-inline" data-loading-submit data-loading-text="Rejestruję…">'
                        + '<input type="hidden" name="_token" value="' + esc(csrfToken) + '">'
                        + '<input type="hidden" name="filter" value="{{ $filter }}">'
                        + '<input type="hidden" name="preview" value="' + esc(txId || '') + '">'
                        + '<button type="submit" class="btn btn-sm btn-outline-success" data-loading-text="Rejestruję…">Zarejestruj wpłatę iFirma</button>'
                        + '</form>');
                }
                linksEl.innerHTML = links.join(' ');
                var ifirmaStatusBtn = document.getElementById('bankTxPreviewIfirmaStatusBtn');
                if (ifirmaStatusBtn) {
                    ifirmaStatusBtn.addEventListener('click', function () {
                        checkIfirmaStatus(ifirmaStatusUrl, ifirmaStatusBtn);
                    });
                }

                applyMatchHighlights(modalEl, match ? match.reason_codes : [], snapshot.txData || {}, {
                    orderInvoice: order && order.invoice ? order.invoice : null
                });
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
                var exactInput = document.getElementById('bankTxManualLookupExact');
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
                    var params = new URLSearchParams();
                    params.set('q', q);
                    params.set('exact', (exactInput && exactInput.checked) ? '1' : '0');
                    var response = await fetch(lookupCasesUrl + '?' + params.toString(), {
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

            (function initManualLookupExactPreference() {
                var exactInput = document.getElementById('bankTxManualLookupExact');
                if (!exactInput) {
                    return;
                }
                var storageKey = 'accounting_bank_imports_lookup_exact_v1';
                try {
                    var raw = localStorage.getItem(storageKey);
                    if (raw === '1') {
                        exactInput.checked = true;
                    } else if (raw === '0') {
                        exactInput.checked = false;
                    }
                } catch (e) {
                    // keep default unchecked
                }
                exactInput.addEventListener('change', function () {
                    try {
                        localStorage.setItem(storageKey, exactInput.checked ? '1' : '0');
                    } catch (e) {
                        // ignore
                    }
                });
            })();

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
                var unlinkBtn = document.getElementById('bankTxPreviewUnlinkBtn');
                var buttons = previewButtons();
                var idx = buttons.indexOf(btn);
                var nextBtn = idx >= 0 && idx < buttons.length - 1 ? buttons[idx + 1] : null;
                var nextTxId = nextBtn ? (nextBtn.getAttribute('data-tx-id') || '') : '';

                setFormPreviewTargets(nextTxId);

                if (unlinkBtn) {
                    unlinkBtn.classList.add('d-none');
                }

                if (canAct === 'package') {
                    acceptForm.classList.add('d-none');
                    rejectForm.classList.add('d-none');
                    ignoreForm.classList.remove('d-none');
                    ignoreForm.setAttribute('action', btn.getAttribute('data-ignore-url') || '');
                    acceptForm.removeAttribute('data-amount-mismatch');
                    acceptForm.removeAttribute('data-split-package');
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    ignoreBtn.textContent = 'Ignoruj transakcję';
                    setManualLinkPanelVisible(true);
                } else if (canAct === 'match') {
                    acceptForm.classList.remove('d-none');
                    rejectForm.classList.remove('d-none');
                    ignoreForm.classList.remove('d-none');
                    acceptForm.setAttribute('action', btn.getAttribute('data-accept-url') || '');
                    rejectForm.setAttribute('action', btn.getAttribute('data-reject-url') || '');
                    ignoreForm.setAttribute('action', btn.getAttribute('data-ignore-url') || '');
                    acceptForm.removeAttribute('data-split-package');
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
                    acceptForm.removeAttribute('data-split-package');
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    ignoreBtn.textContent = 'Ignoruj transakcję';
                    setManualLinkPanelVisible(true);
                } else if (canAct === 'accepted') {
                    acceptForm.classList.add('d-none');
                    rejectForm.classList.add('d-none');
                    ignoreForm.classList.add('d-none');
                    acceptForm.removeAttribute('data-amount-mismatch');
                    acceptForm.removeAttribute('data-split-package');
                    acceptForm.removeAttribute('data-accept-confirmed');
                    setRegisterIfirma(acceptForm, false);
                    setIfirmaAlreadyPaid(acceptForm, false);
                    var previewAccepted = {};
                    try { previewAccepted = JSON.parse(btn.getAttribute('data-preview') || '{}'); } catch (e) {}
                    var canAddMore = previewAccepted.remaining && previewAccepted.remaining.can_add;
                    setManualLinkPanelVisible(!!canAddMore);
                    if (unlinkBtn) {
                        unlinkBtn.classList.remove('d-none');
                        unlinkBtn.setAttribute('data-unlink-url', btn.getAttribute('data-unlink-url') || '');
                        var summary = ((previewAccepted.tx && previewAccepted.tx.amount) ? previewAccepted.tx.amount : '')
                            + ((previewAccepted.tx && previewAccepted.tx.date) ? ' · ' + previewAccepted.tx.date : '');
                        if (previewAccepted.match && previewAccepted.match.debt_case_id) {
                            summary += ' · sprawa #' + previewAccepted.match.debt_case_id;
                        }
                        unlinkBtn.setAttribute('data-unlink-summary', summary || ('przelew #' + (btn.getAttribute('data-tx-id') || '')));
                    }
                } else {
                    acceptForm.classList.add('d-none');
                    rejectForm.classList.add('d-none');
                    ignoreForm.classList.add('d-none');
                    acceptForm.removeAttribute('data-amount-mismatch');
                    acceptForm.removeAttribute('data-split-package');
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

                var allocationsBox = document.getElementById('bankTxPreviewAllocations');
                if (!allocationsBox) {
                    var transferRoot = document.getElementById('bankTxPreviewTransfer');
                    if (transferRoot && transferRoot.parentElement) {
                        allocationsBox = document.createElement('div');
                        allocationsBox.id = 'bankTxPreviewAllocations';
                        allocationsBox.className = 'mt-3 small';
                        transferRoot.parentElement.appendChild(allocationsBox);
                    }
                }
                if (allocationsBox) {
                    var allocHtml = '';
                    var allocations = preview.allocations || [];
                    if (allocations.length) {
                        allocHtml += '<div class="fw-semibold mb-1">Przypisane części</div>';
                        allocHtml += '<div class="small text-muted mb-1">Kliknij numer FV, aby zobaczyć zamówienie po prawej i zarejestrować wpłatę w iFirma.</div>';
                        allocHtml += '<ul class="mb-2 ps-3" id="bankTxPreviewAllocationsList">';
                        allocations.forEach(function (row, idx) {
                            var invoiceLabel = row.invoice || '—';
                            var invoiceBtn = row.form_order_id
                                ? ('<button type="button" class="btn btn-link btn-sm p-0 align-baseline bank-allocation-select-btn"'
                                    + ' data-allocation-index="' + idx + '"'
                                    + ' title="Pokaż zamówienie i rejestrację iFirma">'
                                    + esc(invoiceLabel)
                                    + '</button>')
                                : ('<span class="text-muted">(' + esc(invoiceLabel) + ')</span>');
                            allocHtml += '<li class="bank-allocation-row" data-allocation-index="' + idx + '">'
                                + esc(row.allocated || '') + ' → '
                                + (row.debt_case_url
                                    ? ('<a href="' + esc(row.debt_case_url) + '" target="_blank" rel="noopener">Sprawa #' + esc(String(row.debt_case_id)) + '</a>')
                                    : '—')
                                + ' (' + invoiceBtn + ')'
                                + '</li>';
                        });
                        allocHtml += '</ul>';
                    }
                    if (preview.package && preview.package.items && preview.package.items.length) {
                        allocHtml += '<div class="fw-semibold mb-1">Pakiet do akceptacji (' + esc(String(preview.package.count)) + ' FV)</div>';
                        allocHtml += '<div class="small text-muted mb-1">Suma '
                            + esc(preview.package.sum_formatted || '') + ' = przelew '
                            + esc(preview.package.transfer_formatted || '') + '</div>';
                        allocHtml += '<ul class="mb-2 ps-3">';
                        preview.package.items.forEach(function (item) {
                            allocHtml += '<li><span class="fw-semibold">' + esc(item.invoice || '—') + '</span>'
                                + ' · ' + esc(item.amount || '')
                                + (item.form_order_url
                                    ? (' · <a href="' + esc(item.form_order_url) + '" target="_blank" rel="noopener">Zam. #' + esc(String(item.form_order_id)) + '</a>')
                                    : '')
                                + (item.debt_case_url
                                    ? (' · <a href="' + esc(item.debt_case_url) + '" target="_blank" rel="noopener">Sprawa #' + esc(String(item.debt_case_id)) + '</a>')
                                    : ' · <span class="text-muted">utworzy sprawę</span>')
                                + '</li>';
                        });
                        allocHtml += '</ul>';
                    }
                    if (preview.package && preview.package.accept_url) {
                        var filterInput = document.querySelector('input[name="filter"]');
                        var filterVal = filterInput ? filterInput.value : '';
                        allocHtml += '<form method="POST" action="' + esc(preview.package.accept_url) + '" class="d-inline bank-import-package-form" data-loading-submit data-loading-text="Akceptuję pakiet…" data-package-count="' + esc(String(preview.package.count)) + '">'
                            + '<input type="hidden" name="_token" value="' + esc(csrfToken) + '">'
                            + '<input type="hidden" name="filter" value="' + esc(filterVal) + '">'
                            + '<input type="hidden" name="register_ifirma_payment" value="0" class="bank-import-register-ifirma">'
                            + '<button type="submit" class="btn btn-sm btn-primary">Akceptuj pakiet (' + esc(String(preview.package.count)) + ')</button>'
                            + '</form>';
                    }
                    allocationsBox.innerHTML = allocHtml;
                    allocationsBox.querySelectorAll('.bank-import-package-form').forEach(function (form) {
                        form.addEventListener('submit', function (e) {
                            if (form.getAttribute('data-package-confirmed') === '1') {
                                form.removeAttribute('data-package-confirmed');
                                return;
                            }
                            e.preventDefault();
                            pendingPackageForm = form;
                            var countEl = document.getElementById('bankImportAcceptPackageCount');
                            if (countEl) {
                                countEl.textContent = form.getAttribute('data-package-count') || 'kilka';
                            }
                            setRegisterIfirma(form, false);
                            if (acceptPackageModal) {
                                acceptPackageModal.show();
                            }
                        });
                    });
                    allocationsBox.querySelectorAll('.bank-allocation-select-btn').forEach(function (allocBtn) {
                        allocBtn.addEventListener('click', function () {
                            var idx = Number(allocBtn.getAttribute('data-allocation-index') || '-1');
                            var row = allocations[idx];
                            if (!row || !row.form_order_id) {
                                return;
                            }
                            selectAcceptedAllocation(row, allocBtn);
                        });
                    });
                }

                var lookupResults = document.getElementById('bankTxManualLookupResults');
                var lookupStatus = document.getElementById('bankTxManualLookupStatus');
                if (lookupResults) lookupResults.innerHTML = '';
                if (lookupStatus) lookupStatus.textContent = '';

                activeAllocationContext = null;
                if ((preview.allocations || []).length && preview.allocations[0].form_order_id) {
                    activeAllocationContext = {
                        register_ifirma_url: preview.allocations[0].register_ifirma_url || '',
                        ifirma_status_url: preview.allocations[0].ifirma_status_url || '',
                        match_id: preview.allocations[0].match_id || null,
                    };
                    if (currentPreviewBtn) {
                        if (preview.allocations[0].register_ifirma_url) {
                            currentPreviewBtn.setAttribute('data-register-ifirma-url', preview.allocations[0].register_ifirma_url);
                        }
                        if (preview.allocations[0].ifirma_status_url) {
                            currentPreviewBtn.setAttribute('data-ifirma-status-url', preview.allocations[0].ifirma_status_url);
                        }
                    }
                }

                renderOrderPanelFromSnapshot(originalOrderSnapshot, false);
                syncActionForms(btn);
                highlightActiveAllocation(0);
            }

            function highlightActiveAllocation(index) {
                var list = document.getElementById('bankTxPreviewAllocationsList');
                if (!list) return;
                list.querySelectorAll('.bank-allocation-row').forEach(function (li) {
                    var active = Number(li.getAttribute('data-allocation-index')) === Number(index);
                    li.classList.toggle('fw-semibold', active);
                    var btn = li.querySelector('.bank-allocation-select-btn');
                    if (btn) {
                        btn.classList.toggle('link-success', active);
                        btn.classList.toggle('fw-bold', active);
                    }
                });
            }

            async function selectAcceptedAllocation(row, triggerBtn) {
                if (!row || !row.form_order_id) {
                    return;
                }
                if (triggerBtn) {
                    triggerBtn.disabled = true;
                }
                try {
                    var params = new URLSearchParams();
                    params.set('form_order_id', String(row.form_order_id));
                    var response = await fetch(lookupOrderPreviewUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    var payload = await response.json();
                    if (!response.ok || !payload.order) {
                        throw new Error((payload && payload.message) ? payload.message : 'Nie udało się wczytać zamówienia.');
                    }

                    activeAllocationContext = {
                        register_ifirma_url: row.register_ifirma_url || '',
                        ifirma_status_url: row.ifirma_status_url || '',
                        match_id: row.match_id || null,
                    };
                    if (currentPreviewBtn) {
                        if (row.register_ifirma_url) {
                            currentPreviewBtn.setAttribute('data-register-ifirma-url', row.register_ifirma_url);
                        } else {
                            currentPreviewBtn.removeAttribute('data-register-ifirma-url');
                        }
                        if (row.ifirma_status_url) {
                            currentPreviewBtn.setAttribute('data-ifirma-status-url', row.ifirma_status_url);
                        }
                    }

                    var txId = currentPreviewBtn ? (currentPreviewBtn.getAttribute('data-tx-id') || '') : '';
                    var txData = originalOrderSnapshot ? (originalOrderSnapshot.txData || {}) : {};
                    renderOrderPanelFromSnapshot({
                        order: payload.order,
                        match: row.match || null,
                        txId: txId,
                        txData: txData
                    }, false);

                    var unlinkBtn = document.getElementById('bankTxPreviewUnlinkBtn');
                    if (unlinkBtn && row.unlink_url) {
                        unlinkBtn.classList.remove('d-none');
                        unlinkBtn.setAttribute('data-unlink-url', row.unlink_url);
                        var summary = (txData.amount || '') + (txData.date ? ' · ' + txData.date : '');
                        if (row.debt_case_id) {
                            summary += ' · sprawa #' + row.debt_case_id;
                        }
                        if (row.invoice) {
                            summary += ' · FV ' + row.invoice;
                        }
                        unlinkBtn.setAttribute('data-unlink-summary', summary || ('match #' + (row.match_id || '')));
                    }

                    var allocations = [];
                    try {
                        var preview = JSON.parse(currentPreviewBtn.getAttribute('data-preview') || '{}');
                        allocations = preview.allocations || [];
                    } catch (e) {}
                    var idx = allocations.findIndex(function (a) { return Number(a.match_id) === Number(row.match_id); });
                    highlightActiveAllocation(idx >= 0 ? idx : 0);
                    setClearPeekBtnVisible(false);
                } catch (e) {
                    var box = document.getElementById('bankTxPreviewAllocations');
                    if (box) {
                        var err = document.createElement('div');
                        err.className = 'text-danger small mt-1';
                        err.textContent = e.message || 'Nie udało się przełączyć alokacji.';
                        box.appendChild(err);
                        setTimeout(function () { err.remove(); }, 4000);
                    }
                } finally {
                    if (triggerBtn) {
                        triggerBtn.disabled = false;
                    }
                }
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
            var lookupClearBtn = document.getElementById('bankTxManualLookupClearBtn');
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
            if (lookupClearBtn) {
                lookupClearBtn.addEventListener('click', function () {
                    if (lookupInput) {
                        lookupInput.value = '';
                        lookupInput.focus();
                    }
                    var results = document.getElementById('bankTxManualLookupResults');
                    if (results) {
                        results.innerHTML = '';
                    }
                    var status = document.getElementById('bankTxManualLookupStatus');
                    if (status) {
                        status.textContent = '';
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

            var unlinkModalEl = document.getElementById('bankImportUnlinkModal');
            var unlinkForm = document.getElementById('bankImportUnlinkForm');
            var unlinkSummary = document.getElementById('bankImportUnlinkSummary');
            if (unlinkModalEl && unlinkForm) {
                unlinkModalEl.addEventListener('show.bs.modal', function (event) {
                    var btn = event.relatedTarget;
                    if (!btn) return;
                    unlinkForm.setAttribute('action', btn.getAttribute('data-unlink-url') || '');
                    if (unlinkSummary) {
                        unlinkSummary.textContent = btn.getAttribute('data-unlink-summary') || '—';
                    }
                });
            }

            var autoPreviewId = new URLSearchParams(window.location.search).get('preview');
            var autoMatchId = new URLSearchParams(window.location.search).get('match');
            if (autoPreviewId) {
                var autoBtn = document.querySelector('.bank-tx-preview-btn[data-tx-id="' + autoPreviewId + '"]');
                if (autoBtn) {
                    var openPreviewModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    if (autoMatchId) {
                        modalEl.addEventListener('shown.bs.modal', function onAutoPreviewShown() {
                            modalEl.removeEventListener('shown.bs.modal', onAutoPreviewShown);
                            try {
                                var previewData = JSON.parse(autoBtn.getAttribute('data-preview') || '{}');
                                var allocations = previewData.allocations || [];
                                var target = allocations.find(function (row) {
                                    return String(row.match_id) === String(autoMatchId);
                                });
                                if (target && target.form_order_id) {
                                    selectAcceptedAllocation(target, null);
                                    var list = document.getElementById('bankTxPreviewAllocationsList');
                                    if (list) {
                                        list.querySelectorAll('.bank-allocation-select-btn').forEach(function (btn) {
                                            var idx = Number(btn.getAttribute('data-allocation-index') || '-1');
                                            if (allocations[idx] && String(allocations[idx].match_id) === String(autoMatchId)) {
                                                btn.classList.add('fw-semibold');
                                            }
                                        });
                                    }
                                }
                            } catch (e) {}
                        });
                    }
                    openPreviewModal.show(autoBtn);
                }
            }
        });
    </script>

    @if($import->canBeDeleted())
        <div class="modal fade" id="bankImportDeleteFromShowModal" tabindex="-1" aria-labelledby="bankImportDeleteFromShowModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger" id="bankImportDeleteFromShowModalLabel">Usunąć ten import?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Import #{{ $import->id }} · {{ $import->original_filename }}</p>
                        <div class="alert alert-warning mb-0 small">
                            Usunięcie kasuje przelewy i sugestie z tego wgrania.
                            Dozwolone tylko bez zaakceptowanych powiązań ze sprawami.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <form method="POST" action="{{ route('accounting.bank-imports.destroy', $import) }}" data-loading-submit data-loading-text="Usuwam…">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" data-loading-text="Usuwam…">Usuń import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
