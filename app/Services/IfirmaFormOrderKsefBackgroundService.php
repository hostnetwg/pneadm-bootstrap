<?php

namespace App\Services;

use App\Jobs\FetchFormOrderKsefNumberJob;
use App\Jobs\SubmitFormOrderToKsefJob;
use App\Models\FormOrder;
use App\Models\OpsRun;
use App\Models\OpsRunItem;
use App\Services\Ops\OpsRunRecorder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class IfirmaFormOrderKsefBackgroundService
{
    public function __construct(
        private IfirmaApiService $api,
        private IfirmaFormOrderKsefSyncService $sync,
        private OpsRunRecorder $ops
    ) {}

    public function queueAfterInvoice(FormOrder $order, bool $sendEmail, ?int $actorUserId = null): void
    {
        if ($order->hasConfirmedKsef()) {
            return;
        }

        if ($sendEmail && ! $order->ksef_email_pending) {
            $order->ksef_email_pending = true;
        }

        $invoiceId = trim((string) ($order->ifirma_invoice_id ?? ''));
        if ($invoiceId === '') {
            $order->ksef_status = 'failed';
            $order->ksef_error = 'Brak ID faktury iFirma — nie można zlecić wysyłki do KSeF.';
            $order->save();
            $this->recordItem($order, OpsRunItem::STATUS_FAILED, $order->ksef_error, $actorUserId);

            return;
        }

        if ($order->ksef_status === 'pending') {
            $order->save();
            $this->recordItem(
                $order,
                OpsRunItem::STATUS_PENDING,
                'Faktura już przekazana do KSeF — dociąganie numeru w tle.',
                $actorUserId
            );
            $this->dispatchFetch($order->id, 1);

            return;
        }

        $order->ksef_status = 'pending';
        $order->ksef_error = null;
        $order->save();

        $this->recordItem(
            $order,
            OpsRunItem::STATUS_PENDING,
            'Zlecono wysyłkę do KSeF w tle.',
            $actorUserId,
            ['invoice_number' => $order->invoice_number]
        );

        SubmitFormOrderToKsefJob::dispatch($order->id);
    }

    public function sendAndScheduleFetch(FormOrder $order): void
    {
        $order->refresh();

        if ($order->hasConfirmedKsef()) {
            $this->recordItem(
                $order,
                OpsRunItem::STATUS_SUCCESS,
                'Numer KSeF był już zapisany.',
                null,
                ['ksef_number' => $order->ksef_number]
            );

            return;
        }

        $invoiceId = trim((string) ($order->ifirma_invoice_id ?? ''));
        if ($invoiceId === '') {
            $this->markFailed($order, 'Brak ID faktury iFirma.');

            return;
        }

        Log::info('iFirma KSeF tło: POST ksef/send', [
            'order_id' => $order->id,
            'invoice_id' => $invoiceId,
        ]);

        $ksefResult = $this->api->sendInvoiceToKsef($invoiceId, 'fakturakraj');

        if (($ksefResult['status'] ?? null) !== 'success') {
            if ($this->recoverAfterSendFailure($order, $invoiceId, $ksefResult)) {
                return;
            }

            $error = $ksefResult['message'] ?? 'Nieznany błąd przesyłania do KSeF';
            $this->markFailed($order, $error);

            return;
        }

        $ksefNumber = $this->api->extractNumerKSeFFromInvoicePayload(
            isset($ksefResult['data']) && is_array($ksefResult['data']) ? $ksefResult['data'] : null
        );

        $order->ksef_status = 'pending';
        $order->ksef_error = null;
        $order->save();

        if ($ksefNumber !== null && $ksefNumber !== '') {
            $this->persistNumberViaSync($order);

            return;
        }

        $this->recordItem(
            $order,
            OpsRunItem::STATUS_PENDING,
            'Przekazano do KSeF. Oczekiwanie na numer z MF.',
            null,
            ['invoice_number' => $order->invoice_number]
        );

        $this->fetchOrReschedule($order, 1);
    }

    public function fetchOrReschedule(FormOrder $order, int $attempt): void
    {
        $order->refresh();

        if ($order->hasConfirmedKsef()) {
            $this->recordItem(
                $order,
                OpsRunItem::STATUS_SUCCESS,
                'Pobrano numer KSeF.',
                null,
                $this->successPayload($order)
            );

            return;
        }

        $invoiceId = trim((string) ($order->ifirma_invoice_id ?? ''));
        if ($invoiceId === '') {
            $this->markFailed($order, 'Brak ID faktury iFirma przy dociąganiu numeru KSeF.');

            return;
        }

        $sync = $this->sync->syncFromIfirmaInvoiceId($order);
        $order->refresh();

        if ($order->hasConfirmedKsef()) {
            $this->recordItem(
                $order,
                OpsRunItem::STATUS_SUCCESS,
                'Pobrano numer KSeF.',
                null,
                $this->successPayload($order, $sync)
            );

            return;
        }

        $invoiceDetails = $this->api->getInvoice($invoiceId);
        if (($invoiceDetails['status'] ?? null) === 'success' && isset($invoiceDetails['data']) && is_array($invoiceDetails['data'])) {
            $rejection = $this->api->detectKsefRejectionFromInvoicePayload($invoiceDetails['data']);
            if ($rejection !== null) {
                $this->markFailed($order, $rejection);

                return;
            }
        }

        $maxAttempts = max(1, (int) config('services.ifirma.ksef_background_max_attempts', 40));
        if ($attempt >= $maxAttempts) {
            $msg = 'Faktura jest w iFirma i została przekazana do KSeF, ale Ministerstwo Finansów nie nadało jeszcze numeru KSeF. Można dociągnąć ikoną Odśwież przy numerze faktury.';
            $order->ksef_status = 'pending';
            $order->ksef_error = $msg;
            $order->save();
            $this->recordItem($order, OpsRunItem::STATUS_PENDING, $msg, null, [
                'attempts' => $attempt,
                'invoice_number' => $order->invoice_number,
            ]);

            Log::warning('iFirma KSeF tło: wyczerpano próby dociągnięcia numeru', [
                'order_id' => $order->id,
                'attempt' => $attempt,
            ]);

            return;
        }

        $this->recordItem(
            $order,
            OpsRunItem::STATUS_PENDING,
            'Oczekiwanie na numer KSeF (próba '.$attempt.').',
            null,
            ['attempt' => $attempt, 'invoice_number' => $order->invoice_number]
        );

        $this->dispatchFetch($order->id, $attempt + 1);
    }

    /**
     * @param  array<string, mixed>  $ksefResult
     */
    private function recoverAfterSendFailure(FormOrder $order, string $invoiceId, array $ksefResult): bool
    {
        $invoiceDetails = $this->api->getInvoice($invoiceId);
        if (($invoiceDetails['status'] ?? null) === 'success' && isset($invoiceDetails['data']) && is_array($invoiceDetails['data'])) {
            $existing = $this->api->extractNumerKSeFFromInvoicePayload($invoiceDetails['data']);
            if ($existing !== null && $existing !== '') {
                $this->persistNumberViaSync($order);

                return true;
            }
        }

        $message = strtolower((string) ($ksefResult['message'] ?? ''));
        if ($message !== '' && (
            str_contains($message, 'już')
            || str_contains($message, 'juz')
            || str_contains($message, 'already')
            || str_contains($message, 'wcześniej')
            || str_contains($message, 'wczesniej')
        )) {
            $order->ksef_status = 'pending';
            $order->ksef_error = null;
            $order->save();
            $this->dispatchFetch($order->id, 1);

            return true;
        }

        return false;
    }

    private function persistNumberViaSync(FormOrder $order): void
    {
        $sync = $this->sync->syncFromIfirmaInvoiceId($order);
        $order->refresh();

        if (! $order->hasConfirmedKsef()) {
            $this->dispatchFetch($order->id, 1);

            return;
        }

        $this->recordItem(
            $order,
            OpsRunItem::STATUS_SUCCESS,
            'Pobrano numer KSeF.',
            null,
            $this->successPayload($order, $sync)
        );
    }

    private function markFailed(FormOrder $order, string $error): void
    {
        $order->ksef_status = 'failed';
        $order->ksef_error = $error;
        $order->save();

        $this->recordItem($order, OpsRunItem::STATUS_FAILED, $error, null, [
            'invoice_number' => $order->invoice_number,
        ]);

        Log::error('iFirma KSeF tło: błąd', [
            'order_id' => $order->id,
            'error' => $error,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordItem(
        FormOrder $order,
        string $status,
        string $message,
        ?int $actorUserId,
        array $extra = []
    ): void {
        $run = $this->ops->dailyRun(OpsRun::TYPE_KSEF_BACKGROUND, null, $actorUserId);
        $this->ops->upsertItem($run, $order, $status, $message, array_merge([
            'invoice_number' => $order->invoice_number,
            'ksef_number' => $order->ksef_number,
            'ksef_status' => $order->ksef_status,
            'emails_sent_count' => (int) ($extra['emails_sent_count'] ?? 0),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>|null  $sync
     * @return array<string, mixed>
     */
    private function successPayload(FormOrder $order, ?array $sync = null): array
    {
        $emails = $sync['emails_sent'] ?? [];

        return [
            'invoice_number' => $order->invoice_number,
            'ksef_number' => $order->ksef_number,
            'ksef_status' => $order->ksef_status,
            'emails_sent_count' => is_array($emails) ? count($emails) : 0,
            'emails_sent' => $emails,
        ];
    }

    private function dispatchFetch(int $formOrderId, int $attempt): void
    {
        if ($this->wouldExecuteInline()) {
            Log::warning('iFirma KSeF tło: pominięto kolejkę dociągnięcia numeru (sync queue)', [
                'order_id' => $formOrderId,
                'attempt' => $attempt,
            ]);

            return;
        }

        $delay = $attempt <= 1
            ? max(15, (int) config('services.ifirma.ksef_background_retry_seconds', 60))
            : max(30, (int) config('services.ifirma.ksef_background_retry_seconds', 60));

        FetchFormOrderKsefNumberJob::dispatch($formOrderId, $attempt)
            ->delay(now()->addSeconds($delay));
    }

    private function wouldExecuteInline(): bool
    {
        if (config('queue.default') !== 'sync') {
            return false;
        }

        return ! Queue::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\QueueFake;
    }
}
