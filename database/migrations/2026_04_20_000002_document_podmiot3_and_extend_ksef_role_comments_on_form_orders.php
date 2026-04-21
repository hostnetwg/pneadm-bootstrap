<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KSeF / iFirma — ETAP 2 (rozszerzenie o JST rola 8 i Członek grupy VAT rola 9).
 *
 * Ta migracja jest CZYSTO DOKUMENTACYJNA — nie zmienia struktury tabeli ani
 * nie dotyka żadnych wierszy. Jedyne zmiany to natywne komentarze MySQL:
 *
 * 1) Kolumny `recipient_*` dostają komentarze wyjaśniające, że w kontekście
 *    KSeF/iFirma przechowują dane Podmiotu3 (dodatkowego podmiotu na fakturze).
 *    Nazwa `recipient_*` pozostaje dla zgodności wstecznej z kontraktem
 *    publicznego formularza pnedu.pl (patrz wariant C w docs/KSEF_FORM_ORDERS.md).
 *
 * 2) Komentarz `ksef_additional_entity_role` zostaje zaktualizowany do zakresu
 *    ETAP 2 (dodano `vat_group_member`, rozszerzono listę obsługiwanych ról).
 *
 * W down() przywracamy dokładne oryginalne komentarze z migracji:
 *  - 2025_10_17_205515_create_form_orders_table.php (recipient_*),
 *  - 2025_10_17_211833_increase_column_sizes_in_form_orders_table.php
 *    (recipient_postal_code, recipient_nip — tam zmieniono długość, ale
 *    komentarz został stary; tu go zachowujemy przez ponowne zastosowanie).
 *  - 2026_04_20_000001_add_ksef_additional_entity_metadata_to_form_orders_table.php
 *    (ksef_additional_entity_role).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->string('recipient_name', 500)
                ->nullable()
                ->comment('Dane Podmiotu3 (dodatkowego podmiotu na fakturze w rozumieniu iFirma / KSeF) — nazwa. Historycznie kolumna nazywa się recipient_name, bo pierwotnie pełniła rolę tylko „odbiorca”; od ETAP 2 (JST, grupa VAT) jest rozumiana jako uniwersalne miejsce na dane Podmiotu3. Rola konfigurowana przez ksef_additional_entity_role. Kontrakt zapisu w pnedu.pl pozostaje niezmieniony.')
                ->change();

            $table->string('recipient_address', 500)
                ->nullable()
                ->comment('Dane Podmiotu3 — ulica i numer. Patrz komentarz recipient_name.')
                ->change();

            $table->string('recipient_postal_code', 50)
                ->nullable()
                ->comment('Dane Podmiotu3 — kod pocztowy. Patrz komentarz recipient_name.')
                ->change();

            $table->string('recipient_city', 255)
                ->nullable()
                ->comment('Dane Podmiotu3 — miejscowość. Patrz komentarz recipient_name.')
                ->change();

            $table->string('recipient_nip', 50)
                ->nullable()
                ->comment('Dane Podmiotu3 — NIP. Może być nadpisany przez ksef_additional_entity_identifier (gdy ksef_additional_entity_id_type=NIP). Patrz komentarz recipient_name.')
                ->change();

            $table->string('ksef_additional_entity_role', 30)
                ->nullable()
                ->comment('KSeF Podmiot3 (ETAP 2): kanoniczny kod roli (lowercase). Obsługiwane w mapowaniu iFirma: odbiorca → OdbiorcaNaFakturze.Rola=ODBIORCA, jst_recipient → JEDN_SAMORZADU_TERYT (KSeF rola 8), vat_group_member → CZLONEK_GRUPY_VAT (KSeF rola 9). Pozostałe wartości zostają zapisane, ale blokują wystawienie faktury (fail-fast). Patrz docs/KSEF_FORM_ORDERS.md.')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('form_orders', function (Blueprint $table) {
            $table->string('recipient_name', 500)
                ->nullable()
                ->comment('Nazwa odbiorcy (odb_nazwa)')
                ->change();

            $table->string('recipient_address', 500)
                ->nullable()
                ->comment('Adres odbiorcy (odb_adres)')
                ->change();

            $table->string('recipient_postal_code', 50)
                ->nullable()
                ->comment('Kod pocztowy odbiorcy (odb_kod)')
                ->change();

            $table->string('recipient_city', 255)
                ->nullable()
                ->comment('Miejscowość odbiorcy (odb_poczta)')
                ->change();

            $table->string('recipient_nip', 50)
                ->nullable()
                ->comment('NIP odbiorcy (odb_nip)')
                ->change();

            $table->string('ksef_additional_entity_role', 30)
                ->nullable()
                ->comment('KSeF Podmiot3 (ETAP 1): kanoniczny kod roli (lowercase). ETAP 1 obsługuje tylko odbiorca → iFirma OdbiorcaNaFakturze.Rola=ODBIORCA. Inne wartości (np. jst_recipient) są zapisywane, ale blokują wystawienie faktury z Podmiotem3 do czasu potwierdzonego mapowania iFirma (ETAP 2). Patrz docs/KSEF_FORM_ORDERS.md.')
                ->change();
        });
    }
};
