@php
    $sentCount = (int) ($mailStatus['sent_count'] ?? 0);
    $lastSentAt = $mailStatus['last_sent_at'] ?? null;
    $hasQueued = (bool) ($mailStatus['has_queued'] ?? false);
    $hasFailed = (bool) ($mailStatus['has_failed'] ?? false);
    $lastError = $mailStatus['last_error'] ?? null;
    $lastDelivery = is_array($mailStatus['last_delivery'] ?? null) ? $mailStatus['last_delivery'] : null;
    $sentWithoutRealDelivery = (bool) ($mailStatus['sent_without_real_delivery'] ?? false);

    if ($sentCount > 0 && $sentWithoutRealDelivery) {
        $state = 'sent_log_only';
    } elseif ($sentCount > 0) {
        $state = 'sent';
    } elseif ($hasQueued) {
        $state = 'queued';
    } elseif ($hasFailed) {
        $state = 'failed';
    } else {
        $state = 'not_sent';
    }

    $tooltipParts = ['E-mail: przeniesienie na pnedu.pl'];
    if ($state === 'sent' && $lastSentAt) {
        $tooltipParts[] = 'Wysłano: '.$lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i');
        if ($sentCount > 1) {
            $tooltipParts[] = 'Liczba wysyłek: '.$sentCount;
        }
        if ($lastDelivery) {
            $tooltipParts[] = 'Mailer: '.($lastDelivery['mailer'] ?? '?');
        }
    } elseif ($state === 'sent_log_only' && $lastSentAt) {
        $tooltipParts[] = 'Zapisano tylko lokalnie (log) — brak wysyłki do Internetu';
        $tooltipParts[] = $lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i');
    } elseif ($state === 'queued') {
        $tooltipParts[] = 'W kolejce do wysłania';
    } elseif ($state === 'failed') {
        $tooltipParts[] = 'Ostatnia próba nieudana';
        if ($lastError) {
            $tooltipParts[] = $lastError;
        }
    } else {
        $tooltipParts[] = 'Nie wysłano';
    }
    $tooltip = implode(' — ', $tooltipParts);

    $iconClass = match ($state) {
        'sent' => 'bi-envelope-check-fill text-success',
        'sent_log_only' => 'bi-envelope-exclamation-fill text-warning',
        'queued' => 'bi-hourglass-split text-warning',
        'failed' => 'bi-envelope-x-fill text-danger',
        default => 'bi-envelope text-secondary',
    };
@endphp
<div class="small" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ e($tooltip) }}">
    <i class="bi {{ $iconClass }}" aria-hidden="true"></i>
    @if($state === 'sent' && $lastSentAt)
        <span class="d-block text-success">{{ $lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}</span>
        @if($sentCount > 1)
            <span class="d-block text-muted">{{ $sentCount }}×</span>
        @endif
    @elseif($state === 'sent_log_only' && $lastSentAt)
        <span class="d-block text-warning">{{ $lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i') }} (log)</span>
    @elseif($state === 'queued')
        <span class="d-block text-warning">w kolejce</span>
    @elseif($state === 'failed')
        <span class="d-block text-danger">błąd</span>
    @else
        <span class="d-block text-muted">nie wysłano</span>
    @endif
</div>
