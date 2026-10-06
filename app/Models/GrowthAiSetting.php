<?php

namespace App\Models;

use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton Growth OS AI defaults (DEC-052). API keys stay in config/env.
 */
class GrowthAiSetting extends Model
{
    public const SINGLETON_ID = 1;

    public const SETTINGS_CACHE_KEY = 'growth_ai_settings_singleton';

    public const SETTINGS_CACHE_TTL_SECONDS = 60;

    protected $table = 'growth_ai_settings';

    protected $fillable = [
        'general_model',
        'general_reasoning_effort',
        'research_model',
        'research_reasoning_effort',
        'updated_by',
    ];

    protected $casts = [
        'updated_by' => 'integer',
    ];

    public static function forgetSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    public static function getSettings(): self
    {
        return Cache::remember(
            self::SETTINGS_CACHE_KEY,
            self::SETTINGS_CACHE_TTL_SECONDS,
            static fn () => self::loadSettingsFromDatabase(),
        );
    }

    /**
     * Validated defaults for execution. Fail closed to recommended Sol if row is corrupt.
     *
     * @return array{
     *     general_model: string,
     *     general_reasoning_effort: string,
     *     research_model: string,
     *     research_reasoning_effort: string
     * }
     */
    public static function resolvedDefaults(): array
    {
        $row = self::getSettings();
        $recommended = self::recommendedDefaults();

        $generalModel = self::safeModel((string) $row->general_model, $recommended['general_model']);
        $researchModel = self::safeModel((string) $row->research_model, $recommended['research_model'], requiresWebSearch: true);

        return [
            'general_model' => $generalModel,
            'general_reasoning_effort' => self::safeEffort(
                $generalModel,
                (string) $row->general_reasoning_effort,
                $recommended['general_reasoning_effort'],
            ),
            'research_model' => $researchModel,
            'research_reasoning_effort' => self::safeEffort(
                $researchModel,
                (string) $row->research_reasoning_effort,
                $recommended['research_reasoning_effort'],
            ),
        ];
    }

    /**
     * @return array{
     *     general_model: string,
     *     general_reasoning_effort: string,
     *     research_model: string,
     *     research_reasoning_effort: string
     * }
     */
    public static function recommendedDefaults(): array
    {
        return [
            'general_model' => GrowthAiModelCatalog::DEFAULT_GENERAL_MODEL,
            'general_reasoning_effort' => GrowthAiModelCatalog::DEFAULT_GENERAL_EFFORT,
            'research_model' => GrowthAiModelCatalog::DEFAULT_RESEARCH_MODEL,
            'research_reasoning_effort' => GrowthAiModelCatalog::DEFAULT_RESEARCH_EFFORT,
        ];
    }

    public static function updateDefaults(array $attributes, ?int $updatedBy = null): self
    {
        $row = self::query()->find(self::SINGLETON_ID) ?? new self(['id' => self::SINGLETON_ID]);
        $row->fill([
            'general_model' => $attributes['general_model'],
            'general_reasoning_effort' => $attributes['general_reasoning_effort'],
            'research_model' => $attributes['research_model'],
            'research_reasoning_effort' => $attributes['research_reasoning_effort'],
            'updated_by' => $updatedBy,
        ]);
        $row->id = self::SINGLETON_ID;
        $row->save();
        self::forgetSettingsCache();

        return $row;
    }

    private static function loadSettingsFromDatabase(): self
    {
        try {
            $row = self::query()->find(self::SINGLETON_ID);
            if ($row instanceof self) {
                return $row;
            }

            $recommended = self::recommendedDefaults();
            $row = new self([
                'id' => self::SINGLETON_ID,
                ...$recommended,
            ]);
            $row->id = self::SINGLETON_ID;
            $row->save();

            return $row->fresh() ?? $row;
        } catch (\Throwable) {
            return new self([
                'id' => self::SINGLETON_ID,
                ...self::recommendedDefaults(),
            ]);
        }
    }

    private static function safeModel(string $model, string $fallback, bool $requiresWebSearch = false): string
    {
        if (! GrowthAiModelCatalog::has($model) || ! GrowthAiModelCatalog::supportsStructuredOutput($model)) {
            return $fallback;
        }

        if ($requiresWebSearch && ! GrowthAiModelCatalog::supportsWebSearch($model)) {
            return $fallback;
        }

        return $model;
    }

    private static function safeEffort(string $model, string $effort, string $fallback): string
    {
        return GrowthAiModelCatalog::supportsEffort($model, $effort) ? $effort : $fallback;
    }
}
