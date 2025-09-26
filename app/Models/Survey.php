<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'title',
        'description',
        'imported_at',
        'imported_by',
        'source',
        'total_responses',
        'metadata'
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Relacja do kursu
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relacja do instruktora
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Relacja do użytkownika, który zaimportował ankietę
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Relacja do pytań ankiety
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('question_order');
    }

    /**
     * Relacja do odpowiedzi
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Pobiera średnią ocenę dla pytań ratingowych
     */
    public function getAverageRating(): float
    {
        $ratingQuestions = $this->questions()->where('question_type', 'rating')->get();
        
        if ($ratingQuestions->isEmpty()) {
            return 0;
        }

        $totalRating = 0;
        $totalResponses = 0;

        foreach ($ratingQuestions as $question) {
            $responses = $this->responses()
                ->get()
                ->pluck('response_data')
                ->map(function ($data) use ($question) {
                    return $data[$question->question_text] ?? null;
                })
                ->filter()
                ->map(function ($value) {
                    return is_numeric($value) ? (float) $value : 0;
                });

            $totalRating += $responses->sum();
            $totalResponses += $responses->count();
        }

        return $totalResponses > 0 ? round($totalRating / $totalResponses, 2) : 0;
    }

    /**
     * Pobiera statystyki odpowiedzi
     */
    public function getResponseStats(): array
    {
        $stats = [];
        
        foreach ($this->questions as $question) {
            $responses = $this->responses()
                ->get()
                ->pluck('response_data')
                ->map(function ($data) use ($question) {
                    return $data[$question->question_text] ?? null;
                })
                ->filter();

            $stats[$question->question_text] = [
                'type' => $question->question_type,
                'total_responses' => $responses->count(),
                'responses' => $responses->values()->toArray()
            ];
        }

        return $stats;
    }

    /**
     * Grupuje pytania w siatki i zwraca jako kolekcję grup
     */
    public function getGroupedQuestions(): \Illuminate\Support\Collection
    {
        $questions = $this->questions()->orderBy('question_order')->get();
        $grouped = collect();
        $processed = collect();

        foreach ($questions as $question) {
            // Jeśli pytanie już zostało przetworzone, pomiń
            if ($processed->contains($question->id)) {
                continue;
            }

            if ($question->isPartOfGrid()) {
                // Pobierz całą grupę siatki
                $gridGroup = $question->getGridGroup();
                $grouped->push([
                    'type' => 'grid',
                    'main_text' => $question->getGridMainText(),
                    'question_type' => $question->question_type,
                    'questions' => $gridGroup,
                    'is_rating_grid' => $question->isRating(),
                    'is_choice_grid' => $question->isMultipleChoice() || $question->isSingleChoice()
                ]);
                
                // Oznacz wszystkie pytania z grupy jako przetworzone
                $processed = $processed->merge($gridGroup->pluck('id'));
            } else {
                // Pojedyncze pytanie
                $grouped->push([
                    'type' => 'single',
                    'question' => $question
                ]);
                $processed->push($question->id);
            }
        }

        return $grouped;
    }

    /**
     * Zwraca rzeczywistą liczbę pytań (grup pytań)
     */
    public function getActualQuestionsCount(): int
    {
        return $this->getGroupedQuestions()->count();
    }
}
