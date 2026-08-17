<?php

namespace Tests\Unit;

use App\Services\IfirmaApiService;
use Tests\TestCase;

class IfirmaPelnyNumerExtractionTest extends TestCase
{
    public function test_extracts_pelny_numer_from_nested_wynik(): void
    {
        $svc = new IfirmaApiService;

        $payload = [
            'response' => [
                'Kod' => 0,
                'Informacja' => 'OK',
                'Wynik' => [
                    'Identyfikator' => 99887766,
                    'PelnyNumer' => '56/8/2026',
                ],
            ],
        ];

        $this->assertSame('56/8/2026', $svc->extractPelnyNumerFromInvoicePayload($payload));
        $this->assertFalse($svc->looksLikeIfirmaDocumentId('56/8/2026'));
        $this->assertTrue($svc->looksLikeIfirmaDocumentId('99887766'));
    }

    public function test_extracts_pelny_numer_from_response_root(): void
    {
        $svc = new IfirmaApiService;

        $this->assertSame('1/11/2025/ProForma', $svc->extractPelnyNumerFromInvoicePayload([
            'response' => [
                'PelnyNumer' => '1/11/2025/ProForma',
                'Zaplacono' => 0,
            ],
        ]));
    }

    public function test_rejects_identyfikator_used_as_pelny_numer(): void
    {
        $svc = new IfirmaApiService;

        $this->assertNull($svc->extractPelnyNumerFromInvoicePayload([
            'PelnyNumer' => '99887766',
        ]));
        $this->assertNull($svc->extractPelnyNumerFromInvoicePayload([]));
        $this->assertTrue($svc->isMissingOrIfirmaDocumentId('99887766'));
        $this->assertTrue($svc->isMissingOrIfirmaDocumentId(''));
        $this->assertFalse($svc->isMissingOrIfirmaDocumentId('56/8/2026'));
    }
}
