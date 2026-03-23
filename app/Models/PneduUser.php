<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Użytkownik zarejestrowany na pnedu.pl (baza pnedu, tabela users).
 */
class PneduUser extends Model
{
    protected $connection = 'pnedu';

    protected $table = 'users';

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
}
