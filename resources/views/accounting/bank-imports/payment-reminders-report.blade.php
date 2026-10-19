<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Raport kampanii · {{ $campaign->audienceLabel() }}
            </h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('accounting.bank-imports.payment-reminders.index', ['audience' => $campaign->audience]) }}"
                   class="btn btn-outline-secondary btn-sm">Kreator</a>
                <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-outline-secondary btn-sm">Import wyciągu</a>
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

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 small">
                        <div class="col-md-3"><span class="text-muted">Status:</span> <strong>{{ $campaign->status }}</strong></div>
                        <div class="col-md-3"><span class="text-muted">Uruchomił:</span> {{ $campaign->starter?->name ?: '—' }}</div>
                        <div class="col-md-3"><span class="text-muted">Start:</span> {{ $campaign->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                        <div class="col-md-3"><span class="text-muted">Wybrane:</span> {{ $campaign->selected_count }}</div>
                        <div class="col-md-3">E-mail: {{ $campaign->send_email ? 'TAK' : 'NIE' }} ({{ $campaign->email_template }})</div>
                        <div class="col-md-3">SMS: {{ $campaign->send_sms ? 'TAK' : 'NIE' }} ({{ $campaign->sms_template }})</div>
                        <div class="col-md-3">iFirma: {{ $campaign->check_ifirma ? 'sprawdzaj' : 'wyłączone' }}</div>
                        <div class="col-md-3">PDF: {{ $campaign->attach_ifirma_pdf ? 'TAK' : 'NIE' }}</div>
                    </div>
                    @if($campaign->isActive())
                        <div class="mt-3">
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cancelCampaignModal">
                                Anuluj oczekujące
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row g-2 mb-3">
                @foreach($counts as $status => $n)
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 small">
                            <div class="text-muted">{{ \App\Models\DebtPaymentReminderCampaignItem::statusLabelFor($status) }}</div>
                            <div class="fs-5 fw-semibold">{{ $n }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Pozycje</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Sprawa</th>
                            <th>Status</th>
                            <th>E-mail</th>
                            <th>SMS</th>
                            <th>Powód / błąd</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($campaign->items as $item)
                            @php $case = $item->debtCase; @endphp
                            <tr>
                                <td class="small">
                                    @if($case)
                                        <a href="{{ route('accounting.collections.show', $case) }}">#{{ $case->id }}</a>
                                        <span class="text-muted">{{ $case->invoice_number ?: '' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">{{ $item->statusLabel() }}</td>
                                <td class="small">{{ $item->email_outcome ?: '—' }}</td>
                                <td class="small">{{ $item->sms_outcome ?: '—' }}</td>
                                <td class="small">
                                    @if($item->skip_reason)
                                        {{ $exclusionLabels[$item->skip_reason] ?? $item->skip_reason }}
                                    @endif
                                    @if($item->error_message)
                                        <div class="text-danger">{{ $item->error_message }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($campaign->isActive())
        <div class="modal fade" id="cancelCampaignModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Anulować oczekujące zadania?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body small">
                        Zadania już w trakcie mogą się dokończyć. Oczekujące zostaną anulowane.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <form method="POST" action="{{ route('accounting.bank-imports.payment-reminders.cancel', $campaign) }}" data-loading-submit>
                            @csrf
                            <button type="submit" class="btn btn-danger">Anuluj oczekujące</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
