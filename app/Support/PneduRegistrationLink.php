<?php

namespace App\Support;

/**
 * Link do /register na pnedu.pl z zablokowanym adresem z listy uczestników.
 * Podpis musi zgadzać się z pnedu App\Support\RegistrationEmailLock.
 */
class PneduRegistrationLink
{
    public static function url(string $email, ?string $firstName = null, ?string $lastName = null): string
    {
        $email = trim($email);
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');

        return $base.'/register?'.http_build_query([
            'email' => $email,
            'first_name' => trim((string) $firstName),
            'last_name' => trim((string) $lastName),
            'email_lock' => self::lock($email),
        ]);
    }

    public static function lock(string $email): string
    {
        $normalized = strtolower(trim($email));

        return hash_hmac('sha256', $normalized, (string) config('services.pneadm.api_token'));
    }
}
