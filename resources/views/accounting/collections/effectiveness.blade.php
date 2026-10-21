<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Skuteczność kontaktu
            </h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('accounting.collections.index', ['status' => 'closed']) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Zamknięte (z kontaktem)
                </a>
                <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-primary btn-sm">
                    Lista spraw
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .debt-reminder-count-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            font-weight: 400;
            line-height: 1.5;
        }
        .debt-reminder-last-sent {
            font-size: 0.75rem;
            line-height: 1.2;
            margin-top: 0.2rem;
        }
    </style>

    <div class="py-3">
        <div class="container-fluid px-4">
            <p class="text-muted small mb-3">
                Zamknięte sprawy, w których był monit (e-mail/SMS wysłany albo telefon w historii).
                Kolumna „Dni” = dni kalendarzowe od ostatniego kontaktu do daty zamknięcia.
                Sprawy zamknięte tylko po dopasowaniu przelewu (bez monitu) są tutaj pomijane —
                zobacz filtr <em>Bez kontaktu</em> na liście zamkniętych.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="card h-100 border-success">
                        <div class="card-body">
                            <div class="small text-muted">Zamknięte z kontaktem</div>
                            <div class="fs-4 fw-semibold">{{ $stats['closed_with_contact'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Zamknięte bez kontaktu</div>
                            <div class="fs-4 fw-semibold">{{ $stats['closed_without_contact'] }}</div>
                            <div class="small text-muted mt-1">
                                <a href="{{ route('accounting.collections.index', ['status' => 'closed', 'contact' => 'without']) }}">Pokaż na liście</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 border-info">
                        <div class="card-body">
                            <div class="small text-muted">Średnia dni kontakt → zamknięcie</div>
                            <div class="fs-4 fw-semibold">
                                {{ $stats['avg_days'] !== null ? number_format($stats['avg_days'], 1, ',', ' ') : '—' }}
                            </div>
                            <div class="small text-muted mt-1">
                                mediana: {{ $stats['median_days'] !== null ? $stats['median_days'] : '—' }}
                                @if(($stats['sample_size'] ?? 0) > 0)
                                    · n={{ $stats['sample_size'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">W wybranym okresie</div>
                            <div class="fs-4 fw-semibold">{{ $stats['in_period'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">Filtry</div>
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.collections.effectiveness') }}" class="row g-2 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="search" class="form-label small mb-1">Szukaj</label>
                            <input type="text" id="search" name="search" class="form-control form-control-sm"
                                   value="{{ $search }}" placeholder="FV, ID, nazwa, e-mail">
                        </div>
                        <div class="col-8 col-md-3">
                            <label for="days" class="form-label small mb-1">Zamknięte w okresie</label>
                            <select id="days" name="days" class="form-select form-select-sm">
                                <option value="30" @selected($days === 30)>Ostatnie 30 dni</option>
                                <option value="90" @selected($days === 90)>Ostatnie 90 dni</option>
                                <option value="180" @selected($days === 180)>Ostatnie 180 dni</option>
                                <option value="365" @selected($days === 365)>Ostatnie 365 dni</option>
                                <option value="0" @selected($days === 0)>Bez limitu</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filtruj</button>
                            @if($search !== '' || $days !== 90)
                                <a href="{{ route('accounting.collections.effectiveness') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Zamknięte z kontaktem</span>
                    <span class="small text-muted">Wyświetlanie {{ $cases->firstItem() ?? 0 }}-{{ $cases->lastItem() ?? 0 }} z {{ $cases->total() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sprawa</th>
                                <th>Zamówienie / faktura</th>
                                <th>Klient</th>
                                <th>Zamknięto</th>
                                <th>Ostatni kontakt</th>
                                <th class="text-center">Dni</th>
                                <th class="text-center">E-mail</th>
                                <th class="text-center">SMS</th>
                                <th class="text-center">Telefon</th>
                                <th>Powód</th>
                                <th class="text-end">Kwota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                @php
                                    $order = $case->formOrder;
                                    $reminderSentCount = $case->reminderEmailsSentCount();
                                    $reminderSmsSentCount = $case->reminderSmsSentCount();
                                    $phoneCallsCount = $case->phoneCallsLoggedCount();
                                    $lastContactAt = $case->lastCollectionContactAt();
                                    $lastContactLabel = $case->reminderLastSentLabel($lastContactAt);
                                    $channelLabel = $case->lastCollectionContactChannelLabel();
                                    $daysToClose = $case->daysFromLastContactToClose();
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('accounting.collections.show', $case) }}" class="fw-semibold text-decoration-none">
                                            #{{ $case->id }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('form-orders.show', $order->id) }}" class="text-decoration-none">#{{ $order->id }}</a>
                                        <div class="small text-muted">FV: {{ $case->invoice_number ?: ($order->invoice_number ?: '—') }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $order->recipient_name ?: $order->buyer_name ?: $order->orderer_name ?: '—' }}</div>
                                        <div class="small text-muted">{{ $order->orderer_email ?: $order->display_participant_email }}</div>
                                    </td>
                                    <td>
                                        {{ $case->closed_at?->timezone(config('app.timezone'))->format('d.m.Y') ?: '—' }}
                                    </td>
                                    <td>
                                        <div>{{ $lastContactLabel ?: '—' }}</div>
                                        @if($channelLabel)
                                            <div class="small text-muted">{{ $channelLabel }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($daysToClose === null)
                                            <span class="text-muted">—</span>
                                        @elseif($daysToClose < 0)
                                            <span class="text-muted" title="Kontakt po dacie zamknięcia">{{ $daysToClose }}</span>
                                        @else
                                            <span class="fw-semibold">{{ $daysToClose }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge debt-reminder-count-badge {{ $case->reminderEmailsSentBadgeClass($reminderSentCount) }}">
                                            {{ $reminderSentCount }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge debt-reminder-count-badge {{ $case->reminderSmsSentBadgeClass($reminderSmsSentCount) }}">
                                            {{ $reminderSmsSentCount }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge debt-reminder-count-badge {{ $case->phoneCallsLoggedBadgeClass($phoneCallsCount) }}">
                                            {{ $phoneCallsCount }}
                                        </span>
                                    </td>
                                    <td class="small">{{ $case->closure_reason ?: '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($case->amount_gross ?? $order->product_price ?? 0), 2, ',', ' ') }} zł</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        Brak zamkniętych spraw z kontaktem dla wybranych filtrów.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($cases->hasPages())
                    <div class="card-footer">
                        {{ $cases->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
