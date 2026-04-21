<?php

namespace App\Services;

use App\Models\FormOrder;
use InvalidArgumentException;

/**
 * Wspólny builder obiektu Kontrahent w payloadzie iFirma dla wszystkich czterech
 * ścieżek wystawiania dokumentu z widoku szczegółów zamówienia:
 *
 *  - PRO-FORMA                 (endpoint: fakturaproformakraj.json)
 *  - Faktura iFirma            (endpoint: fakturakraj.json)
 *  - Faktura iFirma z Odbiorcą (endpoint: fakturakraj.json + Kontrahent.OdbiorcaNaFakturze)
 *  - Faktura iFirma + KSeF     (endpoint: fakturakraj.json + sendInvoiceToKsef; ten sam tryb Podmiotu3 co „z Odbiorcą”)
 *
 * Zamiast trzech niemal identycznych bloków w kontrolerze (~40 linii każdy) mamy
 * jedno miejsce, które wie, jak budować obiekt Kontrahent dla faktury krajowej
 * oraz uproszczony wariant dla faktury pro forma.
 *
 * Kluczowe decyzje (ETAP 3, patrz docs/KSEF_FORM_ORDERS.md):
 *  - buildForInvoice() ⇒ pełny format Kontrahent (Kraj='Polska', PrefiksUE='PL',
 *    OsobaFizyczna=false, Email); `OdbiorcaNaFakturze` zależy od `podmiot3_mode`
 *    (`ignore` / `auto` / `required` / `invoice_with_receiver`) — patrz stałe `PODMIOT3_MODE_*`.
 *  - buildForProForma() ⇒ wariant „pro-forma” (Kraj='PL', tylko niepuste pola).
 *    NIGDY nie dokleja OdbiorcaNaFakturze, bo publiczna dokumentacja iFirma
 *    (https://api.ifirma.pl/wystawianie-faktury-proforma/) nie wymienia tego
 *    pola w obiekcie Kontrahent dla pro formy, a zasada projektu to „nie zgaduj
 *    obsługi endpointu pro formy”. Pro forma też nie podlega KSeF, więc
 *    Podmiot3 nie ma tu uzasadnienia biznesowego.
 *
 * Tryby Podmiotu3 dla `buildForInvoice()` — parametr `podmiot3_mode`:
 *  - `ignore`   — nigdy nie woła mappera; zawsze faktura bez `OdbiorcaNaFakturze`
 *                 (przycisk „Wystaw Fakturę iFirma”).
 *  - `auto`     — domyślny; dokleja `OdbiorcaNaFakturze` tylko gdy
 *                 `isKsefAdditionalEntityEnabled()` (mapper, fail-fast 422).
 *  - `required` — gate 400 gdy Podmiot3 wyłączony; w przeciwnym razie jak `auto`
 *                 (nieużywane przez `form_orders`; testy / ewentualna twarda ścieżka).
 *  - `invoice_with_receiver` — przycisk „z Odbiorcą” i czerwony KSeF: przy aktywnych metadanych KSeF
 *                 (`recipient`) pełny mapper; przy `none` — tylko `recipient_*`
 *                 jako ODBIORCA, jeśli kompletne; inaczej faktura tylko z nabywcą.
 */
class IfirmaKontrahentBuilder
{
    public const PODMIOT3_MODE_IGNORE = 'ignore';

    public const PODMIOT3_MODE_AUTO = 'auto';

    public const PODMIOT3_MODE_REQUIRED = 'required';

    /** Przycisk „Wystaw Fakturę iFirma z Odbiorcą” — KSeF mapper lub legacy z recipient_*. */
    public const PODMIOT3_MODE_INVOICE_WITH_RECEIVER = 'invoice_with_receiver';

    private IfirmaAdditionalEntityMapper $additionalEntityMapper;

    public function __construct(?IfirmaAdditionalEntityMapper $additionalEntityMapper = null)
    {
        $this->additionalEntityMapper = $additionalEntityMapper ?? new IfirmaAdditionalEntityMapper;
    }

