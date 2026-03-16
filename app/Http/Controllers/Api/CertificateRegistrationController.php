<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificateRegistrationController extends Controller
{
    /**
     * Status rejestracji zaświadczenia po tokenie (czy formularz jest aktywny).
     * GET /api/certificate-registration/status/{token}
     */
    public function status(string $token)
    {
        $course = Course::with('instructor')->where('certificate_registration_token', $token)->first();

        if (!$course) {
            return response()->json([
                'active' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ]);
        }

        if (!$course->certificate_registration_open) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
                'message' => 'Rejestracja zaświadczenia jest wyłączona.',
            ]);
        }

        $now = now();
        if ($course->certificate_registration_starts_at && $now->lt($course->certificate_registration_starts_at)) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
                'message' => 'Rejestracja nie jest jeszcze dostępna.',
            ]);
        }
        if ($course->certificate_registration_ends_at && $now->gt($course->certificate_registration_ends_at)) {
            return response()->json([
                'active' => false,
                'course_title' => $course->title,
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
            'instructor_name' => $instructorName,
            'instructor_photo' => $instructorPhoto,
        ]);
    }

    /**
     * Rejestracja uczestnika (utworzenie rekordu w participants).
     * POST /api/certificate-registration/register
     * Body: token, first_name, last_name, email
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:64',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'rodo_consent' => 'required|accepted',
            'newsletter_consent' => 'sometimes|boolean',
        ], [
            'rodo_consent.accepted' => 'Musisz wyrazić zgodę na przetwarzanie danych osobowych.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Nieprawidłowe dane.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $course = Course::where('certificate_registration_token', $request->input('token'))->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Link jest nieprawidłowy lub wygasł.',
            ], 404);
        }

        if (!$course->certificate_registration_open) {
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

        $emailNormalized = strtolower(trim($request->input('email')));
        $existing = Participant::where('course_id', $course->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'already_registered' => true,
                'message' => 'Jesteś już zarejestrowany dla tego szkolenia.',
            ], 422);
        }

        $maxOrder = (int) Participant::where('course_id', $course->id)->max('order');
        Participant::create([
            'course_id' => $course->id,
            'order' => $maxOrder + 1,
            'first_name' => trim($request->input('first_name')),
            'last_name' => trim($request->input('last_name')),
            'email' => $request->input('email'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Zostałeś zarejestrowany. Zaświadczenie otrzymasz w oddzielnej wiadomości.',
        ]);
    }
}
