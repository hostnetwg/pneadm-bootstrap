<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaKontrahentBuilder;
use App\Services\IfirmaKontrahentException;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Testy wspólnego buildera iFirma (ETAP 3 + migracja PodmiotyDodatkowe 2026-08).
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

    public function test_build_for_invoice_returns_full_kontrahent_without_podmiot3_fields(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]));

        $kontrahent = $this->builder->buildForInvoice($order);

        $this->assertSame('Nabywca Testowy Sp. z o.o.', $kontrahent['Nazwa']);
        $this->assertSame('5270103391', $kontrahent['NIP']);
        $this->assertSame('Polska', $kontrahent['Kraj']);
        $this->assertSame('test@example.com', $kontrahent['Email']);
        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
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

    // ----- buildPodmiotyDodatkowe ---------------------------------------

    public function test_build_podmioty_dodatkowe_returns_empty_when_source_none_and_auto_mode(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]));

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order);

        $this->assertSame([], $podmioty);
    }

    public function test_build_podmioty_dodatkowe_attaches_entry_when_podmiot3_enabled(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_AUTO,
        ]);

        $this->assertCount(1, $podmioty);
        $this->assertSame('ACME Sp. z o.o.', $podmioty[0]['Nazwa']);
        $this->assertSame('ODBIORCA', $podmioty[0]['Rola']);
        $this->assertSame('1234563218', $podmioty[0]['NIP']);
        $this->assertTrue($podmioty[0]['CzyDomyslny']);
    }

    public function test_build_podmioty_dodatkowe_gate_fails_when_required_and_source_none(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
        ]));

        $this->expectException(IfirmaKontrahentException::class);
        $this->expectExceptionMessageMatches('/KSeF Podmiot3/');

        $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_REQUIRED,
        ]);
    }

    public function test_build_podmioty_dodatkowe_propagates_runtime_exception_from_mapper(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes([
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
                'recipient_nip' => '',
            ])
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/wymaga niepustego NIP/');

        $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_REQUIRED,
        ]);
    }

    public function test_build_podmioty_dodatkowe_ignore_mode_returns_empty_even_when_podmiot3_complete(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_IGNORE,
        ]);

        $this->assertSame([], $podmioty);
    }

    public function test_build_podmioty_dodatkowe_auto_mode_fails_on_incomplete_recipient(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            [
                'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
                'recipient_name' => '',
                'recipient_postal_code' => '',
                'recipient_city' => '',
            ]
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/brak wymaganych danych Podmiotu3/');

        $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_AUTO,
        ]);
    }

    public function test_build_podmioty_dodatkowe_rejects_unknown_podmiot3_mode(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nieznany podmiot3_mode/');

        $this->builder->buildPodmiotyDodatkowe($order, ['podmiot3_mode' => 'bogus']);
    }

    public function test_build_podmioty_dodatkowe_invoice_with_receiver_legacy_when_source_none(): void
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

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertCount(1, $podmioty);
        $this->assertSame('Odbiorca Sp.', $podmioty[0]['Nazwa']);
        $this->assertSame('ODBIORCA', $podmioty[0]['Rola']);
        $this->assertSame('9442251080', $podmioty[0]['NIP']);
    }

    public function test_build_podmioty_dodatkowe_invoice_with_receiver_empty_when_recipient_incomplete(): void
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

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertSame([], $podmioty);
    }

    public function test_build_podmioty_dodatkowe_invoice_with_receiver_uses_mapper_when_ksef_recipient(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes([
                'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
            ])
        ));

        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_INVOICE_WITH_RECEIVER,
        ]);

        $this->assertSame('JEDN_SAMORZADU_TERYT', $podmioty[0]['Rola']);
    }

    // ----- buildForProForma ---------------------------------------------

    public function test_build_for_proforma_uses_pl_country_and_conditional_fields(): void
    {
        $order = $this->makeOrder($this->baseBuyerAttributes());

        $kontrahent = $this->builder->buildForProForma($order);

        $this->assertSame('PL', $kontrahent['Kraj']);
        $this->assertSame('5270103391', $kontrahent['NIP']);
        $this->assertArrayNotHasKey('PrefiksUE', $kontrahent);
    }

    public function test_build_for_proforma_never_includes_podmioty_dodatkowe(): void
    {
        $order = $this->makeOrder(array_merge(
            $this->baseBuyerAttributes(),
            $this->basePodmiot3Attributes()
        ));

        $kontrahent = $this->builder->buildForProForma($order);
        $podmioty = $this->builder->buildPodmiotyDodatkowe($order, [
            'podmiot3_mode' => IfirmaKontrahentBuilder::PODMIOT3_MODE_IGNORE,
        ]);

        $this->assertArrayNotHasKey('OdbiorcaNaFakturze', $kontrahent);
        $this->assertSame([], $podmioty);
    }
}
