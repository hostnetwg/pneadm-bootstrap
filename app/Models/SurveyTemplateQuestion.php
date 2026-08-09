<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateQuestion extends Model
{
    public const TYPES = [
        'rating',
        'text',
        'multiple_choice',
        'single_choice',
        'date',
        'time',
        'testimonial',
        'availability',
    ];

    protected $fillable = [
        'survey_template_id',
        'question_key',
        'question_text',
        'question_type',
        'question_order',
        'options',
        'is_required',
        'meta',
    ];

    protected $casts = [
        'options' => 'array',
        'meta' => 'array',
        'is_required' => 'boolean',
        'question_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }
}
