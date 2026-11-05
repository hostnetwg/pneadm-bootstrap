<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Odzyskane opóźnione wpłaty
            </h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('accounting.collections.effectiveness') }}" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-graph-up"></i> Skuteczność
                </a>
                <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-primary btn-sm">
                    Lista spraw
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <p class="text-muted small mb-3">
                Raport pokazuje zaakceptowane wpłaty z wyciągu, których data operacji bankowej jest po terminie płatności FV.
                Powiadomienia to e-mail, SMS i telefon zapisane w historii sprawy przed zaakceptowaniem wpłaty.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="card h-100 border-success">
                        <div class="card-body">
                            <div class="small text-muted">Wpłaty w filtrze</div>
                            <div class="fs-4 fw-semibold">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Z powiadomieniem</div>
                            <div class="fs-4 fw-semibold">{{ $stats['with_notifications'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Kwota odzyskana</div>
                            <div class="fs-4 fw-semibold">{{ number_format((float) $stats['amount'], 2, ',', ' ') }} zł</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">Największe opóźnienie</div>
                            <div class="fs-4 fw-semibold">{{ $stats['max_delay'] !== null ? $stats['max_delay'].' dni' : '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">Filtry</div>
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.collections.recovered-overdue') }}" class="row g-2 align-items-end">
                        <div class="col-12 col-md-5">
                            <label for="search" class="form-label small mb-1">Szukaj</label>
                            <input type="text" id="search" name="search" class="form-control form-control-sm"
                                   value="{{ $filters['search'] }}" placeholder="FV, ID, nazwa, e-mail, przelew">
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="days" class="form-label small mb-1">Data wpłaty</label>
                            <select id="days" name="days" class="form-select form-select-sm">
                                <option value="30" @selected($filters['days'] === 30)>Ostatnie 30 dni</option>
                                <option value="90" @selected($filters['days'] === 90)>Ostatnie 90 dni</option>
                                <option value="180" @selected($filters['days'] === 180)>Ostatnie 180 dni</option>
                                <option value="365" @selected($filters['days'] === 365)>Ostatnie 365 dni</option>
                                <option value="0" @selected($filters['days'] === 0)>Bez limitu</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="contact" class="form-label small mb-1">Powiadomienia</label>
                            <select id="contact" name="contact" class="form-select form-select-sm">
                                <option value="all" @selected($filters['contact'] === 'all')>Wszystkie</option>
                                <option value="with" @selected($filters['contact'] === 'with')>Z powiadomieniem</option>
                                <option value="without" @selected($filters['contact'] === 'without')>Bez powiadomienia</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filtruj</button>
                            <a href="{{ route('accounting.collections.recovered-overdue.export-csv', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                CSV
                            </a>
                            @if($filters['search'] !== '' || $filters['days'] !== 90 || $filters['contact'] !== 'all')
                                <a href="{{ route('accounting.collections.recovered-overdue') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Wpłaty po terminie</span>
                    <span class="small text-muted">Wyświetlanie {{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} z {{ $rows->total() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Wpłata</th>
                                <th class="text-center">Opóźnienie</th>
                                <th class="text-end">Kwota</th>
                                <th>Sprawa / FV</th>
                                <th>Klient</th>
                                <th class="text-center">E-mail</th>
                                <th class="text-center">SMS</th>
                                <th class="text-center">Telefon</th>
                                <th>Źródło</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                @php
                                    $case = $row['case'];
                                    $order = $row['order'];
                                    $transaction = $row['transaction'];
                                    $counts = $row['notification_counts'];
                                @endphp
                                <tr>
                                    <td>
                                        <div>{{ $row['operation_date']->format('d.m.Y') }}</div>
                                        <div class="small text-muted">Przelew #{{ $transaction->id }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-danger">{{ $row['delay_days'] }} dni</span>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $row['allocated_amount'], 2, ',', ' ') }} zł</td>
                                    <td>
                                        @if($case)
                                            <a href="{{ route('accounting.collections.show', $case) }}" class="fw-semibold text-decoration-none">#{{ $case->id }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        <div class="small text-muted">
                                            FV: {{ $row['invoice_number'] ?: '—' }}
                                            @if($order)
                                                · zam. <a href="{{ route('form-orders.show', $order) }}">#{{ $order->id }}</a>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $row['customer_name'] }}</td>
                                    <td class="text-center"><span class="badge {{ $counts['email'] > 0 ? 'text-bg-primary' : 'text-bg-light border text-muted' }}">{{ $counts['email'] }}</span></td>
                                    <td class="text-center"><span class="badge {{ $counts['sms'] > 0 ? 'text-bg-primary' : 'text-bg-light border text-muted' }}">{{ $counts['sms'] }}</span></td>
                                    <td class="text-center"><span class="badge {{ $counts['phone'] > 0 ? 'text-bg-primary' : 'text-bg-light border text-muted' }}">{{ $counts['phone'] }}</span></td>
                                    <td class="small">
                                        @if($transaction->bank_statement_import_id)
                                            <a href="{{ route('accounting.bank-imports.show', ['bankImport' => $transaction->bank_statement_import_id, 'filter' => 'accepted', 'preview' => $transaction->id]) }}">
                                                Import #{{ $transaction->bank_statement_import_id }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        Brak odzyskanych opóźnionych wpłat dla wybranych filtrów.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rows->hasPages())
                    <div class="card-footer">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
