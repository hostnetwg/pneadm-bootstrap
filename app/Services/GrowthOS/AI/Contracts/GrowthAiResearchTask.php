<?php

namespace App\Services\GrowthOS\AI\Contracts;

/**
 * Marker for tasks that may call hosted web_search. Other Growth AI tasks must not.
 */
interface GrowthAiResearchTask extends GrowthAiTask
{
    public function requiresWebSearch(): bool;
}
