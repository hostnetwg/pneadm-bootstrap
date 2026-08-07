<?php

namespace App\Services\Bank;

use App\Models\BankStatementImport;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BankStatementCoverageService
{
    /**
     * Luki w pokryciu okresów wyciągów (#Za okres) między importami
     * oraz opcjonalnie brak pokrycia do daty odniesienia (domyślnie dziś).
     *
     * @return list<array{from: string, to: string, days: int, trailing: bool}>
     */
    public function detectGaps(?CarbonInterface $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? Carbon::today())->startOfDay();

        $imports = BankStatementImport::query()
            ->whereNotNull('period_from')
            ->whereNotNull('period_to')
            ->orderBy('period_from')
            ->get(['period_from', 'period_to']);

        if ($imports->isEmpty()) {
            return [];
        }

        /** @var list<array{0: CarbonInterface, 1: CarbonInterface}> $ranges */
        $ranges = [];
        foreach ($imports as $import) {
            $from = $import->period_from->copy()->startOfDay();
            $to = $import->period_to->copy()->startOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }
            $ranges[] = [$from, $to];
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0]->timestamp <=> $b[0]->timestamp);

        /** @var list<array{0: CarbonInterface, 1: CarbonInterface}> $merged */
        $merged = [];
        foreach ($ranges as [$from, $to]) {
            if ($merged === []) {
                $merged[] = [$from, $to];

                continue;
            }

            $lastIndex = count($merged) - 1;
            [$lastFrom, $lastTo] = $merged[$lastIndex];

            // Nakładające się lub stykające się okresy (np. 31.01 i 01.02) = ciągłe pokrycie.
            if ($from->lte($lastTo->copy()->addDay())) {
                if ($to->gt($lastTo)) {
                    $merged[$lastIndex] = [$lastFrom, $to];
                }

                continue;
            }

            $merged[] = [$from, $to];
        }

        $gaps = [];
        for ($i = 0, $n = count($merged) - 1; $i < $n; $i++) {
            $gapFrom = $merged[$i][1]->copy()->addDay();
            $gapTo = $merged[$i + 1][0]->copy()->subDay();
            if ($gapFrom->lte($gapTo)) {
                $gaps[] = $this->makeGap($gapFrom, $gapTo, trailing: false);
            }
        }

        $lastTo = $merged[array_key_last($merged)][1];
        if ($lastTo->lt($asOf)) {
            $gapFrom = $lastTo->copy()->addDay();
            if ($gapFrom->lte($asOf)) {
                $gaps[] = $this->makeGap($gapFrom, $asOf, trailing: true);
            }
        }

        return $gaps;
    }

    /**
     * @param  list<array{from: string, to: string, days: int, trailing?: bool}>  $gaps
     */
    public function formatGapsSummary(array $gaps, int $maxShow = 5): string
    {
        if ($gaps === []) {
            return '';
        }

        $parts = [];
        foreach (array_slice($gaps, 0, $maxShow) as $gap) {
            $parts[] = $gap['from'] === $gap['to']
                ? $gap['from']
                : $gap['from'].' → '.$gap['to'].' ('.$gap['days'].' dni)';
        }

        $summary = implode('; ', $parts);
        $rest = count($gaps) - $maxShow;
        if ($rest > 0) {
            $summary .= '; … (+'.$rest.')';
        }

        return $summary;
    }

    /**
     * @return array{from: string, to: string, days: int, trailing: bool}
     */
    private function makeGap(CarbonInterface $from, CarbonInterface $to, bool $trailing): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => (int) $from->diffInDays($to) + 1,
            'trailing' => $trailing,
        ];
    }
}
