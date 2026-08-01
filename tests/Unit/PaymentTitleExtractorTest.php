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
}
