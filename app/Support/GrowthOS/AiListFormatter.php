<?php

namespace App\Support\GrowthOS;

final class AiListFormatter
{
    public static function lineBreaks(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $value = self::breakNumbered($value);
        $value = self::breakBullets($value);

        return $value;
    }

    private static function breakNumbered(string $value): string
    {
        if (! preg_match_all('/(?<!\d)(\d{1,2})(?:\.|\))(?=\s+\S)/u', $value, $matches, PREG_OFFSET_CAPTURE)) {
            return $value;
        }

        /** @var list<array{n: int, offset: int}> $hits */
        $hits = [];
        foreach ($matches[1] as $index => $number) {
            $hits[] = [
                'n' => (int) $number[0],
                'offset' => $matches[0][$index][1],
            ];
        }

        /** @var list<list<array{n: int, offset: int}>> $runs */
        $runs = [];
        $run = [];
        $expected = 1;
        foreach ($hits as $hit) {
            if ($hit['n'] === $expected) {
                $run[] = $hit;
                $expected++;

                continue;
            }

            if (count($run) >= 2) {
                $runs[] = $run;
            }

            if ($hit['n'] === 1) {
                $run = [$hit];
                $expected = 2;

                continue;
            }

            $run = [];
            $expected = 1;
        }

        if (count($run) >= 2) {
            $runs[] = $run;
        }

        $offsets = [];
        foreach ($runs as $items) {
            foreach ($items as $hit) {
                $offsets[] = $hit['offset'];
            }
        }

        return self::breakBefore($value, $offsets);
    }

    private static function breakBullets(string $value): string
    {
        $offsets = [];

        if (preg_match_all('/(?<!\S)•(?=\s+\S)/u', $value, $dots, PREG_OFFSET_CAPTURE) && count($dots[0]) >= 2) {
            foreach ($dots[0] as $marker) {
                $offsets[] = $marker[1];
            }
        }

        if (preg_match_all('/(?:^|[.!?]\s+)([-–])(?=\s+\S)/u', $value, $dashes, PREG_OFFSET_CAPTURE) && count($dashes[0]) >= 2) {
            foreach ($dashes[1] as $marker) {
                $offsets[] = $marker[1];
            }
        }

        return self::breakBefore($value, $offsets);
    }

    /**
     * @param  list<int>  $offsets
     */
    private static function breakBefore(string $value, array $offsets): string
    {
        rsort($offsets);
        foreach ($offsets as $offset) {
            if ($offset <= 0) {
                continue;
            }

            $before = $offset - 1;
            while ($before >= 0 && ($value[$before] === ' ' || $value[$before] === "\t")) {
                $before--;
            }

            if ($before < 0 || $value[$before] === "\n") {
                continue;
            }

            $value = substr($value, 0, $before + 1)."\n".substr($value, $offset);
        }

        return $value;
    }
}
