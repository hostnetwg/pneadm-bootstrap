<?php

namespace Tests\Feature;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Services\DebtCustomerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtCustomerProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_nip_is_primary_when_buyer_and_recipient_exist(): void
    {
        $current = $this->order([
            'buyer_nip' => '9999999999',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'sekretariat@example.test',
        ]);
        $sameSchool = $this->order([
            'buyer_nip' => '8888888888',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'inna@example.test',
        ]);
        $sameOrganOnly = $this->order([
            'buyer_nip' => '9999999999',
            'recipient_nip' => '2222222222',
            'orderer_email' => 'inna@example.test',
        ]);
        $sameOrdererOnly = $this->order([
            'buyer_nip' => '7777777777',
            'recipient_nip' => '3333333333',
            'orderer_email' => 'sekretariat@example.test',
        ]);

        $relatedIds = $this->service()->relatedOrders($this->service()->identityForOrder($current))->pluck('id');

        $this->assertTrue($relatedIds->contains($current->id));
        $this->assertTrue($relatedIds->contains($sameSchool->id));
        $this->assertFalse($relatedIds->contains($sameOrganOnly->id));
        $this->assertFalse($relatedIds->contains($sameOrdererOnly->id));
    }

    public function test_complete_recipient_profile_matches_legacy_school_without_recipient_nip(): void
    {
        $current = $this->order([
            'recipient_name' => 'Szkoła Podstawowa nr 1',
            'recipient_address' => 'ul. Mickiewicza 7',
            'recipient_postal_code' => '12-345',
            'recipient_city' => 'Kurzętnik',
            'orderer_email' => 'sekretariat@sp1.example.test',
        ]);
        $sameRecipientProfile = $this->order([
            'recipient_name' => '  szkoła podstawowa nr 1 ',
            'recipient_address' => 'ulica Mickiewicza 7',
            'recipient_postal_code' => '12-345',
            'recipient_city' => 'kurzętnik',
            'orderer_email' => 'inna@sp1.example.test',
        ]);
        $sameOrdererEmail = $this->order([
            'recipient_name' => 'Inna szkoła',
            'recipient_address' => 'ul. Szkolna 2',
            'recipient_postal_code' => '12-345',
            'recipient_city' => 'Kurzętnik',
            'orderer_email' => 'sekretariat@sp1.example.test',
        ]);
        $nameOnly = $this->order([
            'recipient_name' => 'Szkoła Podstawowa nr 1',
            'recipient_address' => null,
            'recipient_postal_code' => null,
            'recipient_city' => null,
            'orderer_email' => 'dyrektor@example.test',
        ]);

        $relatedIds = $this->service()->relatedOrders($this->service()->identityForOrder($current))->pluck('id');

        $this->assertTrue($relatedIds->contains($current->id));
        $this->assertTrue($relatedIds->contains($sameRecipientProfile->id));
        $this->assertTrue($relatedIds->contains($sameOrdererEmail->id));
        $this->assertFalse($relatedIds->contains($nameOnly->id));
    }

    public function test_buyer_nip_is_used_when_there_is_no_recipient(): void
    {
        $current = $this->order([
            'buyer_nip' => '1234567890',
            'orderer_email' => 'biuro@example.test',
        ]);
        $sameBuyer = $this->order([
            'buyer_nip' => '123-456-78-90',
            'orderer_email' => 'inne@example.test',
        ]);
        $sameOrdererOnly = $this->order([
            'buyer_nip' => '0000000000',
            'orderer_email' => 'biuro@example.test',
        ]);

        $relatedIds = $this->service()->relatedOrders($this->service()->identityForOrder($current))->pluck('id');

        $this->assertTrue($relatedIds->contains($current->id));
        $this->assertTrue($relatedIds->contains($sameBuyer->id));
        $this->assertFalse($relatedIds->contains($sameOrdererOnly->id));
    }

    public function test_private_customer_uses_orderer_email_and_ignores_participant_email(): void
    {
        $current = $this->order(['orderer_email' => 'jan@example.test']);
        $this->participant($current, 'teacher@example.test');
        $sameOrderer = $this->order(['orderer_email' => 'JAN@example.test']);
        $sameParticipantOnly = $this->order(['orderer_email' => 'inna@example.test']);
        $this->participant($sameParticipantOnly, 'teacher@example.test');

        $relatedIds = $this->service()->relatedOrders($this->service()->identityForOrder($current))->pluck('id');

        $this->assertTrue($relatedIds->contains($current->id));
        $this->assertTrue($relatedIds->contains($sameOrderer->id));
        $this->assertFalse($relatedIds->contains($sameParticipantOnly->id));
    }

    public function test_vip_score_for_school_does_not_grow_from_same_buyer_organ_when_recipient_nip_differs(): void
    {
        $current = $this->order([
            'product_price' => 700,
            'buyer_nip' => '9999999999',
            'recipient_nip' => '1111111111',
        ]);

        for ($i = 1; $i <= 8; $i++) {
            $this->order([
                'product_price' => 700,
                'buyer_nip' => '9999999999',
                'recipient_nip' => '22222222'.$i,
            ]);
        }

        $profile = $this->service()->profileForOrder($current);

        $this->assertSame(1, $profile['related_orders_count']);
        $this->assertLessThan(60, $profile['relationship_score']);
    }

    public function test_link_reasons_for_recipient_nip_strategy(): void
    {
        $current = $this->order([
            'buyer_nip' => '9999999999',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'sekretariat@example.test',
        ]);
        $sameSchool = $this->order([
            'buyer_nip' => '8888888888',
            'recipient_nip' => '1111111111',
            'orderer_email' => 'inna@example.test',
        ]);

        $identity = $this->service()->identityForOrder($current);
        $reasons = $this->service()->linkReasonsForRelatedOrder($sameSchool, $identity);

        $this->assertSame('recipient_nip', $identity['strategy']);
        $this->assertSame('NIP odbiorcy: 1111111111', $this->service()->identitySummary($identity));
        $this->assertCount(1, $reasons);
        $this->assertSame('recipient_nip', $reasons[0]['key']);
        $this->assertSame('NIP odbiorcy', $reasons[0]['label']);
        $this->assertSame('1111111111', $reasons[0]['value']);
        $this->assertSame('high', $reasons[0]['strength']);
    }

    public function test_link_reasons_for_recipient_profile_may_include_orderer_email(): void
    {
        $current = $this->order([
            'recipient_name' => 'Szkoła Podstawowa nr 1',
            'recipient_address' => 'ul. Mickiewicza 7',
            'recipient_postal_code' => '12-345',
            'recipient_city' => 'Kurzętnik',
            'orderer_email' => 'sekretariat@sp1.example.test',
        ]);
        $sameProfileAndEmail = $this->order([
            'recipient_name' => 'Szkoła Podstawowa nr 1',
            'recipient_address' => 'ul. Mickiewicza 7',
            'recipient_postal_code' => '12-345',
            'recipient_city' => 'Kurzętnik',
            'orderer_email' => 'sekretariat@sp1.example.test',
        ]);

        $identity = $this->service()->identityForOrder($current);
        $reasons = $this->service()->linkReasonsForRelatedOrder($sameProfileAndEmail, $identity);
        $keys = array_column($reasons, 'key');

        $this->assertSame('recipient_profile', $identity['strategy']);
        $this->assertStringContainsString('Dane odbiorcy', $this->service()->identitySummary($identity));
        $this->assertContains('recipient_profile', $keys);
        $this->assertContains('orderer_email', $keys);
    }

    private function service(): DebtCustomerProfileService
    {
        return app(DebtCustomerProfileService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): FormOrder
    {
        static $seq = 1;

        $order = FormOrder::create(array_merge([
            'product_name' => 'Testowe szkolenie '.$seq,
            'product_price' => 300,
            'order_date' => now()->subDays($seq),
            'invoice_number' => $seq.'/8/2026',
        ], $attributes));

        $seq++;

        return $order;
    }

    private function participant(FormOrder $order, string $email): void
    {
        FormOrderParticipant::create([
            'form_order_id' => $order->id,
            'participant_firstname' => 'Anna',
            'participant_lastname' => 'Test',
            'participant_email' => $email,
            'is_primary' => true,
        ]);
    }
}
