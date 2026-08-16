{{-- Lista uczestników zamówienia + provision PNEDU per osoba / wszyscy --}}
@php
    use App\Services\FormOrderOperationalStatusService;

    $ops = app(FormOrderOperationalStatusService::class);
    $courseIdForProvision = $ops->resolveCourseId($zamowienie);
    $orderParticipants = $zamowienie->relationLoaded('participants')
        ? $zamowienie->participants->sortBy('id')->values()
        : $zamowienie->participants()->orderBy('id')->get();
    $activeParticipants = $orderParticipants->filter(fn ($p) => trim((string) ($p->participant_email ?? '')) !== '');
    $unprovisionedCount = $courseIdForProvision
        ? $activeParticipants->filter(fn ($p) => ! $ops->isParticipantProvisioned($p, $courseIdForProvision))->count()
        : $activeParticipants->count();
    $hasPubligoIds = $zamowienie->hasEffectivePubligoIds();
    $publigoAlreadySent = (int) $zamowienie->publigo_sent === 1;
    $expandFopId = isset($expandFopId) ? (int) $expandFopId : 0;
@endphp

<div id="formOrderParticipantsRoot"
     data-participants-partial-url="{{ route('form-orders.participants-cards', $zamowienie->id) }}">
<div class="card mb-3">
    <div class="card-header bg-success text-white py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-people"></i>
            UCZESTNIK{{ $activeParticipants->count() > 1 ? 'CY' : '' }}
            @if($activeParticipants->count() > 0)
                <span class="badge bg-light text-success ms-1">{{ $activeParticipants->count() }}</span>
            @endif
        </h6>
    </div>
    <div class="card-body py-2">
        @forelse($activeParticipants as $fop)
            @php
                $isProvisioned = $courseIdForProvision
                    ? $ops->isParticipantProvisioned($fop, $courseIdForProvision)
                    : (bool) $fop->participant_id;
                $fullName = trim((string) $fop->full_name) ?: '—';
                $email = trim((string) $fop->participant_email);
                $emailDiffers = ! empty($zamowienie->orderer_email)
                    && strtolower($email) !== strtolower(trim((string) $zamowienie->orderer_email));
                $liveAccess = $fop->relationLoaded('participant')
                    ? $fop->participant?->liveAccess
                    : $fop->participant?->liveAccess;
            @endphp
            <div class="border rounded p-2 mb-2 {{ $loop->last ? 'mb-0' : '' }} bg-light bg-opacity-50"
                 data-fop-card="{{ (int) $fop->id }}">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <div>
                        <strong>{{ $fullName }}</strong>
                        @if($activeParticipants->count() > 1)
                            <span class="badge bg-secondary ms-1">{{ $loop->iteration }}</span>
                        @endif
                        @if($fop->is_primary)
                            <span class="badge bg-primary ms-1">główny</span>
                        @endif
                        @if($isProvisioned)
                            <span class="badge bg-success ms-1 js-pnedu-badge">PNEDU</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn-outline-success btn-sm"
                            onclick="copyTextToClipboard(@js($fullName), 'Skopiowano uczestnika')">
                        <i class="bi bi-clipboard"></i> Uczestnik
                    </button>
                </div>
                @if($email !== '')
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small>
                            <i class="bi bi-envelope"></i>
                            <a href="mailto:{{ $email }}"
                               class="text-decoration-none @if(! $emailDiffers) bg-warning bg-opacity-25 px-1 rounded @endif"
                               @if(! $emailDiffers) title="Ten sam email co do faktury" @endif>
                                {{ $email }}
                            </a>
                        </small>
                        <button type="button" class="btn btn-outline-info btn-sm"
                                onclick="copyTextToClipboard(@js($email), 'Skopiowano e-mail')">
                            <i class="bi bi-clipboard"></i> Email uczestnika
                        </button>
                    </div>
                @endif

                @unless($isProvisioned)
                    @if($emailDiffers)
                        <div class="form-check mb-2 small">
                            <input class="form-check-input js-add-to-sendy"
                                   type="checkbox"
                                   value="1"
                                   id="addToSendy_{{ (int) $fop->id }}"
                                   data-fop-id="{{ (int) $fop->id }}">
                            <label class="form-check-label" for="addToSendy_{{ (int) $fop->id }}">
                                Dodaj uczestnika do listy e-mailowej
                            </label>
                        </div>
                    @endif
                    <button type="button"
                            class="btn btn-warning btn-sm w-100 js-pnedu-provision-btn"
                            onclick="provisionPnedu({{ $zamowienie->id }}, { formOrderParticipantId: {{ (int) $fop->id }} })">
                        <i class="bi bi-plus-circle"></i> Dodaj uczestnika do PNEDU
                    </button>
                    <div id="pneduResult_{{ (int) $fop->id }}" class="js-pnedu-card-result mt-2"></div>
                @else
                    @php
                        $cmStatus = $liveAccess?->status
                            ?: ($activeParticipants->count() === 1 ? $zamowienie->pnedu_clickmeeting_status : null);
                        $cmMessage = $liveAccess?->message
                            ?: ($activeParticipants->count() === 1 ? $zamowienie->pnedu_clickmeeting_message : null);
                        $cmSyncedAt = $liveAccess?->synced_at
                            ?: ($activeParticipants->count() === 1 ? $zamowienie->pnedu_clickmeeting_synced_at : null);
                        $cmBadgeClass = match ($cmStatus) {
                            'success' => 'bg-success',
                            'failed' => 'bg-danger',
                            'token_missing' => 'bg-warning text-dark',
                            'skipped_missing_event_id' => 'bg-warning text-dark',
                            'skipped_not_clickmeeting' => 'bg-secondary',
                            default => 'bg-secondary',
                        };
                        $cmLabel = match ($cmStatus) {
                            'success' => 'Dodano',
                            'failed' => 'Błąd',
                            'token_missing' => 'Brak tokenu',
                            'skipped_missing_event_id' => 'Pominięto (brak ID)',
                            'skipped_not_clickmeeting' => 'Pominięto (inna platforma)',
                            default => 'Brak informacji',
                        };
                        $cmDetail = filled(trim((string) $cmMessage))
                            ? $cmMessage
                            : 'Status ClickMeeting będzie widoczny po provisionie z integracją CM.';
                        $step2Ok = \App\Services\FormOrderPneduProvisionService::isClickMeetingStepOk(
                            $cmStatus,
                            $liveAccess?->token,
                            $liveAccess?->access_type !== null ? (int) $liveAccess->access_type : null
                        );
                        $step3Ok = true;
                        $okSteps = 1 + ($step2Ok ? 1 : 0) + ($step3Ok ? 1 : 0);
                        $startExpanded = $okSteps < 3 || $expandFopId === (int) $fop->id;
                    @endphp
                    @include('form-orders.partials.participant-pnedu-status', [
                        'zamowienie' => $zamowienie,
                        'fop' => $fop,
                        'fullName' => $fullName,
                        'email' => $email,
                        'liveAccess' => $liveAccess,
                        'cmStatus' => $cmStatus,
                        'cmBadgeClass' => $cmBadgeClass,
                        'cmLabel' => $cmLabel,
                        'cmDetail' => $cmDetail,
                        'cmSyncedAt' => $cmSyncedAt,
                        'step2Ok' => $step2Ok,
                        'step3Ok' => $step3Ok,
                        'okSteps' => $okSteps,
                        'totalSteps' => 3,
                        'startExpanded' => $startExpanded,
                    ])
                @endunless
            </div>
        @empty
            <p class="text-muted small mb-0">Brak uczestników w zamówieniu.</p>
        @endforelse

        @if($unprovisionedCount > 1)
            <div class="mt-2">
                <button type="button"
                        class="btn w-100 js-pnedu-provision-btn text-white"
                        style="background-color: #c77700; border-color: #a86300;"
                        onclick="provisionPneduAll({{ $zamowienie->id }})">
                    <i class="bi bi-people"></i> Dodaj wszystkich naraz ({{ $unprovisionedCount }})
                </button>
                <div id="pneduResultAll" class="js-pnedu-card-result mt-2"></div>
            </div>
        @elseif($unprovisionedCount === 1 && $activeParticipants->count() === 1)
            {{-- pojedynczy przycisk jest już przy karcie --}}
        @endif

        @php
            $provisionedCount = $activeParticipants->count() - $unprovisionedCount;
            $canResetAll = (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))
                && $provisionedCount > 1;
        @endphp

        @if($zamowienie->pnedu_provisioned_at && $unprovisionedCount === 0)
            <div class="alert alert-success mb-2 mt-2 py-2">
                <i class="bi bi-check-circle"></i>
                <strong>Dostęp PNEDU przyznany dla wszystkich uczestników.</strong>
                <small class="d-block text-muted mt-1">
                    Data: {{ $zamowienie->pnedu_provisioned_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                </small>
                @if($canResetAll)
                    <div class="mt-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger js-reset-pnedu-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#resetPneduModal"
                                data-reset-all="1"
                                data-participant-name="wszyscy uczestnicy ({{ $provisionedCount }})"
                                data-participant-email=""
                                data-has-cm-token="0">
                            <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU wszystkim
                        </button>
                    </div>
                @endif
            </div>
        @elseif($canResetAll)
            <div class="mt-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger js-reset-pnedu-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#resetPneduModal"
                        data-reset-all="1"
                        data-participant-name="wszyscy provisionowani ({{ $provisionedCount }})"
                        data-participant-email=""
                        data-has-cm-token="0">
                    <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU wszystkim ({{ $provisionedCount }})
                </button>
            </div>
        @endif

        {{-- PUBLIGO (legacy) — bez zmian w multi-participant --}}
        @if($hasPubligoIds)
            <div class="mt-2">
                @if($publigoAlreadySent)
                    <div class="alert alert-success mb-2 py-2">
                        <i class="bi bi-check-circle"></i>
                        <strong>Zamówienie zostało wysłane do Publigo</strong>
                        <small class="d-block text-muted mt-1">
                            Data: {{ $zamowienie->publigo_sent_at ? $zamowienie->publigo_sent_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') : 'Nieznana' }}
                        </small>
                        @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#resetPubligoModal">
                                    <i class="bi bi-arrow-clockwise"></i> Resetuj status Publigo
                                </button>
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-primary w-100" disabled title="Już wysłane do Publigo">
                        <i class="bi bi-plus-circle"></i> Dodaj zamówienie przez PUBLIGO
                    </button>
                @else
                    <button type="button" class="btn btn-primary w-100" id="publigoOrderBtn" onclick="createPubligoOrder({{ $zamowienie->id }})">
                        <i class="bi bi-plus-circle"></i> Dodaj zamówienie przez PUBLIGO
                    </button>
                @endif
                <div id="publigoResult" class="mt-2"></div>
            </div>
        @endif

        <div id="pneduResult" class="mt-2"></div>
    </div>
</div>
</div>
