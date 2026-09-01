<?php

namespace Tests\Unit;

use App\Services\IfirmaAccountingMonthSyncService;
use App\Services\IfirmaApiService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class IfirmaAccountingMonthSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_nothing_when_accounting_month_already_matches(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 9,
                'year' => 2026,
            ]);
        $api->shouldNotReceive('changeAccountingMonth');

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(Carbon::parse('2026-09-15'));

        $this->assertSame('success', $result['status']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['steps']);
    }

    public function test_advances_accounting_month_with_nast_when_target_is_ahead(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 8,
                'year' => 2026,
            ]);
        $api->shouldReceive('changeAccountingMonth')
            ->once()
            ->with('NAST', false)
            ->andReturn(['status' => 'success']);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 9,
                'year' => 2026,
            ]);

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(Carbon::parse('2026-09-01'));

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['steps']);
        $this->assertSame(8, $result['from_month']);
        $this->assertSame(9, $result['to_month']);
    }

    public function test_uses_carry_from_previous_year_when_advancing_from_december(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 12,
                'year' => 2025,
            ]);
        $api->shouldReceive('changeAccountingMonth')
            ->once()
            ->with('NAST', true)
            ->andReturn(['status' => 'success']);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 1,
                'year' => 2026,
            ]);

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(Carbon::parse('2026-01-02'));

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['changed']);
    }

    public function test_returns_config_error_when_abonent_key_missing(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'config_error',
                'message' => 'Brak skonfigurowanego klucza autoryzacji abonent (IFIRMA_KEY_ABONENT).',
            ]);

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(now());

        $this->assertSame('config_error', $result['status']);
        $this->assertStringContainsString('IFIRMA_KEY_ABONENT', $result['message']);
    }

    public function test_returns_error_when_put_change_fails(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 8,
                'year' => 2026,
            ]);
        $api->shouldReceive('changeAccountingMonth')
            ->once()
            ->with('NAST', false)
            ->andReturn([
                'status' => 'error',
                'message' => 'Odmowa zmiany miesiąca księgowego.',
            ]);

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(Carbon::parse('2026-09-01'));

        $this->assertSame('error', $result['status']);
        $this->assertSame('Odmowa zmiany miesiąca księgowego.', $result['message']);
    }

    public function test_rejects_excessive_month_gap(): void
    {
        $api = Mockery::mock(IfirmaApiService::class);
        $api->shouldReceive('getAccountingMonth')
            ->once()
            ->andReturn([
                'status' => 'success',
                'month' => 1,
                'year' => 2024,
            ]);
        $api->shouldNotReceive('changeAccountingMonth');

        $service = new IfirmaAccountingMonthSyncService($api);
        $result = $service->ensureMatchesDate(Carbon::parse('2026-09-01'));

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('przekracza', $result['message']);
    }
}
