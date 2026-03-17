<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mapowanie znormalizowany e-mail → unikalny token do linków pobierania zaświadczeń.
 * Jeden token na adres e-mail; używany na pnedu.pl w URL: /certificates/{token}.
 */
class ParticipantDownloadToken extends Model
{
    protected $fillable = [
        'email_normalized',
        'token',
    ];

    /**
     * Normalizacja e-maila (trim + lowercase) do porównań i zapisu.
     */
    public static function normalizeEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }
        return strtolower(trim($email));
    }

    /**
     * Pobierz lub utwórz token dla danego e-maila. Zwraca token (64 znaki).
     * Dla pustego e-maila zwraca pusty string (nie tworzy rekordu).
     */
    public static function getOrCreateTokenForEmail(?string $email): string
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return '';
        }

        $record = self::firstOrCreate(
            ['email_normalized' => $normalized],
            ['token' => Str::random(64)]
        );

        return $record->token;
    }

    /**
     * Pobierz token dla e-maila (bez tworzenia). Zwraca null gdy brak.
     */
    public static function getTokenForEmail(?string $email): ?string
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }
        $record = self::where('email_normalized', $normalized)->first();
        return $record?->token;
    }

    /**
     * Znajdź rekord po tokenie. Zwraca null, jeśli nie istnieje.
     */
    public static function findByToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return self::where('token', $token)->first();
    }
}
