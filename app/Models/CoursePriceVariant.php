<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;
use Carbon\Carbon;

class CoursePriceVariant extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'is_active',
        'price',
        'is_promotion',
        'promotion_price',
        'promotion_type',
        'promotion_start',
        'promotion_end',
        'access_type',
        'access_start_datetime',
        'access_end_datetime',
        'access_duration_value',
        'access_duration_unit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'is_promotion' => 'boolean',
        'promotion_price' => 'decimal:2',
        'promotion_start' => 'datetime',
        'promotion_end' => 'datetime',
        'access_start_datetime' => 'datetime',
        'access_end_datetime' => 'datetime',
        'access_duration_value' => 'integer',
    ];

    /**
     * Relacja do kursu
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Sprawdza czy promocja jest aktywna
     */
    public function isPromotionActive(): bool
    {
        if (!$this->is_promotion || $this->promotion_type === 'disabled') {
            return false;
        }

        if ($this->promotion_type === 'unlimited') {
            return true;
        }

        if ($this->promotion_type === 'time_limited') {
            $now = now();
            return $this->promotion_start <= $now && $now <= $this->promotion_end;
        }

        return false;
    }

    /**
     * Zwraca aktualną cenę (promocyjną jeśli aktywna, w przeciwnym razie podstawową)
     */
    public function getCurrentPrice(): float
    {
        if ($this->isPromotionActive() && $this->promotion_price !== null) {
            return (float) $this->promotion_price;
        }
        return (float) $this->price;
    }

    /**
     * Oblicza datę końca dostępu dla typu 5 (określony czas od daty)
     */
    public function calculateAccessEndDate(): ?Carbon
    {
        if ($this->access_type !== '5' || !$this->access_start_datetime || !$this->access_duration_value || !$this->access_duration_unit) {
            return null;
        }

        $start = Carbon::parse($this->access_start_datetime);
        
        return match($this->access_duration_unit) {
            'hours' => $start->copy()->addHours($this->access_duration_value),
            'days' => $start->copy()->addDays($this->access_duration_value),
            'months' => $start->copy()->addMonths($this->access_duration_value),
            'years' => $start->copy()->addYears($this->access_duration_value),
            default => null,
        };
    }

    /**
     * Sprawdza czy dostęp jest teraz dostępny
     */
    public function isAccessAvailable(): bool
    {
        $now = now();

        return match($this->access_type) {
            '1' => true, // Bezterminowy, natychmiastowy
            '2' => $this->access_start_datetime <= $now && ($this->access_end_datetime === null || $now <= $this->access_end_datetime),
            '3' => true, // Określony czas, natychmiastowy (czas liczy się od momentu zakupu)
            '4' => $this->access_start_datetime <= $now && $now <= $this->access_end_datetime,
            '5' => $this->access_start_datetime <= $now && ($this->calculateAccessEndDate() === null || $now <= $this->calculateAccessEndDate()),
            default => false,
        };
    }

    /**
     * Pobiera nazwę typu dostępu
     */
    public function getAccessTypeName(): string
    {
        return match($this->access_type) {
            '1' => 'Bezterminowy, z natychmiastowym dostępem',
            '2' => 'Bezterminowy, od określonej daty',
            '3' => 'Przez określony czas, z natychmiastowym dostępem',
            '4' => 'Od określonej daty, z ustaloną datą końca',
            '5' => 'Przez określony czas, od określonej daty',
            default => 'Nieznany typ',
        };
    }

    /**
     * Pobiera nazwę typu promocji
     */
    public function getPromotionTypeName(): string
    {
        return match($this->promotion_type) {
            'disabled' => 'Wyłączona',
            'unlimited' => 'Bez ram czasowych',
            'time_limited' => 'Ograniczona czasowo',
            default => 'Nieznany typ',
        };
    }
}
