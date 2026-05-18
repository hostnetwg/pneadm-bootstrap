@php
    $certStatus = $course->certificate_download_status ?? 'in_preparation';
    $certStatusLabels = [
        'download_enabled' => 'Udostępnione pobieranie zaświadczeń',
        'in_preparation' => 'Zaświadczenie w przygotowaniu',
        'no_certificate' => 'Brak zaświadczenia',
    ];
    $certStatusLabel = $certStatusLabels[$certStatus] ?? $certStatus;

    $emailContents = [];
    if ($courseAccessHasVideos) {
        $emailContents[] = 'dostęp do nagrania szkolenia';
    }
    if ($courseAccessHasMaterials) {
        $emailContents[] = 'materiały szkoleniowe';
    }
    if ($courseAccessHasCertificate) {
        $emailContents[] = 'informację o pobraniu zaświadczenia';
    }
@endphp

<div class="mb-3">
    <h6 class="fw-bold mb-2">Zasoby szkolenia na pnedu.pl</h6>
    <ul class="list-unstyled mb-0 small">
        <li class="mb-1">
            @if($courseAccessHasVideos)
                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Nagrania – dodane</span>
            @else
                <span class="text-muted"><i class="bi bi-x-circle me-1"></i>Nagrania – brak</span>
            @endif
        </li>
        <li class="mb-1">
            @if($courseAccessHasMaterials)
                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Materiały – dodane</span>
            @else
                <span class="text-muted"><i class="bi bi-x-circle me-1"></i>Materiały – brak</span>
            @endif
        </li>
        <li>
            @if($courseAccessHasCertificate)
                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Zaświadczenie – pobieranie udostępnione</span>
            @else
                <span class="text-muted"><i class="bi bi-x-circle me-1"></i>Zaświadczenie – {{ $certStatusLabel }}</span>
            @endif
        </li>
    </ul>
</div>

@if($courseAccessCanSendEmail)
    <div class="alert alert-info border-0 mb-2">
        <i class="bi bi-info-circle me-1"></i>
        <strong>E-mail zostanie wysłany.</strong>
        W treści pojawi się m.in.:
        <span class="d-block mt-1">{{ implode(', ', $emailContents) }}.</span>
    </div>
    <div class="alert alert-light border mb-0 small">
        <i class="bi bi-person-check me-1"></i>
        <strong>Konta pnedu.pl:</strong> nie zakładamy konta automatycznie.
        Uczestnik z kontem dostanie link do panelu szkolenia;
        bez konta – link do zaświadczenia (token, jeśli włączone) oraz informację o dobrowolnej rejestracji na swój adres e-mail (nagrania/materiały).
    </div>
@else
    <div class="alert alert-warning border-0 mb-0">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>E-mail nie zostanie wysłany.</strong>
        <p class="mb-2 mt-2">Brak zasobów do udostępnienia uczestnikowi. Aby włączyć wysyłkę, w edycji kursu:</p>
        <ul class="mb-0 ps-3">
            @if(! $courseAccessHasVideos)
                <li>dodaj co najmniej jedno nagranie,</li>
            @endif
            @if(! $courseAccessHasMaterials)
                <li>dodaj materiały (linki do plików),</li>
            @endif
            @if(! $courseAccessHasCertificate)
                <li>ustaw status zaświadczeń na „Udostępnij pobieranie zaświadczeń” (obecnie: {{ $certStatusLabel }}).</li>
            @endif
        </ul>
    </div>
@endif
