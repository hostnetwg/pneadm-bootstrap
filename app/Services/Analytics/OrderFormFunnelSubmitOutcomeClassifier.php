<?php

namespace App\Services\Analytics;

use Carbon\Carbon;

/**
 * Klasyfikuje wynik sesji po kliknięciu „Wyślij” z tolerancją czasową (grace period).
 */
class OrderFormFunnelSubmitOutcomeClassifier
{
    public function gracePeriodSoftMinutes(): int
    {
        return max(1, (int) config('analytics.order_form_funnel.grace_period_soft_minutes', 15));
    }

    public function gracePeriodFinalMinutes(): int
    {
        return max(1, (int) config('analytics.order_form_funnel.grace_period_final_minutes', 60));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function classify(array $session, Carbon $statDate, string $timezone): ?string
    {
        $flags = $session['flags'];

        if ($flags['order_created']) {
            return null;
        }

        if (! $flags['reached_submit_clicked']) {
            return null;
        }

        if ($flags['client_validation_failed']) {
            return 'validation_abandonment';
        }

        if ($flags['server_submit_attempted']) {
            if ($flags['server_validation_failed']) {
                return 'server_validation_abandonment';
            }

            if (! $flags['order_create_failed'] && $this->isSessionMature($session, $statDate, $timezone)) {
                return 'backend_result_missing';
            }

            return null;
        }

        $submitAt = $session['submit_clicked_at'] ?? null;
        if (! $submitAt instanceof Carbon) {
            return null;
        }

        $finalGraceEnd = $submitAt->copy()->addMinutes($this->gracePeriodFinalMinutes());

        if (! $this->isPastFinalGrace($finalGraceEnd, $statDate, $timezone)) {
            return 'pending_after_submit_clicked';
        }

        return 'abandoned_after_submit_clicked';
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function isSessionMature(array $session, Carbon $statDate, string $timezone): bool
    {
        $lastEventAt = $session['last_event_at'] ?? null;
        if ($lastEventAt instanceof Carbon) {
            $maturityAt = $lastEventAt->copy()->addHours(2);
            if (now()->gte($maturityAt)) {
                return true;
            }
        }

        return $this->isStatDateMatureByLag($statDate, $timezone);
    }

    private function isPastFinalGrace(Carbon $finalGraceEnd, Carbon $statDate, string $timezone): bool
    {
        if (now()->gte($finalGraceEnd)) {
            return true;
        }

        return $this->isStatDateMatureByLag($statDate, $timezone);
    }

    private function isStatDateMatureByLag(Carbon $statDate, string $timezone): bool
    {
        $lagDays = max(1, (int) config('analytics.order_form_funnel.aggregation_lag_days', 2));
        $lagCutoff = Carbon::now($timezone)->subDays($lagDays)->startOfDay();

        return $statDate->copy()->timezone($timezone)->startOfDay()->lt($lagCutoff);
    }
}
