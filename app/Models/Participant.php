<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

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
        
        return $this->access_expires_at->isPast();
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

        if ($this->access_expires_at->isPast()) {
            return 'Wygasł';
        }

        return $this->access_expires_at->diffForHumans();
    }
    
}
