<?php

namespace App\Services;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OnlineCourseEnrollmentPubligoImportService
{
    public const ACCESS_SOURCE = 'publigo_migration';

    public const MAX_ROWS = 10000;

    /**
     * @return array{
     *     imported: int,
     *     skipped_existing: int,
     *     skipped_expired: int,
     *     skipped_invalid: int,
     *     errors: list<string>
     * }
     */
    public function importUploadedFile(OnlineCourse $course, UploadedFile $file, bool $skipExpired): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Nie udało się odczytać wgranego pliku CSV.');
        }

        $parsed = $this->parseFile($path);

        return $this->importRows(
            $course,
            $parsed['rows'],
            $parsed['errors'],
            $skipExpired,
            $parsed['skipped_in_file']
        );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, skipped_in_file: int}
     */
    public function parseFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Nie udało się otworzyć pliku CSV.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('Plik CSV jest pusty lub nieczytelny.');
            }
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                throw new RuntimeException('Plik CSV jest pusty lub nieczytelny.');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];

            $normalizedHeader = array_map(function ($column) {
                $value = mb_strtolower(trim((string) $column, "\" \t\n\r"));
                $value = str_replace(['"', "'"], '', $value);

                return $this->stripPolishDiacritics($value);
            }, $header);

            $rows = [];
            $errors = [];
            $skippedInFile = 0;
            $seenEmails = [];
            $line = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                if (count($rows) + count($errors) + $skippedInFile >= self::MAX_ROWS) {
                    $errors[] = 'Przekroczono limit '.self::MAX_ROWS.' wierszy. Podziel plik na części.';
                    break;
                }

                if (count($row) < count($normalizedHeader)) {
                    $row = array_pad($row, count($normalizedHeader), '');
                } elseif (count($row) > count($normalizedHeader)) {
                    $row = array_slice($row, 0, count($normalizedHeader));
                }

                $csvData = array_combine($normalizedHeader, $row);
                if ($csvData === false) {
                    $errors[] = 'Wiersz '.$line.': nie udało się sparsować kolumn.';

                    continue;
                }

                $mapped = $this->mapRow($csvData, $line);
                if ($mapped['error'] !== null) {
                    $errors[] = $mapped['error'];

                    continue;
                }

                $email = $mapped['email'];
                if (isset($seenEmails[$email])) {
                    $skippedInFile++;

                    continue;
                }
                $seenEmails[$email] = true;
                $rows[] = $mapped;
            }

            return ['rows' => $rows, 'errors' => $errors, 'skipped_in_file' => $skippedInFile];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $parseErrors
     * @return array{
     *     imported: int,
     *     skipped_existing: int,
     *     skipped_expired: int,
     *     skipped_invalid: int,
     *     errors: list<string>
     * }
     */
    public function importRows(OnlineCourse $course, array $rows, array $parseErrors, bool $skipExpired, int $skippedInFile = 0): array
    {
        $imported = 0;
        $skippedExisting = $skippedInFile;
        $skippedExpired = 0;
        $nowUtc = Carbon::now('UTC');

        $existingEmails = $course->enrollments()
            ->pluck('email')
            ->map(fn ($email) => OnlineCourseEnrollment::normalizeEmail((string) $email))
            ->filter()
            ->flip()
            ->all();

        DB::transaction(function () use ($course, $rows, $skipExpired, $nowUtc, &$existingEmails, &$imported, &$skippedExisting, &$skippedExpired) {
            foreach ($rows as $row) {
                $email = $row['email'];
                if (isset($existingEmails[$email])) {
                    $skippedExisting++;

                    continue;
                }

                $expiresAt = $row['access_expires_at'];
                if ($skipExpired && $expiresAt instanceof Carbon && $expiresAt->lt($nowUtc)) {
                    $skippedExpired++;

                    continue;
                }

                OnlineCourseEnrollment::query()->create([
                    'online_course_id' => $course->id,
                    'email' => $email,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'access_expires_at' => $expiresAt,
                    'access_source' => self::ACCESS_SOURCE,
                    'legacy_publigo_user_id' => $row['legacy_publigo_user_id'],
                    'notes' => null,
                ]);

                $existingEmails[$email] = true;
                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped_existing' => $skippedExisting,
            'skipped_expired' => $skippedExpired,
            'skipped_invalid' => count($parseErrors),
            'errors' => array_slice($parseErrors, 0, 20),
        ];
    }

    public function flashMessage(array $result): string
    {
        $parts = [sprintf('Zaimportowano %d dostępów.', (int) $result['imported'])];
        if ((int) $result['skipped_existing'] > 0) {
            $parts[] = sprintf('Pominięto %d (e-mail już na kursie lub duplikat w pliku).', (int) $result['skipped_existing']);
        }
        if ((int) $result['skipped_expired'] > 0) {
            $parts[] = sprintf('Pominięto %d z już wygasłym dostępem.', (int) $result['skipped_expired']);
        }
        if ((int) $result['skipped_invalid'] > 0) {
            $parts[] = sprintf('Pominięto %d niepoprawnych wierszy.', (int) $result['skipped_invalid']);
        }
        if (! empty($result['errors'])) {
            $parts[] = 'Szczegóły: '.implode(' ', $result['errors']);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $csvData
     * @return array{
     *     line: int,
     *     email: string,
     *     first_name: string,
     *     last_name: ?string,
     *     phone: ?string,
     *     legacy_publigo_user_id: ?string,
     *     access_expires_at: ?Carbon,
     *     error: ?string
     * }
     */
    private function mapRow(array $csvData, int $line): array
    {
        $emailRaw = $this->firstValue($csvData, ['e-mail uczestnika', 'email uczestnika', 'e-mail', 'email']);
        $email = OnlineCourseEnrollment::normalizeEmail($emailRaw);
        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->invalidRow($line, 'brak lub nieprawidłowy e-mail.');
        }

        $fullName = trim((string) $this->firstValue($csvData, ['imie i nazwisko', 'imię i nazwisko', 'name', 'nazwisko']), "\" \t");
        if ($fullName === '') {
            return $this->invalidRow($line, 'brak imienia i nazwiska.');
        }
        $nameParts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $firstName = trim((string) ($nameParts[0] ?? ''));
        $lastName = trim((string) ($nameParts[1] ?? ''));
        if ($firstName === '') {
            return $this->invalidRow($line, 'brak imienia.');
        }

        $phoneRaw = $this->firstValue($csvData, ['numer telefonu', 'nr telefonu', 'telefon', 'telephone', 'phone']);
        $phone = $phoneRaw !== null && trim($phoneRaw) !== ''
            ? mb_substr(trim($phoneRaw, "\" \t"), 0, 50)
            : null;

        $idRaw = $this->firstValue($csvData, ['id']);
        $legacyId = $idRaw !== null && trim($idRaw) !== ''
            ? mb_substr(trim($idRaw), 0, 32)
            : null;

        $expiresRaw = $this->firstValue($csvData, ['dostep wygasa', 'dostęp wygasa']);
        $expiresAt = $this->parseAccessExpiresAt($expiresRaw);
        if ($expiresRaw !== null && trim($expiresRaw) !== '' && ! $this->isUnlimitedExpiry($expiresRaw) && $expiresAt === null) {
            return $this->invalidRow($line, 'nie udało się odczytać daty wygaśnięcia ('.$expiresRaw.').');
        }

        return [
            'line' => $line,
            'email' => $email,
            'first_name' => mb_substr($firstName, 0, 190),
            'last_name' => $lastName === '' ? null : mb_substr($lastName, 0, 190),
            'phone' => $phone,
            'legacy_publigo_user_id' => $legacyId,
            'access_expires_at' => $expiresAt,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     line: int,
     *     email: string,
     *     first_name: string,
     *     last_name: ?string,
     *     phone: ?string,
     *     legacy_publigo_user_id: ?string,
     *     access_expires_at: ?Carbon,
     *     error: string
     * }
     */
    private function invalidRow(int $line, string $message): array
    {
        return [
            'line' => $line,
            'email' => '',
            'first_name' => '',
            'last_name' => null,
            'phone' => null,
            'legacy_publigo_user_id' => null,
            'access_expires_at' => null,
            'error' => 'Wiersz '.$line.': '.$message,
        ];
    }

    private function parseAccessExpiresAt(?string $raw): ?Carbon
    {
        if ($raw === null) {
            return null;
        }
        $value = trim($raw, "\" \t");
        if ($value === '' || $this->isUnlimitedExpiry($value)) {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'd.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value, 'Europe/Warsaw');
                if ($parsed instanceof Carbon) {
                    if ($format === 'Y-m-d') {
                        $parsed->endOfDay();
                    }

                    return $parsed->utc();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function isUnlimitedExpiry(string $value): bool
    {
        $normalized = $this->stripPolishDiacritics(mb_strtolower(trim($value, "\" \t")));

        return in_array($normalized, ['bez limitu', '-', 'n/a', 'na', 'unlimited'], true);
    }

    /**
     * @param  array<string, mixed>  $csvData
     * @param  list<string>  $keys
     */
    private function firstValue(array $csvData, array $keys): ?string
    {
        foreach ($keys as $key) {
            $lookup = $this->stripPolishDiacritics(mb_strtolower($key));
            if (array_key_exists($lookup, $csvData) && $csvData[$lookup] !== null && $csvData[$lookup] !== '') {
                return (string) $csvData[$lookup];
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripPolishDiacritics(string $value): string
    {
        $map = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ];

        return strtr($value, $map);
    }
}
