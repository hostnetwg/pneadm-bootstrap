<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Participant;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CertificateRegistrationService
{
    public function __construct(
        private readonly ParticipantAccessExpiryService $accessExpiry,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     birth_date?: ?string,
     *     birth_place?: ?string,
     *     collect_birth_data: bool,
     * }  $data
     * @return array{updated: bool, message: string}
     */
    public function registerOrUpdate(Course $course, array $data): array
    {
        $emailNormalized = Participant::normalizeEmail($data['email']);
        if ($emailNormalized === null) {
            throw new \InvalidArgumentException('E-mail jest wymagany.');
        }

        $maxAttempts = 3;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($course, $data, $emailNormalized) {
                    return $this->registerOrUpdateInTransaction($course, $data, $emailNormalized);
                });
            } catch (UniqueConstraintViolationException|QueryException $e) {
                if ($attempt >= $maxAttempts - 1 || ! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Nie udało się zapisać uczestnika.');
    }

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     birth_date?: ?string,
     *     birth_place?: ?string,
     *     collect_birth_data: bool,
     * }  $data
     * @return array{updated: bool, message: string}
     */
    private function registerOrUpdateInTransaction(Course $course, array $data, string $emailNormalized): array
    {
        $lockedCourse = Course::query()->lockForUpdate()->find($course->id);
        if ($lockedCourse === null) {
            throw new \RuntimeException('Kurs nie istnieje.');
        }

        $accessExpiresAt = $this->accessExpiry->defaultExpiresAtFromCourseEnd($lockedCourse);

        $existing = $this->findExistingParticipant($lockedCourse->id, $emailNormalized);

        $attributes = [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => trim($data['email']),
            'email_normalized' => $emailNormalized,
            'access_expires_at' => $accessExpiresAt,
        ];

        if ($data['collect_birth_data']) {
            $attributes['birth_date'] = ! empty($data['birth_date']) ? $data['birth_date'] : null;
            $attributes['birth_place'] = ! empty($data['birth_place']) ? trim((string) $data['birth_place']) : null;
        }

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update($attributes);

            return [
                'updated' => true,
                'message' => 'Twoje dane zostały zaktualizowane.',
            ];
        }

        $order = (int) $lockedCourse->next_participant_order;
        $lockedCourse->increment('next_participant_order');

        Participant::create(array_merge($attributes, [
            'course_id' => $lockedCourse->id,
            'order' => $order,
        ]));

        return [
            'updated' => false,
            'message' => 'Zostałeś zarejestrowany. Zaświadczenie otrzymasz w oddzielnej wiadomości.',
        ];
    }

    private function findExistingParticipant(int $courseId, string $emailNormalized): ?Participant
    {
        $existing = Participant::withTrashed()
            ->where('course_id', $courseId)
            ->where('email_normalized', $emailNormalized)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Participant::withTrashed()
            ->where('course_id', $courseId)
            ->whereNull('email_normalized')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }
}
