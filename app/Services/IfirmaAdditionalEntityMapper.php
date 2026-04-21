<?php

namespace App\Services;

use App\Models\FormOrder;
use RuntimeException;

/**
 * Mapowanie metadanych KSeF Podmiot3 na fragment payloadu iFirma (blok
 * Kontrahent.OdbiorcaNaFakturze — patrz https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/).
 *
 * Obsługiwane role (ETAP 2):
 *  - `odbiorca`         → iFirma `ODBIORCA`              (KSeF rola 1, ETAP 1)
 *  - `jst_recipient`    → iFirma `JEDN_SAMORZADU_TERYT`  (KSeF rola 8, ETAP 2)
 *  - `vat_group_member` → iFirma `CZLONEK_GRUPY_VAT`     (KSeF rola 9, ETAP 2)
 *
 * Zasada fail-fast (patrz docs/KSEF_FORM_ORDERS.md — sekcja „Reguła fail-fast”):
 *  - `ksef_entity_source = 'none'`              ⇒ build() zwraca null,
 *  - rola spoza obsługiwanej listy              ⇒ RuntimeException,
 *  - `id_type` inny niż NULL/''/'NIP'           ⇒ RuntimeException (zero cichego fallbacku),
 *  - niekompletne dane `recipient_*`            ⇒ RuntimeException,
 *  - rola wymagająca NIP (JST, grupa VAT) + pusty NIP ⇒ RuntimeException.
 *
 * Uwaga nazewnicza: kolumny `recipient_*` są historycznie nazwane, ale
 * semantycznie trzymają dane Podmiotu3 niezależnie od wybranej roli. Nie
 * zmieniamy nazw kolumn, żeby nie rozjechać kontraktu z publicznym formularzem
 * pnedu.pl (wariant C w dokumentacji KSEF_FORM_ORDERS.md).
 *
 * Legacy: `buildLegacyRecipientPhysicalOnly()` — zwykły odbiorca (rola ODBIORCA)
 * wyłącznie z `recipient_*`, gdy KSeF jest wyłączony (`ksef_entity_source=none`).
 */
class IfirmaAdditionalEntityMapper
{
    /**
     * Zbuduj fragment OdbiorcaNaFakturze dla bloku Kontrahent w payloadzie iFirma.
     *
     * Zwraca:
     *  - null                — gdy Podmiot3 nieaktywny (source = 'none'),
     *  - array<string,mixed> — gdy source = 'recipient' i konfiguracja jest obsługiwana.
     *
     * @return array<string,mixed>|null
     *
     * @throws RuntimeException gdy konfiguracja Podmiotu3 nie jest obsługiwana
     *                          (nieznana rola / id_type / brak danych / brak NIP dla JST/grupy VAT).
     */
    public function build(FormOrder $order): ?array
    {
        if (! $order->isKsefAdditionalEntityEnabled()) {
            return null;
        }

        $role = $order->ksef_additional_entity_role;
        if (! FormOrder::isKsefRoleSupported($role)) {
            throw new RuntimeException(
                'KSeF Podmiot3: rola "'.$role.'" nie jest obsługiwana. '
                .'Dozwolone wartości: "'.FormOrder::KSEF_ROLE_ODBIORCA.'", '
                .'"'.FormOrder::KSEF_ROLE_JST_RECIPIENT.'", '
                .'"'.FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER.'" (lub brak = domyślnie odbiorca). '
                .'Patrz docs/KSEF_FORM_ORDERS.md — sekcja „Obsługiwane role”.'
            );
        }

        $idType = $order->ksef_additional_entity_id_type;
        if (! FormOrder::isKsefIdTypeSupported($idType)) {
            throw new RuntimeException(
                'KSeF Podmiot3: typ identyfikatora "'.$idType.'" nie jest obsługiwany. '
                .'Dozwolona wartość: "'.FormOrder::KSEF_ID_TYPE_NIP.'" (lub brak, wtedy używamy recipient_nip). '
                .'Nie wykonujemy cichego fallbacku do recipient_nip dla innych typów identyfikatora.'
            );
        }

        $recipientName = trim((string) $order->recipient_name);
        $recipientPostalCode = trim((string) $order->recipient_postal_code);
        $recipientCity = trim((string) $order->recipient_city);

        if ($recipientName === '' || $recipientPostalCode === '' || $recipientCity === '') {
            throw new RuntimeException(
                'KSeF Podmiot3: brak wymaganych danych Podmiotu3 (recipient_name / recipient_postal_code / recipient_city). '
                .'Uzupełnij dane lub zmień źródło Podmiotu3 na "none".'
            );
        }

        $nip = $this->resolveNip($order);

        if (FormOrder::isKsefRoleRequiringNip($role) && ($nip === null || $nip === '')) {
            throw new RuntimeException(
                'KSeF Podmiot3: rola "'.$role.'" (iFirma: '.FormOrder::ksefRoleIfirmaCode($role).') wymaga niepustego NIP. '
                .'Uzupełnij recipient_nip lub ksef_additional_entity_identifier (typ NIP). '
                .'KSeF nie przyjmie JST ani członka grupy VAT bez NIP podmiotu — blokujemy request przed uderzeniem do iFirma.'
            );
        }

        $odbiorca = [
            'UzywajDanychOdbiorcyNaFakturach' => true,
            'Nazwa' => $recipientName,
            'KodPocztowy' => $recipientPostalCode,
            'Miejscowosc' => $recipientCity,
        ];

        $recipientAddress = trim((string) $order->recipient_address);
        if ($recipientAddress !== '') {
            $odbiorca['Ulica'] = $recipientAddress;
        }

        if ($nip !== null) {
            $odbiorca['NIP'] = $nip;
        }

        $odbiorca['Kraj'] = 'Polska';
        $odbiorca['Rola'] = $this->mapRoleToIfirma($role);

        return $odbiorca;
    }

