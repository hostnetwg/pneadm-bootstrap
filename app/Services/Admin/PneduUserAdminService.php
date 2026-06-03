<?php

namespace App\Services\Admin;

use App\Models\FormOrder;
use App\Models\Participant;
use App\Models\PneduUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PneduUserAdminService
{
    public const DELIVERABILITY_ALL = 'all';

    public const DELIVERABILITY_UNDELIVERABLE = 'undeliverable';

    public const DELIVERABILITY_DELIVERABLE = 'deliverable';

    /** @var list<string>|null */
    private ?array $paidEnrollmentEmailsCache = null;

    public function deliverabilityColumnsAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        try {
            $available = Schema::connection('pnedu')->hasColumn('users', 'email_undeliverable_at')
                && Schema::connection('pnedu')->hasColumn('users', 'email_undeliverable_reason');
        } catch (\Throwable) {
            $available = false;
        }

        return $available;
    }

    /**
     * @return array{
     *     undeliverable: int,
     *     undeliverable_unverified: int,
     *     undeliverable_paid: int,
     *     undeliverable_recent_7d: int,
     *     unverified: int,
     *     unverified_deliverable: int,
     *     unverified_paid: int
     * }
     */
    public function listStats(bool $activeOnly = true): array
    {
        $base = PneduUser::query();
        if ($activeOnly) {
            $base->whereNull('deleted_at');
        }

        $unverified = (clone $base)->whereNull('email_verified_at');

        if (! $this->deliverabilityColumnsAvailable()) {
            return [
                'undeliverable' => 0,
                'undeliverable_unverified' => 0,
                'undeliverable_paid' => 0,
                'undeliverable_recent_7d' => 0,
                'unverified' => (clone $unverified)->count(),
                'unverified_deliverable' => (clone $unverified)->count(),
                'unverified_paid' => $this->countWithPaidEnrollment(clone $unverified),
            ];
        }

        $undeliverable = (clone $base)->whereNotNull('email_undeliverable_at');

        return [
            'undeliverable' => (clone $undeliverable)->count(),
            'undeliverable_unverified' => (clone $undeliverable)->whereNull('email_verified_at')->count(),
            'undeliverable_paid' => $this->countWithPaidEnrollment(clone $undeliverable),
            'undeliverable_recent_7d' => (clone $undeliverable)
                ->where('email_undeliverable_at', '>=', now()->subDays(7))
                ->count(),
            'unverified' => (clone $unverified)->count(),
            'unverified_deliverable' => (clone $unverified)->whereNull('email_undeliverable_at')->count(),
            'unverified_paid' => $this->countWithPaidEnrollment(clone $unverified),
        ];
    }

    /**
     * @param  Builder<PneduUser>  $query
     */
    public function applyListFilters(Builder $query, array $filters): void
    {
        if ($filters['email'] !== null && $filters['email'] !== '') {
            $term = '%'.addcslashes($filters['email'], '%_\\').'%';
            $query->where('email', 'like', $term);
        }

        if ($filters['name'] !== null && $filters['name'] !== '') {
            $term = '%'.addcslashes($filters['name'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereRaw(
                        "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                        [$term]
                    );
            });
        }

        if (! empty($filters['registered_from'])) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($filters['registered_from'])->startOfDay());
        }

        if (! empty($filters['registered_to'])) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($filters['registered_to'])->endOfDay());
        }

        if ($filters['verified'] === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($filters['verified'] === 'no') {
            $query->whereNull('email_verified_at');
        }

        if ($this->deliverabilityColumnsAvailable()) {
            if ($filters['deliverability'] === self::DELIVERABILITY_UNDELIVERABLE) {
                $query->whereNotNull('email_undeliverable_at');
            } elseif ($filters['deliverability'] === self::DELIVERABILITY_DELIVERABLE) {
                $query->whereNull('email_undeliverable_at');
            }

            if ($filters['undeliverable_reason'] !== null && $filters['undeliverable_reason'] !== '') {
                $query->where('email_undeliverable_reason', $filters['undeliverable_reason']);
            }
        }

        if ($filters['has_paid'] === 'yes') {
            $this->applyHasPaidCourseFilter($query);
        } elseif ($filters['has_paid'] === 'no') {
            $this->applyWithoutPaidCourseFilter($query);
        }
    }

    /**
     * @param  Builder<PneduUser>  $query
     * @return Builder<PneduUser>
     */
    public function applyHasPaidCourseFilter(Builder $query): Builder
    {
        $paidEmails = $this->paidEnrollmentNormalizedEmails();

        if ($paidEmails === []) {
            return $query->whereRaw('0 = 1');
        }

        return $this->whereEmailInNormalizedList($query, $paidEmails);
    }

    /**
     * @param  Builder<PneduUser>  $query
     */
    private function applyWithoutPaidCourseFilter(Builder $query): void
    {
        $paidEmails = $this->paidEnrollmentNormalizedEmails();

        if ($paidEmails === []) {
            return;
        }

        $this->whereEmailNotInNormalizedList($query, $paidEmails);
    }

    /**
     * @param  Builder<PneduUser>  $query
     */
    private function countWithPaidEnrollment(Builder $query): int
    {
        $paidEmails = $this->paidEnrollmentNormalizedEmails();

        if ($paidEmails === []) {
            return 0;
        }

        return $this->whereEmailInNormalizedList(clone $query, $paidEmails)->count();
    }

    /**
     * @param  Builder<PneduUser>  $query
     * @param  list<string>  $normalizedEmails
     * @return Builder<PneduUser>
     */
    private function whereEmailInNormalizedList(Builder $query, array $normalizedEmails): Builder
    {
        return $query->where(function (Builder $outer) use ($normalizedEmails) {
            foreach (array_chunk($normalizedEmails, 400) as $chunk) {
                $outer->orWhere(function (Builder $inner) use ($chunk) {
                    foreach ($chunk as $email) {
                        $inner->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<PneduUser>  $query
     * @param  list<string>  $normalizedEmails
     */
    private function whereEmailNotInNormalizedList(Builder $query, array $normalizedEmails): void
    {
        $query->where(function (Builder $outer) use ($normalizedEmails) {
            foreach (array_chunk($normalizedEmails, 400) as $chunk) {
                $outer->where(function (Builder $inner) use ($chunk) {
                    foreach ($chunk as $email) {
                        $inner->whereRaw('LOWER(TRIM(email)) != ?', [$email]);
                    }
                });
            }
        });
    }

    /**
     * E-maile z zapisem na płatne szkolenie (osobne zapytanie do bazy pneadm — bez cross-DB JOIN).
     *
     * @return list<string>
     */
    public function paidEnrollmentNormalizedEmails(): array
    {
        if ($this->paidEnrollmentEmailsCache !== null) {
            return $this->paidEnrollmentEmailsCache;
        }

        $this->paidEnrollmentEmailsCache = Participant::query()
            ->whereHas('course', fn ($q) => $q->where('is_paid', 1))
            ->whereNull('deleted_at')
            ->pluck('email')
            ->map(fn ($email) => Participant::normalizeEmail($email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->paidEnrollmentEmailsCache;
    }

    public function userHasPaidCourseEnrollment(PneduUser $user): bool
    {
        $norm = Participant::normalizeEmail($user->email);
        if ($norm === null) {
            return false;
        }

        return in_array($norm, $this->paidEnrollmentNormalizedEmails(), true);
    }

    /**
     * @return Collection<int, FormOrder>
     */
    public function relatedFormOrdersForEmail(?string $email, int $limit = 8): Collection
    {
        $norm = Participant::normalizeEmail($email);
        if ($norm === null) {
            return collect();
        }

        return FormOrder::query()
            ->with(['participants'])
            ->where(function (Builder $q) use ($norm) {
                $q->whereRaw('LOWER(TRIM(orderer_email)) = ?', [$norm])
                    ->orWhereHas('participants', function (Builder $p) use ($norm) {
                        $p->whereRaw('LOWER(TRIM(participant_email)) = ?', [$norm]);
                    });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<string>
     */
    public function paidEnrollmentEmailSetForEmails(Collection $emails): array
    {
        $normalized = $emails
            ->map(fn ($email) => Participant::normalizeEmail($email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return [];
        }

        $paidSet = array_flip($this->paidEnrollmentNormalizedEmails());

        return array_values(array_filter($normalized, fn (string $email) => isset($paidSet[$email])));
    }

    public function undeliverableReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'permanent_bounce' => 'Trwały bounce (hard)',
            'complaint' => 'Skarga (complaint)',
            default => $reason ?: '—',
        };
    }
}
