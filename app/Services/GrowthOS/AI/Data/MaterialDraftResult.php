<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class MaterialDraftResult
{
    /**
     * @param  list<array{title: string, url: string, domain: string}>  $researchSources
     */
    public function __construct(
        public string $draft,
        public string $changeSummary,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public string $schemaVersion,
        public string $requestId,
        public string $reasoningEffort = '',
        public string $selectionSource = '',
        public bool $webSearchUsed = false,
        public array $researchSources = [],
        public bool $webSearchRequested = false,
        public ?string $webSearchNote = null,
    ) {}
}
