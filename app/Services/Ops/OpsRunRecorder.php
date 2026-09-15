<?php

namespace App\Services\Ops;

use App\Models\Course;
use App\Models\FormOrder;
use App\Models\OpsRun;
use App\Models\OpsRunItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OpsRunRecorder
{
    public function dailyRun(string $type, ?Carbon $day = null, ?int $actorUserId = null): OpsRun
    {
        $day = ($day ?? Carbon::now('Europe/Warsaw'))->copy()->timezone('Europe/Warsaw')->startOfDay();

        $run = OpsRun::query()->firstOrCreate(
            [
                'type' => $type,
                'period_date' => $day->toDateString(),
            ],
            [
                'status' => OpsRun::STATUS_RUNNING,
                'title' => $this->defaultTitle($type, $day),
                'actor_user_id' => $actorUserId,
                'summary' => $this->emptySummary($type),
                'started_at' => now(),
            ]
        );

        if ($actorUserId !== null && $run->actor_user_id === null) {
            $run->actor_user_id = $actorUserId;
            $run->save();
        }

        return $run;
    }

    public function startRun(string $type, string $title, ?Carbon $periodDate = null, ?int $actorUserId = null): OpsRun
    {
        return OpsRun::query()->create([
            'type' => $type,
            'status' => OpsRun::STATUS_RUNNING,
            'title' => $title,
            'period_date' => $periodDate?->timezone('Europe/Warsaw')->toDateString(),
            'actor_user_id' => $actorUserId,
            'summary' => $this->emptySummary($type),
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertItem(
        OpsRun $run,
        Model $subject,
        string $status,
        string $message,
        array $payload = []
    ): OpsRunItem {
        $item = OpsRunItem::query()->updateOrCreate(
            [
                'ops_run_id' => $run->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ],
            [
                'status' => $status,
                'message' => $message,
                'payload' => $payload,
            ]
        );

        $this->refreshSummary($run->fresh() ?? $run);

        return $item;
    }

    public function refreshSummary(OpsRun $run): void
    {
        $items = $run->items()->get();
        $counts = [
            'total' => $items->count(),
            'pending' => $items->where('status', OpsRunItem::STATUS_PENDING)->count(),
            'success' => $items->where('status', OpsRunItem::STATUS_SUCCESS)->count(),
            'failed' => $items->where('status', OpsRunItem::STATUS_FAILED)->count(),
        ];

        if ($run->type === OpsRun::TYPE_KSEF_BACKGROUND) {
            $numbers = $items->filter(function (OpsRunItem $item) {
                $number = $item->payload['ksef_number'] ?? null;

                return is_string($number) && trim($number) !== '';
            })->count();
            $counts['numbers_received'] = $numbers;
            $counts['emails_sent'] = $items->sum(fn (OpsRunItem $item) => (int) ($item->payload['emails_sent_count'] ?? 0));
        }

        if ($run->type === OpsRun::TYPE_ACCESS_EXPIRY_REMINDERS) {
            $counts['courses'] = $items->count();
            $counts['queued'] = $items->sum(fn (OpsRunItem $item) => (int) ($item->payload['queued'] ?? 0));
        }

        $status = OpsRun::STATUS_RUNNING;
        if ($counts['total'] === 0) {
            $status = OpsRun::STATUS_SUCCESS;
        } elseif ($counts['failed'] > 0 && $counts['success'] === 0 && $counts['pending'] === 0) {
            $status = OpsRun::STATUS_FAILED;
        } elseif ($counts['failed'] > 0 || $counts['pending'] > 0) {
            $status = $counts['pending'] > 0 ? OpsRun::STATUS_RUNNING : OpsRun::STATUS_PARTIAL;
        } else {
            $status = OpsRun::STATUS_SUCCESS;
        }

        $run->summary = $counts;
        $run->status = $status;
        if ($status !== OpsRun::STATUS_RUNNING) {
            $run->finished_at = now();
        }
        $run->save();
    }

    public function subjectLabel(OpsRunItem $item): string
    {
        if ($item->subject_type === FormOrder::class || $item->subject_type === (new FormOrder)->getMorphClass()) {
            $order = $item->subject;

            return $order instanceof FormOrder
                ? 'Zamówienie #'.$order->id.($order->invoice_number ? ' · FV '.$order->invoice_number : '')
                : 'Zamówienie #'.$item->subject_id;
        }

        if ($item->subject_type === Course::class || $item->subject_type === (new Course)->getMorphClass()) {
            $course = $item->subject;

            return $course instanceof Course
                ? 'Szkolenie #'.$course->id.' · '.$course->title
                : 'Szkolenie #'.$item->subject_id;
        }

        return class_basename($item->subject_type).' #'.$item->subject_id;
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(string $type): array
    {
        $base = [
            'total' => 0,
            'pending' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        if ($type === OpsRun::TYPE_KSEF_BACKGROUND) {
            $base['numbers_received'] = 0;
            $base['emails_sent'] = 0;
        }

        if ($type === OpsRun::TYPE_ACCESS_EXPIRY_REMINDERS) {
            $base['courses'] = 0;
            $base['queued'] = 0;
        }

        return $base;
    }

    private function defaultTitle(string $type, Carbon $day): string
    {
        $date = $day->format('d.m.Y');

        return match ($type) {
            OpsRun::TYPE_KSEF_BACKGROUND => 'KSeF w tle — '.$date,
            OpsRun::TYPE_ACCESS_EXPIRY_REMINDERS => 'Przypomnienia o wygaśnięciu dostępu — '.$date,
            default => $type.' — '.$date,
        };
    }
}
