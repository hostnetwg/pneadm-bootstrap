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
                            <a href="{{ route('closed-training-cases.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th style="width: 4.5rem;" title="Identyfikator w temacie e-maila do szkoły">Symbol</th>
                                <th>Temat / placówka</th>
                                <th style="min-width: 8rem;">Instruktor</th>
                                <th style="min-width: 9rem;">Termin</th>
                                <th style="min-width: 8.5rem;" title="Szkoła = ryczałt brutto; trener = wynagrodzenie. ✓ = potwierdzone">Kwoty</th>
                                <th style="min-width: 6.5rem;" title="Ostatni wpis w historii sprawy">Ostatni wpis</th>
                                <th>Status</th>
                                <th>Opiekun</th>
                                <th>Następne działanie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
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
                                    <td>
                                        <span title="{{ $case->course ? 'Ze powiązanego szkolenia' : 'Z ustaleń na sprawie' }}">
                                            {{ \Illuminate\Support\Str::limit($case->listInstructorName(), 28) }}
                                        </span>
                                        @if($case->course)
                                            <div class="text-muted" style="font-size: 0.7rem;">ze szkolenia</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="{{ $case->listScheduleLabelCompact() === '—' ? 'text-muted' : '' }}"
                                              title="{{ $case->listScheduleLabel() }}">
                                            {{ $case->listScheduleLabelCompact() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="{{ $case->client_price === null ? 'text-muted' : '' }} {{ $case->client_price_confirmed_at ? 'text-success fw-semibold' : '' }}"
                                             title="Cena dla szkoły{{ $case->client_price_confirmed_at ? ' — potwierdzona' : '' }}">
                                            @if($case->client_price_confirmed_at)<span class="me-1" aria-hidden="true">✓</span>@endif
                                            S: {{ $case->formatMoneyCompact($case->client_price) }}
                                        </div>
                                        <div class="{{ $case->instructor_fee === null ? 'text-muted' : '' }} {{ $case->instructor_fee_confirmed_at ? 'text-success fw-semibold' : '' }}"
                                             title="Wynagrodzenie instruktora{{ $case->instructor_fee_confirmed_at ? ' — potwierdzone' : '' }}">
                                            @if($case->instructor_fee_confirmed_at)<span class="me-1" aria-hidden="true">✓</span>@endif
                                            T: {{ $case->formatMoneyCompact($case->instructor_fee) }}
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
                                    <td colspan="9" class="text-muted p-4">Brak spraw dla tego filtra.</td>
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
