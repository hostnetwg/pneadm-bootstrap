<?php

namespace App\Services;

use App\Models\OnlineCourseEnrollment;
use App\Models\PneduUser;

class OnlineCourseEnrollmentPneduAccountLookup
{
    /**
     * @param  iterable<mixed>  $emails
     * @return array<string, PneduUser>
     */
    public function byEmails(iterable $emails): array
    {
        $normalized = $this->normalizedEmails($emails);
        if ($normalized === []) {
            return [];
        }

        return PneduUser::query()
            ->whereIn('email', $normalized)
            ->get()
            ->mapWithKeys(function (PneduUser $user) {
                $email = OnlineCourseEnrollment::normalizeEmail((string) $user->email);

                return $email ? [$email => $user] : [];
            })
            ->all();
    }

    /**
     * @param  iterable<mixed>  $emails
     * @return list<string>
     */
    public function emailsHavingAccounts(iterable $emails): array
    {
        $normalized = $this->normalizedEmails($emails);
        if ($normalized === []) {
            return [];
        }

        return PneduUser::query()
            ->whereIn('email', $normalized)
            ->pluck('email')
            ->map(fn ($email) => OnlineCourseEnrollment::normalizeEmail((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<mixed>  $emails
     * @return list<string>
     */
    private function normalizedEmails(iterable $emails): array
    {
        return collect($emails)
            ->map(fn ($email) => OnlineCourseEnrollment::normalizeEmail(is_string($email) ? $email : (string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
