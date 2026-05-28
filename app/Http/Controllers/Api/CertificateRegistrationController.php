<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Participant;
use App\Services\ParticipantAccessExpiryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificateRegistrationController extends Controller
{
    /**
     * @return array{ok: bool, iso: ?string, message: ?string}
     */
    private function parseBirthDateInput(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['ok' => true, 'iso' => null, 'message' => null];
        }

        $raw = trim($raw);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $dt = Carbon::parse($raw)->startOfDay();
            } elseif (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $raw)) {
                $dt = Carbon::createFromFormat('!d.m.Y', $raw)->startOfDay();
            } else {
                return ['ok' => false, 'iso' => null, 'message' => 'Podaj datę urodzenia w formacie dd.mm.rrrr, np. 03.05.1984.'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'iso' => null, 'message' => 'Podana data urodzenia jest nieprawidłowa (sprawdź dzień i miesiąc).'];
        }

        if ($dt->isFuture()) {
            return ['ok' => false, 'iso' => null, 'message' => 'Data urodzenia nie może być z przyszłości.'];
        }

        return ['ok' => true, 'iso' => $dt->format('Y-m-d'), 'message' => null];
    }

    private function formatCourseStartDisplay(Course $course): ?string
    {
        if ($course->start_date === null) {
            return null;
        }

        $dt = $course->start_date->copy()->timezone(config('app.timezone'));

        if ((int) $dt->format('H') === 0 && (int) $dt->format('i') === 0) {
            return $dt->format('d.m.Y');
        }

        return $dt->format('d.m.Y H:i');
    }

    private function formatCertificateRegistrationEndsDisplay(Course $course): ?string
    {
        if ($course->certificate_registration_ends_at === null) {
            return null;
        }

        return $course->certificate_registration_ends_at
            ->copy()
            ->timezone(config('app.timezone'))
            ->format('d.m.Y H:i');
    }

    /**
     * Status rejestracji zaświadczenia po tokenie (czy formularz jest aktywny).
     * GET /api/certificate-registration/status/{token}
     */
    public function status(string $token)
    {
        $course = Course::with('instructor')->where('certificate_registration_token', $token)->first();

        if (! $course) {
            return response()->json([
                'active' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ]);
        }

        $courseStartDisplay = $this->formatCourseStartDisplay($course);
        $registrationEndsDisplay = $this->formatCertificateRegistrationEndsDisplay($course);

        if (! $course->certificate_registration_open) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
                'course_start_display' => $courseStartDisplay,
                'certificate_registration_ends_at_display' => $registrationEndsDisplay,
                'message' => 'Rejestracja zaświadczenia jest wyłączona.',
            ]);
        }

        $now = now();
        if ($course->certificate_registration_starts_at && $now->lt($course->certificate_registration_starts_at)) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
                'course_start_display' => $courseStartDisplay,
                'certificate_registration_ends_at_display' => $registrationEndsDisplay,
                'message' => 'Rejestracja nie jest jeszcze dostępna.',
            ]);
        }
        if ($course->certificate_registration_ends_at && $now->gt($course->certificate_registration_ends_at)) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
                'course_start_display' => $courseStartDisplay,
                'certificate_registration_ends_at_display' => $registrationEndsDisplay,
                'message' => 'Rejestracja zakończyła się.',
            ]);
        }

        $instructorName = null;
        $instructorPhoto = null;
        if ($course->instructor) {
            $instructorName = $course->instructor->full_title_name ?? $course->instructor->full_name;
            $instructorPhoto = $course->instructor->photo ? ltrim($course->instructor->photo, '/') : null;
        }

        return response()->json([
            'active' => true,
            'course_title' => $course->title,
            'course_start_display' => $courseStartDisplay,
            'certificate_registration_ends_at_display' => $registrationEndsDisplay,
            'instructor_name' => $instructorName,
            'instructor_photo' => $instructorPhoto,
            'certificate_registration_collect_birth_data' => (bool) $course->certificate_registration_collect_birth_data,
            'certificate_registration_birth_data_required' => (bool) $course->certificate_registration_birth_data_required,
        ]);
    }

    /**
     * Rejestracja uczestnika (utworzenie lub aktualizacja rekordu w participants przy tym samym e-mailu na kursie).
     * POST /api/certificate-registration/register
     * Body: token, first_name, last_name, email [, birth_date, birth_place gdy kurs zbiera te dane]
     */
    public function register(Request $request)
    {
        $course = Course::where('certificate_registration_token', $request->input('token'))->first();

        if (! $course) {
            return response()->json([
                'success' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ], 404);
        }

        if (! $course->certificate_registration_open) {
            return response()->json([
                'success' => false,
                'message' => 'Rejestracja zaświadczenia jest wyłączona.',
            ], 403);
        }

        $now = now();
        if ($course->certificate_registration_starts_at && $now->lt($course->certificate_registration_starts_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Rejestracja nie jest jeszcze dostępna.',
            ], 403);
        }
        if ($course->certificate_registration_ends_at && $now->gt($course->certificate_registration_ends_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Rejestracja zakończyła się.',
            ], 403);
        }

        $rules = [
            'token' => 'required|string|max:64',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'rodo_consent' => 'required|accepted',
            'newsletter_consent' => 'sometimes|boolean',
        ];
        $messages = [
            'rodo_consent.accepted' => 'Musisz wyrazić zgodę na przetwarzanie danych osobowych.',
            'birth_date.required' => 'Podaj datę urodzenia.',
            'birth_date.date' => 'Podaj prawidłową datę urodzenia (np. 03.05.1984).',
            'birth_place.required' => 'Podaj miejsce urodzenia.',
        ];

        if ($course->certificate_registration_collect_birth_data) {
            if ($course->certificate_registration_birth_data_required) {
                $rules['birth_date'] = 'required|date';
                $rules['birth_place'] = 'required|string|max:255';
            } else {
                $rules['birth_date'] = 'nullable|date';
                $rules['birth_place'] = 'nullable|string|max:255';
            }
        }

        if ($course->certificate_registration_collect_birth_data) {
            $parsed = $this->parseBirthDateInput($request->input('birth_date'));
            if (! $parsed['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nieprawidłowe dane.',
                    'errors' => ['birth_date' => [$parsed['message']]],
                ], 422);
            }
            $request->merge(['birth_date' => $parsed['iso']]);

            $place = trim((string) $request->input('birth_place', ''));
            $request->merge(['birth_place' => $place === '' ? null : $place]);
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Nieprawidłowe dane.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $emailNormalized = strtolower(trim($request->input('email')));
        $existing = Participant::where('course_id', $course->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->first();

        if ($existing) {
            $update = [
                'first_name' => trim($request->input('first_name')),
                'last_name' => trim($request->input('last_name')),
                'email' => trim($request->input('email')),
            ];

            if ($existing->access_expires_at === null) {
                $update['access_expires_at'] = app(ParticipantAccessExpiryService::class)
                    ->defaultExpiresAtFromCourseEnd($course);
            }

            if ($course->certificate_registration_collect_birth_data) {
                $update['birth_date'] = $request->filled('birth_date') ? $request->input('birth_date') : null;
                $update['birth_place'] = $request->filled('birth_place') ? trim((string) $request->input('birth_place')) : null;
            }

            $existing->update($update);

            return response()->json([
                'success' => true,
                'updated' => true,
                'message' => 'Twoje dane zostały zaktualizowane.',
            ]);
        }

        $maxOrder = (int) Participant::where('course_id', $course->id)->max('order');

        $payload = [
            'course_id' => $course->id,
            'order' => $maxOrder + 1,
            'first_name' => trim($request->input('first_name')),
            'last_name' => trim($request->input('last_name')),
            'email' => trim($request->input('email')),
            'access_expires_at' => app(ParticipantAccessExpiryService::class)
                ->defaultExpiresAtFromCourseEnd($course),
        ];

        if ($course->certificate_registration_collect_birth_data) {
            $payload['birth_date'] = $request->filled('birth_date') ? $request->input('birth_date') : null;
            $payload['birth_place'] = $request->filled('birth_place') ? trim((string) $request->input('birth_place')) : null;
        }

        Participant::create($payload);

        return response()->json([
            'success' => true,
            'updated' => false,
            'message' => 'Zostałeś zarejestrowany. Zaświadczenie otrzymasz w oddzielnej wiadomości.',
        ]);
    }
}
