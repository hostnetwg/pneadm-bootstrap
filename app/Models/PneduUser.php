<?php

namespace App\Models;

use App\Notifications\PneduFrontendResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Użytkownik zarejestrowany na pnedu.pl (baza pnedu, tabela users).
 */
class PneduUser extends Model implements CanResetPasswordContract, HasLocalePreference
{
    use CanResetPassword;
    use Notifiable;

    protected $connection = 'pnedu';

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'birth_place',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
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
}
