<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'question_text',
        'question_type',
        'question_order',
        'options'
    ];

    protected $casts = [
        'options' => 'array'
    ];

    /**
     * Relacja do ankiety
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * Sprawdza czy pytanie jest typu rating
     */
    public function isRating(): bool
    {
        return $this->question_type === 'rating';
    }

    /**
     * Sprawdza czy pytanie jest typu tekst
     */
    public function isText(): bool
    {
        return $this->question_type === 'text';
    }

    /**
     * Sprawdza czy pytanie jest typu wielokrotnego wyboru
     */
    public function isMultipleChoice(): bool
    {
        return $this->question_type === 'multiple_choice';
    }

    /**
     * Sprawdza czy pytanie jest typu jednokrotnego wyboru
     */
    public function isSingleChoice(): bool
    {
        return $this->question_type === 'single_choice';
    }

    /**
     * Pobiera odpowiedzi dla tego pytania
     */
    public function getResponses()
    {
        return $this->survey->responses()
            ->get()
            ->pluck('response_data')
            ->map(function ($data) {
                return $data[$this->question_text] ?? null;
            })
            ->filter();
    }

    /**
     * Pobiera statystyki dla pytania ratingowego
     */
    public function getRatingStats(): array
    {
        if (!$this->isRating()) {
            return [];
        }

        $responses = $this->getResponses()
            ->map(function ($value) {
                return is_numeric($value) ? (int) $value : 0;
            })
            ->filter(function ($value) {
                return $value >= 1 && $value <= 5;
            });

        if ($responses->isEmpty()) {
            return [
                'average' => 0,
                'count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ];
        }

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($responses as $rating) {
            $distribution[$rating]++;
        }

        return [
            'average' => round($responses->avg(), 2),
            'count' => $responses->count(),
            'distribution' => $distribution
        ];
    }
}
