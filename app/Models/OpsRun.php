<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsRun extends Model
{
    public const TYPE_KSEF_BACKGROUND = 'ksef_background';

    public const TYPE_ACCESS_EXPIRY_REMINDERS = 'access_expiry_reminders';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'status',
        'title',
        'period_date',
        'actor_user_id',
        'summary',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpsRunItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return array{label: string, badge_class: string}
     */
    public static function typeMeta(string $type): array
    {
        return match ($type) {
            self::TYPE_KSEF_BACKGROUND => [
                'label' => 'KSeF (w tle)',
                'badge_class' => 'bg-danger',
            ],
            self::TYPE_ACCESS_EXPIRY_REMINDERS => [
                'label' => 'Przypomnienia o wygaśnięciu dostępu',
                'badge_class' => 'bg-warning text-dark',
            ],
            default => [
                'label' => $type,
                'badge_class' => 'bg-secondary',
            ],
        };
    }

    /**
     * @return array{label: string, badge_class: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            self::STATUS_RUNNING => [
                'label' => 'w toku',
                'badge_class' => 'bg-info text-dark',
            ],
            self::STATUS_SUCCESS => [
                'label' => 'OK',
                'badge_class' => 'bg-success',
            ],
            self::STATUS_PARTIAL => [
                'label' => 'częściowo',
                'badge_class' => 'bg-warning text-dark',
            ],
            self::STATUS_FAILED => [
                'label' => 'błąd',
                'badge_class' => 'bg-danger',
            ],
            default => [
                'label' => $status,
                'badge_class' => 'bg-secondary',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function knownTypes(): array
    {
        return [
            self::TYPE_KSEF_BACKGROUND,
            self::TYPE_ACCESS_EXPIRY_REMINDERS,
        ];
    }
}
