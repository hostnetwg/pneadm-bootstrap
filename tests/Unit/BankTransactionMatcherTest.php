<?php

namespace Tests\Unit;

use App\Models\BankTransaction;
use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\BankTransactionMatch;
use App\Services\Bank\BankTransactionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTransactionMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_confidence_on_invoice_number_and_amount(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '320/7/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '320/7/2026',
            'amount_gross' => 365,
            'opened_at' => now(),
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-31',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'SZKOLA TESTOWA, F-ra 320/7/2026 PRZELEW',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-1',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_HIGH, $suggestions[0]['confidence']);
        $this->assertContains('amount_match', $suggestions[0]['match_reasons']);
    }

    public function test_medium_confidence_on_order_id_with_amount(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie ID',
            'product_price' => 500,
            'order_date' => now()->subDays(2),
            'invoice_number' => '99/1/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-28',
            'amount' => 500,
            'currency' => 'PLN',
            'description' => 'Zaplata zamowienie ID '.$order->id.' za szkolenie',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-2',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_MEDIUM, $suggestions[0]['confidence']);
    }

    public function test_matches_order_number_phrase_from_transfer_title(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie nr zamówienia',
            'product_price' => 365,
            'order_date' => now()->subDays(2),
            'invoice_number' => '304/6/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-01',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'KULESZA EWELINA zamówienie nr '.$order->id.' PRZELEW ZEWNĘTRZNY PRZYCHODZĄCY',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-order-nr',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertContains('order_id:'.$order->id, $suggestions[0]['match_reasons']);
    }

    public function test_medium_confidence_on_buyer_name_without_nip_case_insensitive(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie prywatne',
            'product_price' => 365,
            'order_date' => now()->subDays(3),
            'invoice_number' => '50/7/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Jan Kowalski',
            'buyer_nip' => null,
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-20',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'JAN KOWALSKI UL. TESTOWA 1 00-001 WARSZAWA PRZELEW ZEWNETRZNY PRZYCHODZACY',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-3',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_MEDIUM, $suggestions[0]['confidence']);
        $this->assertTrue(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'buyer_name:'))
        );
    }

    public function test_buyer_name_match_skipped_when_order_has_nip(): void
    {
        FormOrder::create([
            'product_name' => 'Szkolenie firma',
            'product_price' => 365,
            'order_date' => now()->subDays(3),
            'invoice_number' => '51/7/2026',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Jan Kowalski',
            'buyer_nip' => '5250001009',
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-20',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'JAN KOWALSKI UL. TESTOWA 1 PRZELEW',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-4',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertFalse(
            collect($suggestions)->contains(
                fn (array $s) => collect($s['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'buyer_name:'))
            )
        );
    }

    public function test_invoice_match_demoted_on_ksef_and_party_conflict(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '21/7/2026',
            'ksef_number' => '7392137630-20260701-577AE3000011-BF',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Gmina Zawonia',
            'recipient_name' => 'Szkoła Podstawowa im. Księdza Wawrzyńca Bochenka w Czeszowie',
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-23',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZEDSZKOLE NR 3 IM. CZESLAWA JANCZARSKIEGO W KOLUSZKACH UL. STASZICA 36 95-040 KOLUSZKI, F-RA 21/7/2026 NR KSEF:7392137630-20260715-00FEFF-400000-86 PRZELEW ZEWNETRZNY PRZYCHODZACY 27102033780000160203323920',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-conflict',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        // KSeF z tytułu nie istnieje w DB → fallback na FV + demotion (ksef_mismatch / party)
        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_LOW, $suggestions[0]['confidence']);
        $this->assertTrue(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'ksef_mismatch:'))
        );
        $this->assertContains('party_name_mismatch', $suggestions[0]['match_reasons']);
    }

    public function test_ksef_hit_wins_over_conflicting_invoice_number(): void
    {
        $wrongByInvoice = FormOrder::create([
            'product_name' => 'Złe FV w tytule',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '21/7/2026',
            'ksef_number' => '7392137630-20260701-577AE3000011-BF',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Gmina Zawonia',
            'recipient_name' => 'Szkoła w Czeszowie',
        ]);

        $correctByKsef = FormOrder::create([
            'product_name' => 'Właściwe po KSeF',
            'product_price' => 365,
            'order_date' => now()->subDays(4),
            'invoice_number' => '88/7/2026',
            'ksef_number' => '7392137630-20260715-00FEFF400000-86',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Przedszkole Nr 3',
            'recipient_name' => 'Przedszkole Nr 3 w Koluszkach',
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-23',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZEDSZKOLE NR 3, F-RA 21/7/2026 NR KSEF:7392137630-20260715-00FEFF-400000-86 PRZELEW',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-ksef-priority',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertCount(1, $suggestions);
        $this->assertSame($correctByKsef->id, $suggestions[0]['form_order_id']);
        $this->assertNotSame($wrongByInvoice->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_MEDIUM, $suggestions[0]['confidence']);
        $this->assertTrue(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'ksef_number:'))
        );
        $this->assertTrue(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'invoice_number_mismatch:'))
        );
        $this->assertContains('amount_match', $suggestions[0]['match_reasons']);
    }

    public function test_ksef_and_matching_invoice_stays_high(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Zgodne FV i KSeF',
            'product_price' => 365,
            'order_date' => now()->subDays(2),
            'invoice_number' => '21/7/2026',
            'ksef_number' => '7392137630-20260715-00FEFF400000-86',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Przedszkole Nr 3',
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-23',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'PRZEDSZKOLE, F-RA 21/7/2026 NR KSEF:7392137630-20260715-00FEFF-400000-86',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-ksef-high',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_HIGH, $suggestions[0]['confidence']);
        $this->assertFalse(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'invoice_number_mismatch:'))
        );
    }

    public function test_suggests_order_when_transfer_uses_cancelled_invoice_number_from_notes(): void
    {
        $order = FormOrder::create([
            'product_name' => 'Szkolenie po anulowanej FV',
            'product_price' => 365,
            'order_date' => now()->subDays(5),
            'invoice_number' => '140/7/2026',
            'notes' => 'Anulowano FV 138/7/2026, wystawiono poprawną',
            'invoice_payment_delay' => 14,
            'payment_mode' => FormOrder::PAYMENT_MODE_DEFERRED_INVOICE,
            'payment_status' => FormOrder::PAYMENT_STATUS_SUBMITTED,
            'buyer_name' => 'Szkoła Podstawowa w Parchowie',
        ]);

        $tx = new BankTransaction([
            'operation_date' => '2026-07-22',
            'amount' => 365,
            'currency' => 'PLN',
            'description' => 'SZKOLA PODSTAWOWA W PARCHOWIE 138/7/2026 PRZELEW',
            'is_incoming' => true,
            'fingerprint' => 'test-fp-cancelled-in-notes',
        ]);

        $suggestions = (new BankTransactionMatcher)->suggest($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertSame($order->id, $suggestions[0]['form_order_id']);
        $this->assertSame(BankTransactionMatch::CONFIDENCE_HIGH, $suggestions[0]['confidence']);
        $this->assertTrue(
            collect($suggestions[0]['match_reasons'])->contains(fn ($r) => str_starts_with((string) $r, 'invoice_number_in_notes:'))
        );
        $this->assertContains('amount_match', $suggestions[0]['match_reasons']);
    }
}
