<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class MaterialDraftResult
{
    public function __construct(
        public string $draft,
        public string $changeSummary,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public string $schemaVersion,
        public string $requestId,
    ) {}
}
