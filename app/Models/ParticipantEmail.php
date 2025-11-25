<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParticipantEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'first_participant_id',
        'participants_count',
        'is_verified',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'participants_count' => 'integer',
    ];

    /**
     * Relacja do pierwszego uczestnika z tym emailem
     */
    public function firstParticipant()
    {
        return $this->belongsTo(Participant::class, 'first_participant_id');
    }

    /**
     * Relacja do wszystkich uczestników z tym emailem
     */
    public function participants()
    {
        return $this->hasMany(Participant::class, 'email', 'email');
    }
}



