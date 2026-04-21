<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaKontrahentBuilder;
use App\Services\IfirmaKontrahentException;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Testy wspólnego buildera bloku Kontrahent dla iFirma (ETAP 3).
 *
 * Zakres:
 *  - buildForInvoice() — format „Polska/PrefiksUE/OsobaFizyczna/Email”,
 *    `podmiot3_mode`: ignore / auto / required (delegacja do mappera poza trybem ignore).
 *  - buildForProForma() — uproszczony format (Kraj='PL'), NIGDY bez
 *    OdbiorcaNaFakturze (brak potwierdzenia w dokumentacji iFirma).
 *
 * Testy nie dotykają bazy — używamy Eloquent Model + forceFill().
 */
class IfirmaKontrahentBuilderTest extends TestCase
{
    private IfirmaKontrahentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new IfirmaKontrahentBuilder;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(array $attributes): FormOrder
    {
        $order = new FormOrder;
        $order->forceFill($attributes);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseBuyerAttributes(array $overrides = []): array
    {
        return array_merge([
            'buyer_name' => 'Nabywca Testowy Sp. z o.o.',
            'buyer_address' => 'ul. Kontrahencka 10',
            'buyer_postal_code' => '00-002',
            'buyer_city' => 'Warszawa',
            'buyer_nip' => '5270103391',
            'orderer_email' => 'Test@Example.COM',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePodmiot3Attributes(array $overrides = []): array
    {
        return array_merge([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_ODBIORCA,
            'recipient_name' => 'ACME Sp. z o.o.',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Warszawa',
            'recipient_address' => 'ul. Testowa 1',
            'recipient_nip' => '1234563218',
        ], $overrides);
    }

    // ----- buildForInvoice ----------------------------------------------

    public function test_build_for_invoice_returns_full_kontrahent_without_podmiot3_by_default(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]));

        $kontrahent = $this->builder->buildForInvoice($order);

        $this->assertSame('Nabywca Testowy Sp. z o.o.', $kontrahent['Nazwa']);
        $this->assertSame('5270103391', $kontrahent['NIP']);
        $this->assertSame('ul. Kontrahencka 10', $kontrahent['Ulica']);
        $this->assertSame('00-002', $kontrahent['KodPocztowy']);
        $this->assertSame('Warszawa', $kontrahent['Miejscowosc']);
        $this->assertSame('Polska', $kontrahent['Kraj']);
        $this->assertSame('PL', $kontrahent['PrefiksUE']);
        $this->assertFalse($kontrahent['OsobaFizyczna']);
        $this->assertSame('test@example.com', $kontrahent['Email']);
        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
    }

    public function test_build_for_invoice_attaches_odbiorca_when_podmiot3_enabled(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_AUTO,
        ]);

