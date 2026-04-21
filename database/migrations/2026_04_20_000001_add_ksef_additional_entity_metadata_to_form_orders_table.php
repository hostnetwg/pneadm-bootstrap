<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KSeF / iFirma — ETAP 1 (Podmiot3).
 *
 * Dodaje 4+1 pola metadanych sterujących traktowaniem istniejących danych
 * recipient_* jako dodatkowego podmiotu (Podmiot3 / OdbiorcaNaFakturze).
 *
 * Nie dubluje danych recipient_* i nie zmienia istniejących kolumn.
 * Szczegóły logiki, zasady fail-fast oraz kanoniczne kody ról opisuje
 * docs/KSEF_FORM_ORDERS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->string('ksef_entity_source', 20)
                ->default('none')
                ->after('ksef_error')
                ->comment('KSeF Podmiot3 (ETAP 1): źródło danych dodatkowego podmiotu. Dozwolone: none | recipient. none = nie używamy Podmiotu3; recipient = Podmiot3 budowany z kolumn recipient_*. ETAP 1 nie obsługuje wariantu custom.');

            $table->string('ksef_additional_entity_role', 30)
                ->nullable()
                ->after('ksef_entity_source')
                ->comment('KSeF Podmiot3 (ETAP 1): kanoniczny kod roli (lowercase). ETAP 1 obsługuje tylko odbiorca → iFirma OdbiorcaNaFakturze.Rola=ODBIORCA. Inne wartości (np. jst_recipient) są zapisywane, ale blokują wystawienie faktury z Podmiotem3 do czasu potwierdzonego mapowania iFirma (ETAP 2). Patrz docs/KSEF_FORM_ORDERS.md.');

            $table->string('ksef_additional_entity_id_type', 20)
                ->nullable()
                ->after('ksef_additional_entity_role')
                ->comment('KSeF Podmiot3 (ETAP 1): typ identyfikatora. ETAP 1 obsługuje wyłącznie NIP (lub wartość NULL → fallback do recipient_nip). Pozostałe typy są zapisywane, ale blokują wystawienie faktury z Podmiotem3 (fail-fast).');

            $table->string('ksef_additional_entity_identifier', 50)
                ->nullable()
                ->after('ksef_additional_entity_id_type')
                ->comment('KSeF Podmiot3 (ETAP 1): wartość identyfikatora. Przy ksef_additional_entity_id_type=NIP nadpisuje recipient_nip w payloadzie iFirma (po normalizacji do cyfr). Pusty + id_type=NIP ⇒ używamy recipient_nip. Nigdy nie jest używana jako cichy fallback dla innych id_type.');

            $table->text('ksef_admin_note')
                ->nullable()
                ->after('ksef_additional_entity_identifier')
                ->comment('KSeF Podmiot3: wewnętrzna notatka administratora (kontekst, decyzja, tickety). Nie wchodzi do payloadu iFirma i nie jest wysyłana do KSeF.');

            $table->index('ksef_entity_source', 'idx_form_orders_ksef_entity_source');
        });

        // Backfill rekordów historycznych: odwzorowujemy dokładnie warunek
        // budowania bloku Kontrahent.OdbiorcaNaFakturze w FormOrdersController
        // (createIfirmaInvoiceWithReceiver/WithKsef), tj. recipient_name +
        // recipient_postal_code + recipient_city niepuste. Tylko dla takich rekordów
        // ustawiamy ksef_entity_source='recipient' i ksef_additional_entity_role='odbiorca',
        // żeby zachować pełną zgodność wsteczną dla kolejnego wystawienia faktury.
        DB::connection('mysql')->table('form_orders')
            ->whereNotNull('recipient_name')
            ->where('recipient_name', '!=', '')
            ->whereNotNull('recipient_postal_code')
            ->where('recipient_postal_code', '!=', '')
            ->whereNotNull('recipient_city')
            ->where('recipient_city', '!=', '')
            ->update([
                'ksef_entity_source' => 'recipient',
                'ksef_additional_entity_role' => 'odbiorca',
            ]);
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->dropIndex('idx_form_orders_ksef_entity_source');
            $table->dropColumn([
                'ksef_admin_note',
                'ksef_additional_entity_identifier',
                'ksef_additional_entity_id_type',
                'ksef_additional_entity_role',
                'ksef_entity_source',
            ]);
        });
    }
};
