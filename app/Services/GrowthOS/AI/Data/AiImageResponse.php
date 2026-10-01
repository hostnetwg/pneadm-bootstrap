<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class AiImageResponse
{
    public function __construct(
        public string $bytes,
        public string $provider,
        public string $model,
        public string $requestId,
        public int $latencyMs,
    ) {}
}
