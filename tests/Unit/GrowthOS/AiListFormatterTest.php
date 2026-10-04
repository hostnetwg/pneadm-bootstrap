<?php

namespace Tests\Unit\GrowthOS;

use App\Support\GrowthOS\AiListFormatter;
use PHPUnit\Framework\TestCase;

class AiListFormatterTest extends TestCase
{
    public function test_parenthesis_agenda_starts_each_item_on_a_new_line(): void
    {
        $agenda = '1) Wprowadzenie i cele (8 min). 2) Kryteria źródeł i lista kontrolna bezpieczeństwa (12 min). 3) Warsztat — przygotowanie i import materiału (20 min). 4) Warsztat — generowanie konspektu, pytań i karty pracy (25 min). 5) Weryfikacja wyników i scenariusze ryzyka (12 min). 6) Materiały do pobrania i Q&A (13 min). Całość ~90 min.';

        $this->assertSame(
            "1) Wprowadzenie i cele (8 min).\n2) Kryteria źródeł i lista kontrolna bezpieczeństwa (12 min).\n3) Warsztat — przygotowanie i import materiału (20 min).\n4) Warsztat — generowanie konspektu, pytań i karty pracy (25 min).\n5) Weryfikacja wyników i scenariusze ryzyka (12 min).\n6) Materiały do pobrania i Q&A (13 min). Całość ~90 min.",
            AiListFormatter::lineBreaks($agenda),
        );
    }

    public function test_parenthesis_list_after_intro_starts_on_a_new_line(): void
    {
        $value = 'Do pobrania po webinarze: 1) skrócona lista kontrolna; 2) szablon workflow; 3) zestaw promptów.';

        $this->assertSame(
            "Do pobrania po webinarze:\n1) skrócona lista kontrolna;\n2) szablon workflow;\n3) zestaw promptów.",
            AiListFormatter::lineBreaks($value),
        );
    }

    public function test_numbered_list_starts_each_item_on_a_new_line(): void
    {
        $this->assertSame(
            "1. Punkt jeden.\n2. Punkt dwa.\n3. Punkt 3.",
            AiListFormatter::lineBreaks('1. Punkt jeden. 2. Punkt dwa. 3. Punkt 3.'),
        );
    }

    public function test_bullet_list_starts_each_item_on_a_new_line(): void
    {
        $this->assertSame(
            "• pierwszy\n• drugi\n• trzeci",
            AiListFormatter::lineBreaks('• pierwszy • drugi • trzeci'),
        );
    }

    public function test_existing_line_breaks_stay(): void
    {
        $value = "1. Punkt jeden.\n2. Punkt dwa.";

        $this->assertSame($value, AiListFormatter::lineBreaks($value));
    }

    public function test_a_single_number_does_not_split_the_sentence(): void
    {
        $value = 'Webinar trwa ok. 60 minut i ma 1. część praktyczną.';

        $this->assertSame($value, AiListFormatter::lineBreaks($value));
    }

    public function test_hyphen_inside_a_sentence_stays(): void
    {
        $value = 'Nauczyciele - zwłaszcza początkujący - oraz dyrektorzy.';

        $this->assertSame($value, AiListFormatter::lineBreaks($value));
    }

    public function test_hyphen_list_after_sentences_breaks(): void
    {
        $this->assertSame(
            "- Punkt jeden.\n- Punkt dwa.",
            AiListFormatter::lineBreaks('- Punkt jeden. - Punkt dwa.'),
        );
    }
}
