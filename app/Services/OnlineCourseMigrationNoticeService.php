<?php

namespace App\Services;

use App\Jobs\SendOnlineCourseMigrationNoticeJob;
use App\Mail\OnlineCourseMigrationNoticeMail;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Models\OnlineCourseMigrationNotice;
use App\Models\PneduUser;
use App\Services\Mail\SystemMailDiagnostics;
use App\Support\PneduRegistrationLink;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class OnlineCourseMigrationNoticeService
{
    public const SOURCE = 'publigo_migration';

    public function __construct(
        private readonly OnlineCourseEnrollmentPneduAccountLookup $accounts,
    ) {}

    /**
     * @return array{subject: string, body_html: string, has_pnedu_account: bool}
     */
    public function samplePreview(bool $hasPneduAccount): array
    {
        $email = 'anna@example.com';
        $mail = $this->mailable(
            firstName: 'Anna',
            lastName: 'Przykładowa',
            email: $email,
            courseTitles: ['Przykładowy kurs pierwszy', 'Przykładowy kurs drugi'],
            hasPneduAccount: $hasPneduAccount,
        );

        return $this->rendered($mail, $hasPneduAccount);
    }

    /**
     * @return array{success: bool, error: ?string, http_code: int, to?: string, subject?: string, body_html?: string, has_pnedu_account?: bool, course_count?: int, sent_count?: int}
     */
    public function previewForEmail(string $email): array
    {
        $person = $this->person($email);
        if ($person === null) {
            return [
                'success' => false,
                'error' => 'Ta osoba nie ma ważnego dostępu przeniesionego ze starej platformy.',
                'http_code' => 422,
            ];
        }

        $hasAccount = $this->hasAccount($person['email']);
        $mail = $this->mailable(
            firstName: $person['first_name'],
            lastName: $person['last_name'],
            email: $person['email'],
            courseTitles: $person['course_titles'],
            hasPneduAccount: $hasAccount,
        );
        $rendered = $this->rendered($mail, $hasAccount);

        return [
            'success' => true,
            'error' => null,
            'http_code' => 200,
            'to' => $person['email'],
            'subject' => $rendered['subject'],
            'body_html' => $rendered['body_html'],
            'has_pnedu_account' => $hasAccount,
            'course_count' => count($person['course_titles']),
            'sent_count' => $this->sentCount($person['email']),
        ];
    }

    /**
     * @return array{success: bool, error: ?string, email: ?string}
     */
    public function sendToEmail(string $email, ?int $createdBy = null): array
    {
        $person = $this->person($email);
        if ($person === null) {
            return [
                'success' => false,
                'error' => 'Ta osoba nie ma ważnego dostępu przeniesionego ze starej platformy.',
                'email' => null,
            ];
        }

        $notice = $this->queueNotice($person['email'], $createdBy);
        $job = new SendOnlineCourseMigrationNoticeJob($notice->id);
        dispatch_sync($job);
        $notice->refresh();

        if ($notice->status !== OnlineCourseMigrationNotice::STATUS_SENT) {
            return [
                'success' => false,
                'error' => $notice->error_message ?: 'Nie udało się wysłać e-maila.',
                'email' => $person['email'],
            ];
        }

        return ['success' => true, 'error' => null, 'email' => $person['email']];
    }

    /**
     * @return array{success: bool, error: ?string, queued: int}
     */
    public function sendBulk(?int $courseId, string $search, string $account = 'all', ?int $createdBy = null): array
    {
        $emails = $this->unsentEmails($courseId, $search, $account);
        if ($emails->isEmpty()) {
            return [
                'success' => false,
                'error' => 'Brak osób, które jeszcze nie dostały tego listu.',
                'queued' => 0,
            ];
        }

        $jobs = [];
        $ids = [];
        foreach ($emails as $email) {
            $notice = $this->queueNotice($email, $createdBy);
            $ids[] = $notice->id;
            $jobs[] = new SendOnlineCourseMigrationNoticeJob($notice->id);
        }

        $batch = Bus::batch($jobs)
            ->name($this->batchName())
            ->dispatch();

        OnlineCourseMigrationNotice::query()
            ->whereIn('id', $ids)
            ->update(['batch_id' => $batch->id]);

        return ['success' => true, 'error' => null, 'queued' => count($jobs)];
    }

    public function deliver(int $noticeId): void
    {
        $notice = OnlineCourseMigrationNotice::query()->find($noticeId);
        if (! $notice || $notice->status === OnlineCourseMigrationNotice::STATUS_SENT) {
            return;
        }

        $person = $this->person($notice->email);
        if ($person === null) {
            $this->markFailed($notice, 'Brak ważnego dostępu przeniesionego ze starej platformy.');

            return;
        }

        $hasAccount = $this->hasAccount($person['email']);
        $mail = $this->mailable(
            firstName: $person['first_name'],
            lastName: $person['last_name'],
            email: $person['email'],
            courseTitles: $person['course_titles'],
            hasPneduAccount: $hasAccount,
        );

        try {
            $delivery = app(SystemMailDiagnostics::class)->send($person['email'], $mail);
            $notice->update([
                'status' => OnlineCourseMigrationNotice::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'meta' => [
                    'has_pnedu_account' => $hasAccount,
                    'course_ids' => $person['course_ids'],
                    'course_titles' => $person['course_titles'],
                    'delivery' => $delivery,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($notice, $e->getMessage());

            throw $e;
        }
    }

    public function markCancelled(int $noticeId): void
    {
        $notice = OnlineCourseMigrationNotice::query()->find($noticeId);
        if (! $notice || $notice->status !== OnlineCourseMigrationNotice::STATUS_QUEUED) {
            return;
        }

        $this->markFailed($notice, 'Wysyłka przerwana.');
    }

    public function people(?int $courseId, string $search, string $account, string $direction): LengthAwarePaginator
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $this->peopleQuery($courseId, $search, $account)
            ->orderBy('course_count', $direction)
            ->orderBy('online_course_enrollments.email')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array{sent_count: int, last_sent_at: ?Carbon}>
     */
    public function sendStatsFor(Collection $rows): array
    {
        $emails = $rows->pluck('email')->filter()->unique()->values();
        if ($emails->isEmpty()) {
            return [];
        }

        $stats = [];
        $logs = OnlineCourseMigrationNotice::query()
            ->whereIn('email', $emails->all())
            ->where('status', OnlineCourseMigrationNotice::STATUS_SENT)
            ->selectRaw('email, COUNT(*) as sent_count, MAX(sent_at) as last_sent_at')
            ->groupBy('email')
            ->get();

        foreach ($logs as $log) {
            $stats[(string) $log->email] = [
                'sent_count' => (int) $log->sent_count,
                'last_sent_at' => $log->last_sent_at ? Carbon::parse($log->last_sent_at) : null,
            ];
        }

        return $stats;
    }

    /**
     * @param  iterable<mixed>  $emails
     * @return array<string, PneduUser>|null
     */
    public function accountsFor(iterable $emails): ?array
    {
        try {
            return $this->accounts->byEmails($emails);
        } catch (\Throwable) {
            return null;
        }
    }

    public function unsentCount(?int $courseId, string $search, string $account = 'all'): int
    {
        return $this->unsentEmails($courseId, $search, $account)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, OnlineCourse>
     */
    public function filterCourses()
    {
        return OnlineCourse::query()
            ->whereIn('id', function ($query) {
                $query->select('online_course_id')
                    ->from('online_course_enrollments');
                $this->eligible($query, 'online_course_enrollments');
            })
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    public function batchName(): string
    {
        return 'online-course-migration-notice';
    }

    /**
     * @return array{active: bool, state: string, batch_id: mixed, total: int, processed: int, pending: int, failed: int}
     */
    public function batchStatus(): array
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $this->batchName())
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return [
                'active' => false,
                'state' => 'none',
                'batch_id' => null,
                'total' => 0,
                'processed' => 0,
                'pending' => 0,
                'failed' => 0,
            ];
        }

        $total = (int) ($row->total_jobs ?? 0);
        $pending = (int) ($row->pending_jobs ?? 0);
        $failed = (int) ($row->failed_jobs ?? 0);
        $processed = max(0, $total - $pending);
        $state = 'active';
        $active = true;
        if (! empty($row->cancelled_at)) {
            $state = 'cancelled';
            $active = false;
        } elseif (! empty($row->finished_at)) {
            $state = 'finished';
            $active = false;
        }

        return [
            'active' => $active,
            'state' => $state,
            'batch_id' => $row->id,
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function cancelBatch(): array
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $this->batchName())
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->first();

        if (! $row) {
            return ['success' => false, 'message' => 'Brak aktywnej wysyłki.'];
        }

        $batch = Bus::findBatch($row->id);
        if ($batch && ! $batch->cancelled()) {
            $batch->cancel();
        }

        OnlineCourseMigrationNotice::query()
            ->where('batch_id', $row->id)
            ->where('status', OnlineCourseMigrationNotice::STATUS_QUEUED)
            ->update([
                'status' => OnlineCourseMigrationNotice::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => 'Wysyłka przerwana.',
            ]);

        return ['success' => true];
    }

    /**
     * @return array{email: string, first_name: string, last_name: string, course_titles: list<string>, course_ids: list<int>}|null
     */
    public function person(string $email): ?array
    {
        $email = OnlineCourseEnrollment::normalizeEmail($email);
        if ($email === null) {
            return null;
        }

        $rows = $this->eligibleQuery()
            ->where('email', $email)
            ->with('onlineCourse:id,title')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $latest = $rows->first();
        $courses = $rows
            ->unique('online_course_id')
            ->sortBy(fn (OnlineCourseEnrollment $row) => mb_strtolower($this->plainTitle($row->onlineCourse?->title)))
            ->values();

        return [
            'email' => $email,
            'first_name' => trim((string) $latest->first_name),
            'last_name' => trim((string) $latest->last_name),
            'course_titles' => $courses->map(fn (OnlineCourseEnrollment $row) => $this->plainTitle($row->onlineCourse?->title))->all(),
            'course_ids' => $courses->map(fn (OnlineCourseEnrollment $row) => (int) $row->online_course_id)->all(),
        ];
    }

    private function peopleQuery(?int $courseId, string $search, string $account = 'all'): Builder
    {
        $latest = $this->eligibleQuery()
            ->selectRaw('MAX(id) as id')
            ->groupBy('email');

        $query = OnlineCourseEnrollment::query()
            ->joinSub($latest, 'migration_notice_latest', 'migration_notice_latest.id', '=', 'online_course_enrollments.id')
            ->select([
                'online_course_enrollments.email',
                'online_course_enrollments.first_name',
                'online_course_enrollments.last_name',
            ])
            ->selectSub($this->courseCountSubquery(), 'course_count');

        if ($courseId) {
            $query->whereExists(function ($sub) use ($courseId) {
                $sub->selectRaw('1')
                    ->from('online_course_enrollments as course_match')
                    ->whereColumn('course_match.email', 'online_course_enrollments.email')
                    ->where('course_match.online_course_id', $courseId);
                $this->eligible($sub, 'course_match');
            });
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%'.$this->escapeLike($search).'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('online_course_enrollments.email', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('online_course_enrollments as name_match')
                            ->whereColumn('name_match.email', 'online_course_enrollments.email')
                            ->where(function ($name) use ($like) {
                                $name->where('name_match.first_name', 'like', $like)
                                    ->orWhere('name_match.last_name', 'like', $like);
                            });
                        $this->eligible($sub, 'name_match');
                    });
            });
        }

        $this->applyAccountFilter($query, $account);

        return $query;
    }

    private function applyAccountFilter(Builder $query, string $account): void
    {
        if ($account !== 'yes' && $account !== 'no') {
            return;
        }

        try {
            $emails = (clone $query)->pluck('online_course_enrollments.email');
            $withAccount = $this->accounts->emailsHavingAccounts($emails);
        } catch (\Throwable) {
            return;
        }

        if ($account === 'yes') {
            if ($withAccount === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('online_course_enrollments.email', $withAccount);
            }

            return;
        }

        if ($withAccount !== []) {
            $query->whereNotIn('online_course_enrollments.email', $withAccount);
        }
    }

    private function courseCountSubquery(): Builder
    {
        $query = OnlineCourseEnrollment::query()
            ->from('online_course_enrollments as course_count_rows')
            ->selectRaw('COUNT(DISTINCT course_count_rows.online_course_id)')
            ->whereColumn('course_count_rows.email', 'online_course_enrollments.email');
        $this->eligible($query, 'course_count_rows');

        return $query;
    }

    /**
     * @return Collection<int, string>
     */
    private function unsentEmails(?int $courseId, string $search, string $account = 'all'): Collection
    {
        return $this->peopleQuery($courseId, $search, $account)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('online_course_migration_notices')
                    ->whereColumn('online_course_migration_notices.email', 'online_course_enrollments.email')
                    ->whereIn('online_course_migration_notices.status', [
                        OnlineCourseMigrationNotice::STATUS_SENT,
                        OnlineCourseMigrationNotice::STATUS_QUEUED,
                    ]);
            })
            ->pluck('online_course_enrollments.email');
    }

    private function queueNotice(string $email, ?int $createdBy): OnlineCourseMigrationNotice
    {
        return OnlineCourseMigrationNotice::query()->create([
            'email' => $email,
            'status' => OnlineCourseMigrationNotice::STATUS_QUEUED,
            'created_by' => $createdBy,
            'queued_at' => now(),
        ]);
    }

    private function sentCount(string $email): int
    {
        return OnlineCourseMigrationNotice::query()
            ->where('email', $email)
            ->where('status', OnlineCourseMigrationNotice::STATUS_SENT)
            ->count();
    }

    private function hasAccount(string $email): bool
    {
        $accounts = $this->accountsFor([$email]);

        return is_array($accounts) && isset($accounts[$email]);
    }

    /**
     * @param  list<string>  $courseTitles
     */
    private function mailable(
        string $firstName,
        string $lastName,
        string $email,
        array $courseTitles,
        bool $hasPneduAccount,
    ): OnlineCourseMigrationNoticeMail {
        $base = rtrim((string) config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');

        return new OnlineCourseMigrationNoticeMail(
            participantFirstName: trim($firstName),
            participantEmail: $email,
            courseTitles: $courseTitles,
            hasPneduAccount: $hasPneduAccount,
            loginUrl: $base.'/login?'.http_build_query(['email' => $email]),
            registerUrl: PneduRegistrationLink::url($email, $firstName, $lastName),
            forgotPasswordUrl: $base.'/forgot-password',
            coursesUrl: $base.'/dashboard/kursy-online',
        );
    }

    /**
     * @return array{subject: string, body_html: string, has_pnedu_account: bool}
     */
    private function rendered(OnlineCourseMigrationNoticeMail $mail, bool $hasPneduAccount): array
    {
        $built = $mail->build();

        return [
            'subject' => (string) ($built->subject ?? ''),
            'body_html' => $built->render(),
            'has_pnedu_account' => $hasPneduAccount,
        ];
    }

    private function eligibleQuery(): Builder
    {
        $query = OnlineCourseEnrollment::query();
        $this->eligible($query, 'online_course_enrollments');

        return $query;
    }

    private function eligible(Builder|\Illuminate\Database\Query\Builder $query, string $alias): void
    {
        $now = Carbon::now('UTC');
        $query->where($alias.'.access_source', self::SOURCE)
            ->whereNotNull($alias.'.email')
            ->where($alias.'.email', '!=', '')
            ->where(function ($inner) use ($alias, $now) {
                $inner->whereNull($alias.'.access_expires_at')
                    ->orWhere($alias.'.access_expires_at', '>=', $now);
            });
    }

    private function markFailed(OnlineCourseMigrationNotice $notice, string $message): void
    {
        $notice->update([
            'status' => OnlineCourseMigrationNotice::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $message,
        ]);
    }

    private function plainTitle(?string $title): string
    {
        $plain = trim(str_replace(
            ['&nbsp;', "\xc2\xa0"],
            ' ',
            strip_tags(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ));
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);

        return $plain !== '' ? $plain : 'Kurs online';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
