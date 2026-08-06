<?php

namespace Tests\Feature;

use App\Models\DebtCollectionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtCollectionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_collections_index_links_to_settings(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('accounting.collections.index'));

        $response->assertOk();
        $response->assertSee('Ustawienia', false);
        $response->assertSee(route('accounting.collections.settings.edit'), false);
    }

    public function test_user_can_save_contact_phone_setting(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $edit = $this->actingAs($user)->get(route('accounting.collections.settings.edit'));
        $edit->assertOk();
        $edit->assertSee('Telefon kontaktowy', false);

        $response = $this->actingAs($user)->post(route('accounting.collections.settings.update'), [
            'contact_phone' => '+48 111 222 333',
        ]);

        $response->assertRedirect(route('accounting.collections.settings.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('debt_collection_settings', [
            'id' => 1,
            'contact_phone' => '+48 111 222 333',
            'updated_by' => $user->id,
        ]);
        $this->assertSame('+48 111 222 333', DebtCollectionSetting::contactPhone());
    }
}
