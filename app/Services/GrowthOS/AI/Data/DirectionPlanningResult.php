<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class DirectionPlanningResult
{
    /**
     * @param  array{why_now: string, audience: string, problem: string, takeaway: string, sell_later: string}  $direction
     * @param  list<string>  $titleSuggestions
     * @param  list<array{title: string, url: string, domain: string}>  $researchSources
     */
    public function __construct(
        public string $workingTopic,
        public array $direction,
        public array $titleSuggestions,
        public string $changeSummary,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public string $schemaVersion,
        public string $requestId,
        public array $researchSources,
        public bool $webSearchUsed,
    ) {}
}
