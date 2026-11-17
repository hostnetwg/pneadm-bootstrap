<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Własna treść promptu systemowego Growth OS. Brak wiersza oznacza wersję z kodu.
 */
class GrowthAiPrompt extends Model
{
    protected $fillable = [
        'prompt_key',
        'body',
        'updated_by',
    ];

    protected $casts = [
        'updated_by' => 'integer',
    ];
}
