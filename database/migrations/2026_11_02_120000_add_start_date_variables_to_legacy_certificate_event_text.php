<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Na zaświadczeniu szkolenia ma być data rozpoczęcia („ukończył/a szkolenie zorganizowane w dniu …”).
 *
 * - Szablony 2, 3, 6–9 mają event_text bez zmiennych („zorganizowanym w dniu”). Do 2026-07-08 renderer sam
 *   dopisywał „ {start_date} r. [w wymiarze X minut, ]przez”; od 9a8c4b4 tekst idzie dosłownie i PDF nie ma
 *   daty. Dopisujemy tę końcówkę jako zmienne — tylko do tekstu, który nadal nie ma żadnej zmiennej.
 * - Szablony 1 i 5 używają {data_konca} — zamieniamy na {data_rozpoczecia}.
 * - Szablon 10 (kursy online) celowo bez zmian.
 */
return new class extends Migration
{
    private const LEGACY_TEMPLATE_IDS = [2, 3, 6, 7, 8, 9];

    private const END_DATE_TEMPLATE_IDS = [1, 5];

    private const SUFFIX = ' {data_rozpoczecia} r. {czas_trwania}przez';

    public function up(): void
    {
        $this->rewrite(self::LEGACY_TEMPLATE_IDS, function (string $eventText): ?string {
            if (trim($eventText) === '' || preg_match('/\{[a-z0-9_]+\}/i', $eventText)) {
                return null;
            }

            return rtrim($eventText).self::SUFFIX;
        });

        $this->rewrite(self::END_DATE_TEMPLATE_IDS, fn (string $eventText): ?string => str_contains($eventText, '{data_konca}')
            ? str_replace('{data_konca}', '{data_rozpoczecia}', $eventText)
            : null);
    }

    public function down(): void
    {
        $this->rewrite(self::LEGACY_TEMPLATE_IDS, fn (string $eventText): ?string => str_ends_with($eventText, self::SUFFIX)
            ? substr($eventText, 0, -strlen(self::SUFFIX))
            : null);

        $this->rewrite(self::END_DATE_TEMPLATE_IDS, fn (string $eventText): ?string => str_contains($eventText, '{data_rozpoczecia}')
            ? str_replace('{data_rozpoczecia}', '{data_konca}', $eventText)
            : null);
    }

    /**
     * @param  list<int>  $templateIds
     * @param  callable(string): ?string  $transform  null = bez zmian
     */
    private function rewrite(array $templateIds, callable $transform): void
    {
        $templates = DB::table('certificate_templates')->whereIn('id', $templateIds)->get(['id', 'config']);

        foreach ($templates as $template) {
            $config = json_decode((string) $template->config, true);
            if (! is_array($config) || ! is_array($config['blocks'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($config['blocks'] as $key => $block) {
                $eventText = $block['config']['event_text'] ?? null;
                if (($block['type'] ?? null) !== 'course_info' || ! is_string($eventText)) {
                    continue;
                }

                $new = $transform($eventText);
                if ($new !== null) {
                    $config['blocks'][$key]['config']['event_text'] = $new;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('certificate_templates')->where('id', $template->id)->update([
                    'config' => json_encode($config),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
