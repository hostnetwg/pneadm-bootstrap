<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketingCampaignController;
use ReflectionMethod;
use Tests\TestCase;

class MarketingCampaignDuplicateTest extends TestCase
{
    public function test_append_suffix_respects_fifty_character_limit(): void
    {
        $method = new ReflectionMethod(MarketingCampaignController::class, 'appendSuffixToCampaignCode');
        $method->setAccessible(true);
        $controller = new MarketingCampaignController;

        $longBase = str_repeat('a', 48);
        $result = $method->invoke($controller, $longBase, '-2');

        $this->assertSame(50, strlen($result));
        $this->assertStringEndsWith('-2', $result);
    }

    public function test_suggest_duplicate_name_appends_kopia_suffix(): void
    {
        $method = new ReflectionMethod(MarketingCampaignController::class, 'suggestDuplicateName');
        $method->setAccessible(true);
        $controller = new MarketingCampaignController;

        $this->assertSame(
            'Newsletter wrzesień (kopia)',
            $method->invoke($controller, 'Newsletter wrzesień'),
        );
    }
}
