<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $table = 'instructors';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'bio',
        'photo',
        'is_active',
    ];

    // Pełne imię i nazwisko
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
