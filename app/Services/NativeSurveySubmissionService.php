<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyTestimonial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zapis odpowiedzi z natywnego formularza (pnedu / adm).
 */
class NativeSurveySubmissionService
{
    /**
     * @param  array<string, mixed>  $answers  keyed by question id or question_text
     * @param  array<string, mixed>  $testimonialPayload
     * @param  array{respondent_id?: ?string, participant_id?: ?int, respondent_email?: ?string}  $identity
     */
    public function submit(Survey $survey, array $answers, array $testimonialPayload = [], array $identity = []): SurveyResponse
    {
        $survey->loadMissing('questions');

        $responseData = [];
        $errors = [];

        foreach ($survey->questions as $question) {
            $raw = $answers[(string) $question->id] ?? $answers[$question->question_text] ?? null;

            if ($question->question_type === 'testimonial') {
                continue;
            }

            $normalized = $this->normalizeAnswer($question, $raw);

            if ($normalized === null || $normalized === '' || $normalized === []) {
                // Wymagane: rating i single_choice traktujemy jako obowiązkowe gdy typ rating
                if (in_array($question->question_type, ['rating'], true)) {
                    $errors['q_'.$question->id] = 'To pytanie jest wymagane.';
                }

                continue;
            }

            $responseData[$question->question_text] = $normalized;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($survey, $responseData, $testimonialPayload, $identity) {
            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'response_data' => $responseData,
                'submitted_at' => now(),
                'respondent_id' => $identity['respondent_id'] ?? null,
                'participant_id' => $identity['participant_id'] ?? null,
                'respondent_email' => $identity['respondent_email'] ?? null,
            ]);

            $survey->increment('total_responses');

            $this->storeTestimonialIfPresent($survey, $response, $testimonialPayload);

            return $response;
        });
    }

    private function normalizeAnswer(SurveyQuestion $question, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($question->question_type) {
            'rating' => is_numeric($raw) ? (int) $raw : null,
            'multiple_choice', 'availability' => array_values(array_filter((array) $raw, fn ($v) => filled($v))),
            'single_choice', 'text', 'date', 'time' => is_string($raw) ? trim($raw) : $raw,
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeTestimonialIfPresent(Survey $survey, SurveyResponse $response, array $payload): void
    {
        $quote = trim((string) ($payload['quote'] ?? ''));
        $name = trim((string) ($payload['author_name'] ?? ''));
        $consent = filter_var($payload['publish_consent'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($quote === '' || $name === '') {
            return;
        }

        $rating = isset($payload['rating']) && is_numeric($payload['rating'])
            ? (int) $payload['rating']
            : null;

        SurveyTestimonial::create([
            'survey_id' => $survey->id,
            'survey_response_id' => $response->id,
            'course_id' => $survey->course_id,
            'author_name' => $name,
            'author_role' => trim((string) ($payload['author_role'] ?? '')) ?: null,
            'author_city' => trim((string) ($payload['author_city'] ?? '')) ?: null,
            'avatar_type' => $payload['avatar_type'] ?? 'preset',
            'avatar_preset' => $payload['avatar_preset'] ?? 'woman-straight-brown',
            'avatar_path' => $payload['avatar_path'] ?? null,
            'quote' => $quote,
            'rating' => $rating,
            'publish_consent' => $consent,
            'is_published' => false,
            'display_order' => 100,
        ]);
    }
}
