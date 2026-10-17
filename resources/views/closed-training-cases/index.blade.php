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
                            <input type="text" name="q" id="q" value="{{ $search }}" class="form-control" placeholder="Temat, placówka, osoba, e-mail">
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
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Temat szkolenia</th>
                                <th>Placówka</th>
                                <th>Status</th>
                                <th>Opiekun</th>
                                <th>Następne działanie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                <tr>
                                    <td>
                                        <a href="{{ route('closed-training-cases.show', $case) }}">{{ $case->trainingTopicLabel() }}</a>
                                        <div class="small text-muted">{{ $case->sourceLabel() }}</div>
                                    </td>
                                    <td>
                                        {{ $case->institution ?: '—' }}
                                        <div class="small text-muted">{{ $case->contact_name }}</div>
                                    </td>
                                    <td><span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span></td>
                                    <td>{{ $case->assignee?->name ?: '—' }}</td>
                                    <td class="{{ $case->nextActionIsOverdue() ? 'text-danger' : '' }}">
                                        {{ $case->next_action ?: '—' }}
                                        @if($case->next_action_at)
                                            <div class="small">{{ $case->formatLocal($case->next_action_at) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted p-4">Brak spraw dla tego filtra.</td>
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
