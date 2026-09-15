<?php

namespace App\Jobs;

use App\Models\FormOrder;
use App\Services\IfirmaFormOrderKsefBackgroundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchFormOrderKsefNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public int $formOrderId,
        public int $attempt = 1
    ) {}

    public function handle(IfirmaFormOrderKsefBackgroundService $background): void
    {
        $order = FormOrder::query()->find($this->formOrderId);
        if (! $order) {
            return;
        }

        $background->fetchOrReschedule($order, $this->attempt);
    }
}
