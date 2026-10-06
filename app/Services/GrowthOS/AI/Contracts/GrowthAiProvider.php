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
     * @param  array{
     *     web_search?: bool,
     *     require_web_search?: bool,
     *     use_research_model?: bool,
     *     model?: string,
     *     reasoning_effort?: string,
     *     max_output_tokens?: int,
     *     selection_source?: string
     * }  $options
     */
    public function generateStructured(
        string $taskType,
        string $instructions,
        array $input,
        array $schema,
        array $options = [],
    ): AiProviderResponse;
}
