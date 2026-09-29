<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class ConceptRevisionResult
{
    /**
     * @param  array<string, mixed>  $concept
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public array $concept,
        public array $changedFields,
        public string $changeSummary,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public string $schemaVersion,
        public string $requestId,
    ) {}
}
