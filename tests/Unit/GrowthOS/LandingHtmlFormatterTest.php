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
        $this->assertStringContainsString('bez <!DOCTYPE>', $instructions);
        $this->assertStringContainsString('nie dodawaj klasy container ani żadnego przycisku', $instructions);
        $this->assertStringNotContainsString('btn btn-primary', $instructions);
    }

    public function test_unsafe_html_is_removed_and_bootstrap_markup_stays(): void
    {
        $html = LandingHtmlFormatter::sanitize(
            '<section class="py-5"><h1 class="display-6">Tytuł</h1><p class="lead">Opis webinaru.</p><script>alert(1)</script><h2>Zapisz się</h2><a class="btn btn-primary" href="[LINK DO ZAPISU]" onclick="evil()">Zapisz się bezpłatnie</a><a href="javascript:alert(1)">zły</a></section>'
        );

        $this->assertStringContainsString('<h1 class="display-6">Tytuł</h1>', $html);
        $this->assertStringContainsString('Opis webinaru.', $html);
        $this->assertStringNotContainsString('btn', $html);
        $this->assertStringNotContainsString('Zapisz się', $html);
        $this->assertStringNotContainsString(MaterialDraftTask::LINK_PLACEHOLDER, $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('onclick', strtolower($html));
        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringNotContainsString('alert(1)', $html);
    }
}
