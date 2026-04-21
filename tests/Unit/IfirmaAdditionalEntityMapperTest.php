<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use App\Services\IfirmaAdditionalEntityMapper;
use RuntimeException;
use Tests\TestCase;

/**
 * Testy mapowania KSeF Podmiot3 → iFirma OdbiorcaNaFakturze.
 *
 * Zakres ETAP 2 (role obsługiwane):
 *   - odbiorca          → ODBIORCA
 *   - jst_recipient     → JEDN_SAMORZADU_TERYT (KSeF rola 8)
 *   - vat_group_member  → CZLONEK_GRUPY_VAT    (KSeF rola 9)
 *
 * Reguły: docs/KSEF_FORM_ORDERS.md.
 * Testy nie wymagają DB — używamy czystego Eloquent Model z forceFill().
 */
class IfirmaAdditionalEntityMapperTest extends TestCase
{
    private IfirmaAdditionalEntityMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new IfirmaAdditionalEntityMapper;
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
    private function baseRecipientAttributes(array $overrides = []): array
    {
        return array_merge([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
            'recipient_name' => 'ACME Sp. z o.o.',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Warszawa',
            'recipient_address' => 'ul. Testowa 1',
            'recipient_nip' => '1234563218',
        ], $overrides);
    }

    public function test_returns_null_when_source_is_none(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
            'recipient_name' => 'ACME',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Warszawa',
        ]);

        $this->assertNull($this->mapper->build($order));
    }

    public function test_returns_null_when_source_is_null(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => null,
            'recipient_name' => 'ACME',
        ]);

        $this->assertNull($this->mapper->build($order));
    }

    public function test_builds_payload_with_defaults_from_recipient(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '123-456-32-18',
        ]));

        $payload = $this->mapper->build($order);

        $this->assertSame(true, $payload['UzywajDanychOdbiorcyNaFakturach']);
        $this->assertSame('ACME Sp. z o.o.', $payload['Nazwa']);
        $this->assertSame('00-001', $payload['KodPocztowy']);
        $this->assertSame('Warszawa', $payload['Miejscowosc']);
        $this->assertSame('ul. Testowa 1', $payload['Ulica']);
        $this->assertSame('1234563218', $payload['NIP']);
        $this->assertSame('Polska', $payload['Kraj']);
        $this->assertSame('ODBIORCA', $payload['Rola']);
    }

    public function test_uses_explicit_nip_when_id_type_is_nip_and_identifier_present(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '1234567890',
            'ksef_additional_entity_id_type' => FormOrder::KSEF_ID_TYPE_NIP,
            'ksef_additional_entity_identifier' => '987-654-32-10',
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('9876543210', $payload['NIP']);
    }

    public function test_falls_back_to_recipient_nip_when_id_type_is_nip_but_identifier_empty(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '1234567890',
            'ksef_additional_entity_id_type' => FormOrder::KSEF_ID_TYPE_NIP,
            'ksef_additional_entity_identifier' => null,
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('1234567890', $payload['NIP']);
    }

    public function test_omits_nip_when_neither_identifier_nor_recipient_nip_present_for_odbiorca(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
            'recipient_name' => 'Klient prywatny',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Warszawa',
            'recipient_nip' => null,
        ]);

        $payload = $this->mapper->build($order);
        $this->assertArrayNotHasKey('NIP', $payload);
    }

    public function test_fail_fast_on_unsupported_id_type(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'ksef_additional_entity_id_type' => 'IDWew',
            'ksef_additional_entity_identifier' => 'ABC-123',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/nie jest obsługiwany/');
        $this->mapper->build($order);
    }

    public function test_fail_fast_on_unsupported_role(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'ksef_additional_entity_role' => 'employee',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/nie jest obsługiwana/');
        $this->mapper->build($order);
    }

    public function test_fail_fast_on_incomplete_recipient_data(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
            'recipient_name' => 'ACME',
            'recipient_postal_code' => '',
            'recipient_city' => 'Warszawa',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/brak wymaganych danych Podmiotu3/i');
        $this->mapper->build($order);
    }

    public function test_null_role_is_treated_as_default_odbiorca(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'ksef_additional_entity_role' => null,
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('ODBIORCA', $payload['Rola']);
    }

    public function test_null_id_type_uses_recipient_nip(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'ksef_additional_entity_id_type' => null,
            'ksef_additional_entity_identifier' => 'IGNORED',
            'recipient_nip' => 'PL-111-222-33-44',
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('1112223344', $payload['NIP']);
    }

    // ===== ETAP 2: JST =====

    public function test_jst_recipient_builds_payload_with_ifirma_role(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_name' => 'Gmina Miejska Kraków',
            'recipient_address' => 'pl. Wszystkich Świętych 3-4',
            'recipient_postal_code' => '31-004',
            'recipient_city' => 'Kraków',
            'recipient_nip' => '6761013717',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
        ]));

        $payload = $this->mapper->build($order);

        $this->assertSame('JEDN_SAMORZADU_TERYT', $payload['Rola']);
        $this->assertSame('Gmina Miejska Kraków', $payload['Nazwa']);
        $this->assertSame('6761013717', $payload['NIP']);
    }

    public function test_jst_recipient_uses_explicit_nip_override(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '1234567890',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
            'ksef_additional_entity_id_type' => FormOrder::KSEF_ID_TYPE_NIP,
            'ksef_additional_entity_identifier' => '676-10-13-717',
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('6761013717', $payload['NIP']);
    }

    public function test_jst_recipient_fails_fast_on_empty_nip(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => null,
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/wymaga niepustego NIP/');
        $this->mapper->build($order);
    }

    public function test_jst_recipient_fails_fast_on_incomplete_recipient_data(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT,
            'recipient_name' => 'Gmina Warszawa',
            'recipient_postal_code' => '00-001',
            'recipient_city' => '',
            'recipient_nip' => '5252248481',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_JST_RECIPIENT,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/brak wymaganych danych Podmiotu3/i');
        $this->mapper->build($order);
    }

    // ===== ETAP 2: członek grupy VAT =====

    public function test_vat_group_member_builds_payload_with_ifirma_role(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_name' => 'Spółka członek X',
            'recipient_nip' => '5252248481',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER,
        ]));

        $payload = $this->mapper->build($order);

        $this->assertSame('CZLONEK_GRUPY_VAT', $payload['Rola']);
        $this->assertSame('5252248481', $payload['NIP']);
    }

    public function test_vat_group_member_fails_fast_on_empty_nip(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/wymaga niepustego NIP/');
        $this->mapper->build($order);
    }

    public function test_vat_group_member_override_nip_via_identifier(): void
    {
        $order = $this->makeOrder($this->baseRecipientAttributes([
            'recipient_nip' => '1111111111',
            'ksef_additional_entity_role' => FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER,
            'ksef_additional_entity_id_type' => FormOrder::KSEF_ID_TYPE_NIP,
            'ksef_additional_entity_identifier' => '5252248481',
        ]));

        $payload = $this->mapper->build($order);
        $this->assertSame('5252248481', $payload['NIP']);
    }

    public function test_build_legacy_recipient_physical_only_returns_null_when_incomplete(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
            'recipient_name' => 'ACME',
            'recipient_postal_code' => '',
            'recipient_city' => 'Warszawa',
        ]);

        $this->assertNull($this->mapper->buildLegacyRecipientPhysicalOnly($order));
    }

    public function test_build_legacy_recipient_physical_only_ignores_ksef_identifier_when_building_nip(): void
    {
        $order = $this->makeOrder([
            'ksef_entity_source' => FormOrder::KSEF_ENTITY_SOURCE_NONE,
            'recipient_name' => 'ACME',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Warszawa',
            'recipient_address' => 'ul. Legacy 2',
            'recipient_nip' => '123-456-32-18',
            'ksef_additional_entity_identifier' => '5252248481',
            'ksef_additional_entity_id_type' => FormOrder::KSEF_ID_TYPE_NIP,
        ]);

        $payload = $this->mapper->buildLegacyRecipientPhysicalOnly($order);
        $this->assertNotNull($payload);
        $this->assertSame('ODBIORCA', $payload['Rola']);
        $this->assertSame('1234563218', $payload['NIP']);
        $this->assertSame('ul. Legacy 2', $payload['Ulica']);
    }
}
