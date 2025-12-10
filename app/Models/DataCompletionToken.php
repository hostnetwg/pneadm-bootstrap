<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DataCompletionToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generuje nowy token dla emaila
     */
    public static function generateForEmail(string $email, int $expiresInDays = 30): self
    {
        // Sprawdź czy istnieje aktywny token dla tego emaila
        $existing = self::where('email', $email)
            ->whereNull('used_at')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        // Generuj nowy token
        $token = self::create([
            'email' => strtolower(trim($email)),
            'token' => Str::random(64),
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return $token;
    }

    /**
     * Sprawdza czy token jest ważny
     */
    public function isValid(): bool
    {
        if ($this->used_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Oznacza token jako użyty
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}

