<?php

namespace Tests\Unit;

use App\Services\OnlineCourseEnrollmentPubligoImportService;
use Carbon\Carbon;
use Tests\TestCase;

class OnlineCourseEnrollmentPubligoImportServiceTest extends TestCase
{
    public function test_parses_publigo_csv_unlimited_dated_and_first_name_only(): void
    {
        $service = new OnlineCourseEnrollmentPubligoImportService;
        $parsed = $service->parseFile(base_path('tests/fixtures/online-courses/publigo_enrollments_sample.csv'));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(0, $parsed['skipped_in_file']);
        $this->assertCount(4, $parsed['rows']);

        $byEmail = collect($parsed['rows'])->keyBy('email');

        $anna = $byEmail['anna.nowak@example.test'];
        $this->assertSame('Anna', $anna['first_name']);
        $this->assertSame('Nowak', $anna['last_name']);
        $this->assertSame('501111111', $anna['phone']);
        $this->assertSame('255', $anna['legacy_publigo_user_id']);
        $this->assertNull($anna['access_expires_at']);

        $jan = $byEmail['jan.kowalski@example.test'];
        $this->assertSame('Jan', $jan['first_name']);
        $this->assertSame('Kowalski', $jan['last_name']);
        $this->assertInstanceOf(Carbon::class, $jan['access_expires_at']);
        $this->assertSame(
            '2027-02-09 18:43:33',
            $jan['access_expires_at']->copy()->utc()->format('Y-m-d H:i:s')
        );

        $iwona = $byEmail['iwona.solo@example.test'];
        $this->assertSame('Iwona', $iwona['first_name']);
        $this->assertNull($iwona['last_name']);
        $this->assertNull($iwona['phone']);
    }

    public function test_file_duplicate_emails_are_counted_as_skipped_in_file(): void
    {
        $csv = <<<'CSV'
ID,"E-mail uczestnika","Imię i nazwisko","Numer telefonu",Postęp,"Dopisano do kursu","Dostęp wygasa"
1,dup@example.test,"Anna Nowak",501111111,"0 / 1 (0%)",-,"Bez limitu"
2,dup@example.test,"Anna Inna",502222222,"0 / 1 (0%)",-,"Bez limitu"
CSV;
        $path = $this->writeTempCsv($csv);
        $parsed = (new OnlineCourseEnrollmentPubligoImportService)->parseFile($path);
        @unlink($path);

        $this->assertCount(1, $parsed['rows']);
        $this->assertSame(1, $parsed['skipped_in_file']);
        $this->assertSame([], $parsed['errors']);
    }

    public function test_invalid_expiry_is_reported(): void
    {
        $csv = <<<'CSV'
ID,"E-mail uczestnika","Imię i nazwisko","Numer telefonu",Postęp,"Dopisano do kursu","Dostęp wygasa"
1,bad-date@example.test,"Anna Nowak",501111111,"0 / 1 (0%)",-,"nie-data"
CSV;
        $path = $this->writeTempCsv($csv);
        $parsed = (new OnlineCourseEnrollmentPubligoImportService)->parseFile($path);
        @unlink($path);

        $this->assertSame([], $parsed['rows']);
        $this->assertNotEmpty($parsed['errors']);
        $this->assertStringContainsString('daty wygaśnięcia', $parsed['errors'][0]);
    }

    private function writeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'publigo_csv_');
        file_put_contents($path, $contents);

        return $path;
    }
}
