<?php

namespace App\Services\GrowthOS\AI\Support;

/**
 * Controlled allowlist of Growth OS text models (DEC-052).
 * Image models stay separate.
 */
final class GrowthAiModelCatalog
{
    public const CHANNEL_GENERAL = 'general';

    public const CHANNEL_RESEARCH = 'research';

    public const EFFORT_LOW = 'low';

    public const EFFORT_MEDIUM = 'medium';

    public const EFFORT_HIGH = 'high';

    public const EFFORT_XHIGH = 'xhigh';

    public const EFFORT_MAX = 'max';

    /** @var list<string> */
    public const EFFORTS = [
        self::EFFORT_LOW,
        self::EFFORT_MEDIUM,
        self::EFFORT_HIGH,
        self::EFFORT_XHIGH,
        self::EFFORT_MAX,
    ];

    public const DEFAULT_GENERAL_MODEL = 'gpt-6.1-sol';

    public const DEFAULT_GENERAL_EFFORT = self::EFFORT_MEDIUM;

    public const DEFAULT_RESEARCH_MODEL = 'gpt-6.1-sol';

    public const DEFAULT_RESEARCH_EFFORT = self::EFFORT_HIGH;

    /**
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     cost_tier: string,
     *     efforts: list<string>,
     *     capabilities: array{structured_output: bool, web_search: bool},
     *     input_per_million: float,
     *     output_per_million: float
     * }>
     */
    public static function models(): array
    {
        // Order: most expensive → cheapest (UI selects). Default remains Sol.
        return [
            'gpt-6-astra' => [
                'id' => 'gpt-6-astra',
                'label' => 'GPT-6 Astra',
                'description' => 'Najwyższa jakość / droższy',
                'cost_tier' => '$$$$$',
                'efforts' => self::EFFORTS,
                'capabilities' => [
                    'structured_output' => true,
                    'web_search' => true,
                ],
                'input_per_million' => 5.00,
                'output_per_million' => 25.00,
            ],
            'gpt-5.5' => [
                'id' => 'gpt-5.5',
                'label' => 'GPT-5.5',
                'description' => 'Poprzedni model researchu',
                'cost_tier' => '$$',
                'efforts' => self::EFFORTS,
                'capabilities' => [
                    'structured_output' => true,
                    'web_search' => true,
                ],
                'input_per_million' => (float) config('growth_ai.research.input_per_million', 2.50),
                'output_per_million' => (float) config('growth_ai.research.output_per_million', 15.00),
            ],
            'gpt-6.1-sol' => [
                'id' => 'gpt-6.1-sol',
                'label' => 'GPT-6.1 Sol',
                'description' => 'Rekomendowany / balans jakości i kosztu',
                'cost_tier' => '$$',
                'efforts' => self::EFFORTS,
                'capabilities' => [
                    'structured_output' => true,
                    'web_search' => true,
                ],
                'input_per_million' => 2.00,
                'output_per_million' => 12.00,
            ],
            'gpt-6-luna' => [
                'id' => 'gpt-6-luna',
                'label' => 'GPT-6 Luna',
                'description' => 'Szybki / ekonomiczny',
                'cost_tier' => '$',
                'efforts' => self::EFFORTS,
                'capabilities' => [
                    'structured_output' => true,
                    'web_search' => true,
                ],
                'input_per_million' => 0.50,
                'output_per_million' => 3.00,
            ],
            'gpt-5-mini' => [
                'id' => 'gpt-5-mini',
                'label' => 'GPT-5 mini',
                'description' => 'Poprzedni model pilotażu',
                'cost_tier' => '$',
                'efforts' => self::EFFORTS,
                'capabilities' => [
                    'structured_output' => true,
                    'web_search' => true,
                ],
                'input_per_million' => (float) config('growth_ai.cost.input_per_million', 0.25),
                'output_per_million' => (float) config('growth_ai.cost.output_per_million', 2.00),
            ],
        ];
    }

