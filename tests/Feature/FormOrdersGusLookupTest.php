<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GusBirService;
use Mockery;
use Tests\TestCase;

class FormOrdersGusLookupTest extends TestCase
{
    public function test_authenticated_user_can_lookup_company_by_nip(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $gusBir = Mockery::mock(GusBirService::class);
        $gusBir->shouldReceive('normalizeNip')
            ->once()
            ->with('123-456-78-90')
            ->andReturn('1234567890');
        $gusBir->shouldReceive('lookupByNip')
            ->once()
            ->with('1234567890')
            ->andReturn([
                'nip' => '1234567890',
                'regon' => '123456789',
                'name' => 'Testowa Szkoła',
                'postcode' => '00-001',
                'city' => 'Warszawa',
                'address' => 'Testowa 1',
            ]);
        $this->app->instance(GusBirService::class, $gusBir);

        $response = $this->actingAs($user)->postJson(route('form-orders.gus-lookup'), [
            'nip' => '123-456-78-90',
            'target' => 'buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Testowa Szkoła')
            ->assertJsonPath('data.postcode', '00-001');
    }

    public function test_invalid_nip_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $gusBir = Mockery::mock(GusBirService::class);
        $gusBir->shouldReceive('normalizeNip')
            ->once()
            ->with('123')
            ->andReturn(null);
        $gusBir->shouldNotReceive('lookupByNip');
        $this->app->instance(GusBirService::class, $gusBir);

        $this->actingAs($user)->postJson(route('form-orders.gus-lookup'), [
            'nip' => '123',
            'target' => 'recipient',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('nip');
    }

    public function test_guest_cannot_lookup_gus(): void
    {
        $this->postJson(route('form-orders.gus-lookup'), [
            'nip' => '1234567890',
            'target' => 'buyer',
        ])->assertUnauthorized();
    }
}
