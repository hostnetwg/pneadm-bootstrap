<?php

namespace App\Services;

use App\Models\FormOrder;
use InvalidArgumentException;

/**
 * Wspólny builder obiektów iFirma dla wystawiania dokumentu z widoku szczegółów zamówienia:
 *
 *  - `buildForInvoice()` — obiekt `Kontrahent` (nabywca) dla `fakturakraj.json`
 *  - `buildPodmiotyDodatkowe()` — tablica wpisów Podmiot3 dla root `PodmiotyDodatkowe`
 *  - `buildForProForma()` — uproszczony `Kontrahent` dla `fakturaproformakraj.json`
 *
 * Od 2026-08-04 iFirma wymaga Podmiotu3 w `PodmiotyDodatkowe` na poziomie dokumentu
 * (nie w `Kontrahent.OdbiorcaNaFakturze`). Patrz docs/KSEF_FORM_ORDERS.md.
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
     * Zbuduj obiekt Kontrahent (nabywca) dla faktury krajowej — bez Podmiotu3.
     *
     * @return array<string,mixed>
     */
    public function buildForInvoice(FormOrder $order): array
    {
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

        return $kontrahent;
    }

    /**
     * Zbuduj tablicę `PodmiotyDodatkowe` dla faktury krajowej (0 lub 1 wpis).
     *
     * Opcje:
     *  - podmiot3_mode (string, domyślnie `self::PODMIOT3_MODE_AUTO`):
     *      `ignore`   — pusta tablica, mapper nie jest wołany.
     *      `auto`     — wpis gdy Podmiot3 aktywny (mapper, fail-fast).
     *      `required` — gate 400 gdy Podmiot3 wyłączony; w przeciwnym razie jak `auto`.
     *      `invoice_with_receiver` — przy `recipient` pełny mapper; przy `none` legacy
     *                 z `recipient_*` (rola ODBIORCA) lub pusta tablica.
     *
     * @param  array{podmiot3_mode?: string}  $options
     * @return list<array<string,mixed>>
     *
     * @throws IfirmaKontrahentException
     * @throws InvalidArgumentException
     * @throws \RuntimeException
     */
    public function buildPodmiotyDodatkowe(FormOrder $order, array $options = []): array
    {
        $mode = $this->resolvePodmiot3Mode($options);

        if ($mode === self::PODMIOT3_MODE_REQUIRED && ! $order->isKsefAdditionalEntityEnabled()) {
            throw new IfirmaKontrahentException(
                'KSeF Podmiot3: ta ścieżka wystawia fakturę z Podmiotem3 (PodmiotyDodatkowe), '
                .'ale ksef_entity_source jest ustawione na "none". Ustaw źródło Podmiotu3 na "recipient" '
                .'w sekcji „KSeF – Podmiot3” zamówienia albo użyj zwykłej ścieżki wystawienia faktury bez odbiorcy.'
            );
        }

        if ($mode === self::PODMIOT3_MODE_IGNORE) {
            return [];
        }

        if ($mode === self::PODMIOT3_MODE_INVOICE_WITH_RECEIVER) {
            if ($order->isKsefAdditionalEntityEnabled()) {
                $podmiot = $this->additionalEntityMapper->build($order);

                return $podmiot !== null ? [$podmiot] : [];
            }

            $podmiot = $this->additionalEntityMapper->buildLegacyRecipientPhysicalOnly($order);

            return $podmiot !== null ? [$podmiot] : [];
        }

        if ($order->isKsefAdditionalEntityEnabled()) {
            $podmiot = $this->additionalEntityMapper->build($order);

            return $podmiot !== null ? [$podmiot] : [];
        }

        return [];
    }

    /**
     * Zbuduj obiekt Kontrahent dla faktury pro forma (fakturaproformakraj.json).
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
     * @param  array{podmiot3_mode?: string}  $options
     */
    private function resolvePodmiot3Mode(array $options): string
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

        return $mode;
    }

    private function normalizeNip(string $raw): ?string
    {
        if (trim($raw) === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $raw) ?? '';

        return $normalized !== '' ? $normalized : null;
    }

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
