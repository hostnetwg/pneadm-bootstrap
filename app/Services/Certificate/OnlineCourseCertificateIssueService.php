<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OnlineCourseCertificateIssueService
{
    public function __construct(
        private CertificateNumberGenerator $numberGenerator,
    ) {}

    /**
     * @param  array{
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     birth_date?: ?string,
     *     birth_place?: ?string,
     * }  $holder
     */
    public function ensure(OnlineCourseEnrollment $enrollment, array $holder): Certificate
    {
        $this->assertHolderMatchesEnrollment($enrollment, $holder);

        $onlineCourse = $enrollment->onlineCourse;
        if (! $onlineCourse instanceof OnlineCourse) {
            throw new InvalidArgumentException('Kurs online nie istnieje.');
        }

        if (! $onlineCourse->certificatesEnabledForDownload()) {
            throw new InvalidArgumentException('Pobieranie zaświadczeń nie jest udostępnione dla tego kursu.');
        }

        $existing = Certificate::query()
            ->where('online_course_id', $onlineCourse->id)
            ->where('online_course_enrollment_id', $enrollment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($enrollment, $onlineCourse, $holder) {
            $issueDate = $this->resolveIssueDate($onlineCourse);
            $courseYear = $this->numberGenerator->resolveCourseYear($onlineCourse);
            $nextSequence = $this->numberGenerator->determineNextSequence($onlineCourse, $courseYear);
            $certificateNumber = $this->numberGenerator->formatCertificateNumber($onlineCourse, $nextSequence, $courseYear);

            return Certificate::create([
                'online_course_id' => $onlineCourse->id,
                'online_course_enrollment_id' => $enrollment->id,
                'participant_id' => null,
                'course_id' => null,
                'certificate_number' => $certificateNumber,
                'issue_date' => $issueDate,
                'holder_first_name' => trim($holder['first_name']),
                'holder_last_name' => trim($holder['last_name']),
                'holder_birth_date' => ! empty($holder['birth_date']) ? $holder['birth_date'] : null,
                'holder_birth_place' => isset($holder['birth_place']) && trim((string) $holder['birth_place']) !== ''
                    ? trim((string) $holder['birth_place'])
                    : null,
                'holder_email_normalized' => OnlineCourseEnrollment::normalizeEmail($holder['email']),
                'generated_at' => now(),
            ]);
        });
    }

    /**
     * Wydanie zaświadczenia z panelu admina (bez wymogu włączonego pobierania na pnedu).
     */
    public function issueForAdmin(OnlineCourseEnrollment $enrollment): Certificate
    {
        $onlineCourse = $enrollment->onlineCourse;
        if (! $onlineCourse instanceof OnlineCourse) {
            throw new InvalidArgumentException('Kurs online nie istnieje.');
        }

        if (! $onlineCourse->certificate_template_id) {
            throw new InvalidArgumentException('Przypisz szablon zaświadczenia w ustawieniach kursu online.');
        }

        $holder = [
            'email' => $enrollment->email,
            'first_name' => trim((string) ($enrollment->first_name ?? '')),
            'last_name' => trim((string) ($enrollment->last_name ?? '')),
        ];

        if ($holder['first_name'] === '' || $holder['last_name'] === '') {
            throw new InvalidArgumentException('Uzupełnij imię i nazwisko w przypisaniu dostępu przed wydaniem zaświadczenia.');
        }

        $existing = Certificate::query()
            ->where('online_course_id', $onlineCourse->id)
            ->where('online_course_enrollment_id', $enrollment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($enrollment, $onlineCourse, $holder) {
            $issueDate = $this->resolveIssueDate($onlineCourse);
            $courseYear = $this->numberGenerator->resolveCourseYear($onlineCourse);
            $nextSequence = $this->numberGenerator->determineNextSequence($onlineCourse, $courseYear);
            $certificateNumber = $this->numberGenerator->formatCertificateNumber($onlineCourse, $nextSequence, $courseYear);

            return Certificate::create([
                'online_course_id' => $onlineCourse->id,
                'online_course_enrollment_id' => $enrollment->id,
                'participant_id' => null,
                'course_id' => null,
                'certificate_number' => $certificateNumber,
                'issue_date' => $issueDate,
                'holder_first_name' => $holder['first_name'],
                'holder_last_name' => $holder['last_name'],
                'holder_birth_date' => null,
                'holder_birth_place' => null,
                'holder_email_normalized' => OnlineCourseEnrollment::normalizeEmail($holder['email']),
                'generated_at' => now(),
            ]);
        });
    }

    /**
     * @param  array{
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     birth_date?: ?string,
     *     birth_place?: ?string,
     * }  $holder
     */
    public function assertHolderMatchesEnrollment(OnlineCourseEnrollment $enrollment, array $holder): void
    {
        $enrollmentEmail = OnlineCourseEnrollment::normalizeEmail($enrollment->email);
        $holderEmail = OnlineCourseEnrollment::normalizeEmail($holder['email'] ?? '');

        if ($enrollmentEmail === null || $holderEmail === null || $enrollmentEmail !== $holderEmail) {
            throw new InvalidArgumentException('Dane użytkownika nie zgadzają się z zapisem na kurs.');
        }

        if (trim($holder['first_name'] ?? '') === '' || trim($holder['last_name'] ?? '') === '') {
            throw new InvalidArgumentException('Imię i nazwisko są wymagane.');
        }

        if ($enrollment->onlineCourse?->certificate_collect_birth_data
            && $enrollment->onlineCourse->certificate_birth_data_required) {
            if (empty($holder['birth_date']) || trim((string) ($holder['birth_place'] ?? '')) === '') {
                throw new InvalidArgumentException('Data i miejsce urodzenia są wymagane.');
            }
        }
    }

    private function resolveIssueDate(OnlineCourse $onlineCourse): string
    {
        if ($onlineCourse->certificate_issue_date) {
            return $onlineCourse->certificate_issue_date->toDateString();
        }

        return Carbon::now()->toDateString();
    }
}
