<?php

namespace App\Services\Admin;

use App\Models\FormOrder;
use App\Models\Participant;
use App\Models\PneduUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PneduUserAdminService
{
    public const DELIVERABILITY_ALL = 'all';

    public const DELIVERABILITY_UNDELIVERABLE = 'undeliverable';

    public const DELIVERABILITY_DELIVERABLE = 'deliverable';

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

        $undeliverable = (clone $base)->whereNotNull('email_undeliverable_at');
        $unverified = (clone $base)->whereNull('email_verified_at');

        return [
            'undeliverable' => (clone $undeliverable)->count(),
            'undeliverable_unverified' => (clone $undeliverable)->whereNull('email_verified_at')->count(),
            'undeliverable_paid' => $this->applyHasPaidCourseFilter(clone $undeliverable)->count(),
            'undeliverable_recent_7d' => (clone $undeliverable)
                ->where('email_undeliverable_at', '>=', now()->subDays(7))
                ->count(),
            'unverified' => (clone $unverified)->count(),
            'unverified_deliverable' => (clone $unverified)->whereNull('email_undeliverable_at')->count(),
            'unverified_paid' => $this->applyHasPaidCourseFilter(clone $unverified)->count(),
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

        if ($filters['deliverability'] === self::DELIVERABILITY_UNDELIVERABLE) {
            $query->whereNotNull('email_undeliverable_at');
        } elseif ($filters['deliverability'] === self::DELIVERABILITY_DELIVERABLE) {
            $query->whereNull('email_undeliverable_at');
        }

        if ($filters['undeliverable_reason'] !== null && $filters['undeliverable_reason'] !== '') {
            $query->where('email_undeliverable_reason', $filters['undeliverable_reason']);
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
        $pneadmDb = DB::connection('mysql')->getDatabaseName();

        return $query->whereExists(function ($sub) use ($pneadmDb) {
            $sub->selectRaw('1')
                ->from("{$pneadmDb}.participants as p")
                ->join("{$pneadmDb}.courses as c", 'c.id', '=', 'p.course_id')
                ->whereRaw('LOWER(TRIM(p.email)) = LOWER(TRIM(users.email))')
                ->where('c.is_paid', 1)
                ->whereNull('p.deleted_at');
        });
    }

    /**
     * @param  Builder<PneduUser>  $query
     */
    private function applyWithoutPaidCourseFilter(Builder $query): void
    {
        $pneadmDb = DB::connection('mysql')->getDatabaseName();

        $query->whereNotExists(function ($sub) use ($pneadmDb) {
            $sub->selectRaw('1')
                ->from("{$pneadmDb}.participants as p")
                ->join("{$pneadmDb}.courses as c", 'c.id', '=', 'p.course_id')
                ->whereRaw('LOWER(TRIM(p.email)) = LOWER(TRIM(users.email))')
                ->where('c.is_paid', 1)
                ->whereNull('p.deleted_at');
        });
    }

    public function userHasPaidCourseEnrollment(PneduUser $user): bool
    {
        $norm = Participant::normalizeEmail($user->email);
        if ($norm === null) {
            return false;
        }

        return Participant::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->whereHas('course', fn ($q) => $q->where('is_paid', 1))
            ->exists();
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
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, $normalized->count(), '?'));

        $matches = Participant::query()
            ->whereRaw('LOWER(TRIM(email)) IN ('.$placeholders.')', $normalized->all())
            ->whereHas('course', fn ($q) => $q->where('is_paid', 1))
            ->pluck('email')
            ->map(fn ($email) => Participant::normalizeEmail($email))
            ->filter()
            ->unique()
            ->all();

        return array_values($matches);
    }
}
