{{-- Zwijany status 3 kroków PNEDU (po provisionie) --}}
@php
    $fopId = (int) $fop->id;
    $fullName = $fullName ?? (trim((string) $fop->full_name) ?: '—');
    $email = $email ?? trim((string) $fop->participant_email);
    $liveAccess = $liveAccess ?? $fop->participant?->liveAccess;
    $startExpanded = ! empty($startExpanded);
    $okSteps = (int) ($okSteps ?? 3);
    $totalSteps = (int) ($totalSteps ?? 3);
    $cmStatus = $cmStatus ?? null;
    $cmBadgeClass = $cmBadgeClass ?? 'bg-secondary';
    $cmLabel = $cmLabel ?? 'Brak informacji';
    $cmDetail = $cmDetail ?? '';
    $cmSyncedAt = $cmSyncedAt ?? null;
    $step2Ok = $step2Ok ?? ($cmStatus !== 'failed');
    $step3Ok = $step3Ok ?? true;
    $barClass = $okSteps >= $totalSteps ? 'border-success text-success' : 'border-warning text-dark';
    $barBg = $okSteps >= $totalSteps ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-25';
    $canAdminReset = auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin');
    $cardHasCmToken = filled(trim((string) ($liveAccess?->token ?? '')));
@endphp
<div class="js-pnedu-status"
     data-fop-id="{{ $fopId }}"
     data-ok-steps="{{ $okSteps }}"
     data-total-steps="{{ $totalSteps }}"
     data-expanded="{{ $startExpanded ? '1' : '0' }}">
    <button type="button"
            class="btn btn-sm w-100 d-flex align-items-center justify-content-between border {{ $barClass }} {{ $barBg }} js-pnedu-status-toggle py-2">
        <span class="d-flex align-items-center gap-2 text-start">
            <i class="bi {{ $okSteps >= $totalSteps ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
            <span>
                <strong>PNEDU {{ $okSteps >= $totalSteps ? 'OK' : 'częściowo' }}</strong>
                <span class="ms-1">· {{ $okSteps }}/{{ $totalSteps }}</span>
            </span>
        </span>
        <i class="bi bi-chevron-down js-pnedu-status-chevron {{ $startExpanded ? 'd-none' : '' }}"></i>
        <i class="bi bi-chevron-up js-pnedu-status-chevron-up {{ $startExpanded ? '' : 'd-none' }}"></i>
    </button>

    <div class="js-pnedu-status-details mt-2 {{ $startExpanded ? '' : 'd-none' }}">
        <div class="p-2 rounded border bg-white small">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <strong>Krok 1: Uczestnik + konto PNEDU</strong>
                    <div class="text-muted">Rekord w szkoleniu i konto na pnedu.pl.</div>
                </div>
                <span class="badge bg-success">OK</span>
            </div>
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <strong>Krok 2: ClickMeeting</strong>
                    @if($cmSyncedAt)
                        <div class="text-muted">Ostatnia próba: {{ $cmSyncedAt->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}</div>
                    @endif
                    <div class="text-muted">{{ $cmDetail }}</div>
                    @if(! empty($liveAccess?->token))
                        <div class="mt-1">
                            <span class="text-muted">Token:</span>
                            <code class="user-select-all">{{ e($liveAccess->token) }}</code>
                        </div>
                    @endif
                </div>
                <span class="badge {{ $step2Ok ? $cmBadgeClass : ($cmBadgeClass === 'bg-success' ? 'bg-danger' : $cmBadgeClass) }}">{{ $step2Ok ? $cmLabel : ($cmLabel === 'Dodano' ? 'Błąd' : $cmLabel) }}</span>
            </div>
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>Krok 3: E-mail do uczestnika</strong>
                    <div class="text-muted">
                        {{ $step3Ok ? 'Wysłano wiadomość z dostępem (lub ponów poniżej).' : 'Wysłanie e-maila nie powiodło się — spróbuj ponownie.' }}
                    </div>
                </div>
                <span class="badge {{ $step3Ok ? 'bg-success' : 'bg-warning text-dark' }}">{{ $step3Ok ? 'OK' : 'Uwaga' }}</span>
            </div>
        </div>

        <div class="d-flex flex-column gap-2 mt-2">
            <button type="button"
                    class="btn btn-sm btn-outline-primary w-100 js-resend-pnedu-access-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#resendPneduAccessModal"
                    data-participant-name="{{ e($fullName) }}"
                    data-preview-url="{{ route('form-orders.pnedu.access-email-preview', ['id' => $zamowienie->id, 'form_order_participant_id' => $fopId]) }}"
                    data-send-url="{{ route('form-orders.pnedu.resend-access-email', $zamowienie->id) }}"
                    data-form-order-participant-id="{{ $fopId }}">
                <i class="bi bi-envelope"></i> Prześlij dostęp ponownie
            </button>
            @if($canAdminReset)
                <button type="button"
                        class="btn btn-sm btn-outline-danger w-100 js-reset-pnedu-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#resetPneduModal"
                        data-reset-all="0"
                        data-form-order-participant-id="{{ $fopId }}"
                        data-participant-name="{{ e($fullName) }}"
                        data-participant-email="{{ e($email) }}"
                        data-has-cm-token="{{ $cardHasCmToken ? '1' : '0' }}">
                    <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp PNEDU
                </button>
            @endif
        </div>
    </div>
</div>
