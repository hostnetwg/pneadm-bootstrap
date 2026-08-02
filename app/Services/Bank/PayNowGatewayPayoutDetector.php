<?php

namespace App\Services\Bank;

use App\Models\BankTransaction;

/**
 * Wykrywa zbiorcze wypłaty rozliczeniowe PayNow (mElements) na wyciągu mBank.
 *
 * Tylko pozytywne wzorce — NIE używa braku FV/KSeF (klienci często ich nie wpisują).
 */
class PayNowGatewayPayoutDetector
{
    public function isPayNowGatewayPayout(BankTransaction $transaction): bool
    {
        if (! $transaction->is_incoming) {
            return false;
        }

        $haystack = $this->normalize(
            trim((string) ($transaction->description ?? '').' '.(string) ($transaction->account_label ?? ''))
        );

        if ($haystack === '') {
            return false;
        }

        // Operator PayNow / mElements
        if (str_contains($haystack, 'MELEMENTS')) {
            return true;
        }

        // Typowy opis wypłaty dziennej: „WYPŁATA ŚRODKÓW NR PON-MWB-…”
        $hasPayoutPhrase = str_contains($haystack, 'WYPLATA SRODKOW');
        $hasPayNowRef = (bool) preg_match('/\bPON-[A-Z0-9-]+/', $haystack);

        return $hasPayoutPhrase && $hasPayNowRef;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper($value, 'UTF-8');
        $map = [
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
        ];

        return strtr($value, $map);
    }
}
