<?php

namespace Tests\Unit;

use App\Services\Bank\MbankStatementParser;
use Tests\TestCase;

class MbankStatementParserTest extends TestCase
{
    public function test_parses_fixture_rows_and_period(): void
    {
        $parser = new MbankStatementParser;
        $path = base_path('tests/fixtures/bank/mbank_sample.csv');
        $parsed = $parser->parseFile($path);

        $this->assertSame('2026-01-01', $parsed['period_from']);
        $this->assertSame('2026-07-31', $parsed['period_to']);
        $this->assertCount(5, $parsed['rows']);

        $first = $parsed['rows'][0];
        $this->assertSame('2026-07-31', $first['operation_date']);
        $this->assertSame(365.0, $first['amount']);
        $this->assertSame('PLN', $first['currency']);
        $this->assertTrue($first['is_incoming']);
        $this->assertSame('70109019550000000120234687', $first['counterparty_account']);
        $this->assertNotEmpty($first['fingerprint']);

        $expense = collect($parsed['rows'])->firstWhere('is_incoming', false);
        $this->assertNotNull($expense);
        $this->assertSame(-50.0, $expense['amount']);
    }

    public function test_parse_amount_handles_polish_format(): void
    {
        $parser = new MbankStatementParser;

        [$amount, $currency] = $parser->parseAmount('1 234,56 PLN');
        $this->assertSame(1234.56, $amount);
        $this->assertSame('PLN', $currency);

        [$neg] = $parser->parseAmount('-50,00 PLN');
        $this->assertSame(-50.0, $neg);
    }

    public function test_split_description_parts_sender_and_title(): void
    {
        $parser = new MbankStatementParser;
        $raw = "PRZEDSZKOLE NIEPUBLICZNE NR 1 UL. SŁONECZNA 2 59-800 LUBAŃ, F-ra 320/7/2026 z 29.07.2026                                                                         PRZELEW ZEWNĘTRZNY PRZYCHODZĄCY                                                   70109019550000000120234687  ";

        $parts = $parser->splitDescriptionParts($raw);

        $this->assertSame('70109019550000000120234687', $parts['counterparty_account']);
        $this->assertNotNull($parts['transfer_type']);
        $this->assertStringContainsString('PRZEDSZKOLE NIEPUBLICZNE NR 1', (string) $parts['sender_estimate']);
        $this->assertStringContainsString('320/7/2026', (string) $parts['title_estimate']);
        $this->assertStringNotContainsString('PRZELEW', (string) $parts['sender_estimate']);
        $this->assertStringNotContainsString('70109019550000000120234687', (string) $parts['title_estimate']);
    }

    public function test_split_description_parts_bare_invoice_after_comma(): void
    {
        $parser = new MbankStatementParser;
        $parts = $parser->splitDescriptionParts(
            'ZESPÓŁ SZKÓŁ W CHRZYPSKU WIELKIM SZKOLNA 34 64-412 CHRZYPSKO WIELKIE, 332/7/2026  ZESPÓŁ SZKÓŁ W CHRZYPSKU WIELKIM   SZKOLNA 34                         64-412 CHRZYPSKO WIELKIE                                               PRZELEW ZEWNĘTRZNY PRZYCHODZĄCY                                                   10908200050000248220000060  '
        );

        $this->assertSame('332/7/2026', $parts['title_estimate']);
        $this->assertStringContainsString('ZESPÓŁ SZKÓŁ W CHRZYPSKU', (string) $parts['sender_estimate']);
    }
}
