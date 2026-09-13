<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasFactory, SoftDeletes;

    public const TAX_EXEMPT = 'exempt';

    public const TAX_STANDARD = 'standard';

    public const ACCESS_UNLIMITED = 'unlimited';

    public const ACCESS_DURATION_FROM_GRANT = 'duration_from_grant';

    public const ACCESS_FIXED_UNTIL = 'fixed_until';

    public const ACCESS_DURATION_UNITS = ['days', 'months', 'years'];

    protected $fillable = [
        'product_offer_id',
        'name',
        'description',
        'is_active',
        'sort_order',
        'price',
        'currency',
        'tax_treatment',
        'tax_rate',
        'tax_exemption_basis',
        'is_promotion',
        'promotion_price',
        'promotion_starts_at',
        'promotion_ends_at',
        'show_promotion_countdown',
        'access_policy',
        'access_starts_at',
        'access_note',
        'access_duration_value',
        'access_duration_unit',
        'access_expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'is_promotion' => 'boolean',
        'promotion_price' => 'decimal:2',
        'promotion_starts_at' => 'immutable_datetime',
        'promotion_ends_at' => 'immutable_datetime',
        'show_promotion_countdown' => 'boolean',
        'access_starts_at' => 'immutable_datetime',
        'access_duration_value' => 'integer',
        'access_expires_at' => 'immutable_datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    public function isPromotionActive(?CarbonInterface $at = null): bool
    {
        if (! $this->is_promotion || $this->promotion_price === null) {
            return false;
        }

        $moment = CarbonImmutable::instance($at ?? now());

        if ($this->promotion_starts_at && $moment->lt($this->promotion_starts_at)) {
            return false;
        }

        if ($this->promotion_ends_at && $moment->gt($this->promotion_ends_at)) {
            return false;
        }

        return true;
    }

    public function omnibusLowestPrice(?CarbonInterface $at = null): ?string
    {
        return app(\App\Services\PriceOmnibusService::class)->lowestFor($this, $at);
    }

    public function currentPrice(?CarbonInterface $at = null): string
    {
        if ($this->isPromotionActive($at)) {
            return (string) $this->promotion_price;
        }

        return (string) $this->price;
    }

    public function promotionEndLabel(?CarbonInterface $at = null): ?string
    {
        if (! $this->isPromotionActive($at) || ! $this->promotion_ends_at) {
            return null;
        }

        return $this->promotion_ends_at->timezone('Europe/Warsaw')->format('d.m.Y H:i');
    }

    public function shouldShowPromotionCountdown(?CarbonInterface $at = null): bool
    {
        if (! $this->show_promotion_countdown || ! $this->isPromotionActive($at) || ! $this->promotion_ends_at) {
            return false;
        }

        return CarbonImmutable::instance($this->promotion_ends_at)->utc()
            ->gt(CarbonImmutable::instance($at ?? now('UTC'))->utc());
    }

    public function promotionCountdownTargetIso(): ?string
    {
        if (! $this->shouldShowPromotionCountdown()) {
            return null;
        }

        return $this->promotion_ends_at->utc()->toIso8601String();
    }

    public function accessExpiresAtFromGrant(CarbonInterface $grantedAt): ?CarbonImmutable
    {
        $grant = CarbonImmutable::instance($grantedAt)->utc();
        if ($this->access_starts_at) {
            $scheduled = CarbonImmutable::instance($this->access_starts_at)->utc();
            if ($scheduled->gt($grant)) {
                $grant = $scheduled;
            }
        }

        return match ($this->access_policy) {
            self::ACCESS_UNLIMITED => null,
            self::ACCESS_DURATION_FROM_GRANT => $this->addDuration($grant),
            self::ACCESS_FIXED_UNTIL => $this->access_expires_at
                ? CarbonImmutable::instance($this->access_expires_at)->utc()
                : null,
            default => null,
        };
    }

    public function accessLabel(): string
    {
        $start = $this->accessStartLabel();

        return match ($this->access_policy) {
            self::ACCESS_UNLIMITED => $start !== null
                ? 'Bezterminowy od '.$start
                : 'Bezterminowy',
            self::ACCESS_DURATION_FROM_GRANT => $this->durationLabel(),
            self::ACCESS_FIXED_UNTIL => $this->access_expires_at
                ? trim(($start !== null ? 'Od '.$start.' ' : '').'do '.$this->access_expires_at->timezone('Europe/Warsaw')->format('d.m.Y H:i'))
                : 'Stała data (nieustawiona)',
            default => 'Nieznana reguła',
        };
    }

    public function accessStartsInFuture(?CarbonInterface $at = null): bool
    {
        if (! $this->access_starts_at) {
            return false;
        }

        return CarbonImmutable::instance($this->access_starts_at)->utc()
            ->gt(CarbonImmutable::instance($at ?? now('UTC'))->utc());
    }

    public function accessStartLabel(): ?string
    {
        if (! $this->access_starts_at) {
            return null;
        }

        return $this->access_starts_at->timezone('Europe/Warsaw')->format('d.m.Y');
    }

    public function taxLabel(): string
    {
        if ($this->tax_treatment === self::TAX_EXEMPT) {
            return 'ZW';
        }

        return number_format(((float) $this->tax_rate) * 100, 2, ',', ' ').'%';
    }

    private function addDuration(CarbonImmutable $grant): ?CarbonImmutable
    {
        $value = (int) $this->access_duration_value;
        if ($value < 1) {
            return null;
        }

        return match ($this->access_duration_unit) {
            'days' => $grant->addDays($value),
            'months' => $grant->addMonthsNoOverflow($value),
            'years' => $grant->addYearsNoOverflow($value),
            default => null,
        };
    }

    private function durationLabel(): string
    {
        $value = (int) $this->access_duration_value;
        $unit = match ($this->access_duration_unit) {
            'days' => $value === 1 ? 'dzień' : 'dni',
            'months' => $value === 1 ? 'miesiąc' : 'mies.',
            'years' => $value === 1 ? 'rok' : 'lat',
            default => '',
        };

        $from = $this->accessStartLabel();

        return trim($value.' '.$unit.' od '.($from ?? 'nadania dostępu'));
    }
}
