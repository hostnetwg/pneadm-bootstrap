<?php

namespace Tests\Unit;

use App\Services\Bank\PaymentTitleExtractor;
use Tests\TestCase;

class PaymentTitleExtractorTest extends TestCase
{
    public function test_extracts_invoice_variants(): void
    {
        $extractor = new PaymentTitleExtractor;

        $cases = [
            'F-ra 320/7/2026 z 29.07.2026' => '320/7/2026',
            'F 308/7/2026' => '308/7/2026',
            'faktura nr 312/7/2026' => '312/7/2026',
            'FV 243/7/2026' => '243/7/2026',
            'FAKTURA 343/7/2026' => '343/7/2026',
            'zapł. za F-rę nr 247/7/2026' => '247/7/2026',
            'faktura nr156/7/2026' => '156/7/2026',
            'w/g 333/7/2026' => '333/7/2026',
            'ZESPÓŁ SZKOLNO-PRZEDSZKOLNY W WIERBCE, w/g 333/7/2026 42-436 WIERBKA' => '333/7/2026',
            'bare 165/5/2026 alone' => '165/5/2026',
        ];

        foreach ($cases as $title => $expected) {
            $result = $extractor->extract($title);
            $this->assertContains(
                $expected,
                $result['invoice_numbers'],
                "Failed for title: {$title}"
            );
        }
    }

    public function test_normalizes_invoice_month_with_or_without_leading_zero(): void
    {
        $extractor = new PaymentTitleExtractor;

        $this->assertSame('333/7/2026', $extractor->normalizeInvoiceNumber('333/07/2026'));
        $this->assertSame('333/7/2026', $extractor->normalizeInvoiceNumber('333 / 7 / 2026'));
    }

    public function test_extracts_order_id_and_nip(): void
    {
        $extractor = new PaymentTitleExtractor;
        $result = $extractor->extract('Zaplata za zamowienie ID 4242 NIP 525-000-10-09');

        $this->assertContains(4242, $result['order_ids']);
        $this->assertContains('5250001009', $result['nips']);
    }

    public function test_extracts_order_number_variants_from_bank_title(): void
    {
        $extractor = new PaymentTitleExtractor;

        $cases = [
            'KULESZA EWELINA zamówienie nr 7431 PRZELEW' => 7431,
            'zapłata za zamowienie nr. 8123' => 8123,
            'zam. nr 9001 za szkolenie' => 9001,
            'nr zamówienia 5510' => 5510,
            'order no 1205' => 1205,
            'order #88' => 88,
            'Potwierdzenie: zapłata za #4587' => 4587,
            'tytuł # 4587 szkolenie' => 4587,
        ];

        foreach ($cases as $title => $expected) {
            $result = $extractor->extract($title);
            $this->assertContains(
                $expected,
                $result['order_ids'],
                "Failed for title: {$title}"
            );
        }
    }

    public function test_extracts_ksef_and_normalizes_extra_hyphen(): void
    {
        $extractor = new PaymentTitleExtractor;
        $result = $extractor->extract(
            'F-RA 21/7/2026 NR KSEF:7392137630-20260715-00FEFF-400000-86 PRZELEW'
        );

        $this->assertContains('7392137630-20260715-00FEFF400000-86', $result['ksef_numbers']);
        $this->assertSame(
            '7392137630-20260715-00FEFF400000-86',
            $extractor->normalizeKsefNumber('7392137630-20260715-00FEFF-400000-86')
        );
        $this->assertSame(
            '7392137630-20260715-00FEFF400000-86',
            $extractor->normalizeKsefNumber('7392137630-20260715-00FEFF400000-86')
        );
    }

    public function test_extracts_bare_ksef_from_bank_description(): void
    {
        $extractor = new PaymentTitleExtractor;
        $result = $extractor->extract(
            'SZKOŁA PODSTAWOWA Z O.INT. NR 216 UL.WOLNA 36/38 04-908 WARSZAWA;PL, '
            .'7392137630-20260708-4A4C66000005-EA, SP216 SZKOŁA PODSTAWOWA Z O.INT. NR 216'
        );

        $this->assertContains('7392137630-20260708-4A4C66000005-EA', $result['ksef_numbers']);
    }
}
