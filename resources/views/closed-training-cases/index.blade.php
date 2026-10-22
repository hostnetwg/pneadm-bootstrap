<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Sprawy szkoleń zamkniętych</h2>
            <a href="{{ route('closed-training-cases.create') }}" class="btn btn-primary">Nowa sprawa</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('closed-training-cases.index') }}" class="row g-3 align-items-end">
                        @if($sort)
                            <input type="hidden" name="sort" value="{{ $sort }}">
                            <input type="hidden" name="dir" value="{{ $dir }}">
                        @endif
                        <div class="col-md-5">
                            <label for="q" class="form-label">Szukaj</label>
                            <input type="text" name="q" id="q" value="{{ $search }}" class="form-control" placeholder="SZ-3, temat, placówka, osoba, e-mail">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="open" @selected($status === 'open')>Otwarte</option>
                                <option value="all" @selected($status === 'all')>Wszystkie</option>
                                @foreach(\App\Models\ClosedTrainingCase::statusOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">Filtruj</button>
                            <a href="{{ route('closed-training-cases.index', array_filter(['sort' => $sort, 'dir' => $sort ? $dir : null])) }}" class="btn btn-outline-secondary">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $sortBase = array_filter([
                    'q' => $search !== '' ? $search : null,
                    'status' => $status !== 'open' ? $status : null,
                ]);
                $symbolDir = ($sort === 'id' && $dir === 'asc') ? 'desc' : 'asc';
                $activityDir = ($sort === 'last_activity' && $dir === 'desc') ? 'asc' : 'desc';
                $symbolSortUrl = route('closed-training-cases.index', array_merge($sortBase, ['sort' => 'id', 'dir' => $symbolDir]));
                $activitySortUrl = route('closed-training-cases.index', array_merge($sortBase, ['sort' => 'last_activity', 'dir' => $activityDir]));
            @endphp

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th style="width: 5.5rem;" title="Sortowanie po numerze sprawy (id)">
                                    <a href="{{ $symbolSortUrl }}" class="text-decoration-none text-dark">
                                        Symbol
                                        @if($sort === 'id')
                                            <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                        @else
                                            <i class="bi bi-caret-down text-muted" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>Temat / placówka</th>
                                <th style="min-width: 14rem;" title="Termin, instruktor (wynagrodzenie), cena dla szkoły. Zielone / ✓ = potwierdzone">Ustalenia</th>
                                <th style="min-width: 7rem;" title="Sortowanie po dacie ostatniego wpisu w historii">
                                    <a href="{{ $activitySortUrl }}" class="text-decoration-none text-dark">
                                        Ostatni wpis
                                        @if($sort === 'last_activity')
                                            <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                        @else
                                            <i class="bi bi-caret-down text-muted" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Opiekun</th>
                                <th>Następne działanie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                @php
                                    $schedule = $case->listScheduleLabelCompact();
                                    $instructor = $case->listInstructorName();
                                    $fee = $case->instructor_fee;
                                    $price = $case->client_price;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('closed-training-cases.show', $case) }}"
                                           class="font-monospace text-decoration-none fw-semibold"
                                           title="Ten sam symbol jest w temacie e-maila do szkoły">[{{ $case->referenceCode() }}]</a>
                                    </td>
                                    <td style="min-width: 12rem; max-width: 18rem;">
                                        <a href="{{ route('closed-training-cases.show', $case) }}" class="fw-semibold text-decoration-none">{{ $case->trainingTopicLabel() }}</a>
                                        <div class="text-muted">{{ $case->institution ?: '—' }} · {{ $case->contact_name }}</div>
                                        @php($messagePreview = trim(preg_replace('/\s+/u', ' ', (string) $case->message)))
                                        @if($messagePreview !== '')
                                            <div class="text-muted text-truncate" style="max-width: 18rem;" title="{{ $messagePreview }}">{{ \Illuminate\Support\Str::limit($messagePreview, 80) }}</div>
                                        @endif
                                    </td>
                                    <td class="font-monospace" style="font-size: 0.8rem; line-height: 1.35;">
                                        <div class="{{ $schedule === '—' ? 'text-muted' : '' }}" title="{{ $case->listScheduleLabel() }}">
                                            <span class="text-muted">TERMIN:</span> {{ $schedule }}
                                        </div>
                                        <div title="{{ $case->course ? 'Instruktor ze powiązanego szkolenia' : 'Instruktor z ustaleń na sprawie' }}">
                                            @if($instructor === '—')
                                                <span class="text-muted">—</span>
                                            @else
                                                {{ $instructor }}@if($fee !== null)
                                                    <span class="{{ $case->instructor_fee_confirmed_at ? 'text-success fw-semibold' : 'text-muted' }}"
                                                          title="Wynagrodzenie{{ $case->instructor_fee_confirmed_at ? ' — potwierdzone' : '' }}">
                                                        ({{ $case->formatMoneyList($fee) }}@if($case->instructor_fee_confirmed_at) ✓@endif)
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="{{ $price === null ? 'text-muted' : '' }} {{ $case->client_price_confirmed_at ? 'text-success fw-semibold' : '' }}"
                                             title="Cena dla szkoły{{ $case->client_price_confirmed_at ? ' — potwierdzona' : '' }}">
                                            <span class="{{ $case->client_price_confirmed_at ? 'text-success' : 'text-muted' }}">CENA:</span>
                                            {{ $case->formatMoneyList($price) }}@if($case->client_price_confirmed_at) ✓@endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap text-muted">
                                        @if($case->lastActivityAt())
                                            {{ $case->formatLocal($case->lastActivityAt()) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span></td>
                                    <td>{{ $case->assignee?->name ?: '—' }}</td>
                                    <td class="{{ $case->nextActionIsOverdue() ? 'text-danger' : '' }}" style="max-width: 12rem;">
                                        <div class="text-truncate" title="{{ $case->next_action }}">{{ $case->next_action ?: '—' }}</div>
                                        @if($case->next_action_at)
                                            <div class="text-muted">{{ $case->formatLocal($case->next_action_at) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted p-4">Brak spraw dla tego filtra.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">{{ $cases->links() }}</div>
        </div>
    </div>
</x-app-layout>
