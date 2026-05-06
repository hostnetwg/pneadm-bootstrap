<?php

namespace Tests\Unit;

use App\Services\IfirmaApiService;
use Tests\TestCase;

class IfirmaKsefNumberExtractionTest extends TestCase
{
    public function test_extracts_canonical_string_field_recursive(): void
    {
        $svc = new IfirmaApiService;

        $payload = [
            'response' => [
                'Wynik' => [
                    'PelnyNumer' => '1/2026',
                    'NumerKSeF' => '78965403202270874273976462-TYLKO-KAES-EFFA',
                ],
            ],
        ];

        $this->assertSame(
            '78965403202270874273976462-TYLKO-KAES-EFFA',
            $svc->extractNumerKSeFFromInvoicePayload($payload)
        );
    }

    public function test_extracts_case_variant_keys_and_numeric_values(): void
    {
        $svc = new IfirmaApiService;

        $this->assertSame('777888999', $svc->extractNumerKSeFFromInvoicePayload([
            'NumerKsef' => 777888999,
        ]));

        $this->assertSame('999888777', $svc->extractNumerKSeFFromInvoicePayload([
            'nested' => ['NR_KSeF' => '999888777'],
        ]));
    }

    public function test_heuristic_key_matching_when_primary_keys_missing(): void
    {
        $svc = new IfirmaApiService;

        $numer = $svc->extractNumerKSeFFromInvoicePayload([
            'FAktura' => [
                'DaneKSeF' => [
                    'NumerReferencyjnyWSystemieKSeF' => 'REF-123-test',
                ],
            ],
        ]);

        $this->assertSame('REF-123-test', $numer);
    }

    public function test_returns_null_when_payload_empty_or_missing(): void
    {
        $svc = new IfirmaApiService;

        $this->assertNull($svc->extractNumerKSeFFromInvoicePayload(null));
        $this->assertNull($svc->extractNumerKSeFFromInvoicePayload([]));
        $this->assertNull($svc->extractNumerKSeFFromInvoicePayload(['InnaFlaga' => true]));
    }
}
