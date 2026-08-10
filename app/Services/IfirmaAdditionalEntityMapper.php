<?php

namespace App\Services;

use App\Models\FormOrder;
use RuntimeException;

/**
 * Mapowanie metadanych KSeF Podmiot3 na wpis w tablicy iFirma `PodmiotyDodatkowe`
 * (patrz https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/).
 *
 * Od 2026-08-04 iFirma nie używa już `Kontrahent.OdbiorcaNaFakturze` /
 * `DaneOdbiorcy` — podmiot dodatkowy trafia na fakturę wyłącznie przez
 * root `PodmiotyDodatkowe` z polem `CzyDomyslny` (wcześniej
 * `UzywajDanychOdbiorcyNaFakturach` w starym formacie).
 *
 * Obsługiwane role (ETAP 2):
 *  - `odbiorca`         → iFirma `ODBIORCA`              (KSeF rola 1, ETAP 1)
 *  - `jst_recipient`    → iFirma `JEDN_SAMORZADU_TERYT`  (KSeF rola 8, ETAP 2)
 *  - `vat_group_member` → iFirma `CZLONEK_GRUPY_VAT`     (KSeF rola 9, ETAP 2)
 *
 * Zasada fail-fast (patrz docs/KSEF_FORM_ORDERS.md — sekcja „Reguła fail-fast”):
 *  - `ksef_entity_source = 'none'`              ⇒ build() zwraca null,
 *  - rola spoza obsługiwanej listy              ⇒ RuntimeException,
 *  - `id_type` inny niż NULL/''/'NIP'/'IDWew'     ⇒ RuntimeException (zero cichego fallbacku),
 *  - niekompletne dane `recipient_*`            ⇒ RuntimeException,
 *  - rola wymagająca NIP (JST, grupa VAT) + pusty NIP ⇒ RuntimeException,
 *  - typ IDWew + JST/VAT: `IdentyfikatorWewnetrznyZNip` + `NIP` z `recipient_nip` (A2),
 *  - typ IDWew + odbiorca: tylko `IdentyfikatorWewnetrznyZNip`.
 *
 * Legacy: `buildLegacyRecipientPhysicalOnly()` — zwykły odbiorca (rola ODBIORCA)
 * wyłącznie z `recipient_*`, gdy KSeF jest wyłączony (`ksef_entity_source=none`).
 */
