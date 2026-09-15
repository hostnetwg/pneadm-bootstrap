<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ $run->title }}
            </h2>
            <a href="{{ route('ops-reports.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Lista raportów
            </a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @php
            $typeMeta = \App\Models\OpsRun::typeMeta($run->type);
            $statusMeta = \App\Models\OpsRun::statusMeta($run->status);
            $summary = $run->summary ?? [];
        @endphp

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge {{ $typeMeta['badge_class'] }}">{{ $typeMeta['label'] }}</span>
                    <span class="badge {{ $statusMeta['badge_class'] }}">{{ $statusMeta['label'] }}</span>
                </div>
                <p class="mb-1">
                    Start: <strong>{{ $run->started_at?->timezone('Europe/Warsaw')->format('d.m.Y H:i') ?: '—' }}</strong>
                    @if($run->finished_at)
                        · koniec: <strong>{{ $run->finished_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}</strong>
                    @endif
                </p>
                @if($run->type === \App\Models\OpsRun::TYPE_KSEF_BACKGROUND)
                    <p class="mb-0 small text-muted">
                        Numery KSeF: {{ $summary['numbers_received'] ?? 0 }}
                        · pozycje: {{ $summary['total'] ?? 0 }}
                        · oczekuje: {{ $summary['pending'] ?? 0 }}
                        · błędy: {{ $summary['failed'] ?? 0 }}
                        · maile: {{ $summary['emails_sent'] ?? 0 }}
                    </p>
                @elseif($run->type === \App\Models\OpsRun::TYPE_ACCESS_EXPIRY_REMINDERS)
                    <p class="mb-0 small text-muted">
                        Szkoleń: {{ $summary['courses'] ?? $summary['total'] ?? 0 }}
                        · zlecono wiadomości: {{ $summary['queued'] ?? 0 }}
                    </p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Obiekt</th>
                                <th>Status</th>
                                <th>Komunikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($run->items as $item)
                                @php
                                    $itemStatus = \App\Models\OpsRun::statusMeta($item->status === 'pending' ? 'running' : $item->status);
                                    $label = $recorder->subjectLabel($item);
                                    $orderUrl = null;
                                    $courseUrl = null;
                                    if ($item->subject instanceof \App\Models\FormOrder) {
                                        $orderUrl = route('form-orders.show', $item->subject->id);
                                    } elseif ($item->subject instanceof \App\Models\Course) {
                                        $courseUrl = route('participants.index', $item->subject);
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        @if($orderUrl)
                                            <a href="{{ $orderUrl }}">{{ $label }}</a>
                                        @elseif($courseUrl)
                                            <a href="{{ $courseUrl }}">{{ $label }}</a>
                                        @else
                                            {{ $label }}
                                        @endif
                                        @if(!empty($item->payload['invoice_number']))
                                            <div class="small text-muted">FV {{ $item->payload['invoice_number'] }}</div>
                                        @endif
                                        @if(!empty($item->payload['ksef_number']))
                                            <div class="small"><code>{{ $item->payload['ksef_number'] }}</code></div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $itemStatus['badge_class'] }}">{{ $itemStatus['label'] }}</span>
                                    </td>
                                    <td class="small">{{ $item->message }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Brak pozycji.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
