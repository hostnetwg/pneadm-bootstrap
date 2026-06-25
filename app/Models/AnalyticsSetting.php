<?php

namespace App\Models;

use App\Enums\Analytics\AnalyticsMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime override trybów analityki pne_analytics (panel: Analityka -> Ustawienia).
 *
 * Tabela aplikacyjna w bazie pneadm (NIE w connection analytics). Odczyt także z pnedu
 * (tam osobny model na connection 'pneadm'), wzorzec jak PaymentDisplayOption.
 *
 * Zasady (spójne z AnalyticsModeResolver w obu projektach):
 * - .env ANALYTICS_ENABLED=false to hard kill switch — ma priorytet nad override,
 * - enabled_override = null    -> użyj config('analytics.enabled'),
 * - enabled_override = false   -> wymuś wyłączenie (off),
 * - enabled_override = true    -> włącz (o ile hard kill switch nie wyłącza),
 * - default_mode_override = null -> użyj config('analytics.default_mode'),
 * - default_mode_override z prawidłowych trybów AnalyticsMode.
 */
class AnalyticsSetting extends Model
{
    public const SINGLETON_ID = 1;

    public const SETTINGS_CACHE_KEY = 'analytics_settings_singleton';

    public const SETTINGS_CACHE_TTL_SECONDS = 60;

    protected $table = 'analytics_settings';

    protected $fillable = [
        'enabled_override',
        'default_mode_override',
        'updated_by',
    ];

    protected $casts = [
        'enabled_override' => 'boolean',
        'updated_by' => 'integer',
    ];

    /**
     * Dozwolone tryby do zapisu w override (bez null — null reprezentujemy osobno jako "use_config").
     *
     * @return list<string>
     */
    public static function allowedModes(): array
    {
        return array_map(static fn (AnalyticsMode $mode): string => $mode->value, AnalyticsMode::cases());
    }

    public static function forgetSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    /**
     * Jedyny wiersz ustawień (id = 1). Fail-safe: w razie błędu zwraca obiekt z domyślami (override = null).
     */
    public static function getSettings(): self
    {
        return Cache::remember(
            self::SETTINGS_CACHE_KEY,
            self::SETTINGS_CACHE_TTL_SECONDS,
            static fn () => self::loadSettingsFromDatabase(),
        );
    }

    /**
     * Runtime override dla enabled: null = brak override (użyj config), bool = override.
     */
    public static function enabledOverride(): ?bool
    {
        $value = self::getSettings()->enabled_override;

        return $value === null ? null : (bool) $value;
    }

    /**
     * Runtime override dla trybu: null = brak override (użyj config), string = jeden z allowedModes().
     */
    public static function defaultModeOverride(): ?string
    {
        $value = self::getSettings()->default_mode_override;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return in_array($value, self::allowedModes(), true) ? $value : null;
    }

    private static function loadSettingsFromDatabase(): self
    {
        try {
            $row = self::query()->find(self::SINGLETON_ID) ?? self::query()->first();

            if ($row) {
                return $row;
            }

            return self::query()->create([
                'id' => self::SINGLETON_ID,
                'enabled_override' => null,
                'default_mode_override' => null,
                'updated_by' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $fallback = new self;
            $fallback->id = self::SINGLETON_ID;
            $fallback->enabled_override = null;
            $fallback->default_mode_override = null;
            $fallback->updated_by = null;

            return $fallback;
        }
    }
}