    /**
     * Zbuduj `OdbiorcaNaFakturze` wyłącznie z kolumn `recipient_*` (rola ODBIORCA w iFirma),
     * bez udziału metadanych KSeF (`ksef_*`).
     *
     * Ścieżka dla przycisku „Wystaw Fakturę iFirma z Odbiorcą”, gdy
     * `ksef_entity_source = 'none'`: jeśli dane fizyczne odbiorcy są kompletne,
     * zwraca payload; w przeciwnym razie `null` (faktura tylko z nabywcą).
     *
     * Celowo **nie** czyta `ksef_additional_entity_identifier` — przy wyłączonym
     * źródle Podmiotu3 metadane mogą być nieaktualne; unikamy cichego wysłania
     * obcego NIP.
     *
     * @return array<string,mixed>|null
     */
    public function buildLegacyRecipientPhysicalOnly(FormOrder $order): ?array
    {
        if (! $order->hasPhysicalRecipientDataComplete()) {
            return null;
        }

        $recipientName = trim((string) $order->recipient_name);
        $recipientPostalCode = trim((string) $order->recipient_postal_code);
        $recipientCity = trim((string) $order->recipient_city);

        $odbiorca = [
            'UzywajDanychOdbiorcyNaFakturach' => true,
            'Nazwa' => $recipientName,
            'KodPocztowy' => $recipientPostalCode,
            'Miejscowosc' => $recipientCity,
            'Kraj' => 'Polska',
            'Rola' => FormOrder::ksefRoleIfirmaCode(FormOrder::KSEF_ROLE_ODBIORCA),
        ];

        $recipientAddress = trim((string) $order->recipient_address);
        if ($recipientAddress !== '') {
            $odbiorca['Ulica'] = $recipientAddress;
        }

        $recipientNip = trim((string) $order->recipient_nip);
        if ($recipientNip !== '') {
            $normalized = preg_replace('/[^0-9]/', '', $recipientNip);
            if ($normalized !== '') {
                $odbiorca['NIP'] = $normalized;
            }
        }

        return $odbiorca;
    }

    /**
     * Rozwiąż wartość pola NIP w payloadzie iFirma.
     *
     * - id_type = 'NIP' + identifier niepusty ⇒ identifier (znormalizowany do cyfr),
     * - id_type = 'NIP' + identifier pusty    ⇒ recipient_nip (znormalizowany),
     * - id_type pusty/NULL                    ⇒ recipient_nip (znormalizowany),
     * - inne id_type                          ⇒ wyjątek już wcześniej (build()).
     */
    private function resolveNip(FormOrder $order): ?string
    {
        $idType = $order->ksef_additional_entity_id_type;
        $identifier = trim((string) $order->ksef_additional_entity_identifier);

        if ($idType === FormOrder::KSEF_ID_TYPE_NIP && $identifier !== '') {
            $normalized = preg_replace('/[^0-9]/', '', $identifier);

            return $normalized !== '' ? $normalized : null;
        }

        $recipientNip = trim((string) $order->recipient_nip);
        if ($recipientNip === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $recipientNip);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Mapowanie kanonicznego kodu roli na wartość oczekiwaną przez iFirma.
     *
     * Walidacja obsługiwanych wartości odbywa się w build() — tu używamy helpera
     * z modelu, a ewentualny default => wyjątek jest zabezpieczeniem przed desynchronizacją.
     */
    private function mapRoleToIfirma(?string $canonicalRole): string
    {
        $code = FormOrder::ksefRoleIfirmaCode($canonicalRole);

        if ($code === null) {
            throw new RuntimeException(
                'KSeF Podmiot3: nieobsługiwana rola "'.$canonicalRole.'" w mapowaniu iFirma. '
                .'Ten wyjątek nie powinien się pojawić po pomyślnej walidacji w build() — sprawdź synchronizację stałych w FormOrder.'
            );
        }

        return $code;
    }
}
