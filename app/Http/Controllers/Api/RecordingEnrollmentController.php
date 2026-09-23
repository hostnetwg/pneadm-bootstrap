<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\RecordingEnrollmentService;
use Illuminate\Http\Request;

class RecordingEnrollmentController extends Controller
{
    public function __construct(
        private readonly RecordingEnrollmentService $enrollmentService,
    ) {}

    /**
     * GET /api/recording-enrollment/status/{token}
     */
    public function status(string $token)
    {
        $course = Course::with('instructor')->where('recording_enrollment_token', $token)->first();

        if (! $course) {
            return response()->json([
                'active' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ]);
        }

        $payload = $this->coursePayload($course);

        if (! $course->isRecordingEnrollmentActiveNow()) {
            return response()->json(array_merge($payload, [
                'active' => false,
                'message' => $course->recordingEnrollmentInactiveMessage(),
            ]));
        }

        return response()->json(array_merge($payload, [
            'active' => true,
        ]));
    }

    /**
     * POST /api/recording-enrollment/register
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'rodo_consent' => ['required', 'accepted'],
        ], [
            'first_name.required' => 'Podaj imię.',
            'last_name.required' => 'Podaj nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'Podaj prawidłowy adres e-mail.',
            'rodo_consent.accepted' => 'Musisz wyrazić zgodę na przetwarzanie danych osobowych.',
        ]);

        $course = Course::query()
            ->where('recording_enrollment_token', $validated['token'])
            ->first();

        if ($course === null) {
            return response()->json([
                'success' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ], 404);
        }

        $result = $this->enrollmentService->register($course, [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
        ]);

        $httpCode = (int) ($result['http_code'] ?? 200);
        unset($result['http_code']);

        return response()->json($result, $httpCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course): array
    {
        $instructorName = null;
        $instructorPhoto = null;
        if ($course->instructor) {
            $instructorName = $course->instructor->full_title_name ?? $course->instructor->full_name;
            $instructorPhoto = $course->instructor->photo ? ltrim($course->instructor->photo, '/') : null;
        }

        $endsDisplay = null;
        if ($course->recording_enrollment_ends_at !== null) {
            $endsDisplay = $course->recording_enrollment_ends_at
                ->copy()
                ->timezone(config('app.timezone'))
                ->format('d.m.Y H:i');
        }

        $courseStartDisplay = null;
        if ($course->start_date !== null) {
            $dt = $course->start_date->copy()->timezone(config('app.timezone'));
            $courseStartDisplay = ((int) $dt->format('H') === 0 && (int) $dt->format('i') === 0)
                ? $dt->format('d.m.Y')
                : $dt->format('d.m.Y H:i');
        }

        return [
            'course_title' => $course->plainTitle(),
            'course_start_display' => $courseStartDisplay,
            'enrollment_ends_at_display' => $endsDisplay,
            'instructor_name' => $instructorName,
            'instructor_photo' => $instructorPhoto,
        ];
    }
}
