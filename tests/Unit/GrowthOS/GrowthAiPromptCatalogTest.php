<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\GrowthAiService;
use App\Services\GrowthOS\AI\Support\GrowthAiPromptLibrary;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GrowthAiPromptCatalogTest extends TestCase
{
    public function test_editor_shows_the_instructions_sent_to_the_api_without_the_search_appendix(): void
    {
        $generate = new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, true);
        $iterate = new DirectionPlanningTask(DirectionPlanningTask::MODE_ITERATE, false);
        $direction = $this->stage('direction');

        $this->assertSame($generate->instructions(), $direction['prompts'][0]['body']);
        $this->assertSame($generate->defaultInstructions(), $direction['prompts'][0]['body']);
        $this->assertStringNotContainsString(trim(GrowthAiService::WEB_SEARCH_APPENDIX), $direction['prompts'][0]['body']);
        $this->assertFalse($direction['prompts'][0]['customized']);
        $this->assertSame($iterate->instructions(), $direction['prompts'][3]['body']);
        $this->assertStringNotContainsString('web_search', $direction['prompts'][3]['body']);
    }

    public function test_image_templates_keep_the_previous_wording(): void
    {
        $task = new GraphicImageTask;
        $description = 'Opis sceny od właściciela.';

        $this->assertSame(
            $description."\n\n".
            "Kompozycja pozioma 16:9. Zostaw spokojną przestrzeń na tekst.\n\n".
            'Na obrazie nie może być żadnego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.',
            $task->prompt($description, 'landscape', false, '', '', '2048x1152'),
        );
        $this->assertStringContainsString(
            'górny i dolny brzeg zostaną lekko przycięte',
            $task->prompt($description, 'landscape', false, '', '', '1536x1024'),
        );
        $this->assertStringContainsString(
            'duży nagłówek „Tytuł webinaru” oraz mniejszy napis „termin z karty projektu”',
            $task->prompt($description, 'landscape', true, 'Tytuł webinaru', 'termin z karty projektu', '2048x1152'),
        );
        $this->assertStringContainsString('{{opis}}', $this->stage('image-generation')['prompts'][0]['body']);
    }

    #[DataProvider('materialLabels')]
    public function test_every_material_has_its_own_prompt(string $id, string $label): void
    {
        $stage = $this->stage($id);

        $this->assertSame($label, $stage['label']);
        $this->assertSame(
            (new MaterialDraftTask(str_replace('material-', '', $id)))->instructions(),
            $stage['prompts'][0]['body'],
        );
    }

    public static function materialLabels(): array
    {
        return [
            ['material-youtube-description', 'Opis YouTube'],
            ['material-facebook-post', 'Post Facebook'],
            ['material-main-graphic', 'Grafika główna'],
            ['material-main-mail', 'Mailing główny'],
            ['material-reminder-mail', 'Mailing przypominający'],
            ['material-host-script', 'Scenariusz prowadzącego'],
        ];
    }

    /**
     * @return array{id: string, label: string, prompts: list<array{key: string, label: string, version: string, body: string, customized: bool, search: bool, placeholders: string}>}
     */
    private function stage(string $id): array
    {
        foreach (GrowthAiPromptLibrary::stages() as $stage) {
            if ($stage['id'] === $id) {
                return $stage;
            }
        }

        $this->fail('Brak etapu '.$id);
    }
}
