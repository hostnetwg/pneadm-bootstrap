<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Traits\LogsActivity;

class Participant extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'course_id',
        'order',        
        'first_name',
        'last_name',
        'email',
        'birth_date',
        'birth_place',
        'access_expires_at'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'access_expires_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'participant_id');
    }

    /**
     * Sprawdź czy dostęp wygasł
     */
    public function hasExpiredAccess(): bool
    {
        if (!$this->access_expires_at) {
            return false; // Bezterminowy dostęp
        }
        
        // Porównujemy w UTC - czas w bazie jest zawsze w UTC
        $now = Carbon::now('UTC');
        $expiresAt = $this->access_expires_at->setTimezone('UTC');
        
        return $expiresAt->lt($now); // lt = less than (mniejsze niż)
    }

    /**
     * Sprawdź czy dostęp jest aktywny
     */
    public function hasActiveAccess(): bool
    {
        return !$this->hasExpiredAccess();
    }

    /**
     * Sprawdź czy ma ograniczony czas dostępu
     */
    public function hasLimitedAccess(): bool
    {
        return $this->access_expires_at !== null;
    }

    /**
     * Pobierz pozostały czas dostępu
     */
    public function getRemainingAccessTime(): ?string
    {
        if (!$this->access_expires_at) {
            return null; // Bezterminowy dostęp
        }

        if ($this->hasExpiredAccess()) {
            return 'Wygasł';
        }

        return $this->access_expires_at->diffForHumans();
    }

    /**
     * Debug - pobierz informacje o czasie dostępu
     */
    public function getAccessDebugInfo(): array
    {
        if (!$this->access_expires_at) {
            return [
                'has_expires' => false,
                'expires_at' => null,
                'now' => now()->format('Y-m-d H:i:s'),
                'is_past' => false,
                'has_expired' => false
            ];
        }

        return [
            'has_expires' => true,
            'expires_at' => $this->access_expires_at->format('Y-m-d H:i:s'),
            'now' => now()->format('Y-m-d H:i:s'),
            'is_past' => $this->access_expires_at->isPast(),
            'has_expired' => $this->hasExpiredAccess()
        ];
    }
    
}
