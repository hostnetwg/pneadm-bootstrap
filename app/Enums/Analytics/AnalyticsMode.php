<?php

namespace App\Enums\Analytics;

enum AnalyticsMode: string
{
    case Full = 'full';
    case Standard = 'standard';
    case Light = 'light';
    case AggregateOnly = 'aggregate_only';
    case Off = 'off';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Standard;
    }
}
