<?php

namespace App\Services\GrowthOS\AI\Support;

use App\Models\GrowthAiSetting;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use InvalidArgumentException;

/**
 * Resolves and validates model + reasoning effort for a single Growth AI request (DEC-052).
 */
final readonly class GrowthAiExecutionOptions
{
    public const SOURCE_DEFAULT = 'default';

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_INHERITED = 'inherited';

    public function __construct(
        public string $model,
        public string $reasoningEffort,
        public string $selectionSource,
        public string $channel,
        public int $maxOutputTokens,
    ) {}

    /**
     * @param  array{model?: ?string, reasoning_effort?: ?string}|null  $inheritFrom
     */
    public static function resolve(
        ?string $requestedModel,
        ?string $requestedEffort,
        string $channel,
        bool $requiresWebSearch = false,
        ?array $inheritFrom = null,
    ): self {
        if (! in_array($channel, [GrowthAiModelCatalog::CHANNEL_GENERAL, GrowthAiModelCatalog::CHANNEL_RESEARCH], true)) {
            throw new InvalidArgumentException('Unknown Growth AI channel.');
        }

        $requestedModel = self::blankToNull($requestedModel);
        $requestedEffort = self::blankToNull($requestedEffort);
        $inheritModel = self::blankToNull(is_string($inheritFrom['model'] ?? null) ? (string) $inheritFrom['model'] : null);
        $inheritEffort = self::blankToNull(is_string($inheritFrom['reasoning_effort'] ?? null) ? (string) $inheritFrom['reasoning_effort'] : null);

        if ($requestedModel !== null || $requestedEffort !== null) {
            $defaults = self::channelDefaults($channel);
            $model = $requestedModel ?? $defaults['model'];
            $effort = $requestedEffort ?? $defaults['effort'];
            $source = self::SOURCE_LOCAL;
        } elseif ($inheritModel !== null && $inheritEffort !== null) {
            $model = $inheritModel;
            $effort = $inheritEffort;
            $source = self::SOURCE_INHERITED;
        } else {
            $defaults = self::channelDefaults($channel);
            $model = $defaults['model'];
            $effort = $defaults['effort'];
            $source = self::SOURCE_DEFAULT;
        }

        self::assertAllowed($model, $effort, $requiresWebSearch);

        return new self(
            model: $model,
            reasoningEffort: $effort,
            selectionSource: $source,
            channel: $channel,
            maxOutputTokens: GrowthAiModelCatalog::maxOutputTokensForEffort($effort),
        );
    }

    /**
     * @return array{model: string, effort: string}
     */
    public static function channelDefaults(string $channel): array
    {
        $settings = GrowthAiSetting::resolvedDefaults();

        if ($channel === GrowthAiModelCatalog::CHANNEL_RESEARCH) {
            return [
                'model' => $settings['research_model'],
                'effort' => $settings['research_reasoning_effort'],
            ];
        }

        return [
            'model' => $settings['general_model'],
            'effort' => $settings['general_reasoning_effort'],
        ];
    }

    public static function assertAllowed(string $model, string $effort, bool $requiresWebSearch = false): void
    {
        if (! GrowthAiModelCatalog::has($model)) {
            throw new GrowthAiException(
                errorType: 'invalid_ai_model',
                userMessage: 'Wybrany model AI nie jest dozwolony. Wybierz model z listy.',
            );
        }

        if (! GrowthAiModelCatalog::supportsStructuredOutput($model)) {
            throw new GrowthAiException(
                errorType: 'invalid_ai_model_capability',
                userMessage: 'Wybrany model AI nie obsługuje wymaganej odpowiedzi strukturalnej.',
            );
        }

        if (! GrowthAiModelCatalog::supportsEffort($model, $effort)) {
            throw new GrowthAiException(
                errorType: 'invalid_ai_reasoning_effort',
                userMessage: 'Wybrany wysiłek myślenia nie jest dostępny dla tego modelu.',
            );
        }

        if ($requiresWebSearch && ! GrowthAiModelCatalog::supportsWebSearch($model)) {
            throw new GrowthAiException(
                errorType: 'invalid_ai_model_capability',
                userMessage: 'Ten model nie obsługuje wyszukiwania w Internecie. Wybierz inny model researchu.',
            );
        }
    }

    /**
     * Validation rules for request forms (optional local override).
     *
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>>
     */
    public static function requestRules(): array
    {
        $modelIds = array_keys(GrowthAiModelCatalog::models());

        return [
            'ai_model' => ['nullable', 'string', \Illuminate\Validation\Rule::in($modelIds)],
            'ai_reasoning_effort' => ['nullable', 'string', \Illuminate\Validation\Rule::in(GrowthAiModelCatalog::EFFORTS)],
        ];
    }

    /**
     * @return array{model: string, reasoning_effort: string, selection_source: string}
     */
    public function toMeta(): array
    {
        return [
            'model' => $this->model,
            'reasoning_effort' => $this->reasoningEffort,
            'selection_source' => $this->selectionSource,
        ];
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
