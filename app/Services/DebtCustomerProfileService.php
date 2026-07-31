<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DebtCustomerProfileService
{
    /**
     * @return array{
     *     relationship_score: int,
     *     risk_score: int,
     *     customer_segment: string,
     *     vip_reason: ?string,
     *     related_orders_count: int,
     *     related_orders_total: float,
     *     online_paid_count: int,
     *     open_debt_cases_count: int,
     *     identity: array{recipient_nip: ?string, buyer_nip: ?string, emails: array<int, string>}
     * }
     */
    public function profileForOrder(FormOrder $order): array
    {
        $identity = $this->identityForOrder($order);
        $relatedOrders = $this->relatedOrders($identity);
        $openDebtCasesCount = DebtCase::query()
            ->active()
            ->whereIn('form_order_id', $relatedOrders->pluck('id')->all())
            ->count();

        $onlinePaidCount = $relatedOrders->filter(function (FormOrder $relatedOrder) {
            if ($relatedOrder->payment_mode !== FormOrder::PAYMENT_MODE_ONLINE_GATEWAY) {
                return false;
            }

            $latest = $relatedOrder->relationLoaded('onlinePaymentOrders')
                ? $relatedOrder->onlinePaymentOrders->sortByDesc('id')->first()
                : null;

            return $latest?->status === OnlinePaymentOrder::STATUS_PAID
                || $relatedOrder->payment_status === FormOrder::PAYMENT_STATUS_PAID;
        })->count();

        $relatedOrdersCount = $relatedOrders->count();
        $relatedOrdersTotal = (float) $relatedOrders->sum(fn (FormOrder $relatedOrder) => (float) ($relatedOrder->product_price ?? 0));
        $relationshipScore = $this->relationshipScore($relatedOrdersCount, $relatedOrdersTotal, $onlinePaidCount);
        $riskScore = min(100, ($openDebtCasesCount * 25) + ($this->isPastDue($order) ? 20 : 0));
        $vipReason = $this->vipReason($relatedOrdersCount, $relatedOrdersTotal, $onlinePaidCount);
        $customerSegment = $this->segment($relationshipScore, $riskScore);

        return [
            'relationship_score' => $relationshipScore,
            'risk_score' => $riskScore,
            'customer_segment' => $customerSegment,
            'vip_reason' => $vipReason,
            'related_orders_count' => $relatedOrdersCount,
            'related_orders_total' => $relatedOrdersTotal,
            'online_paid_count' => $onlinePaidCount,
            'open_debt_cases_count' => $openDebtCasesCount,
            'identity' => $identity,
        ];
    }

    /**
     * @param  array{recipient_nip: ?string, buyer_nip: ?string, emails: array<int, string>}  $identity
     * @return Collection<int, FormOrder>
     */
    public function relatedOrders(array $identity): Collection
    {
        $recipientNip = $identity['recipient_nip'];
        $buyerNip = $identity['buyer_nip'];
        $emails = $identity['emails'];

        if ($recipientNip === null && $buyerNip === null && $emails === []) {
            return collect();
        }

        return FormOrder::query()
            ->with(['primaryParticipant', 'onlinePaymentOrders'])
            ->where(function ($query) use ($recipientNip, $buyerNip, $emails) {
                if ($recipientNip !== null) {
                    $query->orWhereRaw($this->normalizedDigitsSql('recipient_nip').' = ?', [$recipientNip]);
                }

                if ($buyerNip !== null) {
                    $query->orWhereRaw($this->normalizedDigitsSql('buyer_nip').' = ?', [$buyerNip]);
                }

                if ($emails !== []) {
                    $query->orWhereIn(DB::raw('LOWER(TRIM(orderer_email))'), $emails)
                        ->orWhereHas('primaryParticipant', function ($participantQuery) use ($emails) {
                            $participantQuery->whereIn(DB::raw('LOWER(TRIM(participant_email))'), $emails);
                        });
                }
            })
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return array{recipient_nip: ?string, buyer_nip: ?string, emails: array<int, string>}
     */
    public function identityForOrder(FormOrder $order): array
    {
        return [
            'recipient_nip' => $this->digits($order->recipient_nip),
            'buyer_nip' => $this->digits($order->buyer_nip),
            'emails' => collect([
                strtolower(trim((string) ($order->orderer_email ?? ''))),
                strtolower(trim((string) ($order->display_participant_email ?? ''))),
            ])->filter()->unique()->values()->all(),
        ];
    }

    private function relationshipScore(int $ordersCount, float $ordersTotal, int $onlinePaidCount): int
    {
        $score = min(45, $ordersCount * 8)
            + min(35, (int) floor($ordersTotal / 500) * 5)
            + min(20, $onlinePaidCount * 5);

        return min(100, $score);
    }

    private function segment(int $relationshipScore, int $riskScore): string
    {
        if ($relationshipScore >= 60 && $riskScore > 0) {
            return DebtCase::SEGMENT_VIP_OVERDUE;
        }

        if ($relationshipScore >= 60) {
            return DebtCase::SEGMENT_VIP;
        }

        if ($riskScore >= 50) {
            return DebtCase::SEGMENT_RISK;
        }

        return DebtCase::SEGMENT_STANDARD;
    }

    private function vipReason(int $ordersCount, float $ordersTotal, int $onlinePaidCount): ?string
    {
        $reasons = [];

        if ($ordersCount >= 5) {
            $reasons[] = "{$ordersCount} powiązanych zamówień";
        }

        if ($ordersTotal >= 2500) {
            $reasons[] = 'łączna wartość '.number_format($ordersTotal, 2, ',', ' ').' zł';
        }

        if ($onlinePaidCount >= 3) {
            $reasons[] = "{$onlinePaidCount} opłacone online";
        }

        return $reasons === [] ? null : implode(', ', $reasons);
    }

    private function isPastDue(FormOrder $order): bool
    {
        if (! $order->order_date) {
            return false;
        }

        $delay = (int) ($order->invoice_payment_delay ?: 14);

        return $order->order_date->copy()->addDays($delay)->startOfDay()->lt(now()->startOfDay());
    }

    private function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function normalizedDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), '/', ''), '_', '')";
    }
}
