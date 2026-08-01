<?php

namespace App\Services\Bank;

class PaymentTitleExtractor
{
    /**
     * @return array{
     *     invoice_numbers: list<string>,
     *     order_ids: list<int>,
     *     ksef_numbers: list<string>,
     *     nips: list<string>
     * }
     */
    public function extract(string $description): array
    {
        $invoiceNumbers = [];
        $orderIds = [];
        $ksefNumbers = [];
        $nips = [];

        $normalized = $this->normalizeWhitespace($description);

        foreach ($this->extractInvoiceNumbers($normalized) as $number) {
            $invoiceNumbers[] = $this->normalizeInvoiceNumber($number);
        }

        foreach ($this->extractOrderIds($normalized) as $orderId) {
            $orderIds[] = $orderId;
        }

        foreach ($this->extractKsefNumbers($normalized) as $ksef) {
            $normalizedKsef = $this->normalizeKsefNumber($ksef);
            if ($normalizedKsef !== null) {
                $ksefNumbers[] = $normalizedKsef;
            }
        }

        foreach ($this->extractNips($normalized) as $nip) {
            $nips[] = $nip;
        }

        return [
            'invoice_numbers' => array_values(array_unique(array_filter($invoiceNumbers))),
            'order_ids' => array_values(array_unique($orderIds)),
            'ksef_numbers' => array_values(array_unique(array_filter($ksefNumbers))),
            'nips' => array_values(array_unique(array_filter($nips))),
        ];
    }

    public function normalizeInvoiceNumber(string $number): string
    {
        $number = trim($number);
        $number = preg_replace('/\s+/', '', $number) ?? $number;

        if (preg_match('/^(\d{1,6})\/(\d{1,2})\/(\d{4})$/', $number, $matches)) {
            return ((int) $matches[1]).'/'.((int) $matches[2]).'/'.$matches[3];
        }

        return $number;
    }

    /**
     * @return list<string>
     */
    private function extractInvoiceNumbers(string $text): array
    {
        $found = [];

        $patterns = [
            // F-ra / F-RA / F / FV / faktura / Faktura nr / FAKTURA / F-rę nr / w/g
            '/\b(?:F[\-\s]?ra|F[\-\s]?RA|F[\-\s]?rę|FV|Faktura|FAKTURA|faktura)(?:\s*nr\.?|\s*NR\.?|\s*Nr\.?)?\s*[:\s#]*(\d{1,6}\s*\/\s*\d{1,2}\s*\/\s*\d{4})\b/iu',
            '/\b(?:zapł\.?\s*za\s*)?(?:F[\-\s]?rę|F[\-\s]?ra)\s*(?:nr\.?)?\s*[:\s#]*(\d{1,6}\s*\/\s*\d{1,2}\s*\/\s*\d{4})\b/iu',
            '/\bw\/g\s+(\d{1,6}\s*\/\s*\d{1,2}\s*\/\s*\d{4})\b/iu',
            // Bare "F 308/7/2026" or "F-ra 320/7/2026" already covered; also "nr156/7/2026"
            '/\bnr\.?\s*(\d{1,6}\s*\/\s*\d{1,2}\s*\/\s*\d{4})\b/iu',
            // Standalone invoice-looking number near transfer context (still extract all N/M/YYYY)
            '/(?<![A-Za-z0-9\/])(\d{1,6}\/\d{1,2}\/\d{4})(?![A-Za-z0-9\/])/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $found[] = $match;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<int>
     */
    private function extractOrderIds(string $text): array
    {
        $found = [];

        $patterns = [
            '/#\s*ID\s*[:\s]*(\d{1,10})\b/iu',
            '/\bzam[oó]wienie\s+ID\s*[:\s]*(\d{1,10})\b/iu',
            '/\border\s+ID\s*[:\s]*(\d{1,10})\b/iu',
            '/\bID\s*zam[oó]wienia\s*[:\s]*(\d{1,10})\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $id = (int) $match;
                    if ($id > 0) {
                        $found[] = $id;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function extractKsefNumbers(string $text): array
    {
        $found = [];

        // "NR KSEF:…", "KSeF:…", "KSeF …"
        if (preg_match_all('/(?:\bNR\s*)?\bKSeF\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\s]{10,80})/iu', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $normalized = $this->normalizeKsefNumber($match);
                if ($normalized !== null) {
                    $found[] = $normalized;
                }
            }
        }

        // Bare KSeF number from bank transfer descriptions, often without "KSeF" label.
        if (preg_match_all('/\b(\d{10}-\d{8}-[0-9A-F]{4,12}(?:-[0-9A-F]{2,12}){1,3})\b/iu', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $normalized = $this->normalizeKsefNumber($match);
                if ($normalized !== null) {
                    $found[] = $normalized;
                }
            }
        }

        if (preg_match_all('/\b(\d{10}-IZ\d{6}-[0-9A-F]{4,12}(?:-[0-9A-F]{2,12}){1,3})\b/iu', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $normalized = $this->normalizeKsefNumber($match);
                if ($normalized !== null) {
                    $found[] = $normalized;
                }
            }
        }

        return $found;
    }

    public function normalizeKsefNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        // Oficjalny nr KSeF: 10 cyfr NIP + data RRRRMMDD + 12 hex + 2 hex (35 znaków z myślnikami)
        if (preg_match('/\d{10}-\d{8}-[0-9A-F]{12}-[0-9A-F]{2}/', $normalized, $matches) === 1) {
            return $matches[0];
        }

        // Identyfikator zbiorczy (płatność za wiele FV): NIP-IZRRRRMM-12hex-2hex
        if (preg_match('/\d{10}-IZ\d{6}-[0-9A-F]{12}-[0-9A-F]{2}/', $normalized, $matches) === 1) {
            return $matches[0];
        }

        // Banki czasem wstawiają dodatkowy myślnik w segmencie technicznym (6+6 zamiast 12)
        $compact = preg_replace('/[^0-9A-F]/', '', $normalized) ?? '';
        if (preg_match('/^(\d{10})(\d{8})([0-9A-F]{12})([0-9A-F]{2})/', $compact, $matches) === 1) {
            return $matches[1].'-'.$matches[2].'-'.$matches[3].'-'.$matches[4];
        }
        if (preg_match('/^(\d{10})IZ(\d{6})([0-9A-F]{12})([0-9A-F]{2})/', $compact, $matches) === 1) {
            return $matches[1].'-IZ'.$matches[2].'-'.$matches[3].'-'.$matches[4];
        }

        // Fallback: dłuższy ciąg z myślnikami (np. niepełny / legacy zapis)
        if (strlen($normalized) < 15 || ! str_contains($normalized, '-')) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function extractNips(string $text): array
    {
        $found = [];

        if (preg_match_all('/\bNIP[:\s#]*([0-9][\s\-]?[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2})\b/iu', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $digits = preg_replace('/\D+/', '', $match) ?? '';
                if (strlen($digits) === 10) {
                    $found[] = $digits;
                }
            }
        }

        // Standalone 10-digit sequences labeled implicitly — only with NIP keyword above to avoid false positives

        return $found;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }
}
