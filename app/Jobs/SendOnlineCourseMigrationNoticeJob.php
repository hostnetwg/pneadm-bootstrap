<?php

namespace App\Jobs;

use App\Services\OnlineCourseMigrationNoticeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOnlineCourseMigrationNoticeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $noticeId) {}

    public function handle(OnlineCourseMigrationNoticeService $notices): void
    {
        if ($this->batch()?->cancelled()) {
            $notices->markCancelled($this->noticeId);

            return;
        }

        try {
            $notices->deliver($this->noticeId);
        } catch (\Throwable $e) {
            Log::warning('SendOnlineCourseMigrationNoticeJob failed', [
                'notice_id' => $this->noticeId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
