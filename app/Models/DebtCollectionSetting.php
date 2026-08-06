<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DebtCollectionSetting extends Model
{
    public const SINGLETON_ID = 1;

    public const SETTINGS_CACHE_KEY = 'debt_collection_settings_singleton';

    public const SETTINGS_CACHE_TTL_SECONDS = 60;

    protected $table = 'debt_collection_settings';

    protected $fillable = [
        'contact_phone',
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

    public static function contactPhone(): ?string
    {
        $phone = trim((string) (self::getSettings()->contact_phone ?? ''));

        return $phone !== '' ? $phone : null;
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
                'contact_phone' => null,
                'updated_by' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $fallback = new self;
            $fallback->id = self::SINGLETON_ID;
            $fallback->contact_phone = null;
            $fallback->updated_by = null;

            return $fallback;
        }
    }
}
