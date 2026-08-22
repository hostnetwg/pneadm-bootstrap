<?php

namespace App\Services;

use App\Models\FormOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Porzucona płatność online — kryteria zsynchronizowane z pnedu
 * (FormOrderOnlineAbandonmentService::isAbandonedUnpaidOnline).
 *
 * Etap 4: badge + filtr w liście form-orders.
 */
class FormOrderOnlineAbandonmentService
{
    public function abandonmentMinutes(): int
    {
        return max(1, (int) config('form_orders.online_abandonment_minutes', 60));
    }

    /**
     * Moment ostatniej aktywności płatności online (UTC) — do liczenia progu porzucenia.
     */
    public function onlineActivityReferenceAt(FormOrder $order): ?Carbon
    {
        $order->loadMissing('onlinePaymentOrders');

        $candidates = [];

        if ($order->order_date instanceof Carbon) {
            $candidates[] = $order->order_date->copy()->utc();
        } elseif (! empty($order->order_date)) {
            $candidates[] = Carbon::parse($order->order_date, 'UTC');
        }

        if ($order->created_at instanceof Carbon) {
            $candidates[] = $order->created_at->copy()->utc();
        }

        foreach ($order->onlinePaymentOrders as $attempt) {
            if ($attempt->created_at instanceof Carbon) {
                $candidates[] = $attempt->created_at->copy()->utc();
            }
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->max();
    }

    public function isUnpaidOnlineGatewayOrder(FormOrder $order): bool
    {
        if ($order->payment_mode !== FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
            return false;
        }

        if ($order->cancelled_at !== null) {
            return false;
        }

        if ($order->payment_status === FormOrder::PAYMENT_STATUS_PAID) {
            return false;
        }

        if (trim((string) ($order->invoice_number ?? '')) !== '') {
            return false;
        }

        if ($order->status_completed) {
            return false;
        }

        return true;
    }

    /**
     * Czy nieopłacone online uznajemy za porzucone (failed/cancelled od razu;
     * awaiting_payment po ≥ N minutach od ostatniej aktywności).
     */
    public function isAbandonedUnpaidOnline(FormOrder $order, ?Carbon $now = null): bool
    {
        if (! $this->isUnpaidOnlineGatewayOrder($order)) {
            return false;
        }

        $status = $order->payment_status;

        if (in_array($status, [FormOrder::PAYMENT_STATUS_CANCELLED, FormOrder::PAYMENT_STATUS_FAILED], true)) {
            return true;
        }

        if ($status !== FormOrder::PAYMENT_STATUS_AWAITING_PAYMENT) {
            return false;
        }

        $now = ($now ?? now('UTC'))->copy()->utc();
        $reference = $this->onlineActivityReferenceAt($order);

        if ($reference === null) {
            return false;
        }

        return $reference->lte($now->copy()->subMinutes($this->abandonmentMinutes()));
    }

    /**
     * Scope SQL: porzucone nieopłacone zamówienia online (filtr listy adm).
     */
    public function scopeAbandonedUnpaidOnline(Builder $query, ?Carbon $now = null): Builder
    {
        $now = ($now ?? now('UTC'))->copy()->utc();
        $cutoff = $now->copy()->subMinutes($this->abandonmentMinutes())->format('Y-m-d H:i:s');
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.payment_mode", FormOrder::PAYMENT_MODE_ONLINE_GATEWAY)
            ->whereNull("{$table}.cancelled_at")
            ->where("{$table}.payment_status", '!=', FormOrder::PAYMENT_STATUS_PAID)
            ->where(function (Builder $invoice) use ($table) {
                $invoice->whereNull("{$table}.invoice_number")
                    ->orWhere("{$table}.invoice_number", '')
                    ->orWhere("{$table}.invoice_number", '0');
            })
            ->where(function (Builder $completed) use ($table) {
                $completed->where("{$table}.status_completed", '!=', 1)
                    ->orWhereNull("{$table}.status_completed");
            })
            ->where(function (Builder $abandoned) use ($table, $cutoff) {
                $abandoned
                    ->whereIn("{$table}.payment_status", [
                        FormOrder::PAYMENT_STATUS_CANCELLED,
                        FormOrder::PAYMENT_STATUS_FAILED,
                    ])
                    ->orWhere(function (Builder $awaiting) use ($table, $cutoff) {
                        $awaiting
                            ->where("{$table}.payment_status", FormOrder::PAYMENT_STATUS_AWAITING_PAYMENT)
                            ->whereRaw($this->onlineActivityReferenceSql($table).' <= ?', [$cutoff]);
                    });
            });
    }

    private function onlineActivityReferenceSql(string $table = 'form_orders'): string
    {
        return "GREATEST(
            COALESCE({$table}.order_date, {$table}.created_at),
            COALESCE(
                (SELECT MAX(opo.created_at) FROM online_payment_orders opo WHERE opo.form_order_id = {$table}.id),
                {$table}.order_date,
                {$table}.created_at
            )
        )";
    }
}
