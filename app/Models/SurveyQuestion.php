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
     * Aktualizuje typ pytania na podstawie analizy odpowiedzi
     */
    public function updateQuestionTypeFromResponses(): void
    {
        $responses = $this->getResponses();
        if ($responses->isEmpty()) {
            return;
        }

        $uniqueAnswers = $responses->unique()->filter()->values();
        
        // Sprawdź czy to może być single_choice
        if ($uniqueAnswers->count() <= 15 && $uniqueAnswers->count() >= 2) {
            // Sprawdź wzorce opcji wyboru
            $choicePatterns = [
                '/^(tak|nie)$/i',
                '/^(bardzo dobry|dobry|średni|słaby|bardzo słaby)$/i',
                '/^(zdecydowanie tak|raczej tak|nie mam zdania|raczej nie|zdecydowanie nie)$/i',
                '/^(zawsze|często|czasami|rzadko|nigdy)$/i',
                '/^(bardzo zadowolony|zadowolony|neutralny|niezadowolony|bardzo niezadowolony)$/i',
                '/^(portal|facebook|instagram|linkedin|twitter|youtube|tiktok)$/i',
                '/^(dyrekcja|szkoła|nauczyciel|kolega|koleżanka|przełożony|szef)$/i',
                '/^(e-mail|mail|wiadomość|newsletter|strona internetowa|www)$/i',
                '/^(radio|telewizja|gazeta|czasopismo|ulotka|plakat)$/i',
                '/^(inne|inny|inna|inne źródło|inne miejsce)$/i'
            ];
            
            $matchesChoicePattern = false;
            foreach ($choicePatterns as $pattern) {
                $matches = 0;
                foreach ($uniqueAnswers as $answer) {
                    if (preg_match($pattern, trim($answer))) {
                        $matches++;
                    }
                }
                if ($matches >= $uniqueAnswers->count() * 0.6) {
                    $matchesChoicePattern = true;
                    break;
                }
            }
            
            // Sprawdź charakterystyki odpowiedzi
            if (!$matchesChoicePattern) {
                $shortAnswers = $uniqueAnswers->filter(function($answer) {
                    return strlen(trim($answer)) <= 50;
                });
                
                $singleWordAnswers = $uniqueAnswers->filter(function($answer) {
                    return str_word_count(trim($answer)) <= 3;
                });
                
                $choiceKeywords = ['portal', 'facebook', 'instagram', 'dyrekcja', 'szkoła', 'e-mail', 'mail', 'wiadomość', 'radio', 'telewizja', 'gazeta', 'ulotka', 'inne', 'inny', 'inna'];
                $hasChoiceKeywords = $uniqueAnswers->filter(function($answer) use ($choiceKeywords) {
                    foreach ($choiceKeywords as $keyword) {
                        if (stripos($answer, $keyword) !== false) {
                            return true;
                        }
                    }
                    return false;
                })->count() > 0;
                
                if ($shortAnswers->count() >= $uniqueAnswers->count() * 0.7 && 
                    $singleWordAnswers->count() >= $uniqueAnswers->count() * 0.5 && 
                    ($uniqueAnswers->count() <= 10 || $hasChoiceKeywords)) {
                    $matchesChoicePattern = true;
                }
            }
            
            if ($matchesChoicePattern && $this->question_type !== 'single_choice') {
                $this->update(['question_type' => 'single_choice']);
            }
        }
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
        
        // Sprawdź czy to pytanie o dni tygodnia (specjalny przypadek)
        if (strpos($text, 'Które dni tygodnia') !== false && strpos($text, 'godziny rozpoczęcia') !== false) {
            return true;
        }
        
        // Sprawdź czy to pytanie o dni tygodnia (inne warianty)
        if (strpos($text, 'dni tygodnia') !== false && strpos($text, 'godziny') !== false) {
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

    /**
     * Pobierz numer pytania z tekstu
     */
    public function getQuestionNumber(): ?string
    {
        // Sprawdź czy tekst pytania zaczyna się od numeru
        if (preg_match('/^(\d+)\.\s*(.+)$/', $this->question_text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Pobierz tekst pytania bez numeru
     */
    public function getQuestionTextWithoutNumber(): string
    {
        // Sprawdź czy tekst pytania zaczyna się od numeru
        if (preg_match('/^\d+\.\s*(.+)$/', $this->question_text, $matches)) {
            return $matches[1];
        }
        return $this->question_text;
    }
}
