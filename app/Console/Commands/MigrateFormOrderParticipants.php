<?php

namespace App\Console\Commands;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MigrateFormOrderParticipants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formorders:migrate-participants 
                            {--dry-run : Symulacja bez zapisywania do bazy}
                            {--limit= : Limit rekordów do przetworzenia (domyślnie wszystkie)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jednorazowa migracja: z legacy kolumn form_orders (participant_name itd.) do form_order_participants — uruchamiaj tylko na starej bazie; po DROP kolumn polecenie kończy się komunikatem i nic nie robi';

    protected $stats = [
        'total' => 0,
        'migrated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('🚀 Start migracji uczestników z form_orders do form_order_participants');

        if (! Schema::hasColumn('form_orders', 'participant_name')) {
            $this->warn('Brak kolumny form_orders.participant_name — polecenie ma sens tylko przy starej strukturze tabeli.');

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('⚠️  TRYB SYMULACJI - żadne dane nie będą zapisane');
        }

        // Pobierz zamówienia z form_orders, które mają dane uczestnika
        $query = FormOrder::whereNotNull('participant_name')
            ->where('participant_name', '!=', '');

        if ($limit) {
            $query->limit($limit);
            $this->info("📊 Przetwarzanie maksymalnie {$limit} rekordów");
        }

        $orders = $query->get();
        $this->stats['total'] = $orders->count();

        $this->info("📋 Znaleziono {$this->stats['total']} zamówień do przetworzenia\n");

        // Progress bar
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($orders as $order) {
            try {
                // Sprawdź czy uczestnik już istnieje w nowej tabeli
                $existingParticipant = FormOrderParticipant::where('form_order_id', $order->id)
                    ->where('is_primary', 1)
                    ->first();

                if ($existingParticipant) {
                    $this->stats['skipped']++;
                    $progressBar->advance();

                    continue;
                }

                // Rozbij i znormalizuj imię i nazwisko
                $result = $this->parseAndNormalizeName($order->participant_name);

                if (! $result) {
                    $this->stats['errors']++;
                    $this->newLine();
                    $this->error("❌ ID {$order->id}: Nie udało się przetworzyć nazwy: '{$order->participant_name}'");
                    $progressBar->advance();

                    continue;
                }

                // Log przetwarzania
                $logData = [
                    'id' => $order->id,
                    'original' => $order->participant_name,
                    'firstname' => $result['firstname'],
                    'lastname' => $result['lastname'],
                    'email' => $order->participant_email,
                ];

                // Zapisz do nowej tabeli (jeśli nie tryb symulacji)
                if (! $isDryRun) {
                    FormOrderParticipant::create([
                        'form_order_id' => $order->id,
                        'participant_firstname' => $result['firstname'],
                        'participant_lastname' => $result['lastname'],
                        'participant_email' => $order->participant_email ?? '',
                        'is_primary' => 1,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);
                }

                $this->stats['migrated']++;

                // Wyświetl przykłady co 50 rekordów
                if ($this->stats['migrated'] % 50 == 0 && $this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->line("Przykład #{$this->stats['migrated']}:");
                    $this->table(
                        ['Pole', 'Wartość'],
                        [
                            ['ID', $logData['id']],
                            ['Oryginał', $logData['original']],
                            ['Imię', $logData['firstname']],
                            ['Nazwisko', $logData['lastname']],
                            ['Email', $logData['email']],
                        ]
                    );
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->newLine();
                $this->error("❌ Błąd dla ID {$order->id}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Podsumowanie
        $this->displaySummary($isDryRun);

        return Command::SUCCESS;
    }

    /**
     * Rozbija i normalizuje imię i nazwisko
     *
     * Zasady:
     * - Pierwsze słowo/słowa to imię, ostatnie to nazwisko
     * - Usuwa zbędne spacje
     * - Zamienia na format: Pierwsza Litera Wielka
     * - Obsługuje dwuczłonowe nazwiska z myślnikiem
     */
    protected function parseAndNormalizeName(string $fullName): ?array
    {
        // Usuń zbędne spacje
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        if (empty($fullName)) {
            return null;
        }

        // Rozbij na części
        $parts = explode(' ', $fullName);

        if (count($parts) < 1) {
            return null;
        }

        if (count($parts) == 1) {
            // Tylko jedno słowo - traktuj jako nazwisko
            return [
                'firstname' => $this->normalizeNamePart($parts[0]),
                'lastname' => $this->normalizeNamePart($parts[0]),
            ];
        }

        // Ostatnia część to nazwisko, reszta to imię/imiona
        $lastname = array_pop($parts);
        $firstname = implode(' ', $parts);

        return [
            'firstname' => $this->normalizeNamePart($firstname),
            'lastname' => $this->normalizeNamePart($lastname),
        ];
    }

    /**
     * Normalizuje część imienia/nazwiska
     *
     * Zasady:
     * - ADAM → Adam
     * - KOWALSKI → Kowalski
     * - KOWALSKA-NOWAK → Kowalska-Nowak
     * - jan maria → Jan Maria
     */
    protected function normalizeNamePart(string $part): string
    {
        // Usuń zbędne spacje
        $part = trim($part);

        // Rozbij po myślniku (dla nazwisk dwuczłonowych)
        $segments = explode('-', $part);

        // Normalizuj każdy segment
        $normalized = array_map(function ($segment) {
            // Rozbij po spacjach (dla imion złożonych)
            $words = explode(' ', $segment);

            $capitalizedWords = array_map(function ($word) {
                // Konwertuj na małe litery, potem pierwsza wielka
                return mb_convert_case(mb_strtolower($word), MB_CASE_TITLE, 'UTF-8');
            }, $words);

            return implode(' ', $capitalizedWords);
        }, $segments);

        // Połącz z powrotem przez myślnik
        return implode('-', $normalized);
    }

    /**
     * Wyświetla podsumowanie migracji
     */
    protected function displaySummary(bool $isDryRun): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    📊 PODSUMOWANIE                        ');
        $this->info('═══════════════════════════════════════════════════════════');

        $this->table(
            ['Statystyka', 'Liczba'],
            [
                ['Znalezione rekordy', $this->stats['total']],
                ['Zmigrowane', $this->stats['migrated']],
                ['Pominięte (już istnieją)', $this->stats['skipped']],
                ['Błędy', $this->stats['errors']],
            ]
        );

        if ($isDryRun) {
            $this->warn("\n⚠️  To była SYMULACJA - żadne dane nie zostały zapisane");
            $this->info('💡 Uruchom bez --dry-run aby zapisać dane do bazy');
        } else {
            if ($this->stats['migrated'] > 0) {
                $this->info("\n✅ Migracja zakończona pomyślnie!");
                $this->info("📝 Zmigrowano {$this->stats['migrated']} uczestników");
            } else {
                $this->warn("\n⚠️  Nie zmigrowano żadnych rekordów");
            }
        }

        if ($this->stats['errors'] > 0) {
            $this->error("\n❌ Wystąpiły błędy: {$this->stats['errors']}");
            $this->warn('Sprawdź logi powyżej');
        }

        $this->info('═══════════════════════════════════════════════════════════');
    }
}
