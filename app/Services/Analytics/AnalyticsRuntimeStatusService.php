<?php

namespace App\Services\Analytics;

use App\Enums\Analytics\AnalyticsMode;
use App\Models\AnalyticsSetting;

/**
 * Read-only status analityki na potrzeby UI (baner ostrzegawczy w panelach Analityka).
 *
 * Nie zmienia stanu ani logiki zbierania eventów. Korzysta z AnalyticsModeResolver
 * (efektywny tryb) oraz AnalyticsSetting (runtime override) — bez duplikowania logiki w widokach.
 *
 * warning_level:
 * - 'danger'  -> hard kill switch (.env ANALYTICS_ENABLED=false) albo efektywny tryb 'off',
 * - 'warning' -> efektywny tryb 'aggregate_only' lub 'light',
 * - 'none'    -> 'standard'/'full' (baner ostrzegawczy nie jest potrzebny).
 */
class AnalyticsRuntimeStatusService
{
    public function __construct(private readonly AnalyticsModeResolver $resolver) {}

    /**
     * @return array{
     *     config_enabled: bool,
     *     runtime_enabled_override: ?bool,
     *     runtime_default_mode_override: ?string,
     *     effective_enabled: bool,
     *     effective_mode: string,
     *     source: string,
     *     warning_level: string,
     *     show_banner: bool,
     *     message: ?string
     * }
     */
    public function status(): array
    {
        $configEnabled = (bool) config('analytics.enabled', true);
        $enabledOverride = AnalyticsSetting::enabledOverride();
        $modeOverride = AnalyticsSetting::defaultModeOverride();

        $effectiveMode = $this->resolver->resolve()->value;
        $effectiveEnabled = $effectiveMode !== AnalyticsMode::Off->value;

        if (! $configEnabled) {
            $source = 'hard_kill_switch';
        } elseif ($enabledOverride !== null || $modeOverride !== null) {
            $source = 'runtime_override';
        } else {
            $source = 'config';
        }

        [$warningLevel, $message] = $this->resolveWarning($configEnabled, $effectiveMode);

        return [
            'config_enabled' => $configEnabled,
            'runtime_enabled_override' => $enabledOverride,
            'runtime_default_mode_override' => $modeOverride,
            'effective_enabled' => $effectiveEnabled,
            'effective_mode' => $effectiveMode,
            'source' => $source,
            'warning_level' => $warningLevel,
            'show_banner' => $warningLevel !== 'none',
            'message' => $message,
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveWarning(bool $configEnabled, string $effectiveMode): array
    {
        if (! $configEnabled) {
            return ['danger', 'Analityka jest wyłączona przez konfigurację serwera (ANALYTICS_ENABLED=false). Panel nie może tego nadpisać. Eventy nie będą zbierane.'];
        }

        return match ($effectiveMode) {
            AnalyticsMode::Off->value => ['danger', 'Analityka jest wyłączona w ustawieniach runtime. Nowe eventy nie będą zbierane.'],
            AnalyticsMode::AggregateOnly->value => ['warning', 'Analityka działa w trybie bardzo ograniczonym (aggregate_only). Część eventów lejka, płatności lub faktur może nie być zbierana.'],
            AnalyticsMode::Light->value => ['warning', 'Analityka działa w trybie lekkim (light). Zbierany jest ograniczony zestaw eventów.'],
            default => ['none', null],
        };
    }
}
