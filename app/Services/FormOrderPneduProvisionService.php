<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FormOrder;
use App\Models\Instructor;
use App\Models\Participant;
use App\Models\PneduUser;
use App\Notifications\PneduFormOrderProvisionedExistingUser;
use App\Notifications\PneduFormOrderProvisionedNewUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class FormOrderPneduProvisionService
{
    /**
     * @return array{success: bool, error?: string, message?: string, http_code: int, email_warning?: string}
     */
    public function provision(int $formOrderId): array
    {
        $emailWarning = null;

        try {
            $afterCommit = null;

            $payload = DB::connection('mysql')->transaction(function () use ($formOrderId, &$afterCommit) {
                $order = FormOrder::with('primaryParticipant')->lockForUpdate()->find($formOrderId);

                if (! $order) {
                    return ['success' => false, 'error' => 'Zamówienie nie zostało znalezione.', 'http_code' => 404];
                }

                if ($order->pnedu_provisioned_at !== null) {
                    return [
                        'success' => false,
                        'error' => 'Dostęp PNEDU został już przyznany dla tego zamówienia.',
                        'http_code' => 400,
                        'sent_at' => $order->pnedu_provisioned_at->timezone('Europe/Warsaw')->format('d.m.Y H:i'),
                    ];
                }

                $course = Course::query()->with('instructor')->find($order->product_id);
                if (! $course) {
                    return ['success' => false, 'error' => 'Nie znaleziono szkolenia (kursu) dla product_id tego zamówienia.', 'http_code' => 400];
                }

                $p = $order->primaryParticipant;
                $emailRaw = $p ? trim((string) ($p->participant_email ?? '')) : '';
                $email = strtolower($emailRaw);
                $firstName = $p ? trim((string) ($p->participant_firstname ?? '')) : '';
                $lastName = $p ? trim((string) ($p->participant_lastname ?? '')) : '';

                if ($email === '' || ! str_contains($email, '@')) {
                    return ['success' => false, 'error' => 'Brak prawidłowego e-maila uczestnika (form_order_participants, główny uczestnik).', 'http_code' => 400];
                }

                if ($firstName === '' || $lastName === '') {
                    return ['success' => false, 'error' => 'Brak imienia lub nazwiska uczestnika (form_order_participants).', 'http_code' => 400];
                }

                $participantExists = Participant::query()
                    ->where('course_id', $course->id)
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                    ->exists();

                if ($participantExists) {
                    return [
                        'success' => false,
                        'error' => 'Uczestnik z tym adresem e-mail jest już zapisany na to szkolenie (participants).',
                        'http_code' => 400,
                    ];
                }

                $userExisted = PneduUser::query()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                    ->exists();

                if (! $userExisted) {
                    PneduUser::query()->create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'password' => Hash::make(Str::password(48)),
                        'email_verified_at' => now(),
                    ]);
                }

                $birthData = $this->copyBirthDataFromPreviousParticipant($email);
                $accessExpiresAt = $this->computeAccessExpiresAtForCourse($course);

                Participant::query()->create([
                    'course_id' => $course->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailRaw !== '' ? $emailRaw : $email,
                    'birth_date' => $birthData['birth_date'],
                    'birth_place' => $birthData['birth_place'],
                    'order' => Participant::query()->where('course_id', $course->id)->count() + 1,
                    'access_expires_at' => $accessExpiresAt,
                ]);

                $order->pnedu_provisioned_at = now();
                $order->pnedu_user_existed_before = $userExisted;
                if (! $order->save()) {
                    return ['success' => false, 'error' => 'Nie udało się zapisać statusu PNEDU przy zamówieniu.', 'http_code' => 500];
                }

                $afterCommit = [
                    'email' => $email,
                    'user_existed' => $userExisted,
                    'course_title' => (string) $course->title,
                    'instructor_line' => $this->instructorLineForProvisionEmail($course->instructor),
                    'start_date_line' => $this->startDateLineForProvisionEmail($course),
                ];

                return [
                    'success' => true,
                    'message' => 'Uczestnik dodany, konto PNEDU obsłużone. Wysłano wiadomość e-mail do uczestnika.',
                    'http_code' => 200,
                    'provisioned_at' => $order->pnedu_provisioned_at->timezone('Europe/Warsaw')->format('d.m.Y H:i'),
                    'user_existed' => $userExisted,
                ];
            });

            if (! ($payload['success'] ?? false) || $afterCommit === null) {
                return $payload;
            }

            $pneduUser = PneduUser::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$afterCommit['email']])
                ->first();

            if (! $pneduUser) {
                Log::error('FormOrderPneduProvisionService: brak PneduUser po provision', ['email' => $afterCommit['email'], 'form_order_id' => $formOrderId]);

                return array_merge($payload, [
                    'success' => true,
                    'message' => $payload['message'].' Uwaga: nie znaleziono rekordu użytkownika w bazie PNEDU do wysyłki e-maila.',
                    'email_warning' => 'Nie wysłano e-maila — brak użytkownika w bazie pnedu.',
                ]);
            }

            try {
                if ($afterCommit['user_existed']) {
                    $pneduUser->notify(new PneduFormOrderProvisionedExistingUser(
                        $afterCommit['course_title'],
                        $afterCommit['instructor_line'] ?? null,
                        $afterCommit['start_date_line'] ?? null,
                    ));
                } else {
                    $token = Password::broker('pnedu_users')->createToken($pneduUser);
                    $pneduUser->notify(new PneduFormOrderProvisionedNewUser(
                        $token,
                        $afterCommit['course_title'],
                        $afterCommit['instructor_line'] ?? null,
                        $afterCommit['start_date_line'] ?? null,
                    ));
                }
            } catch (Throwable $e) {
                Log::error('FormOrderPneduProvisionService: błąd wysyłki e-maila', [
                    'form_order_id' => $formOrderId,
                    'email' => $afterCommit['email'],
                    'exception' => $e->getMessage(),
                ]);
                $emailWarning = 'Dane zapisano, ale wysłanie e-maila nie powiodło się: '.$e->getMessage();
            }

            if ($emailWarning !== null) {
                $payload['message'] = ($payload['message'] ?? '').' '.$emailWarning;
                $payload['email_warning'] = $emailWarning;
            }

            Log::info('FormOrderPneduProvisionService: sukces', [
                'form_order_id' => $formOrderId,
                'email' => $afterCommit['email'],
                'user_existed' => $afterCommit['user_existed'],
            ]);

            return $payload;
        } catch (Throwable $e) {
            Log::error('FormOrderPneduProvisionService: wyjątek', [
                'form_order_id' => $formOrderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Wystąpił błąd: '.$e->getMessage(),
                'http_code' => 500,
            ];
        }
    }

    /**
     * @return array{birth_date: ?\Carbon\Carbon, birth_place: ?string}
     */
    private function copyBirthDataFromPreviousParticipant(string $email): array
    {
        $previous = Participant::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->where(function ($q) {
                $q->whereNotNull('birth_date')->orWhereNotNull('birth_place');
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $previous) {
            return ['birth_date' => null, 'birth_place' => null];
        }

        return [
            'birth_date' => $previous->birth_date,
            'birth_place' => $previous->birth_place ? (string) $previous->birth_place : null,
        ];
    }

    /**
     * Etykieta + tytuł zawodowy i imię oraz nazwisko prowadzącego (jak na pnedu.pl).
     */
    private function instructorLineForProvisionEmail(?Instructor $instructor): ?string
    {
        if (! $instructor) {
            return null;
        }

        $gender = strtolower((string) ($instructor->gender ?? ''));
        $label = match ($gender) {
            'female', 'f', 'kobieta' => 'Prowadząca',
            default => 'Prowadzący',
        };

        return $label.': '.$instructor->full_title_name;
    }

    /**
     * Data rozpoczęcia — tylko gdy szkolenie nie jest uznane za zakończone (end_date w przeszłości).
     * Po zakończeniu na żywo (np. samo nagranie) daty nie pokazujemy.
     */
    private function startDateLineForProvisionEmail(Course $course): ?string
    {
        if (! $course->start_date) {
            return null;
        }

        if ($course->end_date && $course->end_date->isPast()) {
            return null;
        }

        // Bez end_date: jeśli start już minął, nie pokazujemy terminu (np. dostęp tylko do nagrania po szkoleniu).
        if (! $course->end_date && $course->start_date->isPast()) {
            return null;
        }

        $formatted = $course->start_date->copy()->timezone(config('app.timezone'))->format('d.m.Y G:i');

        return 'Data rozpoczęcia: '.$formatted;
    }

    private function computeAccessExpiresAtForCourse(Course $course): ?Carbon
    {
        $now = now();

        if ($course->start_date) {
            $startDate = $course->start_date;
            if ($now->lt($startDate)) {
                return $startDate->copy()->addMonths(2);
            }

            return $now->copy()->addMonths(2);
        }

        if ($course->access_duration_days) {
            return $now->copy()->addDays((int) $course->access_duration_days);
        }

        return null;
    }
}
