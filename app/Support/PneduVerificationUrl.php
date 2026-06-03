<?php

namespace App\Support;

use App\Models\PneduUser;
use Illuminate\Support\Carbon;

/**
 * Podpisany link weryfikacji e-mail na froncie pnedu.pl (musi używać APP_KEY z pnedu).
 */
class PneduVerificationUrl
{
    public static function forUser(PneduUser $user, ?int $expireMinutes = null): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');
        $id = $user->getKey();
        $hash = sha1($user->getEmailForVerification());
        $expires = Carbon::now()
            ->addMinutes($expireMinutes ?? (int) config('auth.pnedu_verification_expire', 60))
            ->getTimestamp();

        $query = http_build_query(['expires' => $expires]);
        $urlWithoutSignature = "{$base}/verify-email/{$id}/{$hash}?{$query}";

        $key = (string) config('services.pnedu_app_key', config('app.key'));
        $signature = hash_hmac('sha256', $urlWithoutSignature, $key);

        return $urlWithoutSignature.'&signature='.$signature;
    }
}
