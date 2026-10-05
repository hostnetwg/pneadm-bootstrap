<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\ConceptRevisionResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\AddressFormPolicy;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use App\Support\GrowthOS\AiListFormatter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConceptRevisionTask implements GrowthAiTask
{
    public const TYPE = 'concept_revision';

    public function __construct(private readonly bool $fromDirection = false) {}

    public function draftingFromDirection(): self
    {
        return new self(true);
    }

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

    public function type(): string
    {
        return self::TYPE;
    }

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
        $mode = $this->fromDirection
            ? <<<'TEXT'
TRYB: ułóż pierwszą koncepcję na podstawie direction. current_concept może mieć tylko tytuł równy tematowi, a pozostałe pola puste. Uzupełnij wszystkie pola koncepcji. Tytuł może być jaśniejszą wersją tematu, ale nie zmieniaj sensu kierunku i nie wymyślaj innego webinaru. Nie zostawiaj pustych pól. Nie dopisuj faktów, których nie ma w direction.
TEXT
            : <<<'TEXT'
TRYB: zmień istniejącą koncepcję zgodnie z instrukcją. Blok direction jest granicą sensu: nie zaprzeczaj polom topic, audience, problem, takeaway ani sell_later, jeśli są wypełnione. Jeżeli zdanie w current_concept im przeczy, popraw to zdanie. Nie układaj koncepcji od nowa i nie wymyślaj innego webinaru. Puste pola direction nie są powodem, żeby coś dopisywać. Jeżeli pole nie wymaga zmiany ani takiej korekty, przepisz jego bieżącą wartość bez zmian. Ujednolić formę zwrotu w polach tekstowych do style.address_form.
TEXT;

        $base = <<<PROMPT
Jesteś redaktorem koncepcji webinarów edukacyjnych PNE.
Pisz wyłącznie po polsku, profesjonalnie, jasno i praktycznie dla polskiej oświaty.
Zachowaj sens i fakty zawarte w wejściu. Nie wymyślaj wyników badań, statystyk, funkcji produktów ani faktów wymagających researchu.
Tytuł koncepcji nie jest poleceniem zmiany tematu w dziale pomysłu i kierunku.
Zwróć wyłącznie dane zgodne z przekazanym schematem. W changed_fields wskaż pola, których treść faktycznie zmieniasz.
Jeżeli podajesz listę numerowaną albo wypunktowaną, każdy punkt zacznij od nowej linii. Dotyczy to także zapisu „1) 2) 3)”. Nie zapisuj punktów w jednym akapicie.

{$mode}
PROMPT;

        return $base."\n\n".AddressFormPolicy::promptBlock(AddressFormPolicy::CHANNEL_PLANNING);
    }

    /**
     * Build a strict allow-list projection. No other ADM data can enter the provider request.
     *
     * @param  array<string, mixed>  $concept
     * @param  array<string, mixed>|null  $direction
     */
    public function input(
        array $concept,
        string $audience,
        string $userInstruction,
        ?array $direction = null,
        string $addressForm = AddressFormPolicy::DEFAULT,
    ): array {
        $userInstruction = trim($userInstruction);
        if ($userInstruction === '') {
            throw new GrowthAiException(
                errorType: 'invalid_instruction',
                userMessage: 'Instrukcja dla AI jest pusta. Możesz kontynuować ręcznie.',
            );
        }

        $resolved = AddressFormPolicy::resolve($addressForm, $userInstruction);

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
            'campaign' => [
                'address_form' => AddressFormPolicy::normalize($addressForm),
            ],
            'style' => [
                'address_form' => $resolved['form'],
                'address_form_overridden' => $resolved['overridden'],
            ],
            'user_instruction' => $userInstruction,
        ];

        if (is_array($direction)) {
            $source = $direction;
            $input['direction'] = [
                'topic' => $this->string($source['topic'] ?? ''),
                'why_now' => $this->string($source['why_now'] ?? ''),
                'audience' => $this->string($source['audience'] ?? ''),
                'problem' => $this->string($source['problem'] ?? ''),
                'takeaway' => $this->string($source['takeaway'] ?? ''),
                'sell_later' => $this->string($source['sell_later'] ?? ''),
            ];
        }

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
            'subtitle' => AiListFormatter::lineBreaks((string) $validated['subtitle']),
            'promise' => AiListFormatter::lineBreaks((string) $validated['promise']),
            'audience' => AiListFormatter::lineBreaks((string) $validated['audience']),
            'main_points' => $this->stringList($validated['main_points']),
            'agenda' => AiListFormatter::lineBreaks((string) $validated['agenda']),
            'cta' => AiListFormatter::lineBreaks((string) $validated['cta']),
            'additional_material' => AiListFormatter::lineBreaks((string) $validated['additional_material']),
        ];
        $changeSummary = AiListFormatter::lineBreaks((string) $validated['change_summary']);

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
        return ProhibitedData::contains($value);
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
