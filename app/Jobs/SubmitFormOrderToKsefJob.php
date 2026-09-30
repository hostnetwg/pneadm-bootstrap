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

    public ?string $expectedInvoiceId = null;

    public function __construct(
        public int $formOrderId,
        ?string $expectedInvoiceId = null
    ) {
        $this->expectedInvoiceId = $expectedInvoiceId;
    }

    public function uniqueId(): string
    {
        return 'form-order-ksef-send-'.$this->formOrderId.'-'.($this->expectedInvoiceId ?? 'legacy');
    }

    public function handle(IfirmaFormOrderKsefBackgroundService $background): void
    {
        $order = FormOrder::query()->find($this->formOrderId);
        if (! $order) {
            return;
        }

        if (
            $this->expectedInvoiceId !== null
            && trim((string) ($order->ifirma_invoice_id ?? '')) !== $this->expectedInvoiceId
        ) {
            return;
        }

        $background->sendAndScheduleFetch($order);
    }
}
