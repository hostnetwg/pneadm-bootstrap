<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\ConceptRevisionResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConceptRevisionTask
{
    public const TYPE = 'concept_revision';

    /** @var list<string> */
    private const FIELDS = [
        'title',
        'subtitle',
        'promise',
        'audience',
        'main_points',
        'agenda',
        'cta',
        'additional_material',
    ];

    public function promptVersion(): string
    {
        return (string) config('growth_ai.prompt_version');
    }

    public function schemaVersion(): string
    {
        return (string) config('growth_ai.schema_version');
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem koncepcji webinarów edukacyjnych PNE. Zmień koncepcję zgodnie z instrukcją użytkownika.
Pisz wyłącznie po polsku, profesjonalnie, jasno i praktycznie dla polskiej oświaty.
Zachowaj sens i fakty zawarte w wejściu. Nie wymyślaj wyników badań, statystyk, funkcji produktów ani faktów wymagających researchu.
Zwróć wyłącznie dane zgodne z przekazanym schematem. W changed_fields wskaż pola, których treść faktycznie zmieniasz.
Jeżeli pole nie wymaga zmiany, przepisz jego bieżącą wartość bez zmian.
PROMPT;
    }

    /**
     * Build a strict allow-list projection. No other ADM data can enter the provider request.
     *
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    public function input(array $concept, string $audience, string $userInstruction): array
    {
        $userInstruction = trim($userInstruction);
        if ($userInstruction === '') {
            throw new GrowthAiException(
                errorType: 'invalid_instruction',
                userMessage: 'Instrukcja dla AI jest pusta. Możesz kontynuować ręcznie.',
            );
        }

        $input = [
            'current_concept' => [
                'title' => $this->string($concept['title'] ?? ''),
                'subtitle' => $this->string($concept['subtitle'] ?? ''),
                'promise' => $this->string($concept['promise'] ?? ''),
                'audience' => trim($audience),
                'main_points' => $this->stringList($concept['points'] ?? []),
                'agenda' => $this->string($concept['plan'] ?? ''),
                'cta' => $this->string($concept['cta'] ?? ''),
                'additional_material' => $this->string($concept['lead_magnet'] ?? ''),
            ],
            'user_instruction' => $userInstruction,
        ];

        $encoded = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (mb_strlen($encoded) > (int) config('growth_ai.limits.max_input_chars')) {
            throw new GrowthAiException(
                errorType: 'input_too_long',
                userMessage: 'Koncepcja jest zbyt długa do bezpiecznego wysłania do AI. Możesz kontynuować ręcznie.',
            );
        }

        if ($this->containsProhibitedData($encoded)) {
            throw GrowthAiException::dataPolicyViolation();
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => $string,
                'subtitle' => $string,
                'promise' => $string,
                'audience' => $string,
                'main_points' => [
                    'type' => 'array',
                    'items' => $string,
                ],
                'agenda' => $string,
                'cta' => $string,
                'additional_material' => $string,
                'changed_fields' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => self::FIELDS,
                    ],
                ],
                'change_summary' => $string,
            ],
            'required' => [
                ...self::FIELDS,
                'changed_fields',
                'change_summary',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validateAndNormalize(AiProviderResponse $response, array $input): ConceptRevisionResult
    {
        $validator = Validator::make($response->payload, [
            'title' => ['required', 'string', 'max:300'],
            'subtitle' => ['required', 'string', 'max:500'],
            'promise' => ['required', 'string', 'max:1500'],
            'audience' => ['required', 'string', 'max:1000'],
            'main_points' => ['required', 'array', 'min:1', 'max:12'],
            'main_points.*' => ['required', 'string', 'max:700'],
            'agenda' => ['required', 'string', 'max:2500'],
            'cta' => ['required', 'string', 'max:1000'],
            'additional_material' => ['required', 'string', 'max:1000'],
            'changed_fields' => ['required', 'array', 'max:8'],
            'changed_fields.*' => ['required', 'string', 'distinct', Rule::in(self::FIELDS)],
            'change_summary' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw GrowthAiException::invalidResponse();
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();
        $normalized = [
            'title' => trim((string) $validated['title']),
            'subtitle' => trim((string) $validated['subtitle']),
            'promise' => trim((string) $validated['promise']),
            'audience' => trim((string) $validated['audience']),
            'main_points' => $this->stringList($validated['main_points']),
            'agenda' => trim((string) $validated['agenda']),
            'cta' => trim((string) $validated['cta']),
            'additional_material' => trim((string) $validated['additional_material']),
        ];
        $changeSummary = trim((string) $validated['change_summary']);

        $requiredTextFields = array_diff(self::FIELDS, ['main_points']);
        foreach ($requiredTextFields as $field) {
            if ($normalized[$field] === '') {
                throw GrowthAiException::invalidResponse('empty_required_field');
            }
        }

        if ($normalized['main_points'] === [] || in_array('', $normalized['main_points'], true) || $changeSummary === '') {
            throw GrowthAiException::invalidResponse('empty_required_field');
        }

        if ($this->containsProhibitedData(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw GrowthAiException::invalidResponse('prohibited_output_data');
        }

        /** @var array<string, mixed> $current */
        $current = $input['current_concept'];
        $changedFields = array_values(array_filter(
            self::FIELDS,
            fn (string $field): bool => $normalized[$field] !== $current[$field],
        ));

        if ($changedFields === []) {
            throw GrowthAiException::invalidResponse('empty_revision');
        }

        return new ConceptRevisionResult(
            concept: [
                'title' => $normalized['title'],
                'subtitle' => $normalized['subtitle'],
                'promise' => $normalized['promise'],
                'audience' => $normalized['audience'],
                'points' => $normalized['main_points'],
                'plan' => $normalized['agenda'],
                'cta' => $normalized['cta'],
                'lead_magnet' => $normalized['additional_material'],
            ],
            changedFields: $changedFields,
            changeSummary: $changeSummary,
            provider: $response->provider,
            model: $response->model,
            promptVersion: $this->promptVersion(),
            schemaVersion: $this->schemaVersion(),
            requestId: $response->requestId,
        );
    }

    private function containsProhibitedData(string $value): bool
    {
        return preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', $value) === 1
            || preg_match('/(?<!\d)(?:\+?48[\s.-]?)?(?:\d[\s.-]?){9}(?!\d)/u', $value) === 1
            || preg_match('/\b(?:api[_ -]?key|bearer\s+[a-z0-9._-]+|hasło|password|token\s*[:=])\b/iu', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        ));
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }
}
