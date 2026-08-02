<?php

namespace Tests\Unit;

use App\Models\BankTransaction;
use App\Services\Bank\PayNowGatewayPayoutDetector;
use PHPUnit\Framework\TestCase;

class PayNowGatewayPayoutDetectorTest extends TestCase
{
    public function test_detects_melements_payout_description(): void
    {
        $tx = new BankTransaction([
            'is_incoming' => true,
            'description' => 'MELEMENTS SPÓŁKA AKCYJNA, /OPF/X///// WYPŁATA ŚRODKÓW NR PON-MWB-BZ7-RAU ZA DZIEŃ 28.04.2026 UL.PROSTA 18 00-850 WARSZAWA PRZELEW WEWNĘTRZNY PRZYCHODZĄCY 95114020040000340280387239',
            'account_label' => 'MELEMENTS SPÓŁKA AKCYJNA',
        ]);

        $this->assertTrue($this->detector()->isPayNowGatewayPayout($tx));
    }

    public function test_detects_wyplata_srodkow_with_pon_ref_without_melements_label(): void
    {
        $tx = new BankTransaction([
            'is_incoming' => true,
            'description' => 'WYPŁATA ŚRODKÓW NR PON-ABC-123 ZA DZIEŃ 01.05.2026',
            'account_label' => 'PayNow settlement',
        ]);

        $this->assertTrue($this->detector()->isPayNowGatewayPayout($tx));
    }

    public function test_does_not_match_school_transfer_without_invoice_number(): void
    {
        $tx = new BankTransaction([
            'is_incoming' => true,
            'description' => 'SZKOLA PODSTAWOWA NR 1 W KURZETNIKU UL. MICKIEWICZA 7 ZA SZKOLENIE',
            'account_label' => 'Szkoła Podstawowa nr 1',
        ]);

        $this->assertFalse($this->detector()->isPayNowGatewayPayout($tx));
    }

    public function test_does_not_match_only_internal_transfer_phrase(): void
    {
        $tx = new BankTransaction([
            'is_incoming' => true,
            'description' => 'PRZELEW WEWNĘTRZNY PRZYCHODZĄCY OD KLIENTA XYZ',
            'account_label' => 'Klient XYZ',
        ]);

        $this->assertFalse($this->detector()->isPayNowGatewayPayout($tx));
    }

    public function test_outgoing_is_never_paynow_payout(): void
    {
        $tx = new BankTransaction([
            'is_incoming' => false,
            'description' => 'MELEMENTS WYPŁATA ŚRODKÓW NR PON-MWB-XXX',
            'account_label' => 'MELEMENTS',
        ]);

        $this->assertFalse($this->detector()->isPayNowGatewayPayout($tx));
    }

    private function detector(): PayNowGatewayPayoutDetector
    {
        return new PayNowGatewayPayoutDetector;
    }
}
