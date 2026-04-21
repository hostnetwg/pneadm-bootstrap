<?php

namespace Tests\Unit;

use App\Models\FormOrder;
use Tests\TestCase;

/**
 * Testy helperów modelu FormOrder dotyczących KSeF Podmiot3 (ETAP 2).
 */
class FormOrderKsefHelpersTest extends TestCase
{
    public function test_is_ksef_additional_entity_enabled_returns_true_only_for_recipient(): void
    {
        $order = new FormOrder;
        $order->ksef_entity_source = FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT;
        $this->assertTrue($order->isKsefAdditionalEntityEnabled());

        $order->ksef_entity_source = FormOrder::KSEF_ENTITY_SOURCE_NONE;
        $this->assertFalse($order->isKsefAdditionalEntityEnabled());

        $order->ksef_entity_source = null;
        $this->assertFalse($order->isKsefAdditionalEntityEnabled());

        $order->ksef_entity_source = 'custom';
        $this->assertFalse($order->isKsefAdditionalEntityEnabled());
    }

    public function test_has_physical_recipient_data_complete(): void
    {
        $order = new FormOrder;
        $order->forceFill([
            'recipient_name' => 'Firma',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Miasto',
        ]);
        $this->assertTrue($order->hasPhysicalRecipientDataComplete());

        $order2 = new FormOrder;
        $order2->forceFill([
            'recipient_name' => ' ',
            'recipient_postal_code' => '00-001',
            'recipient_city' => 'Miasto',
        ]);
        $this->assertFalse($order2->hasPhysicalRecipientDataComplete());
    }

    public function test_is_role_supported_accepts_etap1_and_etap2_roles(): void
    {
        $this->assertTrue(FormOrder::isKsefRoleSupported(null));
        $this->assertTrue(FormOrder::isKsefRoleSupported(''));
        $this->assertTrue(FormOrder::isKsefRoleSupported(FormOrder::KSEF_ROLE_ODBIORCA));
        $this->assertTrue(FormOrder::isKsefRoleSupported(FormOrder::KSEF_ROLE_JST_RECIPIENT));
        $this->assertTrue(FormOrder::isKsefRoleSupported(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER));

        $this->assertFalse(FormOrder::isKsefRoleSupported('employee'));
        $this->assertFalse(FormOrder::isKsefRoleSupported('payer'));
        $this->assertFalse(FormOrder::isKsefRoleSupported('additional_buyer'));
        $this->assertFalse(FormOrder::isKsefRoleSupported('factor'));
        $this->assertFalse(FormOrder::isKsefRoleSupported('ODBIORCA')); // case-sensitive — kanoniczne = lowercase
        $this->assertFalse(FormOrder::isKsefRoleSupported('8'));
    }

    public function test_is_role_requiring_nip_only_for_jst_and_vat_group(): void
    {
        $this->assertTrue(FormOrder::isKsefRoleRequiringNip(FormOrder::KSEF_ROLE_JST_RECIPIENT));
        $this->assertTrue(FormOrder::isKsefRoleRequiringNip(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER));

        $this->assertFalse(FormOrder::isKsefRoleRequiringNip(null));
        $this->assertFalse(FormOrder::isKsefRoleRequiringNip(''));
        $this->assertFalse(FormOrder::isKsefRoleRequiringNip(FormOrder::KSEF_ROLE_ODBIORCA));
    }

    public function test_ksef_role_ifirma_code_mapping(): void
    {
        $this->assertSame('ODBIORCA', FormOrder::ksefRoleIfirmaCode(null));
        $this->assertSame('ODBIORCA', FormOrder::ksefRoleIfirmaCode(''));
        $this->assertSame('ODBIORCA', FormOrder::ksefRoleIfirmaCode(FormOrder::KSEF_ROLE_ODBIORCA));
        $this->assertSame('JEDN_SAMORZADU_TERYT', FormOrder::ksefRoleIfirmaCode(FormOrder::KSEF_ROLE_JST_RECIPIENT));
        $this->assertSame('CZLONEK_GRUPY_VAT', FormOrder::ksefRoleIfirmaCode(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER));

        $this->assertNull(FormOrder::ksefRoleIfirmaCode('employee'));
        $this->assertNull(FormOrder::ksefRoleIfirmaCode('factor'));
    }

    public function test_is_id_type_supported_only_accepts_nip_or_null(): void
    {
        $this->assertTrue(FormOrder::isKsefIdTypeSupported(null));
        $this->assertTrue(FormOrder::isKsefIdTypeSupported(''));
        $this->assertTrue(FormOrder::isKsefIdTypeSupported(FormOrder::KSEF_ID_TYPE_NIP));

        $this->assertFalse(FormOrder::isKsefIdTypeSupported('PESEL'));
        $this->assertFalse(FormOrder::isKsefIdTypeSupported('IDWew'));
        $this->assertFalse(FormOrder::isKsefIdTypeSupported('BrakID'));
        $this->assertFalse(FormOrder::isKsefIdTypeSupported('nip')); // case-sensitive, kanoniczne = 'NIP'
    }

    public function test_ksef_role_label_for_canonical_codes(): void
    {
        $this->assertStringContainsString('Odbiorca', FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_ODBIORCA));
        $this->assertStringContainsString('JST', FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_JST_RECIPIENT));
        $this->assertStringContainsString('grupy VAT', FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER));
        $this->assertStringContainsString('Nie ustawiono', FormOrder::ksefAdditionalEntityRoleLabel(null));
    }

    public function test_all_canonical_constants_are_lowercase_or_well_known(): void
    {
        // Kanoniczna forma kodów ról = lowercase + podkreślnik
        $this->assertSame(strtolower(FormOrder::KSEF_ROLE_ODBIORCA), FormOrder::KSEF_ROLE_ODBIORCA);
        $this->assertSame(strtolower(FormOrder::KSEF_ROLE_JST_RECIPIENT), FormOrder::KSEF_ROLE_JST_RECIPIENT);
        $this->assertSame(strtolower(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER), FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER);

        // Kanoniczna forma źródła = lowercase
        $this->assertSame(strtolower(FormOrder::KSEF_ENTITY_SOURCE_NONE), FormOrder::KSEF_ENTITY_SOURCE_NONE);
        $this->assertSame(strtolower(FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT), FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT);

        // Typ identyfikatora — zgodny z nazewnictwem KSeF (wielkimi literami), tu: 'NIP'
        $this->assertSame('NIP', FormOrder::KSEF_ID_TYPE_NIP);
    }

    public function test_deprecated_etap1_aliases_still_work(): void
    {
        // Aliasy zgodności wstecznej — rozpoznają wyłącznie zakres ETAP 1
        $this->assertTrue(FormOrder::isKsefRoleSupportedInEtap1(null));
        $this->assertTrue(FormOrder::isKsefRoleSupportedInEtap1(FormOrder::KSEF_ROLE_ODBIORCA));
        $this->assertFalse(FormOrder::isKsefRoleSupportedInEtap1(FormOrder::KSEF_ROLE_JST_RECIPIENT));
        $this->assertFalse(FormOrder::isKsefRoleSupportedInEtap1(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER));

        // isKsefIdTypeSupportedInEtap1 jest w ETAP 2 tożsamy z isKsefIdTypeSupported
        $this->assertTrue(FormOrder::isKsefIdTypeSupportedInEtap1(FormOrder::KSEF_ID_TYPE_NIP));
        $this->assertFalse(FormOrder::isKsefIdTypeSupportedInEtap1('PESEL'));
    }
}