        $this->assertArrayHasKey('OdbiorcaNaFakturze', $kontrahent);
        $this->assertSame('ACME Sp. z o.o.', $kontrahent['OdbiorcaNaFakturze']['Nazwa']);
        $this->assertSame('ODBIORCA', $kontrahent['OdbiorcaNaFakturze']['Rola']);
        $this->assertSame('1234563218', $kontrahent['OdbiorcaNaFakturze']['NIP']);
        // Dane nabywcy nadal są pełne — Podmiot3 nie powinien ich nadpisać.
        $this->assertSame('Nabywca Testowy Sp. z o.o.', $kontrahent['Nazwa']);
        $this->assertSame('5270103391', $kontrahent['NIP']);
    }

    public function test_build_for_invoice_gate_fails_when_podmiot3_mode_required_and_source_none(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]));

        $this->expectException(IfirmaKontrahentException::class);
        $this->expectExceptionMessageMatches('/KSeF Podmiot3/');

        $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_REQUIRED,
        ]);
    }

    public function test_build_for_invoice_propagates_runtime_exception_from_mapper(): void
    {
        // jst_recipient wymaga niepustego NIP — mapper rzuca RuntimeException (nie IfirmaKontrahentException).
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes([
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
                'recipient_nip' => '',
            ])
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/wymaga niepustego NIP/');

        // Wyjątek nie może być IfirmaKontrahentException — to osobny sygnał dla kontrolera (422 vs 400).
        try {
            $this->builder->buildForInvoice($order, [
                'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_REQUIRED,
            ]);
            $this->fail('Expected RuntimeException from mapper');
        } catch (IfirmaKontrahentException $e) {
            $this->fail('Mapper error should NOT be wrapped as IfirmaKontrahentException: '.$e->getMessage());
        }
    }

    public function test_build_for_invoice_ignore_mode_never_attaches_odbiorca_even_when_podmiot3_complete(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_IGNORE,
        ]);

        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
        $this->assertSame('Nabywca Testowy Sp. z o.o.', $kontrahent['Nazwa']);
    }

    public function test_build_for_invoice_ignore_mode_does_not_fail_on_incomplete_recipient_when_source_recipient(): void
    {
        // Scenariusz jak zamówienie 6516: source=recipient (np. backfill), ale recipient_* niekompletny —
        // przycisk „Wystaw Fakturę iFirma” (ignore) nie woła mappera, brak 422.
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            [
                'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_ODBIORCA,
                'recipient_name' => '',
                'recipient_postal_code' => '',
                'recipient_city' => '',
                'recipient_address' => '',
                'recipient_nip' => '',
            ]
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_IGNORE,
        ]);

        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
    }

    public function test_build_for_invoice_auto_mode_still_calls_mapper_and_fails_on_incomplete_recipient(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            [
                'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_ODBIORCA,
                'recipient_name' => '',
                'recipient_postal_code' => '',
                'recipient_city' => '',
            ]
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/brak wymaganych danych Podmiotu3/');

        $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_AUTO,
        ]);
    }

    public function test_build_for_invoice_rejects_unknown_podmiot3_mode(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nieznany podmiot3_mode/');

        $this->builder->buildForInvoice($order, ['podmiot3_mode' => 'bogus']);
    }

    public function test_build_for_invoice_invoice_with_receiver_legacy_when_source_none(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            [
                'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
                'recipient_name' => 'Odbiorca Sp.',
                'recipient_postal_code' => '01-234',
                'recipient_city' => 'Kraków',
                'recipient_address' => 'ul. Dostawy 3',
                'recipient_nip' => '9442251080',
            ]
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertArrayHasKey('OdbiorcaNaFakturze', $kontrahent);
        $this->assertSame('Odbiorca Sp.', $kontrahent['OdbiorcaNaFakturze']['Nazwa']);
        $this->assertSame('ODBIORCA', $kontrahent['OdbiorcaNaFakturze']['Rola']);
        $this->assertSame('9442251080', $kontrahent['OdbiorcaNaFakturze']['NIP']);
    }

    public function test_build_for_invoice_invoice_with_receiver_buyer_only_when_recipient_incomplete(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            [
                'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
                'recipient_name' => '',
                'recipient_postal_code' => '00-001',
                'recipient_city' => 'Warszawa',
            ]
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
    }

    public function test_build_for_invoice_invoice_with_receiver_uses_mapper_when_ksef_recipient(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes([
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
            ])
        ));

        $kontrahent = $this->builder->buildForInvoice($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertSame('JEDN_SAMORZADU_TERYT', $kontrahent['OdbiorcaNaFakturze']['Rola']);
    }

    public function test_build_for_invoice_normalizes_buyer_nip_with_dashes(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'buyer_nip' => '527-01-03-391',
        ]));

        $kontrahent = $this->builder->buildForInvoice($order);

        $this->assertSame('5270103391', $kontrahent['NIP']);
    }

    public function test_build_for_invoice_keeps_null_nip_and_empty_address_when_buyer_incomplete(): void
    {
        $order = $this->makeOrder([
            'buyer_name' => 'Osoba fizyczna',
            'buyer_nip' => '',
            'buyer_address' => null,
            'buyer_postal_code' => null,
            'buyer_city' => null,
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]);

        $kontrahent = $this->builder->buildForInvoice($order);

        $this->assertNull($kontrahent['NIP']);
        $this->assertSame('', $kontrahent['Ulica']);
        $this->assertSame('', $kontrahent['KodPocztowy']);
        $this->assertSame('', $kontrahent['Miejscowosc']);
        $this->assertNull($kontrahent['Email']);
    }

    public function test_build_for_invoice_rejects_invalid_email(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'orderer_email' => 'not-an-email',
        ]));

        $kontrahent = $this->builder->buildForInvoice($order);

        $this->assertNull($kontrahent['Email']);
    }

    // ----- buildForProForma ---------------------------------------------

    public function test_build_for_proforma_uses_pl_country_and_conditional_fields(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes());

        $kontrahent = $this->builder->buildForProForma($order);

        $this->assertSame('Nabywca Testowy Sp. z o.o.', $kontrahent['Nazwa']);
        $this->assertSame('PL', $kontrahent['Kraj']);
        $this->assertSame('ul. Kontrahencka 10', $kontrahent['Ulica']);
        $this->assertSame('00-002', $kontrahent['KodPocztowy']);
        $this->assertSame('Warszawa', $kontrahent['Miejscowosc']);
        $this->assertSame('5270103391', $kontrahent['NIP']);
        $this->assertSame('test@example.com', $kontrahent['Email']);
        // Format pro formy nie używa stałych kluczy PrefiksUE / OsobaFizyczna.
        $this->assertArrayNotHasKey('PrefiksUE', $kontrahent);
        $this->assertArrayNotHasKey('OsobaFizyczna', $kontrahent);
    }

    public function test_build_for_proforma_omits_empty_fields(): void
    {
        $order = $this->makeOrder([
            'buyer_name' => 'Tylko nazwa',
            'buyer_nip' => '',
            'buyer_address' => '',
            'buyer_postal_code' => '',
            'buyer_city' => '',
        ]);

        $kontrahent = $this->builder->buildForProForma($order);

        $this->assertSame('Tylko nazwa', $kontrahent['Nazwa']);
        $this->assertSame('PL', $kontrahent['Kraj']);
        $this->assertArrayNotHasKey('Ulica', $kontrahent);
        $this->assertArrayNotHasKey('KodPocztowy', $kontrahent);
        $this->assertArrayNotHasKey('Miejscowosc', $kontrahent);
        $this->assertArrayNotHasKey('NIP', $kontrahent);
        $this->assertArrayNotHasKey('Email', $kontrahent);
    }

    public function test_build_for_proforma_never_attaches_odbiorca_even_when_podmiot3_enabled(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $kontrahent = $this->builder->buildForProForma($order);

        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
    }
}
