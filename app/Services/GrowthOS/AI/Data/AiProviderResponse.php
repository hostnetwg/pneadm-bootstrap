<?php

namespace App\Services\GrowthOS\AI\Data;

final readonly class AiProviderResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $provider,
        public string $model,
        public string $requestId,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
    ) {}
}
