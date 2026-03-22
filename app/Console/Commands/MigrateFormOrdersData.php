<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateFormOrdersData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:form-orders-data 
                            {--fresh : Wyczyść tabelę form_orders przed migracją}
                            {--limit= : Ogranicz liczbę migrowanych rekordów (do testów)}
                            {--skip= : Pomiń pierwszych N rekordów}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migracja danych z tabeli zamowienia_FORM (certgen) do form_orders (pneadm)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Rozpoczynam migrację danych z zamowienia_FORM do form_orders...');
        $this->newLine();

        // Sprawdzenie czy tabela docelowa istnieje
        try {
            DB::connection('mysql')->table('form_orders')->count();
        } catch (Exception $e) {
            $this->error('❌ Tabela form_orders nie istnieje w bazie pneadm!');
            $this->error('   Uruchom najpierw migrację: php artisan migrate');

            return 1;
        }

        // Sprawdzenie połączenia z bazą certgen
        try {
            $oldCount = DB::connection('mysql_certgen')->table('zamowienia_FORM')->count();
            $this->info("✅ Połączono z bazą certgen. Znaleziono {$oldCount} rekordów.");
        } catch (Exception $e) {
            $this->error('❌ Nie można połączyć się z bazą certgen!');
            $this->error('   Sprawdź konfigurację połączenia w pliku .env');

            return 1;
        }

        // Opcja --fresh: wyczyszczenie tabeli docelowej
        if ($this->option('fresh')) {
            if ($this->confirm('⚠️  Czy na pewno chcesz wyczyścić tabelę form_orders przed migracją?', false)) {
                DB::connection('mysql')->table('form_orders')->truncate();
                $this->warn('🗑️  Wyczyszczono tabelę form_orders.');
            } else {
                $this->info('Anulowano czyszczenie tabeli.');

                return 0;
            }
        }

        // Sprawdzenie czy są już jakieś dane
        $existingCount = DB::connection('mysql')->table('form_orders')->count();
        if ($existingCount > 0) {
            $this->warn("⚠️  Tabela form_orders zawiera już {$existingCount} rekordów.");
            if (! $this->confirm('Czy kontynuować migrację? (może to spowodować duplikaty)', false)) {
                $this->info('Anulowano migrację.');

                return 0;
            }
        }

        // Pobranie danych ze starej tabeli
        $this->info('📥 Pobieram dane z tabeli zamowienia_FORM...');

        $query = DB::connection('mysql_certgen')->table('zamowienia_FORM');

        // Opcja --skip
        if ($skip = $this->option('skip')) {
            $query->skip((int) $skip);
            $this->info("⏭️  Pomijam pierwszych {$skip} rekordów.");
        }

        // Opcja --limit
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $this->info("🔢 Ograniczam do {$limit} rekordów.");
        }

        $oldOrders = $query->orderBy('id')->get();

        $this->info("📊 Pobrano {$oldOrders->count()} rekordów do migracji.");
        $this->newLine();

        // Rozpoczęcie migracji z paskiem postępu
        $bar = $this->output->createProgressBar($oldOrders->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Rozpoczynam...');
        $bar->start();

        $migrated = 0;
        $errors = 0;
        $errorDetails = [];

        DB::connection('mysql')->beginTransaction();

        try {
            foreach ($oldOrders as $oldOrder) {
                try {
                    // Funkcja pomocnicza do walidacji dat
                    $validateDate = function ($date) {
                        if (empty($date)) {
                            return null;
                        }

                        // Sprawdź czy data jest nieprawidłowa
                        // Przypadki: -0001-11-30, 0000-00-00, NULL itp.
                        if (strpos($date, '-0001') !== false) {
                            return null;
                        }
                        if (strpos($date, '0000-00-00') !== false) {
                            return null;
                        }

                        try {
                            $carbonDate = \Carbon\Carbon::parse($date);
                            // Sprawdź czy rok jest w rozsądnym zakresie
                            if ($carbonDate->year < 1970 || $carbonDate->year > 2100) {
                                return null;
                            }

                            return $carbonDate->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            return null;
                        }
                    };

                    // Używamy Query Builder zamiast Eloquent, aby mieć pełną kontrolę nad timestampami
                    DB::connection('mysql')->table('form_orders')->insert([
                        // Zachowanie oryginalnego ID
                        'id' => $oldOrder->id,

                        // Identyfikatory
                        'ident' => $oldOrder->ident ?? null,
                        'ptw' => $oldOrder->PTW ?? null,

                        // Dane zamówienia
                        'order_date' => $validateDate($oldOrder->data_zamowienia),

                        // Produkt/szkolenie
                        'product_id' => $oldOrder->produkt_id ?? null,
                        'product_name' => $oldOrder->produkt_nazwa ?? null,
                        'product_price' => $oldOrder->produkt_cena ?? null,
                        'product_description' => $oldOrder->produkt_opis ?? null,

                        // Integracja z Publigo
                        'publigo_product_id' => $oldOrder->idProdPubligo ?? null,
                        'publigo_price_id' => $oldOrder->price_idProdPubligo ?? null,
                        'publigo_sent' => $oldOrder->publigo_sent ?? 0,
                        'publigo_sent_at' => $validateDate($oldOrder->publigo_sent_at),

                        // Dane zamawiającego (kontaktowe)
                        'orderer_name' => $oldOrder->zam_nazwa ?? null,
                        'orderer_address' => $oldOrder->zam_adres ?? null,
                        'orderer_postal_code' => $oldOrder->zam_kod ?? null,
                        'orderer_city' => $oldOrder->zam_poczta ?? null,
                        'orderer_phone' => $oldOrder->zam_tel ?? null,
                        'orderer_email' => $oldOrder->zam_email ?? null,

                        // Dane nabywcy (do faktury)
                        'buyer_name' => $oldOrder->nab_nazwa ?? null,
                        'buyer_address' => $oldOrder->nab_adres ?? null,
                        'buyer_postal_code' => $oldOrder->nab_kod ?? null,
                        'buyer_city' => $oldOrder->nab_poczta ?? null,
                        'buyer_nip' => $oldOrder->nab_nip ?? null,

                        // Dane odbiorcy
                        'recipient_name' => $oldOrder->odb_nazwa ?? null,
                        'recipient_address' => $oldOrder->odb_adres ?? null,
                        'recipient_postal_code' => $oldOrder->odb_kod ?? null,
                        'recipient_city' => $oldOrder->odb_poczta ?? null,
                        'recipient_nip' => $oldOrder->odb_nip ?? null,

                        // Dane do faktury
                        'invoice_number' => $oldOrder->nr_fakury ?? null,
                        'invoice_notes' => $oldOrder->faktura_uwagi ?? null,
                        'invoice_payment_delay' => $oldOrder->faktura_odroczenie ?? null,

                        // Status i notatki
                        'status_completed' => $oldOrder->status_zakonczone ?? 0,
                        'notes' => $oldOrder->notatki ?? null,
                        'updated_manually_at' => $validateDate($oldOrder->data_update),

                        // Dane techniczne
                        'ip_address' => $oldOrder->ip ?? null,
                        'fb_source' => $oldOrder->fb ?? null,

                        // Timestamps (zachowujemy oryginalne z data_zamowienia)
                        'created_at' => $validateDate($oldOrder->data_zamowienia) ?? now()->format('Y-m-d H:i:s'),
                        'updated_at' => $validateDate($oldOrder->data_update) ?? $validateDate($oldOrder->data_zamowienia) ?? now()->format('Y-m-d H:i:s'),
                    ]);

                    $kontoEmail = isset($oldOrder->konto_email) ? trim((string) $oldOrder->konto_email) : '';
                    $kontoName = isset($oldOrder->konto_imie_nazwisko)
                        ? trim(preg_replace('/\s+/', ' ', (string) $oldOrder->konto_imie_nazwisko))
                        : '';
                    if ($kontoEmail !== '' && $kontoName !== '') {
                        $nameParts = explode(' ', $kontoName);
                        $lastname = count($nameParts) > 1 ? array_pop($nameParts) : $nameParts[0];
                        $firstname = count($nameParts) ? implode(' ', $nameParts) : $lastname;
                        DB::connection('mysql')->table('form_order_participants')->insert([
                            'form_order_id' => $oldOrder->id,
                            'participant_firstname' => $firstname,
                            'participant_lastname' => $lastname,
                            'participant_email' => $kontoEmail,
                            'is_primary' => 1,
                            'deleted_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $migrated++;
                    $bar->setMessage("Migracja rekordu ID: {$oldOrder->id}");
                } catch (Exception $e) {
                    $errors++;
                    $errorDetails[] = [
                        'id' => $oldOrder->id,
                        'error' => $e->getMessage(),
                    ];
                    $bar->setMessage("⚠️  Błąd przy ID: {$oldOrder->id}");
                }

                $bar->advance();
            }

            DB::connection('mysql')->commit();

            $bar->setMessage('Zakończono!');
            $bar->finish();
            $this->newLine(2);

            // Podsumowanie
            $this->info('✅ Migracja zakończona!');
            $this->newLine();
            $this->table(
                ['Statystyka', 'Wartość'],
                [
                    ['Pomyślnie zmigrowane', $migrated],
                    ['Błędy', $errors],
                    ['Łącznie przetworzonych', $oldOrders->count()],
                ]
            );

            // Wyświetlenie błędów jeśli wystąpiły
            if ($errors > 0) {
                $this->newLine();
                $this->warn("⚠️  Wystąpiło {$errors} błędów podczas migracji:");
                $this->table(
                    ['ID rekordu', 'Błąd'],
                    array_map(fn ($err) => [$err['id'], $err['error']], $errorDetails)
                );
            }

            // Weryfikacja
            $this->newLine();
            $newCount = DB::connection('mysql')->table('form_orders')->count();
            $this->info("📊 Tabela form_orders zawiera teraz {$newCount} rekordów.");

            return 0;

        } catch (Exception $e) {
            DB::connection('mysql')->rollBack();

            $bar->finish();
            $this->newLine(2);

            $this->error('❌ Wystąpił krytyczny błąd podczas migracji!');
            $this->error('   '.$e->getMessage());
            $this->warn('🔄 Wszystkie zmiany zostały cofnięte (rollback).');

            return 1;
        }
    }
}
