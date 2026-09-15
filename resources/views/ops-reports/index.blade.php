<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-clipboard2-data me-2"></i>
                Raporty automatów
            </h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <p class="text-muted">
            Dziennik zleceń wykonywanych w tle (KSeF, przypomnienia o wygaśnięciu dostępu).
            To nie są logi kliknięć w panelu.
        </p>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtry</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('ops-reports.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="type" class="form-label">Typ</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Wszystkie</option>
                            @foreach(\App\Models\OpsRun::knownTypes() as $knownType)
                                @php $meta = \App\Models\OpsRun::typeMeta($knownType); @endphp
                                <option value="{{ $knownType }}" @selected($type === $knownType)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Wszystkie</option>
                            <option value="running" @selected($status === 'running')>w toku</option>
                            <option value="success" @selected($status === 'success')>OK</option>
                            <option value="partial" @selected($status === 'partial')>częściowo</option>
                            <option value="failed" @selected($status === 'failed')>błąd</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Od</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Do</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $dateTo }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filtruj</button>
                        <a href="{{ route('ops-reports.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Typ</th>
                                <th>Tytuł</th>
                                <th>Status</th>
                                <th>Podsumowanie</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($runs as $run)
                                @php
                                    $typeMeta = \App\Models\OpsRun::typeMeta($run->type);
                                    $statusMeta = \App\Models\OpsRun::statusMeta($run->status);
                                    $summary = $run->summary ?? [];
                                @endphp
                                <tr>
                                    <td class="text-nowrap">
                                        {{ $run->period_date?->format('d.m.Y') ?: $run->started_at?->timezone('Europe/Warsaw')->format('d.m.Y') }}
                                        <div class="small text-muted">{{ $run->started_at?->timezone('Europe/Warsaw')->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $typeMeta['badge_class'] }}">{{ $typeMeta['label'] }}</span>
                                    </td>
                                    <td>{{ $run->title }}</td>
                                    <td>
                                        <span class="badge {{ $statusMeta['badge_class'] }}">{{ $statusMeta['label'] }}</span>
                                    </td>
                                    <td class="small">
                                        @if($run->type === \App\Models\OpsRun::TYPE_KSEF_BACKGROUND)
                                            numery: {{ $summary['numbers_received'] ?? 0 }}
                                            · pozycje: {{ $summary['total'] ?? 0 }}
                                            @if(($summary['failed'] ?? 0) > 0)
                                                · błędy: {{ $summary['failed'] }}
                                            @endif
                                            @if(($summary['pending'] ?? 0) > 0)
                                                · czeka: {{ $summary['pending'] }}
                                            @endif
                                        @elseif($run->type === \App\Models\OpsRun::TYPE_ACCESS_EXPIRY_REMINDERS)
                                            szkoleń: {{ $summary['courses'] ?? $summary['total'] ?? 0 }}
                                            · zlecono: {{ $summary['queued'] ?? 0 }}
                                        @else
                                            {{ $summary['success'] ?? 0 }}/{{ $summary['total'] ?? 0 }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('ops-reports.show', $run) }}" class="btn btn-sm btn-outline-primary">Szczegóły</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Brak raportów. Pojawią się po pierwszej automatycznej wysyłce KSeF albo przypomnieniu o wygaśnięciu dostępu.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($runs->hasPages())
                <div class="card-footer">
                    {{ $runs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
