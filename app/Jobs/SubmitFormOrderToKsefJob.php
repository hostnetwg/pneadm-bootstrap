<?php

namespace App\Jobs;

use App\Models\FormOrder;
use App\Services\IfirmaFormOrderKsefBackgroundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitFormOrderToKsefJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public int $uniqueFor = 180;

    public function __construct(
        public int $formOrderId
    ) {}

    public function uniqueId(): string
    {
        return 'form-order-ksef-send-'.$this->formOrderId;
    }

    public function handle(IfirmaFormOrderKsefBackgroundService $background): void
    {
        $order = FormOrder::query()->find($this->formOrderId);
        if (! $order) {
            return;
        }

        $background->sendAndScheduleFetch($order);
    }
}