    /**
     * Zbuduj obiekt Kontrahent dla faktury krajowej (fakturakraj.json).
     *
     * Opcje:
     *  - podmiot3_mode (string, domyślnie `self::PODMIOT3_MODE_AUTO`):
     *      `ignore`   — bez `OdbiorcaNaFakturze`, mapper nie jest wołany.
     *      `auto`     — dokleja `OdbiorcaNaFakturze` gdy Podmiot3 aktywny (mapper, fail-fast).
     *      `required` — jak `auto`, ale jeśli Podmiot3 wyłączony → IfirmaKontrahentException (gate).
     *      `invoice_with_receiver` — przy włączonym KSeF jak `auto`; przy `none` legacy
     *                 z `recipient_*` (rola ODBIORCA) lub brak bloku, jeśli dane niekompletne.
     *
     * Wyjątki:
     *  - IfirmaKontrahentException        ⇒ gate `required` zawiódł (kontroler → HTTP 400),
     *  - RuntimeException (z mappera)     ⇒ nieobsługiwana konfiguracja Podmiotu3 (kontroler → HTTP 422),
     *  - InvalidArgumentException         ⇒ nieznany `podmiot3_mode`.
     *
     * @param  array{podmiot3_mode?: string}  $options
     * @return array<string,mixed>
     *
     * @throws IfirmaKontrahentException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function buildForInvoice(FormOrder $order, array $options = []): array
    {
        $mode = $options['podmiot3_mode'] ?? self::PODMIOT3_MODE_AUTO;
        if (! is_string($mode) || ! in_array($mode, [
            self::PODMIOT3_MODE_IGNORE,
            self::PODMIOT3_MODE_AUTO,
            self::PODMIOT3_MODE_REQUIRED,
            self::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ], true)) {
            throw new InvalidArgumentException(
                'IfirmaKontrahentBuilder: nieznany podmiot3_mode '.json_encode($mode, JSON_UNESCAPED_UNICODE).'. '
                .'Dozwolone: "'.self::PODMIOT3_MODE_IGNORE.'", "'.self::PODMIOT3_MODE_AUTO.'", "'
                .self::PODMIOT3_MODE_REQUIRED.'", "'.self::PODMIOT3_MODE_INVOICE_WITH_RECEIVER.'".'
            );
        }

        $kontrahent = [
            'Nazwa' => (string) $order->buyer_name,
            'NIP' => null,
            'Ulica' => '',
            'KodPocztowy' => '',
            'Miejscowosc' => '',
            'Kraj' => 'Polska',
            'PrefiksUE' => 'PL',
            'OsobaFizyczna' => false,
            'Email' => null,
        ];

        $nip = $this->normalizeNip((string) $order->buyer_nip);
        if ($nip !== null) {
            $kontrahent['NIP'] = $nip;
        }

        if (! empty($order->buyer_address)) {
            $kontrahent['Ulica'] = (string) $order->buyer_address;
        }
        if (! empty($order->buyer_postal_code)) {
            $kontrahent['KodPocztowy'] = (string) $order->buyer_postal_code;
        }
        if (! empty($order->buyer_city)) {
            $kontrahent['Miejscowosc'] = (string) $order->buyer_city;
        }

        $email = $this->resolveEmail($order);
        if ($email !== null) {
            $kontrahent['Email'] = $email;
        }

        if ($mode === self::PODMIOT3_MODE_REQUIRED && ! $order->isKsefAdditionalEntityEnabled()) {
            throw new IfirmaKontrahentException(
                'KSeF Podmiot3: ta ścieżka wystawia fakturę z blokiem OdbiorcaNaFakturze, '
                .'ale ksef_entity_source jest ustawione na "none". Ustaw źródło Podmiotu3 na "recipient" '
                .'w sekcji „KSeF – Podmiot3” zamówienia albo użyj zwykłej ścieżki wystawienia faktury bez odbiorcy.'
            );
        }

        if ($mode === self::PODMIOT3_MODE_IGNORE) {
            return $kontrahent;
        }

        if ($mode === self::PODMIOT3_MODE_INVOICE_WITH_RECEIVER) {
            if ($order->isKsefAdditionalEntityEnabled()) {
                $odbiorca = $this->additionalEntityMapper->build($order);
                if ($odbiorca !== null) {
                    $kontrahent['OdbiorcaNaFakturze'] = $odbiorca;
                }
            } else {
                $odbiorca = $this->additionalEntityMapper->buildLegacyRecipientPhysicalOnly($order);
                if ($odbiorca !== null) {
                    $kontrahent['OdbiorcaNaFakturze'] = $odbiorca;
                }
            }

            return $kontrahent;
        }

        if ($order->isKsefAdditionalEntityEnabled()) {
            $odbiorca = $this->additionalEntityMapper->build($order);
            if ($odbiorca !== null) {
                $kontrahent['OdbiorcaNaFakturze'] = $odbiorca;
            }
        }

        return $kontrahent;
    }

    /**
     * Zbuduj obiekt Kontrahent dla faktury pro forma (fakturaproformakraj.json).
     *
     * Uproszczony format (zgodny z dotychczasowym zachowaniem pro formy w projekcie):
     *  - Kraj='PL' (nie 'Polska'),
     *  - brak pól PrefiksUE / OsobaFizyczna / Email jako stałych kluczy,
     *  - puste pola Ulica / KodPocztowy / Miejscowosc / NIP są pomijane.
     *
     * OdbiorcaNaFakturze NIE jest dołączany — patrz class-level PHPDoc.
     *
     * @return array<string,mixed>
     */
    public function buildForProForma(FormOrder $order): array
    {
        $kontrahent = [
            'Nazwa' => (string) $order->buyer_name,
            'Kraj' => 'PL',
        ];

        if (! empty($order->buyer_address)) {
            $kontrahent['Ulica'] = (string) $order->buyer_address;
        }
        if (! empty($order->buyer_postal_code)) {
            $kontrahent['KodPocztowy'] = (string) $order->buyer_postal_code;
        }
        if (! empty($order->buyer_city)) {
            $kontrahent['Miejscowosc'] = (string) $order->buyer_city;
        }

        $nip = $this->normalizeNip((string) $order->buyer_nip);
        if ($nip !== null) {
            $kontrahent['NIP'] = $nip;
        }

        $email = $this->resolveEmail($order);
        if ($email !== null) {
            $kontrahent['Email'] = $email;
        }

        return $kontrahent;
    }

    /**
     * Znormalizuj NIP nabywcy (tylko cyfry). Zwraca null dla pustego wejścia.
     */
    private function normalizeNip(string $raw): ?string
    {
        if (trim($raw) === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $raw) ?? '';

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Rozwiąż pole Email kontrahenta — preferuj orderer_email, fallback na
     * display_participant_email. Walidacja FILTER_VALIDATE_EMAIL.
     */
    private function resolveEmail(FormOrder $order): ?string
    {
        $candidate = null;
        if (! empty($order->orderer_email)) {
            $candidate = strtolower(trim((string) $order->orderer_email));
        } elseif (! empty(trim((string) ($order->display_participant_email ?? '')))) {
            $candidate = strtolower(trim((string) $order->display_participant_email));
        }

        if ($candidate === null || $candidate === '') {
            return null;
        }

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : null;
    }
}
