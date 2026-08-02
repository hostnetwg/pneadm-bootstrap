<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            Windykacja: sprawa #{{ $case->id }}
        </h2>
    </x-slot>

    @php
        $order = $case->formOrder;
        $course = $order?->course;
        $courseTitle = $course
            ? $course->plainTitle((string) ($order->product_name ?: 'Szkolenie'))
            : (string) ($order->product_name ?: '—');
        $courseDateTime = $course?->start_date
            ? $course->start_date->timezone(config('app.timezone'))->format('d.m.Y H:i')
            : null;
        $courseInstructor = trim(($course?->instructor?->first_name ?? '').' '.($course?->instructor?->last_name ?? ''));
        $ordererName = trim((string) ($order->orderer_name ?? ''));
        $ordererEmail = trim((string) ($order->orderer_email ?? ''));
        $participantName = trim((string) ($order->display_participant_name ?? ''));
        $participantEmail = trim((string) ($order->display_participant_email ?? ''));

        $formatPhone = static function (?string $raw): ?array {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return null;
            }
            $digits = preg_replace('/\D+/', '', $raw) ?: '';
            if ($digits === '') {
                return ['display' => $raw, 'tel' => preg_replace('/\s+/', '', $raw) ?: $raw];
            }
            if (strlen($digits) === 9) {
                return [
                    'display' => '+48 '.substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3),
                    'tel' => '+48'.$digits,
                ];
            }
            if (strlen($digits) === 11 && str_starts_with($digits, '48')) {
                return [
                    'display' => '+48 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8, 3),
                    'tel' => '+'.$digits,
                ];
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                $national = substr($digits, 1);

                return [
                    'display' => '+48 '.substr($national, 0, 3).' '.substr($national, 3, 3).' '.substr($national, 6, 3),
                    'tel' => '+48'.$national,
                ];
            }
            if (strlen($digits) >= 10 && strlen($digits) <= 15) {
                return [
                    'display' => '+'.$digits,
                    'tel' => '+'.$digits,
                ];
            }

            return ['display' => $raw, 'tel' => $digits];
        };

        $ordererPhoneFmt = $formatPhone($order->orderer_phone ?? null);
        $participantPhoneFmt = $formatPhone($order->primaryParticipant?->participant?->phone ?? null);
    @endphp

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
                <div class="col-12 col-xl-8">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dane sprawy</div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <dl class="row mb-0 gy-2">
                                        <dt class="col-sm-4 text-muted">Zamówienie</dt>
                                        <dd class="col-sm-8 mb-0">#{{ $order->id }}</dd>

                                        <dt class="col-sm-4 text-muted">Szkolenie</dt>
                                        <dd class="col-sm-8 mb-0">
                                            @if($course)
                                                <a href="{{ route('courses.show', $course->id) }}" class="fw-semibold text-decoration-none">
                                                    {{ $courseTitle }}
                                                </a>
                                            @else
                                                <span class="fw-semibold">{{ $courseTitle }}</span>
                                            @endif
                                            @if($courseDateTime || $courseInstructor !== '')
                                                <div class="small text-muted mt-1">
                                                    @if($courseDateTime)
                                                        <span><i class="bi bi-calendar3"></i> {{ $courseDateTime }}</span>
                                                    @endif
                                                    @if($courseDateTime && $courseInstructor !== '')
                                                        <span class="mx-1">·</span>
                                                    @endif
                                                    @if($courseInstructor !== '')
                                                        <span><i class="bi bi-person"></i> {{ $courseInstructor }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Zamawiający</dt>
                                        <dd class="col-sm-8 mb-0">
                                            <div class="fw-semibold">{{ $ordererName !== '' ? $ordererName : '—' }}</div>
                                            @if($ordererEmail !== '')
                                                <div class="small mt-1">
                                                    <i class="bi bi-envelope"></i>
                                                    <a href="mailto:{{ $ordererEmail }}" class="text-decoration-none">{{ $ordererEmail }}</a>
                                                </div>
                                            @endif
                                            @if($ordererPhoneFmt)
                                                <div class="small mt-1">
                                                    <i class="bi bi-telephone"></i>
                                                    <a href="tel:{{ $ordererPhoneFmt['tel'] }}" class="text-decoration-none text-nowrap">{{ $ordererPhoneFmt['display'] }}</a>
                                                </div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Uczestnik</dt>
                                        <dd class="col-sm-8 mb-0">
                                            <div class="fw-semibold">{{ $participantName !== '' ? $participantName : '—' }}</div>
                                            @if($participantEmail !== '')
                                                <div class="small mt-1">
                                                    <i class="bi bi-envelope"></i>
                                                    <a href="mailto:{{ $participantEmail }}" class="text-decoration-none">{{ $participantEmail }}</a>
                                                    @if($ordererEmail !== '' && strcasecmp($ordererEmail, $participantEmail) === 0)
                                                        <span class="badge text-bg-light border ms-1">jak zamawiający</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($participantPhoneFmt)
                                                <div class="small mt-1">
                                                    <i class="bi bi-telephone"></i>
                                                    <a href="tel:{{ $participantPhoneFmt['tel'] }}" class="text-decoration-none text-nowrap">{{ $participantPhoneFmt['display'] }}</a>
                                                </div>
                                            @elseif($ordererPhoneFmt && ($participantEmail === '' || strcasecmp($ordererEmail, $participantEmail) === 0))
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-telephone"></i> ten sam telefon co zamawiający
                                                </div>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Nabywca</dt>
                                        <dd class="col-sm-8 mb-0">
                                            @if(filled($order->buyer_name) || filled($order->buyer_address) || filled($order->buyer_city) || filled($order->buyer_nip))
                                                <div class="fw-semibold">{{ $order->buyer_name ?: '—' }}</div>
                                                @if(filled($order->buyer_address))
                                                    <div class="small">{{ $order->buyer_address }}</div>
                                                @endif
                                                @if(filled($order->buyer_postal_code) || filled($order->buyer_city))
                                                    <div class="small">{{ trim(($order->buyer_postal_code ?? '').' '.($order->buyer_city ?? '')) }}</div>
                                                @endif
                                                @if(filled($order->buyer_nip))
                                                    <div class="small">NIP: {{ preg_replace('/[^0-9]/', '', (string) $order->buyer_nip) }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Odbiorca</dt>
                                        <dd class="col-sm-8 mb-0">
                                            @if(filled($order->recipient_name) || filled($order->recipient_address) || filled($order->recipient_city) || filled($order->recipient_nip))
                                                <div class="fw-semibold">{{ $order->recipient_name ?: '—' }}</div>
                                                @if(filled($order->recipient_address))
                                                    <div class="small">{{ $order->recipient_address }}</div>
                                                @endif
                                                @if(filled($order->recipient_postal_code) || filled($order->recipient_city))
                                                    <div class="small">{{ trim(($order->recipient_postal_code ?? '').' '.($order->recipient_city ?? '')) }}</div>
                                                @endif
                                                @if(filled($order->recipient_nip))
                                                    <div class="small">NIP: {{ preg_replace('/[^0-9]/', '', (string) $order->recipient_nip) }}</div>
                                                @endif
                                                @if($order->isKsefAdditionalEntityEnabled())
                                                    <div class="small text-muted mt-1">
                                                        Podmiot3:
                                                        {{ \App\Models\FormOrder::ksefAdditionalEntityRoleLabel($order->ksef_additional_entity_role) }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row mb-0 gy-2">
                                        <dt class="col-sm-4 text-muted">Faktura</dt>
                                        <dd class="col-sm-8 mb-0">{{ $case->invoice_number ?: $order->invoice_number ?: '—' }}</dd>

                                        <dt class="col-sm-4 text-muted">KSeF</dt>
                                        <dd class="col-sm-8 mb-0 text-break">{{ $case->ksef_number ?: $order->ksef_number ?: '—' }}</dd>

                                        <dt class="col-sm-4 text-muted">ID iFirma</dt>
                                        <dd class="col-sm-8 mb-0">
                                            @if($order->hasIfirmaInvoiceId())
                                                <code>{{ $order->ifirma_invoice_id }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Status iFirma</dt>
                                        <dd class="col-sm-8 mb-0">
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
                                            <div class="form-text mb-0">Tylko odczyt — nie zamyka sprawy automatycznie.</div>
                                        </dd>

                                        <dt class="col-sm-4 text-muted">Kwota</dt>
                                        <dd class="col-sm-8 mb-0">{{ number_format((float) ($case->amount_gross ?? $order->product_price ?? 0), 2, ',', ' ') }} zł</dd>

                                        <dt class="col-sm-4 text-muted">Termin</dt>
                                        <dd class="col-sm-8 mb-0">{{ $case->due_date?->format('d.m.Y') ?: '—' }}</dd>

                                        <dt class="col-sm-4 text-muted">Utworzył</dt>
                                        <dd class="col-sm-8 mb-0">{{ $case->createdBy?->name ?: '—' }}</dd>

                                        <dt class="col-sm-4 text-muted">Opiekun</dt>
                                        <dd class="col-sm-8 mb-0">{{ $case->assignedTo?->name ?: '—' }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Status operacyjny</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.update', $case) }}" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="status">Status</label>
                                    <select class="form-select form-select-sm" id="status" name="status">
                                        @foreach($statusLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="priority">Priorytet</label>
                                    <select class="form-select form-select-sm" id="priority" name="priority">
                                        <option value="low" @selected($case->priority === 'low')>Niski</option>
                                        <option value="normal" @selected($case->priority === 'normal')>Normalny</option>
                                        <option value="high" @selected($case->priority === 'high')>Wysoki</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="customer_segment">Segment</label>
                                    <select class="form-select form-select-sm" id="customer_segment" name="customer_segment">
                                        @foreach($segmentLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->customer_segment === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="next_action_at">Następny kontakt</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="next_action_at" name="next_action_at"
                                           value="{{ $case->next_action_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="summary">Podsumowanie</label>
                                    <textarea class="form-control form-control-sm" id="summary" name="summary" rows="2">{{ $case->summary }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="vip_reason">Powód VIP / delikatnej obsługi</label>
                                    <input type="text" class="form-control form-control-sm" id="vip_reason" name="vip_reason" value="{{ $case->vip_reason }}">
                                </div>
                                <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="manual_vip" name="manual_vip" value="1" @checked($case->manual_vip)>
                                        <label class="form-check-label" for="manual_vip">VIP ręcznie</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="do_not_auto_dun" name="do_not_auto_dun" value="1" @checked($case->do_not_auto_dun)>
                                        <label class="form-check-label" for="do_not_auto_dun">Bez automatycznego monitu</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-save"></i> Zapisz
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-2 py-2">
                            <button type="button"
                                    class="btn btn-link text-decoration-none text-body fw-semibold p-0 d-inline-flex align-items-center gap-2 border-0"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#caseBankPaymentsCollapse"
                                    aria-expanded="false"
                                    aria-controls="caseBankPaymentsCollapse"
                                    id="caseBankPaymentsToggle">
                                <i class="bi bi-chevron-right case-bank-payments-chevron" aria-hidden="true"></i>
                                <span>Wpłaty z wyciągu</span>
                                @if(($bankPayments ?? collect())->isNotEmpty())
                                    <span class="badge text-bg-secondary">{{ $bankPayments->count() }}</span>
                                @endif
                            </button>
                            <a href="{{ route('accounting.bank-imports.index') }}" class="btn btn-sm btn-outline-secondary">Import wyciągu</a>
                        </div>
                        <div id="caseBankPaymentsCollapse" class="collapse">
                            <div class="card-body border-bottom">
                                <form id="bankTransferSearchForm" class="row g-2 align-items-end">
                                    <div class="col-12 col-lg-5">
                                        <label for="bank_search" class="form-label small mb-1">Szukaj niepowiązanego przelewu</label>
                                        <input type="text"
                                               id="bank_search"
                                               name="bank_search"
                                               class="form-control form-control-sm"
                                               value=""
                                               placeholder="Nadawca, opis, NIP, konto"
                                               maxlength="128"
                                               autocomplete="off">
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <label for="bank_amount" class="form-label small mb-1">Kwota</label>
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               id="bank_amount"
                                               name="bank_amount"
                                               class="form-control form-control-sm"
                                               value="{{ $bankTransferAmount !== null ? number_format((float) $bankTransferAmount, 2, '.', '') : '' }}">
                                    </div>
                                    <div class="col-6 col-lg-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm" id="bankTransferSearchBtn">
                                            <i class="bi bi-search"></i> Szukaj przelewu
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="bankTransferSearchResetBtn">Reset</button>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   value="1"
                                                   id="bank_after_order"
                                                   name="bank_after_order"
                                                   @checked($bankAfterOrderDate ?? true)>
                                            <label class="form-check-label small" for="bank_after_order">
                                                Tylko przelewy z datą operacji ≥ data zamówienia
                                                @if($order?->order_date)
                                                    ({{ $order->order_date->format('Y-m-d') }})
                                                @endif
                                            </label>
                                        </div>
                                        <div class="form-text" id="bankTransferSearchStatus">
                                            Wpisz frazę i kliknij „Szukaj przelewu”. Wyniki obejmują tylko wpływy bez zaakceptowanego/ignorowanego powiązania.
                                        </div>
                                    </div>
                                </form>

                                <div class="mt-3" id="bankTransferSearchResults">
                                    <div class="text-muted small">Brak wyników — wykonaj wyszukiwanie.</div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                @if(($bankPayments ?? collect())->isEmpty())
                                    <div class="p-3 text-muted small">Brak zaakceptowanych wpłat z wyciągu bankowego dla tej sprawy.</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data operacji</th>
                                                    <th class="text-end">Kwota</th>
                                                    <th>Opis</th>
                                                    <th>Zaakceptował</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($bankPayments as $payment)
                                                    @php $tx = $payment->transaction; @endphp
                                                    <tr>
                                                        <td class="small">{{ $tx?->operation_date?->format('Y-m-d') ?? '—' }}</td>
                                                        <td class="text-end fw-semibold text-nowrap">
                                                            {{ $tx ? number_format((float) $tx->amount, 2, ',', ' ').' '.$tx->currency : '—' }}
                                                        </td>
                                                        <td class="small text-break" style="max-width: 28rem;">{{ \Illuminate\Support\Str::limit($tx?->description ?? '—', 160) }}</td>
                                                        <td class="small">
                                                            {{ $payment->acceptedBy?->name ?? '—' }}
                                                            <div class="text-muted">{{ $payment->accepted_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                                        </td>
                                                        <td class="text-end">
                                                            @if($tx)
                                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.bank-imports.show', $tx->bank_statement_import_id) }}">Import</a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
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

    <div class="modal fade" id="bankTransactionLinkConfirmModal" tabindex="-1" aria-labelledby="bankTransactionLinkConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankTransactionLinkConfirmForm">
                    @csrf
                    <input type="hidden" name="register_ifirma_payment" value="0" id="bankTransactionLinkRegisterIfirma">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bankTransactionLinkConfirmModalLabel">Potwierdź powiązanie przelewu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Powiązać wybrany przelew ze sprawą
                            <strong>#{{ $case->id }}</strong>
                            (FV {{ $case->invoice_number ?: $order->invoice_number ?: '—' }})?
                        </p>
                        <div class="border rounded p-2 bg-light small">
                            <div class="fw-semibold" id="bankTransactionLinkSummary">—</div>
                            <div class="text-muted text-break" id="bankTransactionLinkDescription">—</div>
                        </div>
                        <div class="alert alert-info small mt-3 mb-0 d-none" id="bankTransactionLinkIfirmaInfo">
                            Po lokalnym powiązaniu system spróbuje zarejestrować wpłatę w iFirma i odświeżyć status sprawy.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary" id="bankTransactionLinkSubmit">Powiąż lokalnie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        #caseBankPaymentsToggle .case-bank-payments-chevron {
            transition: transform 0.15s ease-in-out;
        }
        #caseBankPaymentsToggle[aria-expanded="true"] .case-bank-payments-chevron {
            transform: rotate(90deg);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('bankTransactionLinkConfirmModal');
            var form = document.getElementById('bankTransactionLinkConfirmForm');
            var registerInput = document.getElementById('bankTransactionLinkRegisterIfirma');
            var summaryEl = document.getElementById('bankTransactionLinkSummary');
            var descriptionEl = document.getElementById('bankTransactionLinkDescription');
            var infoEl = document.getElementById('bankTransactionLinkIfirmaInfo');
            var submitBtn = document.getElementById('bankTransactionLinkSubmit');
            var searchForm = document.getElementById('bankTransferSearchForm');
            var searchInput = document.getElementById('bank_search');
            var amountInput = document.getElementById('bank_amount');
            var afterOrderInput = document.getElementById('bank_after_order');
            var searchStatus = document.getElementById('bankTransferSearchStatus');
            var searchResults = document.getElementById('bankTransferSearchResults');
            var searchResetBtn = document.getElementById('bankTransferSearchResetBtn');
            var searchUrl = @json(route('accounting.collections.bank-transactions.search', $case));
            var defaultAmount = amountInput ? amountInput.value : '';

            function esc(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setSearchStatus(message) {
                if (searchStatus) searchStatus.textContent = message;
            }

            function renderCandidates(candidates) {
                if (!searchResults) return;
                if (!candidates.length) {
                    searchResults.innerHTML = '<div class="text-muted small">Brak kandydatów dla podanych kryteriów.</div>';
                    return;
                }

                var rows = candidates.map(function (c) {
                    return '<tr>'
                        + '<td class="small">' + esc(c.operation_date || '—') + '</td>'
                        + '<td class="text-end fw-semibold text-nowrap">'
                        + esc(c.amount_formatted) + ' ' + esc(c.currency)
                        + (!c.amount_matches ? '<div class="small text-warning">inna kwota</div>' : '')
                        + '</td>'
                        + '<td class="small text-break" style="max-width: 34rem;">'
                        + '<div class="fw-semibold">' + esc(c.account_label || '—') + '</div>'
                        + '<div>' + esc(c.description_short || '—') + '</div>'
                        + '</td>'
                        + '<td class="small">'
                        + '<a href="' + esc(c.import_url) + '" class="text-decoration-none">Import #' + esc(c.import_id) + '</a>'
                        + '<div class="text-muted">' + esc(c.import_filename || '') + '</div>'
                        + '</td>'
                        + '<td class="text-end">'
                        + '<div class="d-flex flex-column flex-md-row gap-1 justify-content-end">'
                        + '<button type="button" class="btn btn-outline-primary btn-sm bank-link-confirm-btn"'
                        + ' data-action="' + esc(c.link_url) + '"'
                        + ' data-register-ifirma="0"'
                        + ' data-summary="' + esc(c.summary) + '"'
                        + ' data-description="' + esc(c.description_confirm) + '">Powiąż lokalnie</button>'
                        + (c.amount_matches
                            ? '<button type="button" class="btn btn-success btn-sm bank-link-confirm-btn"'
                              + ' data-action="' + esc(c.link_url) + '"'
                              + ' data-register-ifirma="1"'
                              + ' data-summary="' + esc(c.summary) + '"'
                              + ' data-description="' + esc(c.description_confirm) + '">+ wpłata iFirma</button>'
                            : '')
                        + '</div></td></tr>';
                }).join('');

                searchResults.innerHTML =
                    '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
                    + '<thead class="table-light"><tr>'
                    + '<th>Data</th><th class="text-end">Kwota</th><th>Nadawca / opis</th><th>Import</th><th class="text-end">Akcja</th>'
                    + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            }

            async function runBankTransferSearch() {
                var q = (searchInput && searchInput.value ? searchInput.value : '').trim();
                if (q.length < 2) {
                    setSearchStatus('Wpisz co najmniej 2 znaki w pole wyszukiwania.');
                    return;
                }

                setSearchStatus('Szukam…');
                var params = new URLSearchParams();
                params.set('bank_search', q);
                if (amountInput && amountInput.value.trim() !== '') {
                    params.set('bank_amount', amountInput.value.trim());
                }
                if (afterOrderInput && afterOrderInput.checked) {
                    params.set('bank_after_order', '1');
                } else {
                    params.set('bank_after_order', '0');
                }

                try {
                    var response = await fetch(searchUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    var payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Błąd wyszukiwania');
                    }
                    var candidates = payload.candidates || [];
                    renderCandidates(candidates);
                    setSearchStatus(candidates.length
                        ? ('Znaleziono: ' + candidates.length)
                        : 'Brak kandydatów dla podanych kryteriów.');
                } catch (e) {
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="text-danger small">Nie udało się wyszukać przelewów.</div>';
                    }
                    setSearchStatus(e.message || 'Nie udało się wyszukać.');
                }
            }

            if (searchForm) {
                searchForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    runBankTransferSearch();
                });
            }
            if (searchResetBtn) {
                searchResetBtn.addEventListener('click', function () {
                    if (searchInput) searchInput.value = '';
                    if (amountInput) amountInput.value = defaultAmount || '';
                    if (afterOrderInput) afterOrderInput.checked = true;
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="text-muted small">Brak wyników — wykonaj wyszukiwanie.</div>';
                    }
                    setSearchStatus('Wpisz frazę i kliknij „Szukaj przelewu”. Wyniki obejmują tylko wpływy bez zaakceptowanego/ignorowanego powiązania.');
                });
            }

            if (!modalEl || !form || !registerInput || !summaryEl || !descriptionEl || !infoEl || !submitBtn || !window.bootstrap) {
                return;
            }

            var modal = new window.bootstrap.Modal(modalEl);
            document.addEventListener('click', function (event) {
                var btn = event.target.closest('.bank-link-confirm-btn');
                if (!btn) return;

                var registerIfirma = btn.getAttribute('data-register-ifirma') === '1';
                form.setAttribute('action', btn.getAttribute('data-action') || '');
                registerInput.value = registerIfirma ? '1' : '0';
                summaryEl.textContent = btn.getAttribute('data-summary') || '—';
                descriptionEl.textContent = btn.getAttribute('data-description') || '—';
                infoEl.classList.toggle('d-none', !registerIfirma);
                submitBtn.textContent = registerIfirma ? 'Powiąż + wpłata iFirma' : 'Powiąż lokalnie';
                submitBtn.className = registerIfirma ? 'btn btn-success' : 'btn btn-primary';
                modal.show();
            });
        });
    </script>
</x-app-layout>
