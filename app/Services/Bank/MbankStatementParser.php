<?php

namespace App\Services\Bank;

use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

class MbankStatementParser
{
    public const HEADER_MARKER = '#Data operacji;#Opis operacji;#Rachunek;#Kategoria;#Kwota;';

    /**
     * @return array{
     *     period_from: ?string,
     *     period_to: ?string,
     *     rows: list<array{
     *         operation_date: string,
     *         description: string,
     *         account_label: ?string,
     *         category: ?string,
     *         amount: float,
     *         currency: string,
     *         counterparty_account: ?string,
     *         is_incoming: bool,
     *         fingerprint: string
     *     }>
     * }
     */
    public function parse(string $contents): array
    {
        $contents = $this->stripBom($contents);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];

        $periodFrom = null;
        $periodTo = null;
        $headerIndex = null;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '#Za okres:') || str_starts_with($trimmed, '#Za okres')) {
                // Next non-empty line usually: 01.01.2025;01.08.2026;
                for ($j = $index + 1; $j < min($index + 4, count($lines)); $j++) {
                    $periodLine = trim($lines[$j]);
                    if ($periodLine === '') {
                        continue;
                    }
                    if (preg_match('/(\d{2}\.\d{2}\.\d{4});(\d{2}\.\d{2}\.\d{4})/', $periodLine, $m)) {
                        $periodFrom = Carbon::createFromFormat('d.m.Y', $m[1])?->toDateString();
                        $periodTo = Carbon::createFromFormat('d.m.Y', $m[2])?->toDateString();
                    }
                    break;
                }
            }

            if ($this->isHeaderLine($trimmed)) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new InvalidArgumentException('Nie znaleziono nagłówka CSV mBank (#Data operacji;...).');
        }

        $csvBody = implode("\n", array_slice($lines, $headerIndex + 1));
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Nie udało się otworzyć bufora CSV.');
        }

        fwrite($handle, $csvBody);
        rewind($handle);

        $rows = [];
        while (($cols = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if ($cols === [null] || $cols === false) {
                continue;
            }

            $dateRaw = trim((string) ($cols[0] ?? ''));
            if ($dateRaw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
                continue;
            }

            $description = $this->sanitizeUtf8(trim((string) ($cols[1] ?? '')));
            $accountLabel = $this->nullableTrim($cols[2] ?? null);
            $category = $this->nullableTrim($cols[3] ?? null);
            $amountRaw = trim((string) ($cols[4] ?? ''));

            if ($amountRaw === '') {
                continue;
            }

            [$amount, $currency] = $this->parseAmount($amountRaw);
            $isIncoming = $amount > 0;
            $counterparty = $this->extractCounterpartyAccount($description);
            $fingerprint = $this->fingerprint($dateRaw, $amount, $description);

            $rows[] = [
                'operation_date' => $dateRaw,
                'description' => $description,
                'account_label' => $accountLabel,
                'category' => $category,
                'amount' => $amount,
                'currency' => $currency,
                'counterparty_account' => $counterparty,
                'is_incoming' => $isIncoming,
                'fingerprint' => $fingerprint,
            ];
        }

        fclose($handle);

        return [
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'rows' => $rows,
        ];
    }

    public function parseFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Nie można odczytać pliku: {$path}");
        }

        return $this->parse($contents);
    }

    /**
     * @return array{0: float, 1: string}
     */
    public function parseAmount(string $raw): array
    {
        $currency = 'PLN';
        if (preg_match('/\b([A-Z]{3})\b/', strtoupper($raw), $m)) {
            $currency = $m[1];
        }

        $numeric = preg_replace('/[^0-9,\.\-]/', '', $raw) ?? '';
        // Polish format: 1 234,56 or 1234,56 — remove thousand dots/spaces already stripped; comma = decimal
        if (str_contains($numeric, ',') && str_contains($numeric, '.')) {
            // Ambiguous: treat last comma as decimal (PL style with thousand dots rare in mBank after strip)
            $numeric = str_replace('.', '', $numeric);
            $numeric = str_replace(',', '.', $numeric);
        } elseif (str_contains($numeric, ',')) {
            $numeric = str_replace(',', '.', $numeric);
        }

        if ($numeric === '' || $numeric === '-' || $numeric === '.') {
            throw new InvalidArgumentException("Niepoprawna kwota: {$raw}");
        }

        return [(float) $numeric, $currency];
    }

    public function fingerprint(string $operationDate, float $amount, string $description): string
    {
        $normalizedDescription = mb_strtolower(preg_replace('/\s+/u', ' ', trim($description)) ?? '');
        $payload = $operationDate.'|'.number_format($amount, 2, '.', '').'|'.$normalizedDescription;

        return hash('sha256', $payload);
    }

    public function extractCounterpartyAccount(string $description): ?string
    {
        // Prefer trailing 26-digit Polish account number
        if (preg_match_all('/\b(\d{26})\b/', $description, $matches)) {
            $candidates = $matches[1];

            return end($candidates) ?: null;
        }

        return null;
    }

    /**
     * Heurystyczny podział opisu mBank na nadawcę i tytuł (niepewny — UI oznacza jako szacunek).
     *
     * @return array{
     *     transfer_type: ?string,
     *     counterparty_account: ?string,
     *     sender_estimate: ?string,
     *     title_estimate: ?string,
     *     body: string
     * }
     */
    public function splitDescriptionParts(string $description): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
        $account = $this->extractCounterpartyAccount($raw);

        $work = $raw;
        if ($account !== null) {
            $work = preg_replace('/\s*'.preg_quote($account, '/').'\s*$/', '', $work) ?? $work;
            $work = trim($work);
        }

        $transferType = null;
        if (preg_match('/\b(PRZELEW\s+(?:ZEWN[ĘE]TRZNY|WEWN[ĘE]TRZNY)\s+PRZYCHODZ[ĄA]CY)\b/iu', $work, $m)) {
            $transferType = mb_strtoupper(preg_replace('/\s+/u', ' ', $m[1]) ?? $m[1], 'UTF-8');
            $work = trim(str_replace($m[1], '', $work));
            $work = trim(preg_replace('/\s+/u', ' ', $work) ?? $work);
        }

        $sender = $work;
        $title = null;

        // 1) Przecinek przed markerem tytułu / numerem FV
        if (preg_match(
            '/^(.*?),\s*((?:zapł\.?\s*za\s+)?(?:F[\-\s]?r[aę]|F[\-\s]?RA|FV|Faktura|FAKTURA|faktura)(?:\s*nr\.?)?\s*.*|\d{1,6}\/\d{1,2}\/\d{4}\b.*)$/iu',
            $work,
            $m
        )) {
            $sender = trim($m[1]);
            $title = trim($m[2]);
        } elseif (preg_match(
            '/^(.*?)(?=\s(?:zapł\.?\s*za\s+)?(?:F[\-\s]?r[aę]|FV|Faktura|FAKTURA|faktura)(?:\s*nr\.?)?\s)/iu',
            $work,
            $m
        ) && trim($m[1]) !== '' && strlen(trim($m[1])) >= 3) {
            // 2) Bez przecinka — tytuł od słowa F-ra / faktura
            $sender = trim($m[1]);
            $title = trim(mb_substr($work, mb_strlen($m[1])));
        }

        // Obetnij typową powtórkę adresu/nazwy w tytule (drugi blok po numerze FV)
        if ($title !== null) {
            if (preg_match(
                '/^((?:zapł\.?\s*za\s+)?(?:F[\-\s]?r[aę]|F[\-\s]?RA|FV|Faktura|FAKTURA|faktura)(?:\s*nr\.?)?\s*[^\d]*\d{1,6}\/\d{1,2}\/\d{4}(?:\s*z\s*\d{2}\.\d{2}\.\d{4})?)(.*)$/iu',
                $title,
                $tm
            )) {
                $core = trim($tm[1]);
                $rest = trim($tm[2]);
                // jeśli reszta wygląda jak powtórka adresu (zaczyna się od ul./kodu/fragmentu nazwy) — pomiń
                if ($rest !== '' && preg_match('/^(?:\d{2}-\d{3}\b|ul\.|UL\.|[A-ZĄĆĘŁŃÓŚŹŻ])/u', $rest)) {
                    $title = $core;
                }
            } elseif (preg_match('/^(\d{1,6}\/\d{1,2}\/\d{4}\b(?:\s*z\s*\d{2}\.\d{2}\.\d{4})?)(.*)$/u', $title, $tm)) {
                $core = trim($tm[1]);
                $rest = trim($tm[2]);
                if ($rest !== '' && preg_match('/^(?:\d{2}-\d{3}\b|ul\.|UL\.|[A-ZĄĆĘŁŃÓŚŹŻ])/u', $rest)) {
                    $title = $core;
                }
            }
        }

        return [
            'transfer_type' => $transferType,
            'counterparty_account' => $account,
            'sender_estimate' => $sender !== '' ? $sender : null,
            'title_estimate' => ($title !== null && $title !== '') ? $title : null,
            'body' => $work,
        ];
    }

    private function isHeaderLine(string $line): bool
    {
        $compact = preg_replace('/\s+/', '', $line) ?? '';
        $expected = preg_replace('/\s+/', '', self::HEADER_MARKER) ?? '';

        return str_starts_with($compact, rtrim($expected, ';'))
            || str_contains($compact, '#Dataoperacji;#Opisoperacji;#Rachunek;#Kategoria;#Kwota');
    }

    private function stripBom(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        return $contents;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($this->sanitizeUtf8((string) $value));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * CSV mBank bywa mieszanym kodowaniem — invalid UTF-8 psuje json_encode / regex /u w UI.
     */
    private function sanitizeUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
    }
}
