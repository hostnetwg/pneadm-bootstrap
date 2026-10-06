<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Support\GrowthAiExecutionOptions;
use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
use Illuminate\Http\Request;

/**
 * Shared helpers for local AI model / effort / web-search controls (DEC-052, DEC-053).
 */
final class GrowthAiRequestOptions
{
    public const WEB_SEARCH_FIELD = 'ai_web_search';

    public const WEB_SEARCH_SKIPPED_MESSAGE = 'AI nie skorzystało z wyszukiwania w sieci. Propozycja opiera się na wiedzy modelu — sprawdź aktualność faktów.';

    /**
     * @return array{model: ?string, reasoning_effort: ?string, web_search: bool}
     */
    public static function fromRequest(Request $request): array
    {
        $model = trim((string) $request->input('ai_model', ''));
        $effort = trim((string) $request->input('ai_reasoning_effort', ''));

        return [
            'model' => $model !== '' ? $model : null,
            'reasoning_effort' => $effort !== '' ? $effort : null,
            // Checkbox + hidden 0/1; missing field defaults to ON (DEC-053).
            'web_search' => $request->exists(self::WEB_SEARCH_FIELD)
                ? $request->boolean(self::WEB_SEARCH_FIELD)
                : true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $proposal
     * @return array{model: ?string, reasoning_effort: ?string}|null
     */
    public static function inheritFromProposal(?array $proposal): ?array
    {
        if ($proposal === null) {
            return null;
        }

        $model = trim((string) ($proposal['model'] ?? $proposal['selected_model'] ?? ''));
        $effort = trim((string) ($proposal['reasoning_effort'] ?? $proposal['selected_reasoning_effort'] ?? ''));

        if ($model === '' || $effort === '') {
            return null;
        }

        return [
            'model' => $model,
            'reasoning_effort' => $effort,
        ];
    }

    /**
     * Resolve options for UI defaults (generate = channel default, iterate = proposal).
     * Web search defaults ON and is not inherited from the previous proposal.
     *
     * @param  array<string, mixed>|null  $proposal
     * @return array{model: string, reasoning_effort: string, label: string, channel: string, web_search: bool}
     */
    public static function uiDefaults(string $channel, ?array $proposal = null): array
    {
        $inherited = self::inheritFromProposal($proposal);
        if ($inherited !== null) {
            return [
                'model' => $inherited['model'],
                'reasoning_effort' => $inherited['reasoning_effort'],
                'label' => GrowthAiModelCatalog::compactLabel($inherited['model'], $inherited['reasoning_effort']),
                'channel' => $channel,
                'web_search' => true,
            ];
        }

        $defaults = GrowthAiExecutionOptions::channelDefaults($channel);

        return [
            'model' => $defaults['model'],
            'reasoning_effort' => $defaults['effort'],
            'label' => GrowthAiModelCatalog::compactLabel($defaults['model'], $defaults['effort']),
            'channel' => $channel,
            'web_search' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $execution
     * @return array{web_search_requested: bool, web_search_used: bool, sources: list<array{title: string, url: string, domain: string}>, web_search_note: ?string}
     */
    public static function webSearchProposalMeta(
        ?array $execution,
        bool $webSearchUsed,
        array $sources = [],
    ): array {
        $requested = (bool) ($execution['web_search'] ?? true);

        return [
            'web_search_requested' => $requested,
            'web_search_used' => $webSearchUsed,
            'sources' => $webSearchUsed ? array_values($sources) : [],
            'web_search_note' => ($requested && ! $webSearchUsed) ? self::WEB_SEARCH_SKIPPED_MESSAGE : null,
        ];
    }
}
