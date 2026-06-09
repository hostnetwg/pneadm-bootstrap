@php
    $schedule = $expiryReminderSchedule ?? null;
@endphp
@if(is_array($schedule))
    <div class="alert alert-light border mb-4 py-3" role="status">
        <div class="d-flex flex-wrap align-items-start gap-2">
            <div class="flex-grow-1">
                <i class="fas fa-bell text-warning me-2"></i>
                <strong>Automatyczne przypomnienia o wygaśnięciu dostępu</strong>
                @if($schedule['enabled'] ?? false)
                    <span class="badge bg-success ms-1">włączone</span>
                @else
                    <span class="badge bg-secondary ms-1">wyłączone</span>
                @endif
                <div class="small text-muted mt-2 mb-0">
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
                    Ręcznie: przycisk „Wyślij przypomnienie” w kolumnie <em>Zaświadczenie</em>.
                </div>
            </div>
        </div>
    </div>
@endif
