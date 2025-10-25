<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormOrderParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'form_order_participants';

    protected $fillable = [
        'form_order_id',
        'participant_firstname',
        'participant_lastname',
        'participant_email',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacja do zamówienia
     */
    public function formOrder()
    {
        return $this->belongsTo(FormOrder::class, 'form_order_id');
    }

    /**
     * Accessor - pełne imię i nazwisko
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->participant_firstname} {$this->participant_lastname}");
    }

    /**
     * Accessor - nazwisko i imię (format formalny)
     */
    public function getFormalNameAttribute()
    {
        return trim("{$this->participant_lastname} {$this->participant_firstname}");
    }

    /**
     * Accessor - inicjały
     */
    public function getInitialsAttribute()
    {
        $f = mb_substr($this->participant_firstname, 0, 1);
        $l = mb_substr($this->participant_lastname, 0, 1);
        return mb_strtoupper("{$f}.{$l}.");
    }
}
