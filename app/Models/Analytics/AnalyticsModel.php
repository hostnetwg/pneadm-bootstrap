<?php

namespace App\Models\Analytics;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

abstract class AnalyticsModel extends Model
{
    protected $connection = 'analytics';

    protected $guarded = [];

    /**
     * Kolumna TIMESTAMP/DATETIME w UTC w bazie → czas lokalny aplikacji (UI).
     */
    public function formatUtcDatetimeLocal(string $column, ?string $format = 'Y-m-d H:i:s'): ?string
    {
        $raw = $this->getRawOriginal($column);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return Carbon::parse($raw, 'UTC')
            ->timezone(config('app.timezone', 'Europe/Warsaw'))
            ->format($format);
    }
}
