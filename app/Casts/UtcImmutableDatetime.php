<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * TIMESTAMP/DATETIME trzymany w UTC (sesja MySQL +00:00).
 * Cast Laravel `immutable_datetime` czyta surowy string w strefie aplikacji,
 * więc godzina UTC była pokazywana jak czas polski.
 *
 * @implements CastsAttributes<CarbonImmutable|null, CarbonInterface|string|null>
 */
class UtcImmutableDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->utc()->format('Y-m-d H:i:s');
        }

        return CarbonImmutable::parse((string) $value, 'UTC')->format('Y-m-d H:i:s');
    }
}
