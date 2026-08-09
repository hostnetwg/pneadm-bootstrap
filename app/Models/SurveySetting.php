<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton ustawień ankiet (id = 1).
 */
class SurveySetting extends Model
{
    public const SINGLETON_ID = 1;

    public const SETTINGS_CACHE_KEY = 'survey_settings_singleton';

    public const SETTINGS_CACHE_TTL_SECONDS = 300;

    public const OPEN_MODE_MANUAL = 'manual';

    public const OPEN_MODE_AUTO = 'auto';

    public const CHANNEL_NATIVE = 'native';

    public const CHANNEL_EXTERNAL = 'external';

    protected $table = 'survey_settings';

    protected $fillable = [
        'open_mode',
        'auto_open_offset_hours',
        'auto_close_after_days',
        'default_channel',
        'default_is_anonymous',
        'default_template_id',
    ];

    protected $casts = [
        'auto_open_offset_hours' => 'integer',
        'auto_close_after_days' => 'integer',
        'default_is_anonymous' => 'boolean',
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
            function () {
                try {
                    $row = self::query()->find(self::SINGLETON_ID);
                    if ($row) {
                        return $row;
                    }

                    return self::query()->create([
                        'id' => self::SINGLETON_ID,
                        'open_mode' => self::OPEN_MODE_MANUAL,
                        'auto_open_offset_hours' => 0,
                        'auto_close_after_days' => 14,
                        'default_channel' => self::CHANNEL_NATIVE,
                        'default_is_anonymous' => true,
                        'default_template_id' => SurveyTemplate::query()->where('is_default', true)->value('id'),
                    ]);
                } catch (\Throwable) {
                    $fallback = new self([
                        'open_mode' => self::OPEN_MODE_MANUAL,
                        'auto_open_offset_hours' => 0,
                        'auto_close_after_days' => 14,
                        'default_channel' => self::CHANNEL_NATIVE,
                        'default_is_anonymous' => true,
                        'default_template_id' => null,
                    ]);
                    $fallback->id = self::SINGLETON_ID;

                    return $fallback;
                }
            }
        );
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'default_template_id');
    }

    public function isAutoOpenMode(): bool
    {
        return $this->open_mode === self::OPEN_MODE_AUTO;
    }
}
