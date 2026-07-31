<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Windykacja: sprawa #{{ $case->id }}
        </h2>
    </x-slot>

    @php($order = $case->formOrder)

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Nie udało się zapisać danych:</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Lista spraw
                    </a>
                    <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">
                        Zamówienie #{{ $order->id }}
                    </a>
                    <a href="{{ route('accounting.debtors.index') }}" class="btn btn-outline-success btn-sm">
                        Lookup faktury
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($previousCase)
                        <a href="{{ route('accounting.collections.show', $previousCase) }}"
                           class="btn btn-outline-dark btn-sm"
                           title="Nowsza sprawa #{{ $previousCase->id }}">
                            <i class="bi bi-chevron-left"></i> Poprzednia
                        </a>
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-chevron-left"></i> Poprzednia
                        </button>
                    @endif
                    @if($nextCase)
                        <a href="{{ route('accounting.collections.show', $nextCase) }}"
                           class="btn btn-outline-dark btn-sm"
                           title="Starsza sprawa #{{ $nextCase->id }}">
                            Następna <i class="bi bi-chevron-right"></i>
                        </a>
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                            Następna <i class="bi bi-chevron-right"></i>
                        </button>
                    @endif
                </div>
            </div>

            @if($case->isVip())
                <div class="alert alert-warning">
                    <div class="fw-semibold">
                        <i class="bi bi-star-fill"></i> VIP / lojalny klient — zalecany kontakt osobisty.
                    </div>
                    <div>
                        Ten kontrahent ma wysoką relację z PNE. Zanim wyślesz formalny monit, rozważ telefon lub personalny e-mail z prośbą o pomoc w identyfikacji wpłaty.
                    </div>
                    @if($case->vip_reason)
                        <div class="small mt-1">Powód: {{ $case->vip_reason }}</div>
                    @endif
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dane sprawy</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Zamówienie</dt>
                                <dd class="col-sm-8">#{{ $order->id }}</dd>
                                <dt class="col-sm-4">Faktura</dt>
                                <dd class="col-sm-8">{{ $case->invoice_number ?: $order->invoice_number ?: '—' }}</dd>
                                <dt class="col-sm-4">KSeF</dt>
                                <dd class="col-sm-8">{{ $case->ksef_number ?: $order->ksef_number ?: '—' }}</dd>
                                <dt class="col-sm-4">ID iFirma</dt>
                                <dd class="col-sm-8">
                                    @if($order->hasIfirmaInvoiceId())
                                        <code>{{ $order->ifirma_invoice_id }}</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                                <dt class="col-sm-4">Status iFirma</dt>
                                <dd class="col-sm-8">
                                    @if($case->ifirma_payment_status)
                                        <span class="badge {{ \App\Services\IfirmaInvoicePaymentStatusService::statusBadgeClass($case->ifirma_payment_status) }}">
                                            {{ $case->ifirmaPaymentStatusLabel() }}
                                        </span>
                                        @if($case->ifirma_synced_at)
                                            <div class="small text-muted">
                                                sync {{ $case->ifirma_synced_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">Nie synchronizowano</span>
                                    @endif
                                    <form method="POST" action="{{ route('accounting.collections.sync-ifirma', $case) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-repeat"></i> Odśwież status z iFirma
                                        </button>
                                    </form>
                                    <div class="form-text">Tylko odczyt — nie zamyka sprawy automatycznie.</div>
                                </dd>
                                <dt class="col-sm-4">Kwota</dt>
                                <dd class="col-sm-8">{{ number_format((float) ($case->amount_gross ?? $order->product_price ?? 0), 2, ',', ' ') }} zł</dd>
                                <dt class="col-sm-4">Termin</dt>
                                <dd class="col-sm-8">{{ $case->due_date?->format('d.m.Y') ?: '—' }}</dd>
                                <dt class="col-sm-4">Klient</dt>
                                <dd class="col-sm-8">
                                    {{ $order->recipient_name ?: $order->buyer_name ?: $order->orderer_name ?: '—' }}
                                    <div class="small text-muted">{{ $order->orderer_email ?: $order->display_participant_email }}</div>
                                </dd>
                                <dt class="col-sm-4">Utworzył</dt>
                                <dd class="col-sm-8">{{ $case->createdBy?->name ?: '—' }}</dd>
                                <dt class="col-sm-4">Opiekun</dt>
                                <dd class="col-sm-8">{{ $case->assignedTo?->name ?: '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Status operacyjny</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.update', $case) }}" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-6 col-lg-3">
                                    <label class="form-label small mb-1" for="status">Status</label>
                                    <select class="form-select form-select-sm" id="status" name="status">
                                        @foreach($statusLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <label class="form-label small mb-1" for="priority">Priorytet</label>
                                    <select class="form-select form-select-sm" id="priority" name="priority">
                                        <option value="low" @selected($case->priority === 'low')>Niski</option>
                                        <option value="normal" @selected($case->priority === 'normal')>Normalny</option>
                                        <option value="high" @selected($case->priority === 'high')>Wysoki</option>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <label class="form-label small mb-1" for="customer_segment">Segment</label>
                                    <select class="form-select form-select-sm" id="customer_segment" name="customer_segment">
                                        @foreach($segmentLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->customer_segment === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <label class="form-label small mb-1" for="next_action_at">Następny kontakt</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="next_action_at" name="next_action_at"
                                           value="{{ $case->next_action_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="summary">Podsumowanie</label>
                                    <textarea class="form-control form-control-sm" id="summary" name="summary" rows="2">{{ $case->summary }}</textarea>
                                </div>
                                <div class="col-12 col-lg-5">
                                    <label class="form-label small mb-1" for="vip_reason">Powód VIP / delikatnej obsługi</label>
                                    <input type="text" class="form-control form-control-sm" id="vip_reason" name="vip_reason" value="{{ $case->vip_reason }}">
                                </div>
                                <div class="col-12 col-lg-5 d-flex align-items-end gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="manual_vip" name="manual_vip" value="1" @checked($case->manual_vip)>
                                        <label class="form-check-label" for="manual_vip">VIP ręcznie</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="do_not_auto_dun" name="do_not_auto_dun" value="1" @checked($case->do_not_auto_dun)>
                                        <label class="form-check-label" for="do_not_auto_dun">Bez automatycznego monitu</label>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-save"></i> Zapisz
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dodaj działanie</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.actions.store', $case) }}" class="row g-2">
                                @csrf
                                <div class="col-6 col-lg-4">
                                    <label class="form-label small mb-1" for="action_type">Typ</label>
                                    <select class="form-select form-select-sm" id="action_type" name="action_type" required>
                                        @foreach($actionTypeLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <label class="form-label small mb-1" for="promised_payment_at">Obietnica do</label>
                                    <input type="date" class="form-control form-control-sm" id="promised_payment_at" name="promised_payment_at">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label small mb-1" for="action_next_action_at">Następny kontakt</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="action_next_action_at" name="next_action_at">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="note">Notatka</label>
                                    <textarea class="form-control form-control-sm" id="note" name="note" rows="3" placeholder="Co ustalono, z kim rozmawiano, jaki następny krok?"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-plus-circle"></i> Dodaj działanie
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dodaj alternatywny kontakt</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.contacts.store', $case) }}" class="row g-2">
                                @csrf
                                <div class="col-6 col-lg-3">
                                    <label class="form-label small mb-1" for="contact_type">Typ</label>
                                    <select class="form-select form-select-sm" id="contact_type" name="contact_type" required>
                                        @foreach($contactTypeLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-5">
                                    <label class="form-label small mb-1" for="value">Wartość</label>
                                    <input type="text" class="form-control form-control-sm" id="value" name="value" required>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label small mb-1" for="source">Źródło</label>
                                    <input type="text" class="form-control form-control-sm" id="source" name="source" placeholder="np. strona szkoły">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="contact_notes">Notatka</label>
                                    <input type="text" class="form-control form-control-sm" id="contact_notes" name="notes">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-person-plus"></i> Dodaj kontakt
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header fw-semibold">Historia działań</div>
                        <div class="card-body">
                            @forelse($case->actions as $action)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="fw-semibold">{{ $action->typeLabel() }}</span>
                                        <span class="small text-muted">{{ $action->happened_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?: '—' }}</span>
                                    </div>
                                    @if($action->promised_payment_at)
                                        <div class="small text-success">Obietnica płatności do: {{ $action->promised_payment_at->format('d.m.Y') }}</div>
                                    @endif
                                    @if($action->next_action_at)
                                        <div class="small text-primary">Następny kontakt: {{ $action->next_action_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                    @endif
                                    <div class="small">{{ $action->note ?: '—' }}</div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person"></i>
                                        {{ $action->user?->name ?: 'System / brak użytkownika' }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Brak działań.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">Kontakty</div>
                        <div class="card-body">
                            @forelse($case->contacts as $contact)
                                <div class="border-bottom pb-2 mb-2">
                                    <span class="badge text-bg-light border">{{ $contact->typeLabel() }}</span>
                                    <span class="fw-semibold">{{ $contact->value }}</span>
                                    @if($contact->source)
                                        <span class="small text-muted">Źródło: {{ $contact->source }}</span>
                                    @endif
                                    @if($contact->notes)
                                        <div class="small">{{ $contact->notes }}</div>
                                    @endif
                                    <div class="small text-muted">
                                        <i class="bi bi-person"></i>
                                        {{ $contact->createdBy?->name ?: 'System / brak użytkownika' }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Brak dodatkowych kontaktów.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header fw-semibold">Historia powiązanych zamówień</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Faktura</th>
                                        <th>Szkolenie</th>
                                        <th class="text-end">Kwota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($relatedOrders as $relatedOrder)
                                        <tr>
                                            <td><a href="{{ route('form-orders.show', $relatedOrder->id) }}">#{{ $relatedOrder->id }}</a></td>
                                            <td>{{ $relatedOrder->invoice_number ?: '—' }}</td>
                                            <td>{{ $relatedOrder->product_name ?: '—' }}</td>
                                            <td class="text-end">{{ number_format((float) ($relatedOrder->product_price ?? 0), 2, ',', ' ') }} zł</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-muted text-center py-3">Brak powiązanych zamówień.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">
                            Powiązania po NIP/e-mailu. Łącznie: {{ $profile['related_orders_count'] }} zamówień,
                            {{ number_format((float) $profile['related_orders_total'], 2, ',', ' ') }} zł.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
