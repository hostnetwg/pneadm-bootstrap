<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'response_data',
        'submitted_at',
        'respondent_id',
        'participant_id',
        'respondent_email',
    ];

    protected $casts = [
        'response_data' => 'array',
        'submitted_at' => 'datetime',
    ];

    /**
     * Relacja do ankiety
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * Pobiera odpowiedź na konkretne pytanie
     */
    public function getAnswerForQuestion(string $questionText)
    {
        return $this->response_data[$questionText] ?? null;
    }

    /**
     * Formatuje odpowiedź do wyświetlenia w UI (tablice → tekst).
     */
    public function formatAnswerForDisplay(string $questionText, ?int $limit = null): string
    {
        $answer = $this->getAnswerForQuestion($questionText);

        if ($answer === null || $answer === '') {
            return '—';
        }

        if (is_array($answer)) {
            $answer = collect($answer)
                ->flatten()
                ->filter(fn ($v) => filled($v))
                ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))
                ->implode(', ');
        } elseif (! is_scalar($answer)) {
            $answer = json_encode($answer, JSON_UNESCAPED_UNICODE) ?: '—';
        } else {
            $answer = (string) $answer;
        }

        if ($answer === '') {
            return '—';
        }

        return $limit !== null ? \Illuminate\Support\Str::limit($answer, $limit) : $answer;
    }

    /**
     * Sprawdza czy odpowiedź zawiera konkretną wartość
     */
    public function hasAnswer(string $questionText, $value): bool
    {
        $answer = $this->getAnswerForQuestion($questionText);

        if (is_array($answer)) {
            return in_array($value, $answer);
        }

        return $answer === $value;
    }

    /**
     * Pobiera wszystkie odpowiedzi ratingowe
     */
    public function getRatingAnswers(): array
    {
        $ratingAnswers = [];

        foreach ($this->survey->questions as $question) {
            if ($question->isRating()) {
                $answer = $this->getAnswerForQuestion($question->question_text);
                if (is_numeric($answer)) {
                    $ratingAnswers[$question->question_text] = (int) $answer;
                }
            }
        }

        return $ratingAnswers;
    }

    /**
     * Pobiera średnią ocenę z tej odpowiedzi
     */
    public function getAverageRating(): float
    {
        $ratingAnswers = $this->getRatingAnswers();

        if (empty($ratingAnswers)) {
            return 0;
        }

        return round(array_sum($ratingAnswers) / count($ratingAnswers), 2);
    }
}
