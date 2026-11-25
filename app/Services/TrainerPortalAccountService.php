<?php

namespace App\Services;

use App\Mail\TrainerPortalAccessMail;
use App\Models\Instructor;
use App\Models\PneduUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class TrainerPortalAccountService
{
    /**
     * @return array{created: bool, relinked: bool}
     */
    public function link(Instructor $instructor): array
    {
        $email = mb_strtolower(trim((string) $instructor->email));
        $previousUserId = $instructor->pnedu_user_id !== null ? (int) $instructor->pnedu_user_id : null;

        $user = PneduUser::withTrashed()->where('email', $email)->first();
        $created = false;

        if ($user === null) {
            $user = new PneduUser;
            $user->first_name = $instructor->first_name;
            $user->last_name = $instructor->last_name;
            $user->email = $email;
            $user->password = Str::password(40);
            $user->email_verified_at = now();
            $user->save();
            $created = true;
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $instructor->pnedu_user_id = $user->id;
        $instructor->save();

        return [
            'created' => $created,
            'relinked' => $previousUserId !== (int) $user->id,
        ];
    }

    public function sendAccessMail(Instructor $instructor, bool $createdAccount): void
    {
        $user = PneduUser::query()->find($instructor->pnedu_user_id);
        if ($user === null || trim((string) $user->email) === '') {
            return;
        }

        $base = rtrim((string) config('marketing.pnedu_public_url'), '/');
        if ($createdAccount) {
            $token = Password::broker('pnedu_users')->createToken($user);
            $actionUrl = $base.'/ustaw-haslo/'.$token.'?'.http_build_query([
                'email' => $user->email,
                'redirect' => '/trener',
            ]);
        } else {
            $actionUrl = $base.'/trener';
        }

        try {
            Mail::to($user->email)->send(new TrainerPortalAccessMail($user, $actionUrl, $createdAccount));
        } catch (Throwable $exception) {
            Log::warning('Trainer portal access mail failed', [
                'instructor_id' => $instructor->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