    /**
     * @return array{id: string, label: string, description: string, cost_tier: string, efforts: list<string>, capabilities: array{structured_output: bool, web_search: bool}, input_per_million: float, output_per_million: float}|null
     */
    public static function model(string $id): ?array
    {
        return self::models()[$id] ?? null;
    }

    public static function has(string $id): bool
    {
        return self::model($id) !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function effortLabels(): array
    {
        return [
            self::EFFORT_LOW => 'Szybki',
            self::EFFORT_MEDIUM => 'Zrównoważony',
            self::EFFORT_HIGH => 'Dokładny',
            self::EFFORT_XHIGH => 'Bardzo dokładny',
            self::EFFORT_MAX => 'Maksymalny',
        ];
    }

    public static function effortLabel(string $effort): string
    {
        return self::effortLabels()[$effort] ?? $effort;
    }

    public static function modelLabel(string $id): string
    {
        return self::model($id)['label'] ?? $id;
    }

    public static function supportsEffort(string $modelId, string $effort): bool
    {
        $model = self::model($modelId);
        if ($model === null) {
            return false;
        }

        return in_array($effort, $model['efforts'], true);
    }

    public static function supportsWebSearch(string $modelId): bool
    {
        return (bool) (self::model($modelId)['capabilities']['web_search'] ?? false);
    }

    public static function supportsStructuredOutput(string $modelId): bool
    {
        return (bool) (self::model($modelId)['capabilities']['structured_output'] ?? false);
    }

    /**
     * Technical Responses API output budget by reasoning effort (not content length limits).
     */
    public static function maxOutputTokensForEffort(string $effort): int
    {
        $configured = (int) config('growth_ai.limits.max_output_tokens', 4000);

        return match ($effort) {
            self::EFFORT_LOW => max(256, $configured),
            self::EFFORT_MEDIUM => max(6000, $configured),
            self::EFFORT_HIGH => max(8000, $configured),
            self::EFFORT_XHIGH, self::EFFORT_MAX => max(12000, $configured),
            default => max(256, $configured),
        };
    }

    /**
     * @return array{input: float, output: float}
     */
    public static function ratesFor(string $modelId): array
    {
        $model = self::model($modelId);
        if ($model === null) {
            return [
                'input' => (float) config('growth_ai.cost.input_per_million', 0.25),
                'output' => (float) config('growth_ai.cost.output_per_million', 2.00),
            ];
        }

        return [
            'input' => $model['input_per_million'],
            'output' => $model['output_per_million'],
        ];
    }

    /**
     * Compact label for UI: "GPT-6.1 Sol · Zrównoważony".
     */
    public static function compactLabel(string $modelId, string $effort): string
    {
        return self::modelLabel($modelId).' · '.self::effortLabel($effort);
    }

    /**
     * Badge for the channel defaults (before a request).
     */
    public static function badgeForChannel(string $channel): string
    {
        $defaults = GrowthAiExecutionOptions::channelDefaults($channel);

        return self::compactLabel($defaults['model'], $defaults['effort']);
    }

    /**
     * Badge for a stored proposal (what actually generated the content), with channel fallback.
     *
     * @param  array<string, mixed>|null  $proposal
     */
    public static function badgeForProposal(?array $proposal, string $fallbackChannel): string
    {
        $model = trim((string) ($proposal['model'] ?? $proposal['selected_model'] ?? ''));
        $effort = trim((string) ($proposal['reasoning_effort'] ?? $proposal['selected_reasoning_effort'] ?? ''));

        if ($model !== '' && $effort !== '') {
            return self::compactLabel($model, $effort);
        }

        if ($model !== '') {
            return self::modelLabel($model);
        }

        return self::badgeForChannel($fallbackChannel);
    }

    public static function enabledPrefix(bool $enabled): string
    {
        return $enabled ? 'AI: ' : 'AI: symulacja lokalna';
    }
}
