@php
    use App\Models\CertificateEmailLog;

    $schedule = $expiryReminderSchedule ?? null;
    $reminderStats = $courseEmailDeliveryStats[CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER] ?? [
        'sent' => 0,
        'queued' => 0,
        'failed_without_sent' => 0,
    ];
    $eligible = (int) ($accessExpiryReminderEligibleCount ?? 0);
    $reminderSent = (int) ($reminderStats['sent'] ?? 0);
    $reminderPct = $eligible > 0 ? min(100, (int) round($reminderSent / $eligible * 100)) : 0;
    $canBulkSend = (bool) ($accessExpiryReminderCanBulkSend ?? false);
    $unsentEligible = (int) ($accessExpiryReminderUnsentCount ?? 0);
@endphp
@if(is_array($schedule))
    <div class="card border mb-4 {{ $canBulkSend ? '' : 'bg-light' }}">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <i class="fas fa-bell text-warning me-2"></i>
                    <strong>Automatyczne przypomnienia o wygaśnięciu dostępu</strong>
                    @if($schedule['enabled'] ?? false)
                        <span class="badge bg-success ms-1">włączone</span>
                    @else
                        <span class="badge bg-secondary ms-1">wyłączone</span>
                    @endif
                </div>
                @if($canBulkSend)
                    <span class="badge {{ $reminderSent > 0 ? 'bg-success' : 'bg-secondary' }} fs-6">
                        {{ $reminderSent }}/{{ $eligible }}
                    </span>
                @endif
            </div>

            <div class="small text-muted mb-3">
                @if($schedule['enabled'] ?? false)
                    Dla uczestników <strong>płatnych</strong> szkoleń z datą <code>access_expires_at</code> i nagraniem lub materiałami
                    system wysyła e-mail
                    @if(!empty($schedule['days_label']))
                        <strong>{{ $schedule['days_label'] }}</strong> przed wygaśnięciem
                    @endif
                    (codziennie o <strong>{{ $schedule['schedule_time'] ?? '08:00' }}</strong>, strefa <strong>{{ $schedule['timezone'] ?? 'Europe/Warsaw' }}</strong>).
                @else
                    Włącz w <code>.env</code>: <code>PARTICIPANT_ACCESS_EXPIRY_REMINDERS_ENABLED=true</code>.
                @endif
                W treści: wygasa dostęp do nagrania i materiałów — <strong>link do zaświadczenia pozostaje bezterminowy</strong>.
                Indywidualnie: „Wyślij przypomnienie” w kolumnie <em>Zaświadczenie</em>.
                Status wysyłki: ikona <i class="fas fa-bell text-secondary"></i> przy numerze zaświadczenia w tabeli.
            </div>

            @if($canBulkSend)
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button"
                            class="btn btn-outline-warning btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#bulkAccessExpiryReminderModal"
                            @if($eligible === 0) disabled title="Brak uczestników kwalifikujących się do przypomnienia" @endif>
                        <i class="fas fa-bell me-1"></i> Wyślij przypomnienie teraz
                    </button>
                </div>

                <p class="mb-2 mb-md-1">
                    Wysłano przypomnienie do <strong>{{ $reminderSent }}</strong> z <strong>{{ $eligible }}</strong>
                    {{ $eligible === 1 ? 'osoby kwalifikującej się teraz' : 'osób kwalifikujących się teraz' }}.
                </p>
                <div class="progress mb-2" style="height: 6px;" role="progressbar" aria-valuenow="{{ $reminderPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Postęp wysyłki przypomnień o wygaśnięciu dostępu">
                    <div class="progress-bar {{ $reminderSent > 0 ? 'bg-warning' : 'bg-secondary' }}" style="width: {{ $reminderPct }}%"></div>
                </div>
                @if(($reminderStats['queued'] ?? 0) > 0 || ($reminderStats['failed_without_sent'] ?? 0) > 0)
                    <p class="small text-muted mb-0">
                        @if(($reminderStats['queued'] ?? 0) > 0)
                            <i class="fas fa-clock text-warning me-1"></i>{{ $reminderStats['queued'] }} w kolejce
                        @endif
                        @if(($reminderStats['failed_without_sent'] ?? 0) > 0)
                            @if(($reminderStats['queued'] ?? 0) > 0)<span class="mx-1">·</span>@endif
                            <i class="fas fa-exclamation-circle text-danger me-1"></i>{{ $reminderStats['failed_without_sent'] }} bez udanej wysyłki
                        @endif
                    </p>
                @endif
            @else
                <p class="text-muted mb-0 small">
                    Zbiorcza wysyłka przypomnień jest dostępna dla płatnych szkoleń z nagraniem lub materiałami.
                </p>
            @endif
        </div>
    </div>

    @if($canBulkSend)
        <div class="modal fade" id="bulkAccessExpiryReminderModal" tabindex="-1" aria-labelledby="bulkAccessExpiryReminderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="bulkAccessExpiryReminderModalLabel">
                            <i class="fas fa-bell me-2"></i> Wyślij przypomnienie teraz
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Masowa wysyłka przypomnień o wygaśnięciu dostępu do nagrań i materiałów (treść jak przy przycisku przy uczestniku).
                            Po zakończeniu lista odświeży się — status wysyłki widać przy ikonie <i class="fas fa-bell"></i> w kolumnie nr zaświadczenia.
                        </p>
                        <p class="small text-muted mb-3">
                            Wymaga działającego workera kolejki (<code>sail artisan queue:work</code>).
                            Postęp wysyłki pojawi się w niebieskim pasku u góry strony.
                        </p>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Tryb wysyłki</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_expiry_reminder_mode" id="bulk_expiry_reminder_mode_unsent" value="unsent" @checked($unsentEligible > 0) @disabled($unsentEligible === 0)>
                                <label class="form-check-label" for="bulk_expiry_reminder_mode_unsent">
                                    Wyślij tylko do kwalifikujących się, do których jeszcze nie wysłano
                                    @if($unsentEligible > 0)
                                        (<strong>{{ $unsentEligible }}</strong>)
                                    @endif
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_expiry_reminder_mode" id="bulk_expiry_reminder_mode_eligible" value="eligible" @checked($unsentEligible === 0 && $eligible > 0) @disabled($eligible === 0)>
                                <label class="form-check-label" for="bulk_expiry_reminder_mode_eligible">
                                    Wyślij ponownie do wszystkich kwalifikujących się
                                    @if($eligible > 0)
                                        (<strong>{{ $eligible }}</strong>)
                                    @endif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        @if($eligible > 0)
                            <form action="{{ route('participants.send-access-expiry-reminders-bulk', $course) }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="mode" id="bulk_expiry_reminder_mode_input" value="{{ $unsentEligible > 0 ? 'unsent' : 'eligible' }}">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-paper-plane me-1"></i> Wyślij
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
