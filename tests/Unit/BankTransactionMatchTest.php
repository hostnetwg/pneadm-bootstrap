<?php

namespace Tests\Unit;

use App\Models\BankTransactionMatch;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankTransactionMatchTest extends TestCase
{
    #[Test]
    public function effective_confidence_is_high_for_multi_invoice_package(): void
    {
        $match = new BankTransactionMatch([
            'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
            'match_reasons' => ['multi_invoice_sum_match', 'amount_match'],
        ]);

        $this->assertSame(BankTransactionMatch::CONFIDENCE_HIGH, $match->effectiveConfidence());
        $this->assertSame('Wysoka', $match->confidenceLabel());
    }

    #[Test]
    public function effective_confidence_is_high_for_split_with_amount_match(): void
    {
        $match = new BankTransactionMatch([
            'confidence' => BankTransactionMatch::CONFIDENCE_LOW,
            'match_reasons' => ['manual_case_link', 'split_allocation', 'amount_match'],
        ]);

        $this->assertSame(BankTransactionMatch::CONFIDENCE_HIGH, $match->effectiveConfidence());
        $this->assertSame('Wysoka', $match->confidenceLabel());
    }

    #[Test]
    public function amount_match_label_for_split_refers_to_allocation_not_full_transfer(): void
    {
        $match = new BankTransactionMatch([
            'match_reasons' => ['split_allocation', 'amount_match'],
        ]);

        $labels = $match->reasonLabels();

        $this->assertContains('Alokowana kwota z przelewu zgodna z FV lub brakującą kwotą FV', $labels);
        $this->assertNotContains('Kwota przelewu = kwota FV/zamówienia', $labels);
    }
}
