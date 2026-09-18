<?php

namespace Tests\Unit;

use App\Services\ReleaseChangelogService;
use Tests\TestCase;

class ReleaseChangelogServiceTest extends TestCase
{
    public function test_parse_markdown_puts_latest_version_first(): void
    {
        $markdown = <<<'MD'
# Historia

## 1.1 — 2026-09-20

Nowy etap.

- Funkcja A

## 1.0 — 2026-09-18

Start.

- Fundament
MD;

        $releases = app(ReleaseChangelogService::class)->parseMarkdown($markdown);

        $this->assertCount(2, $releases);
        $this->assertSame('1.1', $releases[0]['version']);
        $this->assertSame('2026-09-20', $releases[0]['date']);
        $this->assertSame(['Nowy etap.'], $releases[0]['summary']);
        $this->assertSame(['Funkcja A'], $releases[0]['bullets']);
        $this->assertSame('1.0', $releases[1]['version']);
    }

    public function test_format_bullet_keeps_internal_doc_path_as_code(): void
    {
        $html = ReleaseChangelogService::formatBulletHtml(
            'Opis: [CLICKMEETING_TRAININGS.md](docs/CLICKMEETING_TRAININGS.md)'
        );

        $this->assertStringContainsString('CLICKMEETING_TRAININGS.md', $html);
        $this->assertStringContainsString('<code>docs/CLICKMEETING_TRAININGS.md</code>', $html);
        $this->assertStringNotContainsString('<a href="docs/', $html);
    }
}
