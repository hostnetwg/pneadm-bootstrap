<?php

namespace App\Models;

use App\Models\Concerns\NormalizesUserEmail;
use App\Notifications\PneduFrontendResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * Użytkownik zarejestrowany na pnedu.pl (baza pnedu, tabela users).
 */
class PneduUser extends Model implements CanResetPasswordContract, HasLocalePreference
{
    use CanResetPassword;
    use NormalizesUserEmail;
    use Notifiable;
    use SoftDeletes;

    protected $connection = 'pnedu';

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_unique_slot',
        'password',
        'birth_date',
        'birth_place',
        'email_verified_at',
        'email_undeliverable_at',
        'email_undeliverable_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_undeliverable_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'login_count' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $first = trim((string) ($this->first_name ?? ''));
        $last = trim((string) ($this->last_name ?? ''));
        $combined = trim($first.' '.$last);

        return $combined !== '' ? $combined : '—';
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PneduFrontendResetPassword($token));
    }

    public function preferredLocale(): string
    {
        return 'pl';
    }

    public function hasUndeliverableEmail(): bool
    {
        return $this->email_undeliverable_at !== null;
    }

    public function clearEmailDeliverabilityFlags(): void
    {
        $this->email_undeliverable_at = null;
        $this->email_undeliverable_reason = null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function getEmailForVerification(): string
    {
        return (string) $this->email;
    }

    public function unverifiedAccountDeletionDeadline(): ?\Illuminate\Support\Carbon
    {
        if ($this->hasVerifiedEmail()) {
            return null;
        }

        $graceDays = (int) config('auth.pnedu_unverified_grace_days', 90);

        return $this->created_at?->copy()->addDays($graceDays);
    }

    public function sendEmailVerificationNotification(?string $verificationUrl = null): void
    {
        $this->notify(new \App\Notifications\PneduFrontendVerifyEmail($verificationUrl));
    }

    /**
     * Czy przy ponownej wysyłce dostępu wysłać mail „ustaw hasło” (true) vs informacyjny (false).
     *
     * Decyzja na podstawie bieżącego stanu konta w pnedu, nie snapshotu z momentu provisionu.
     */
    public function needsProvisionPasswordSetupEmail(?FormOrder $order = null): bool
    {
        if ($this->last_login_at !== null || (int) ($this->login_count ?? 0) > 0) {
            return false;
        }

        if ($this->passwordWasChangedAfterCreation()) {
            return false;
        }

        if ($order?->pnedu_provisioned_at !== null && $this->created_at !== null) {
            if ($this->created_at->lt($order->pnedu_provisioned_at->copy()->subMinute())) {
                return false;
            }
        }

        if ($order?->pnedu_user_existed_before === true) {
            return false;
        }

        return true;
    }

    public function passwordWasChangedAfterCreation(): bool
    {
        if ($this->created_at === null || $this->updated_at === null) {
            return false;
        }

        return $this->updated_at->gt($this->created_at->copy()->addSeconds(15));
    }
}
