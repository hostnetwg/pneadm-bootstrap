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

    /**
     * Sprawdź czy pytanie jest częścią siatki
     */
    public function isPartOfGrid(): bool
    {
        // Sprawdź czy tekst pytania zawiera wzorce siatki
        $text = $this->question_text;
        
        // Wzorce dla siatek ratingowych (1. Pytanie [tekst])
        if (preg_match('/^\d+\.\s+Pytanie\s+\[.*\]$/', $text)) {
            return true;
        }
        
        // Wzorce dla siatek wielokrotnego wyboru (tekst [opcja])
        if (preg_match('/^(.+?)\s+\[([^\]]+)\]$/', $text)) {
            return true;
        }
        
        return false;
    }

    /**
     * Pobierz główny tekst siatki (bez numeru i opcji)
     */
    public function getGridMainText(): string
    {
        $text = $this->question_text;
        
        // Dla siatek ratingowych: "1. Pytanie [tekst]" -> "Pytanie"
        if (preg_match('/^\d+\.\s+(.+?)\s+\[.*\]$/', $text, $matches)) {
            return $matches[1];
        }
        
        // Dla siatek wielokrotnego wyboru: "tekst [opcja]" -> "tekst"
        if (preg_match('/^(.+?)\s+\[([^\]]+)\]$/', $text, $matches)) {
            return trim($matches[1]);
        }
        
        return $text;
    }

    /**
     * Pobierz opcję z pytania siatki
     */
    public function getGridOption(): string
    {
        $text = $this->question_text;
        
        // Dla siatek ratingowych: "1. Pytanie [tekst]" -> "tekst"
        if (preg_match('/^\d+\.\s+.+?\s+\[(.+)\]$/', $text, $matches)) {
            return $matches[1];
        }
        
        // Dla siatek wielokrotnego wyboru: "tekst [opcja]" -> "opcja"
        if (preg_match('/^.+?\s+\[([^\]]+)\]$/', $text, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    /**
     * Pobierz grupę pytań siatki dla tego pytania
     */
    public function getGridGroup(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->isPartOfGrid()) {
            return collect([$this]);
        }

        $mainText = $this->getGridMainText();
        
        return $this->survey->questions()
            ->where('question_text', 'like', '%' . $mainText . '%')
            ->orderBy('question_order')
            ->get();
    }
}
