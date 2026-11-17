<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Support\GrowthOS\LandingHtmlFormatter;
use Tests\TestCase;

class LandingHtmlFormatterTest extends TestCase
{
    public function test_landing_prompt_asks_for_bootstrap_html(): void
    {
        $instructions = (new MaterialDraftTask(MaterialDraftTask::LANDING_MATERIAL_KEY))->defaultInstructions();

        $this->assertStringContainsString('Bootstrap 5', $instructions);
        $this->assertStringContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $instructions);
        $this->assertStringContainsString('bez <!DOCTYPE>', $instructions);
    }

    public function test_unsafe_html_is_removed_and_bootstrap_markup_stays(): void
    {
        $html = LandingHtmlFormatter::sanitize(
            '<section class="py-5"><h1 class="display-6">Tytuł</h1><script>alert(1)</script><a class="btn btn-primary" href="[LINK DO ZAPISU]" onclick="evil()">Zapisz się</a><a href="javascript:alert(1)">zły</a></section>'
        );

        $this->assertStringContainsString('<h1 class="display-6">Tytuł</h1>', $html);
        $this->assertStringContainsString('class="btn btn-primary"', $html);
        $this->assertStringContainsString('href="[LINK DO ZAPISU]"', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('onclick', strtolower($html));
        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringNotContainsString('alert(1)', $html);
    }
}
