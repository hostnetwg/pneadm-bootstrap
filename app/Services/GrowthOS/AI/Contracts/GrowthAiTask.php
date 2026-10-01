<?php

namespace App\Services\GrowthOS\AI\Contracts;

interface GrowthAiTask
{
    public function type(): string;

    public function promptVersion(): string;

    public function schemaVersion(): string;

    public function instructions(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array;
}