class IfirmaAdditionalEntityMapper
{
    /**
     * Zbuduj jeden wpis do tablicy `PodmiotyDodatkowe` w payloadzie faktury iFirma.
     *
     * Zwraca:
     *  - null                — gdy Podmiot3 nieaktywny (source = 'none'),
     *  - array<string,mixed> — gdy source = 'recipient' i konfiguracja jest obsługiwana.
     *
     * @return array<string,mixed>|null
     *
     * @throws RuntimeException gdy konfiguracja Podmiotu3 nie jest obsługiwana
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
                .'Dozwolone wartości: "'.FormOrder::KSEF_ID_TYPE_NIP.'", "'.FormOrder::KSEF_ID_TYPE_IDWEW.'" (lub brak, wtedy używamy recipient_nip). '
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

        $podmiot = [
            'CzyDomyslny' => true,
            'Nazwa' => $recipientName,
            'KodPocztowy' => $recipientPostalCode,
            'Miejscowosc' => $recipientCity,
        ];

        $recipientAddress = trim((string) $order->recipient_address);
        if ($recipientAddress !== '') {
            $podmiot['Ulica'] = $recipientAddress;
        }

        if ($idType === FormOrder::KSEF_ID_TYPE_IDWEW) {
            $idwew = $this->normalizeIdwewIdentifier(trim((string) $order->ksef_additional_entity_identifier));
            if ($idwew === null) {
                throw new RuntimeException(
                    'KSeF Podmiot3: brak lub niepoprawny identyfikator wewnętrzny (IDWew) w ksef_additional_entity_identifier. '
                    .'Oczekiwany format: 10 cyfr NIP + „-” + 5 cyfr (np. 7743211258-00709).'
                );
            }

            $podmiot['IdentyfikatorWewnetrznyZNip'] = $idwew;

            // A2: JST / grupa VAT — IDWew + NIP Podmiotu3 z recipient_nip (dokumentacja iFirma:
            // https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/ — oba pola dozwolone).
            if (FormOrder::isKsefRoleRequiringNip($role)) {
                $nip = $this->resolveRecipientNipOnly($order);
                if ($nip === null || $nip === '') {
                    throw new RuntimeException(
                        'KSeF Podmiot3: rola "'.$role.'" z typem IDWew wymaga też NIP Podmiotu3 w recipient_nip '
                        .'(obok IdentyfikatorWewnetrznyZNip). Uzupełnij NIP w karcie ODBIORCA / Podmiot3.'
                    );
                }
                $podmiot['NIP'] = $nip;
            }
        } else {
            $nip = $this->resolveNip($order);

            if (FormOrder::isKsefRoleRequiringNip($role) && ($nip === null || $nip === '')) {
                throw new RuntimeException(
                    'KSeF Podmiot3: rola "'.$role.'" (iFirma: '.FormOrder::ksefRoleIfirmaCode($role).') wymaga niepustego NIP. '
                    .'Uzupełnij recipient_nip lub ksef_additional_entity_identifier (typ NIP). '
                    .'KSeF nie przyjmie JST ani członka grupy VAT bez NIP podmiotu — blokujemy request przed uderzeniem do iFirma.'
                );
            }

            if ($nip !== null) {
                $podmiot['NIP'] = $nip;
            }
        }

        $podmiot['Kraj'] = 'Polska';
        $podmiot['Rola'] = $this->mapRoleToIfirma($role);

        return $podmiot;
    }

    /**
     * Normalizacja IDWew do postaci NIP (10 cyfr) + „-” + 5 cyfr (format KSeF FA(3)).
     */
    private function normalizeIdwewIdentifier(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^[0-9]{10}-[0-9]{5}$/', $raw)) {
            return $raw;
        }

        if (preg_match('/^([0-9]{10})([0-9]{5})$/', $raw, $matches)) {
            return $matches[1].'-'.$matches[2];
        }

        return null;
    }

    /**
     * Zbuduj wpis `PodmiotyDodatkowe` wyłącznie z kolumn `recipient_*` (rola ODBIORCA),
     * bez udziału metadanych KSeF (`ksef_*`).
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

        $podmiot = [
            'CzyDomyslny' => true,
            'Nazwa' => $recipientName,
            'KodPocztowy' => $recipientPostalCode,
            'Miejscowosc' => $recipientCity,
            'Kraj' => 'Polska',
            'Rola' => FormOrder::ksefRoleIfirmaCode(FormOrder::KSEF_ROLE_ODBIORCA),
        ];

        $recipientAddress = trim((string) $order->recipient_address);
        if ($recipientAddress !== '') {
            $podmiot['Ulica'] = $recipientAddress;
        }

        $recipientNip = trim((string) $order->recipient_nip);
        if ($recipientNip !== '') {
            $normalized = preg_replace('/[^0-9]/', '', $recipientNip);
            if ($normalized !== '') {
                $podmiot['NIP'] = $normalized;
            }
        }

        return $podmiot;
    }

    /**
     * Rozwiąż wartość pola NIP w payloadzie iFirma (ścieżka typ NIP / brak typu).
     */
    private function resolveNip(FormOrder $order): ?string
    {
        $idType = $order->ksef_additional_entity_id_type;
        $identifier = trim((string) $order->ksef_additional_entity_identifier);

        if ($idType === FormOrder::KSEF_ID_TYPE_NIP && $identifier !== '') {
            $normalized = preg_replace('/[^0-9]/', '', $identifier);

            return $normalized !== '' ? $normalized : null;
        }

        return $this->resolveRecipientNipOnly($order);
    }

    /**
     * NIP wyłącznie z kolumny recipient_nip (nie z identyfikatora — ten przy IDWew to ID wewnętrzny).
     */
    private function resolveRecipientNipOnly(FormOrder $order): ?string
    {
        $recipientNip = trim((string) $order->recipient_nip);
        if ($recipientNip === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $recipientNip);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Mapowanie kanonicznego kodu roli na wartość oczekiwaną przez iFirma.
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
