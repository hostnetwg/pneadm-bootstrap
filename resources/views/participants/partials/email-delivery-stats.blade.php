@php
    $eligible = (int) ($participantsWithEmailCount ?? 0);
    $listStats = $courseEmailDeliveryStats[\App\Models\CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK] ?? ['sent' => 0, 'queued' => 0, 'failed_without_sent' => 0];
    $accessStats = $courseEmailDeliveryStats[\App\Models\CertificateEmailLog::TYPE_COURSE_ACCESS] ?? ['sent' => 0, 'queued' => 0, 'failed_without_sent' => 0];
    $listSent = (int) ($listStats['sent'] ?? 0);
    $accessSent = (int) ($accessStats['sent'] ?? 0);
    $listPct = $eligible > 0 ? min(100, (int) round($listSent / $eligible * 100)) : 0;
    $accessPct = $eligible > 0 ? min(100, (int) round($accessSent / $eligible * 100)) : 0;
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <i class="fas fa-certificate text-primary me-2"></i>
                        <strong>Link do zaświadczeń</strong>
                        <div class="small text-muted mt-1">Lista zaświadczeń lub „Wyślij e-mail to” (token)</div>
                    </div>
                    <span class="badge {{ $listSent > 0 ? 'bg-success' : 'bg-secondary' }} fs-6">
                        {{ $listSent }}/{{ $eligible }}
                    </span>
                </div>
                <p class="mb-2 mb-md-1">
                    Wysłano do <strong>{{ $listSent }}</strong> z <strong>{{ $eligible }}</strong> osób z adresem e-mail.
                </p>
                <div class="progress mb-2" style="height: 6px;" role="progressbar" aria-valuenow="{{ $listPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Postęp wysyłki linku do zaświadczeń">
                    <div class="progress-bar {{ $listSent > 0 ? 'bg-success' : 'bg-secondary' }}" style="width: {{ $listPct }}%"></div>
                </div>
                @if(($listStats['queued'] ?? 0) > 0 || ($listStats['failed_without_sent'] ?? 0) > 0)
                    <p class="small text-muted mb-0">
                        @if(($listStats['queued'] ?? 0) > 0)
                            <i class="fas fa-clock text-warning me-1"></i>{{ $listStats['queued'] }} w kolejce
                        @endif
                        @if(($listStats['failed_without_sent'] ?? 0) > 0)
                            @if(($listStats['queued'] ?? 0) > 0)<span class="mx-1">·</span>@endif
                            <i class="fas fa-exclamation-circle text-danger me-1"></i>{{ $listStats['failed_without_sent'] }} bez udanej wysyłki
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 border {{ ($courseAccessCanSendEmail ?? false) ? '' : 'bg-light' }}">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <i class="fas fa-video text-primary me-2"></i>
                        <strong>{{ $courseAccessEmailLabel ?? 'E-mail: dostęp' }}</strong>
                        <div class="small text-muted mt-1">Zbiorcze „Wyślij e-mail nagranie” / indywidualne „E-mail nagranie”</div>
                    </div>
                    @if($courseAccessCanSendEmail ?? false)
                        <span class="badge {{ $accessSent > 0 ? 'bg-success' : 'bg-secondary' }} fs-6">
                            {{ $accessSent }}/{{ $eligible }}
                        </span>
                    @endif
                </div>
                @if($courseAccessCanSendEmail ?? false)
                    <p class="mb-2 mb-md-1">
                        Wysłano do <strong>{{ $accessSent }}</strong> z <strong>{{ $eligible }}</strong> osób z adresem e-mail.
                    </p>
                    <div class="progress mb-2" style="height: 6px;" role="progressbar" aria-valuenow="{{ $accessPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Postęp wysyłki e-maila z dostępem do szkolenia">
                        <div class="progress-bar {{ $accessSent > 0 ? 'bg-success' : 'bg-secondary' }}" style="width: {{ $accessPct }}%"></div>
                    </div>
                    @if(($accessStats['queued'] ?? 0) > 0 || ($accessStats['failed_without_sent'] ?? 0) > 0)
                        <p class="small text-muted mb-0">
                            @if(($accessStats['queued'] ?? 0) > 0)
                                <i class="fas fa-clock text-warning me-1"></i>{{ $accessStats['queued'] }} w kolejce
                            @endif
                            @if(($accessStats['failed_without_sent'] ?? 0) > 0)
                                @if(($accessStats['queued'] ?? 0) > 0)<span class="mx-1">·</span>@endif
                                <i class="fas fa-exclamation-circle text-danger me-1"></i>{{ $accessStats['failed_without_sent'] }} bez udanej wysyłki
                            @endif
                        </p>
                    @endif
                @else
                    <p class="text-muted mb-0 small">
                        Dla tego szkolenia nie skonfigurowano nagrań, materiałów ani pobierania zaświadczenia — wysyłka tego e-maila jest niedostępna.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
