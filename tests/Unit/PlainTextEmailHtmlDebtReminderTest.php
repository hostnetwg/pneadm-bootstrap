<?php

namespace Tests\Unit;

use App\Support\PlainTextEmailHtml;
use PHPUnit\Framework\TestCase;

class PlainTextEmailHtmlDebtReminderTest extends TestCase
{
    public function test_formats_debt_reminder_labels_bold_and_overdue_red(): void
    {
        $plain = implode("\n", [
            'Dzień dobry,',
            '',
            'SZKOLENIE:',
            'Temat: AI w edukacji',
            'Termin: 10.10.2026, 09:30',
            'Prowadzący: Anna Nowak',
            'Uczestnik: Jan Kowalski',
            'Zamawiający: Firma X',
            '',
            'Dane do przelewu:',
            'Koszt: 499,50 zł (termin płatności: 01.09.2026 — 5 dni po terminie)',
            'Tytuł przelewu: 45/9/2026',
            'Nr konta: 12 3456 7890 1234 5678 9012 3456',
            '',
            'Prosimy o uregulowanie należności na rachunek wskazany na fakturze.',
        ]);

        $html = PlainTextEmailHtml::formatDebtReminderEmailHtml($plain);

        $this->assertStringContainsString(
            '<strong style="font-weight:700;text-decoration:underline;">SZKOLENIE:</strong>',
            $html
        );
        $this->assertStringContainsString(
            'Temat: <strong style="font-weight:700;">AI w edukacji</strong>',
            $html
        );
        $this->assertStringContainsString(
            'Tytuł przelewu: <strong style="font-weight:700;">45/9/2026</strong>',
            $html
        );
        $this->assertStringContainsString(
            'Nr konta: <strong style="font-weight:700;">12 3456 7890 1234 5678 9012 3456</strong>',
            $html
        );
        $this->assertStringContainsString(
            '<span style="color:#dc3545;font-weight:700;">5 dni po terminie</span>',
            $html
        );
        $this->assertStringContainsString(
            'Prosimy o uregulowanie należności na rachunek wskazany na fakturze.',
            $html
        );
        $this->assertStringNotContainsString('<strong style="font-weight:700;">Dzień dobry,', $html);
    }

    public function test_cost_line_without_overdue_is_bold_only(): void
    {
        $html = PlainTextEmailHtml::formatDebtReminderEmailHtml(
            'Koszt: 100,00 zł (termin płatności: 10.10.2026 — za 2 dni)'
        );

        $this->assertStringContainsString(
            'Koszt: <strong style="font-weight:700;">100,00 zł (termin płatności: 10.10.2026 — za 2 dni)</strong>',
            $html
        );
        $this->assertStringNotContainsString('#dc3545', $html);
    }
}
