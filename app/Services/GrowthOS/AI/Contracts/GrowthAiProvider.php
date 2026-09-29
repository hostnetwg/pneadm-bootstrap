<?php

namespace App\Services\GrowthOS\AI\Contracts;

use App\Services\GrowthOS\AI\Data\AiProviderResponse;

interface GrowthAiProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $schema
     */
    public function generateStructured(
        string $taskType,
        string $instructions,
        array $input,
        array $schema,
    ): AiProviderResponse;
}
