<?php

namespace Tests\Unit;

use App\Models\BankStatementImport;
use App\Services\Bank\BankStatementCoverageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankStatementCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BankStatementCoverageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BankStatementCoverageService;
        Carbon::setTestNow(Carbon::parse('2026-08-07'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_gaps_when_no_imports(): void
    {
        $this->assertSame([], $this->service->detectGaps());
    }

    public function test_detects_internal_gap_between_imports(): void
    {
        $this->createImport('2026-01-01', '2026-01-31');
        $this->createImport('2026-02-05', '2026-02-28');

        $gaps = $this->service->detectGaps(Carbon::parse('2026-02-28'));

        $this->assertCount(1, $gaps);
        $this->assertSame('2026-02-01', $gaps[0]['from']);
        $this->assertSame('2026-02-04', $gaps[0]['to']);
        $this->assertSame(4, $gaps[0]['days']);
        $this->assertFalse($gaps[0]['trailing']);
    }

    public function test_adjacent_periods_have_no_internal_gap(): void
    {
        $this->createImport('2026-01-01', '2026-01-31');
        $this->createImport('2026-02-01', '2026-02-28');

        $gaps = $this->service->detectGaps(Carbon::parse('2026-02-28'));

        $this->assertSame([], $gaps);
    }

    public function test_overlapping_periods_are_merged(): void
    {
        $this->createImport('2026-01-01', '2026-01-31');
        $this->createImport('2026-01-15', '2026-02-10');

        $gaps = $this->service->detectGaps(Carbon::parse('2026-02-10'));

        $this->assertSame([], $gaps);
    }

    public function test_trailing_gap_to_as_of_date(): void
    {
        $this->createImport('2026-07-01', '2026-07-31');

        $gaps = $this->service->detectGaps(Carbon::parse('2026-08-07'));

        $this->assertCount(1, $gaps);
        $this->assertSame('2026-08-01', $gaps[0]['from']);
        $this->assertSame('2026-08-07', $gaps[0]['to']);
        $this->assertTrue($gaps[0]['trailing']);
        $this->assertSame(7, $gaps[0]['days']);
    }

    public function test_format_gaps_summary(): void
    {
        $summary = $this->service->formatGapsSummary([
            ['from' => '2026-02-01', 'to' => '2026-02-04', 'days' => 4],
            ['from' => '2026-08-01', 'to' => '2026-08-01', 'days' => 1],
        ]);

        $this->assertStringContainsString('2026-02-01 → 2026-02-04 (4 dni)', $summary);
        $this->assertStringContainsString('2026-08-01', $summary);
    }

    private function createImport(string $from, string $to): BankStatementImport
    {
        return BankStatementImport::create([
            'original_filename' => 'lista_'.$from.'.csv',
            'file_hash' => hash('sha256', $from.$to.uniqid('', true)),
            'source' => BankStatementImport::SOURCE_MBANK,
            'status' => BankStatementImport::STATUS_PARSED,
            'period_from' => $from,
            'period_to' => $to,
            'rows_total' => 0,
            'rows_incoming' => 0,
            'rows_matched' => 0,
            'rows_duplicate' => 0,
        ]);
    }
}
