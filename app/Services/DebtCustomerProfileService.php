<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\OnlinePaymentOrder;
use Illuminate\Support\Collection;

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
     *     identity: array{
     *         strategy: string,
     *         recipient_nip: ?string,
     *         buyer_nip: ?string,
     *         recipient_profile: ?array{name: string, address: string, postal_code: string, city: string},
     *         orderer_email: ?string
     *     }
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
     * @param  array{
     *     strategy: string,
     *     recipient_nip: ?string,
     *     buyer_nip: ?string,
     *     recipient_profile: ?array{name: string, address: string, postal_code: string, city: string},
     *     orderer_email: ?string
     * }  $identity
     * @return Collection<int, FormOrder>
     */
    public function relatedOrders(array $identity): Collection
    {
        if ($identity['strategy'] === 'none') {
            return collect();
        }

        return FormOrder::query()
            ->with(['primaryParticipant', 'onlinePaymentOrders'])
            ->where(fn ($query) => $this->applyIdentityScope($query, $identity))
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return array{
     *     strategy: string,
     *     recipient_nip: ?string,
     *     buyer_nip: ?string,
     *     recipient_profile: ?array{name: string, address: string, postal_code: string, city: string},
     *     orderer_email: ?string
     * }
     */
    public function identityForOrder(FormOrder $order): array
    {
        $recipientNip = $this->digits($order->recipient_nip);
        $buyerNip = $this->digits($order->buyer_nip);
        $recipientProfile = $this->recipientProfile($order);
        $ordererEmail = $this->email($order->orderer_email);

        if ($recipientNip !== null) {
            $strategy = 'recipient_nip';
        } elseif ($recipientProfile !== null) {
            $strategy = 'recipient_profile';
        } elseif ($buyerNip !== null) {
            $strategy = 'buyer_nip';
        } elseif ($ordererEmail !== null) {
            $strategy = 'orderer_email';
        } else {
            $strategy = 'none';
        }

        return [
            'strategy' => $strategy,
            'recipient_nip' => $recipientNip,
            'buyer_nip' => $buyerNip,
            'recipient_profile' => $recipientProfile,
            'orderer_email' => $ordererEmail,
        ];
    }

    public function strategyLabel(string $strategy): string
    {
        return match ($strategy) {
            'recipient_nip' => 'NIP odbiorcy',
            'recipient_profile' => 'Dane odbiorcy (+ e-mail zamawiającego)',
            'buyer_nip' => 'NIP nabywcy',
            'orderer_email' => 'E-mail zamawiającego',
            default => 'Brak klucza identyfikacji',
        };
    }

    /**
     * @param  array{
     *     strategy: string,
     *     recipient_nip: ?string,
     *     buyer_nip: ?string,
     *     recipient_profile: ?array{name: string, address: string, postal_code: string, city: string},
     *     orderer_email: ?string
     * }  $identity
     * @return list<array{key: string, label: string, value: string, strength: string}>
     */
    public function linkReasonsForRelatedOrder(FormOrder $related, array $identity): array
    {
        $reasons = [];

        if ($identity['strategy'] === 'recipient_nip' && $identity['recipient_nip'] !== null) {
            $relatedRecipientNip = $this->digits($related->recipient_nip);
            if ($relatedRecipientNip !== null && $relatedRecipientNip === $identity['recipient_nip']) {
                $reasons[] = [
                    'key' => 'recipient_nip',
                    'label' => 'NIP odbiorcy',
                    'value' => $relatedRecipientNip,
                    'strength' => 'high',
                ];
            }

            return $reasons;
        }

        if ($identity['strategy'] === 'recipient_profile' && $identity['recipient_profile'] !== null) {
            $relatedProfile = $this->recipientProfile($related);
            if ($relatedProfile !== null && $relatedProfile === $identity['recipient_profile']) {
                $reasons[] = [
                    'key' => 'recipient_profile',
                    'label' => 'Dane odbiorcy',
                    'value' => trim($relatedProfile['name'].', '.$relatedProfile['postal_code'].' '.$relatedProfile['city']),
                    'strength' => 'high',
                ];
            }

            $relatedOrdererEmail = $this->email($related->orderer_email);
            if ($identity['orderer_email'] !== null && $relatedOrdererEmail === $identity['orderer_email']) {
                $reasons[] = [
                    'key' => 'orderer_email',
                    'label' => 'E-mail zamawiającego',
                    'value' => $relatedOrdererEmail,
                    'strength' => 'medium',
                ];
            }

            return $reasons;
        }

        if ($identity['strategy'] === 'buyer_nip' && $identity['buyer_nip'] !== null) {
            $relatedBuyerNip = $this->digits($related->buyer_nip);
            if ($relatedBuyerNip !== null && $relatedBuyerNip === $identity['buyer_nip']) {
                $reasons[] = [
                    'key' => 'buyer_nip',
                    'label' => 'NIP nabywcy',
                    'value' => $relatedBuyerNip,
                    'strength' => 'high',
                ];
            }

            return $reasons;
        }

        if ($identity['strategy'] === 'orderer_email' && $identity['orderer_email'] !== null) {
            $relatedOrdererEmail = $this->email($related->orderer_email);
            if ($relatedOrdererEmail === $identity['orderer_email']) {
                $reasons[] = [
                    'key' => 'orderer_email',
                    'label' => 'E-mail zamawiającego',
                    'value' => $relatedOrdererEmail,
                    'strength' => 'high',
                ];
            }
        }

        return $reasons;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FormOrder>  $query
     * @param  array{
     *     strategy: string,
     *     recipient_nip: ?string,
     *     buyer_nip: ?string,
     *     recipient_profile: ?array{name: string, address: string, postal_code: string, city: string},
     *     orderer_email: ?string
     * }  $identity
     */
    private function applyIdentityScope($query, array $identity): void
    {
        if ($identity['strategy'] === 'recipient_nip' && $identity['recipient_nip'] !== null) {
            $query->whereRaw($this->normalizedDigitsSql('recipient_nip').' = ?', [$identity['recipient_nip']]);

            return;
        }

        if ($identity['strategy'] === 'recipient_profile' && $identity['recipient_profile'] !== null) {
            $profile = $identity['recipient_profile'];
            $query->where(function ($inner) use ($profile, $identity) {
                $inner->where(function ($recipient) use ($profile) {
                    $recipient
                        ->whereRaw($this->normalizedTextSql('recipient_name').' = ?', [$profile['name']])
                        ->whereRaw($this->normalizedAddressSql('recipient_address').' = ?', [$profile['address']])
                        ->whereRaw($this->normalizedTextSql('recipient_postal_code').' = ?', [$profile['postal_code']])
                        ->whereRaw($this->normalizedTextSql('recipient_city').' = ?', [$profile['city']]);
                });

                if ($identity['orderer_email'] !== null) {
                    $inner->orWhereRaw('LOWER(TRIM(orderer_email)) = ?', [$identity['orderer_email']]);
                }
            });

            return;
        }

        if ($identity['strategy'] === 'buyer_nip' && $identity['buyer_nip'] !== null) {
            $query->whereRaw($this->normalizedDigitsSql('buyer_nip').' = ?', [$identity['buyer_nip']]);

            return;
        }

        if ($identity['strategy'] === 'orderer_email' && $identity['orderer_email'] !== null) {
            $query->whereRaw('LOWER(TRIM(orderer_email)) = ?', [$identity['orderer_email']]);
        }
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

    /**
     * @return array{name: string, address: string, postal_code: string, city: string}|null
     */
    private function recipientProfile(FormOrder $order): ?array
    {
        $profile = [
            'name' => $this->normalizeText($order->recipient_name),
            'address' => $this->normalizeAddress($order->recipient_address),
            'postal_code' => $this->normalizeText($order->recipient_postal_code),
            'city' => $this->normalizeText($order->recipient_city),
        ];

        return in_array('', $profile, true) ? null : $profile;
    }

    private function email(?string $value): ?string
    {
        $email = strtolower(trim((string) $value));

        return $email !== '' ? $email : null;
    }

    private function normalizeText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['.', ',', ';', ':'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }

    private function normalizeAddress(?string $value): string
    {
        $value = $this->normalizeText($value);
        $value = preg_replace('/\b(ulica|ul)\b/u', 'ul', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }

    private function normalizedDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), '/', ''), '_', '')";
    }

    private function normalizedTextSql(string $column): string
    {
        return "TRIM(REGEXP_REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE({$column}, '')), '.', ' '), ',', ' '), ';', ' '), ':', ' '), '[[:space:]]+', ' '))";
    }

    private function normalizedAddressSql(string $column): string
    {
        return "TRIM(REGEXP_REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE({$column}, '')), '.', ' '), ',', ' '), ';', ' '), ':', ' '), 'ulica', 'ul'), '[[:space:]]+', ' '))";
    }
}
