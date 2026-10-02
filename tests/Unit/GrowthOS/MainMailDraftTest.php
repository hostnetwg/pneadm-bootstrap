<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use PHPUnit\Framework\TestCase;

class MainMailDraftTest extends TestCase
{
    public function test_compose_and_parse_round_trip(): void
    {
        $draft = MaterialDraftTask::composeMainMail(
            ['Temat główny', 'Drugi temat', 'Trzeci temat'],
            'Krótki preheader.',
            "Dzień dobry,\n\nTreść maila.\n\nZespół PNE",
        );

        $this->assertSame(
            "Temat: Temat główny\nInne propozycje tematu:\n- Drugi temat\n- Trzeci temat\nPreheader: Krótki preheader.\n\nDzień dobry,\n\nTreść maila.\n\nZespół PNE",
            $draft,
        );
        $this->assertSame([
            'subject' => 'Temat główny',
            'alternatives' => ['Drugi temat', 'Trzeci temat'],
            'preheader' => 'Krótki preheader.',
            'body' => "Dzień dobry,\n\nTreść maila.\n\nZespół PNE",
        ], MaterialDraftTask::parseMainMail($draft));
    }

    public function test_empty_subject_or_preheader_are_left_out(): void
    {
        $this->assertSame("Temat: Temat\n\nTreść", MaterialDraftTask::composeMainMail(['Temat'], ' ', 'Treść'));
        $this->assertSame("Preheader: Zapowiedź\n\nTreść", MaterialDraftTask::composeMainMail([], 'Zapowiedź', 'Treść'));
        $this->assertSame('Treść', MaterialDraftTask::composeMainMail([], '', 'Treść'));

        $this->assertSame(
            ['subject' => '', 'alternatives' => [], 'preheader' => 'Zapowiedź', 'body' => 'Treść'],
            MaterialDraftTask::parseMainMail("Preheader: Zapowiedź\n\nTreść"),
        );
        $this->assertSame(
            ['subject' => 'Sam temat', 'alternatives' => [], 'preheader' => '', 'body' => ''],
            MaterialDraftTask::parseMainMail('Temat: Sam temat'),
        );
    }

    public function test_text_without_labels_goes_to_the_body(): void
    {
        foreach ([
            'Wersja edukacyjna bez agresywnej sprzedaży.',
            "Dzień dobry,\nTemat: to nie jest nagłówek",
            "Temat: Coś\nZwykła linia bez etykiety\n\nTreść",
        ] as $draft) {
            $this->assertSame(
                ['subject' => '', 'alternatives' => [], 'preheader' => '', 'body' => $draft],
                MaterialDraftTask::parseMainMail($draft),
            );
        }
    }
}
