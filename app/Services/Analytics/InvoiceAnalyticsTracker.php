<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsEventName;
use App\Models\FormOrder;
use Throwable;

/**
 * Backoffice analytics tracker for the invoice_created event (Etap 2C-1).
 *
 * Emits exactly one analytics event the first time a FormOrder receives a valid
 * invoice_number. Per ADR-005 this means "zafakturowane / rozliczone operacyjnie"
 * (invoiced / operationally settled), NOT an actual bank transfer.
 *
 * The event is fail-silent and never carries PII or raw invoice/iFirma/KSeF data.
 */
class InvoiceAnalyticsTracker
{
    public const PATH_IFIRMA = 'ifirma';

    public const PATH_MANUAL = 'manual';

    public const PATH_UNKNOWN = 'unknown';

    public const FLOW_DEFERRED = 'deferred';

    public const FLOW_ONLINE = 'online';

    public const FLOW_UNKNOWN = 'unknown';

    /**
     * Per-request hint about who is setting invoice_number. Controllers may set it
     * right before the Eloquent save so the observer can attribute invoice_path_type.
     * Consumed once and reset, so a stale hint cannot leak into a later event.
     */
    private static ?string $sourceHint = null;

    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public static function hintSource(?string $source): void
    {
        self::$sourceHint = in_array($source, [self::PATH_IFIRMA, self::PATH_MANUAL], true)
            ? $source
            : null;
    }

    public static function consumeSourceHint(): string
    {
        $hint = self::$sourceHint;
        self::$sourceHint = null;

        return in_array($hint, [self::PATH_IFIRMA, self::PATH_MANUAL], true)
            ? $hint
            : self::PATH_UNKNOWN;
    }

    public function trackInvoiceCreated(FormOrder $formOrder, string $invoicePathType = self::PATH_UNKNOWN): void
    {
        try {
            $formOrderId = (int) $formOrder->getKey();

            if ($formOrderId <= 0) {
                return;
            }

            $payload = [
                'event_uuid' => $this->invoiceCreatedEventUuid($formOrderId),
                'form_order_id' => $formOrderId,
            ];

            $courseId = $formOrder->product_id !== null ? (int) $formOrder->product_id : 0;
            if ($courseId > 0) {
                $payload['course_id'] = $courseId;
            }

            $amountGross = $this->orderAmountGross($formOrder);
            if ($amountGross !== null) {
                $payload['amount_snapshot'] = $amountGross;
            }

            $orderFlow = $this->orderFlow($formOrder);

            $metadata = [
                'order_flow' => $orderFlow,
                'invoice_path_type' => $this->normalizePathType($invoicePathType),
            ];

            $paymentType = $this->paymentType($formOrder);
            if ($paymentType !== null) {
                $metadata['payment_type'] = $paymentType;
            }

            if ($amountGross !== null) {
                $metadata['amount_gross'] = $amountGross;
            }

            $payload['metadata'] = $metadata;

            $this->analyticsService->track(AnalyticsEventName::InvoiceCreated, $payload);
        } catch (Throwable) {
            // Fail-silent: analytics must never break invoicing or saving invoice_number.
        }
    }

    public function invoiceCreatedEventUuid(int $formOrderId): string
    {
        return AnalyticsEventName::InvoiceCreated->value.'|'.$formOrderId;
    }

    private function orderFlow(FormOrder $formOrder): string
    {
        return match ($formOrder->payment_mode) {
            FormOrder::PAYMENT_MODE_DEFERRED_INVOICE => self::FLOW_DEFERRED,
            FormOrder::PAYMENT_MODE_ONLINE_GATEWAY => self::FLOW_ONLINE,
            default => self::FLOW_UNKNOWN,
        };
    }

    private function paymentType(FormOrder $formOrder): ?string
    {
        return match ($formOrder->payment_mode) {
            FormOrder::PAYMENT_MODE_DEFERRED_INVOICE => 'deferred_invoice',
            FormOrder::PAYMENT_MODE_ONLINE_GATEWAY => 'online',
            default => null,
        };
    }

    private function orderAmountGross(FormOrder $formOrder): ?float
    {
        $price = $formOrder->product_price;

        if ($price === null || $price === '') {
            return null;
        }

        if (! is_numeric($price)) {
            return null;
        }

        return round((float) $price, 2);
    }

    private function normalizePathType(string $invoicePathType): string
    {
        return in_array($invoicePathType, [self::PATH_IFIRMA, self::PATH_MANUAL], true)
            ? $invoicePathType
            : self::PATH_UNKNOWN;
    }
}
