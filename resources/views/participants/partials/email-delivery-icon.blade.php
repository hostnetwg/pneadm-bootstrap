@php
    use App\Models\CertificateEmailLog;

    $hasEmail = !empty($participant->email) && trim((string) $participant->email) !== '';
    $typeData = $emailRow[$emailType] ?? [
        'sent_count' => 0,
        'last_sent_at' => null,
        'has_queued' => false,
        'has_failed' => false,
        'last_error' => null,
        'last_delivery' => null,
        'sent_without_real_delivery' => false,
    ];
    $sentCount = (int) ($typeData['sent_count'] ?? 0);
    $lastSentAt = $typeData['last_sent_at'] ?? null;
    $hasQueued = (bool) ($typeData['has_queued'] ?? false);
    $hasFailed = (bool) ($typeData['has_failed'] ?? false);
    $lastError = $typeData['last_error'] ?? null;
    $lastDelivery = is_array($typeData['last_delivery'] ?? null) ? $typeData['last_delivery'] : null;
    $sentWithoutRealDelivery = (bool) ($typeData['sent_without_real_delivery'] ?? false);

    if (!$hasEmail) {
        $state = 'no_email';
    } elseif ($sentCount > 0 && $sentWithoutRealDelivery) {
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

    $tooltipParts = [$iconTitle];
    if ($state === 'sent' && $lastSentAt) {
        $tooltipParts[] = 'Przekazano do transportu: '.$lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i');
        if ($sentCount > 1) {
            $tooltipParts[] = "Liczba wysyłek: {$sentCount}";
        }
        if ($lastDelivery) {
            $tooltipParts[] = 'Mailer: '.($lastDelivery['mailer'] ?? '?').', transport: '.($lastDelivery['transport'] ?? '?');
            if (!empty($lastDelivery['message_id'])) {
                $tooltipParts[] = 'Message-ID: '.$lastDelivery['message_id'];
            }
        }
    } elseif ($state === 'sent_log_only' && $lastSentAt) {
        $tooltipParts[] = 'Zapisano tylko lokalnie (log) — brak wysyłki do Internetu';
        $tooltipParts[] = $lastSentAt->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i');
        if ($lastDelivery) {
            $tooltipParts[] = 'Mailer: '.($lastDelivery['mailer'] ?? '?').', transport: '.($lastDelivery['transport'] ?? '?');
        }
    } elseif ($state === 'queued') {
        $tooltipParts[] = 'W kolejce do wysłania';
    } elseif ($state === 'failed') {
        $tooltipParts[] = 'Ostatnia próba nieudana';
        if ($lastError) {
            $tooltipParts[] = $lastError;
        }
    } elseif ($state === 'not_sent') {
        $tooltipParts[] = 'Nie wysłano';
    } elseif ($state === 'no_email') {
        $tooltipParts[] = 'Brak adresu e-mail';
    }
    $tooltip = implode(' — ', $tooltipParts);

    $isCertificate = $emailType === CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK;
    $glyph = $isCertificate ? 'fa-certificate' : 'fa-video';

    $iconClass = match ($state) {
        'sent' => $glyph.' text-success',
        'sent_log_only' => $glyph.' text-warning',
        'queued' => $glyph.' text-warning',
        'failed' => $glyph.' text-danger',
        'not_sent' => $glyph.' text-secondary',
        default => $glyph.' text-muted opacity-50',
    };
@endphp
<span class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ e($tooltip) }}">
    <i class="fas {{ $iconClass }}" aria-hidden="true"></i>
</span>
