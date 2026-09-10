<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollmentEmailLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class OnlineCourseEnrollmentListQuery
{
    public const SORTS = [
        'email',
        'name',
        'phone',
        'expires',
        'source',
        'certificate',
        'pnedu',
    ];

    public function __construct(
        private readonly OnlineCourseEnrollmentPneduAccountLookup $pneduAccountLookup,
    ) {}

    /**
     * @param  array{
     *     q: string,
     *     access: string,
     *     pnedu: string,
     *     source: string,
     *     certificate: string,
     *     mail: string,
     *     sort: string,
     *     dir: string
     * }  $filters
     */
    public function paginate(OnlineCourse $course, array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->filteredQuery($course, $filters);
        $this->applySort($query, $filters);

        return $query
            ->with('certificate')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function filteredQuery(OnlineCourse $course, array $filters): Builder
    {
        $query = $course->enrollments()->getQuery();

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("concat(coalesce(first_name, ''), ' ', coalesce(last_name, '')) like ?", [$like])
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('legacy_publigo_user_id', 'like', $like)
                    ->orWhere('access_source', 'like', $like);
            });
        }

        $access = $filters['access'] ?? 'all';
        $nowUtc = Carbon::now('UTC');
        if ($access === 'unlimited') {
            $query->whereNull('access_expires_at');
        } elseif ($access === 'expired') {
            $query->whereNotNull('access_expires_at')
                ->where('access_expires_at', '<', $nowUtc);
        } elseif ($access === 'active') {
            $query->where(function (Builder $inner) use ($nowUtc) {
                $inner->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>=', $nowUtc);
            });
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '' && $source !== 'all') {
            $query->where('access_source', $source);
        }

        $certificate = $filters['certificate'] ?? 'all';
        if ($certificate === 'yes') {
            $query->whereHas('certificate');
        } elseif ($certificate === 'no') {
            $query->whereDoesntHave('certificate');
        }

        $mail = $filters['mail'] ?? 'all';
        if ($mail === 'sent' || $mail === 'unsent') {
            $existsSent = function ($q) use ($course) {
                $q->selectRaw('1')
                    ->from('online_course_enrollment_email_logs')
                    ->whereColumn('online_course_enrollment_email_logs.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->where('online_course_enrollment_email_logs.online_course_id', $course->id)
                    ->where('online_course_enrollment_email_logs.type', OnlineCourseEnrollmentEmailLog::TYPE_PLATFORM_MIGRATION)
                    ->where('online_course_enrollment_email_logs.status', OnlineCourseEnrollmentEmailLog::STATUS_SENT);
            };
            if ($mail === 'sent') {
                $query->whereExists($existsSent);
            } else {
                $query->whereNotExists($existsSent);
            }
        }

        $pnedu = $filters['pnedu'] ?? 'all';
        if ($pnedu === 'yes' || $pnedu === 'no') {
            $candidateEmails = (clone $query)->pluck('email');
            $withAccount = $this->pneduAccountLookup->emailsHavingAccounts($candidateEmails);
            if ($pnedu === 'yes') {
                if ($withAccount === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('email', $withAccount);
                }
            } elseif ($withAccount === []) {
                // wszyscy na liście są bez konta — bez dodatkowego where
            } else {
                $query->whereNotIn('email', $withAccount);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'email';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'email';
        }

        match ($sort) {
            'name' => $query->orderBy('last_name', $dir)->orderBy('first_name', $dir),
            'phone' => $query->orderBy('phone', $dir),
            'expires' => $query
                ->orderByRaw('access_expires_at is null asc')
                ->orderBy('access_expires_at', $dir),
            'source' => $query->orderBy('access_source', $dir),
            'certificate' => $query->orderBy(
                Certificate::query()
                    ->select('certificate_number')
                    ->whereColumn('certificates.online_course_enrollment_id', 'online_course_enrollments.id')
                    ->limit(1),
                $dir
            ),
            'pnedu' => $this->applyPneduSort($query, $dir),
            default => $query->orderBy('email', $dir),
        };

        $query->orderBy('id', 'asc');
    }

    private function applyPneduSort(Builder $query, string $dir): void
    {
        $emails = (clone $query)->pluck('email');
        $withAccount = $this->pneduAccountLookup->emailsHavingAccounts($emails);
        if ($withAccount === []) {
            $query->orderBy('email', $dir);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($withAccount), '?'));
        $hasFirst = $dir === 'desc' ? 0 : 1;
        $query->orderByRaw(
            'case when email in ('.$placeholders.') then '.$hasFirst.' else '.($hasFirst === 0 ? 1 : 0).' end asc',
            $withAccount
        );
    }
}
