<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use App\Services\Certificate\CertificateGeneratorService;
use App\Services\Certificate\CertificateNumberGenerator;
use App\Models\OnlineCourseEnrollment;
use App\Services\Certificate\OnlineCourseCertificateIssueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CertificateApiController extends Controller
{
    public function __construct(
        private CertificateGeneratorService $certificateGenerator,
        private CertificateNumberGenerator $numberGenerator,
        private OnlineCourseCertificateIssueService $onlineCourseCertificateIssue,
    ) {}

    /**
     * Generuje PDF certyfikatu dla uczestnika lub zapisu na kurs online.
     */
    public function generate(Request $request)
    {
        $hasParticipant = $request->filled('participant_id');
        $hasEnrollment = $request->filled('online_course_enrollment_id');

        if ($hasParticipant === $hasEnrollment) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['subject' => ['Podaj participant_id lub online_course_enrollment_id.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'participant_id' => 'nullable|integer|min:1|required_without:online_course_enrollment_id',
            'online_course_enrollment_id' => 'nullable|integer|min:1|required_without:participant_id',
            'connection' => 'nullable|string',
            'save_to_storage' => 'nullable|boolean',
            'cache' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $connection = $this->normalizeConnection($request->input('connection'));
            $saveToStorage = $request->boolean('save_to_storage');
            $cache = $request->boolean('cache', false);

            if ($hasEnrollment) {
                return $this->generateForEnrollment(
                    (int) $request->input('online_course_enrollment_id'),
                    $connection,
                    $saveToStorage,
                    $cache
                );
            }

            $participantId = (int) $request->input('participant_id');
            $certificate = Certificate::where('participant_id', $participantId)->first();
            if ($certificate && ! empty($certificate->file_path)) {
                $storagePath = Str::replaceFirst('storage/', '', $certificate->file_path);
                if (Storage::disk('public')->exists($storagePath)) {
                    return response(Storage::disk('public')->get($storagePath), 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
                }
            }

            $pdf = $this->certificateGenerator->generatePdf($participantId, [
                'connection' => $connection,
                'save_to_storage' => $saveToStorage,
                'cache' => $cache,
            ]);

            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
        } catch (\Exception $e) {
            Log::error('Certificate API: Error generating PDF', [
                'participant_id' => $request->input('participant_id'),
                'online_course_enrollment_id' => $request->input('online_course_enrollment_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Certificate generation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateForEnrollment(int $enrollmentId, ?string $connection, bool $saveToStorage, bool $cache)
    {
        $certificate = Certificate::query()
            ->where('online_course_enrollment_id', $enrollmentId)
            ->first();

        if ($certificate && ! empty($certificate->file_path)) {
            $storagePath = Str::replaceFirst('storage/', '', $certificate->file_path);
            if (Storage::disk('public')->exists($storagePath)) {
                return response(Storage::disk('public')->get($storagePath), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
            }
        }

        $pdf = $this->certificateGenerator->generatePdfForEnrollment($enrollmentId, [
            'connection' => $connection,
            'save_to_storage' => $saveToStorage,
            'cache' => $cache,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
    }

    /**
     * Tworzy rekord certyfikatu jeśli nie istnieje (odpowiednik GET participants/{id}/certificate).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ensure(Request $request)
    {
        $hasParticipant = $request->filled('participant_id');
        $hasEnrollment = $request->filled('online_course_enrollment_id');

        if ($hasParticipant === $hasEnrollment) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['subject' => ['Podaj participant_id lub online_course_enrollment_id.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'participant_id' => 'nullable|integer|min:1|required_without:online_course_enrollment_id',
            'online_course_enrollment_id' => 'nullable|integer|min:1|required_without:participant_id',
            'connection' => 'nullable|string',
            'holder_email' => 'required_with:online_course_enrollment_id|email|max:255',
            'holder_first_name' => 'required_with:online_course_enrollment_id|string|max:255',
            'holder_last_name' => 'required_with:online_course_enrollment_id|string|max:255',
            'holder_birth_date' => 'nullable|date',
            'holder_birth_place' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        try {
            if ($hasEnrollment) {
                $enrollment = OnlineCourseEnrollment::query()
                    ->with('onlineCourse')
                    ->findOrFail((int) $request->input('online_course_enrollment_id'));

                $existing = Certificate::query()
                    ->where('online_course_enrollment_id', $enrollment->id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'certificate_number' => $existing->certificate_number,
                        'already_existed' => true,
                    ]);
                }

                $certificate = $this->onlineCourseCertificateIssue->ensure($enrollment, [
                    'email' => (string) $request->input('holder_email'),
                    'first_name' => (string) $request->input('holder_first_name'),
                    'last_name' => (string) $request->input('holder_last_name'),
                    'birth_date' => $request->input('holder_birth_date'),
                    'birth_place' => $request->input('holder_birth_place'),
                ]);

                return response()->json([
                    'success' => true,
                    'certificate_number' => $certificate->certificate_number,
                    'already_existed' => false,
                ]);
            }

            $participantId = (int) $request->input('participant_id');
            $participant = Participant::findOrFail($participantId);
            $course = Course::findOrFail($participant->course_id);

            $existing = Certificate::where('participant_id', $participant->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'certificate_number' => $existing->certificate_number,
                    'already_existed' => true,
                ]);
            }

            $courseYear = $this->numberGenerator->resolveCourseYear($course);
            $nextSequence = $this->numberGenerator->determineNextSequence($course, $courseYear);
            $certificateNumber = $this->numberGenerator->formatCertificateNumber($course, $nextSequence, $courseYear);
            $issueDate = $course->issue_date_certyficates
                ? Carbon::parse($course->issue_date_certyficates)->toDateString()
                : Carbon::now()->toDateString();

            Certificate::create([
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
                'issue_date' => $issueDate,
                'generated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'certificate_number' => $certificateNumber,
                'already_existed' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Certificate API: ensure failed', [
                'participant_id' => $request->input('participant_id'),
                'online_course_enrollment_id' => $request->input('online_course_enrollment_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Certificate ensure failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera dane certyfikatu (bez generowania PDF)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        $hasParticipant = $request->filled('participant_id');
        $hasEnrollment = $request->filled('online_course_enrollment_id');

        if ($hasParticipant === $hasEnrollment) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['subject' => ['Podaj participant_id lub online_course_enrollment_id.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'participant_id' => 'nullable|integer|min:1|required_without:online_course_enrollment_id',
            'online_course_enrollment_id' => 'nullable|integer|min:1|required_without:participant_id',
            'connection' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $connection = $this->normalizeConnection($request->input('connection'));

            if ($hasEnrollment) {
                $data = $this->certificateGenerator->getCertificateDataForEnrollment(
                    (int) $request->input('online_course_enrollment_id'),
                    $connection
                );
            } else {
                $data = $this->certificateGenerator->getCertificateData(
                    (int) $request->input('participant_id'),
                    $connection
                );
            }

            unset($data['certificate']);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Certificate API: Error getting certificate data', [
                'participant_id' => $request->input('participant_id'),
                'online_course_enrollment_id' => $request->input('online_course_enrollment_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get certificate data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'Certificate API',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Aktualizuje datę i miejsce urodzenia (dla formularza na pnedu).
     * Body: token, course_id, birth_date, birth_place.
     * Jeśli ten sam e-mail ma wielu uczestników z różnymi imionami/nazwiskami – aktualizacja tylko tego uczestnika (course_id).
     * W przeciwnym razie – aktualizacja wszystkich uczestników z tym e-mailem.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBirthData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'course_id' => 'required|integer|min:1',
            'birth_date' => 'required|date',
            'birth_place' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $tokenRecord = ParticipantDownloadToken::where('token', $request->input('token'))->first();
        if (!$tokenRecord) {
            return response()->json(['error' => 'Invalid token'], 404);
        }

        $emailNormalized = $tokenRecord->email_normalized;
        $courseId = (int) $request->input('course_id');
        $birthDate = $request->input('birth_date');
        $birthPlace = trim($request->input('birth_place', ''));

        $participant = Participant::whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->where('course_id', $courseId)
            ->first();

        if (!$participant) {
            return response()->json(['error' => 'Participant not found for this course'], 404);
        }

        $distinctNames = Participant::whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
            ->selectRaw('LOWER(TRIM(first_name)) as fn, LOWER(TRIM(last_name)) as ln')
            ->distinct()
            ->get();
        $hasConflict = $distinctNames->count() > 1;

        if ($hasConflict) {
            $participant->update([
                'birth_date' => $birthDate,
                'birth_place' => $birthPlace,
            ]);
            $updatedCount = 1;
            $updatedParticipantIds = [$participant->id];
        } else {
            $updatedParticipantIds = Participant::whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
                ->pluck('id')
                ->all();
            $updatedCount = Participant::whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
                ->update([
                    'birth_date' => $birthDate,
                    'birth_place' => $birthPlace,
                ]);
        }

        // Unieważnij zapisane PDF zaświadczeń, żeby przy następnym pobraniu wygenerować plik z uzupełnionymi danymi
        foreach ($updatedParticipantIds as $participantId) {
            $certificate = Certificate::where('participant_id', $participantId)->first();
            if ($certificate && !empty($certificate->file_path)) {
                $storagePath = Str::replaceFirst('storage/', '', $certificate->file_path);
                if ($storagePath !== '' && Storage::disk('public')->exists($storagePath)) {
                    Storage::disk('public')->delete($storagePath);
                }
                $certificate->update(['file_path' => null]);
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Normalizuje nazwę połączenia bazy danych
     * W pneadm-bootstrap: 'pneadm' -> null (domyślne połączenie mysql)
     * 
     * @param string|null $connection
     * @return string|null
     */
    private function normalizeConnection(?string $connection): ?string
    {
        // W pneadm-bootstrap połączenie 'pneadm' oznacza domyślne połączenie (mysql)
        // które wskazuje na bazę pneadm
        if ($connection === 'pneadm') {
            return null; // null = domyślne połączenie
        }

        return $connection;
    }

    /**
     * Oznacza pobranie zaświadczenia (pnedu -> pneadm) na podstawie tokenu i kursu.
     * Zwiększa licznik i ustawia first/last_downloaded_at w tabeli certificates.
     */
    public function markDownloaded(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'course_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $token = (string) $request->input('token');
        $courseId = (int) $request->input('course_id');

        $tokenRecord = ParticipantDownloadToken::findByToken($token);
        if (!$tokenRecord) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        $participant = Participant::whereRaw('LOWER(TRIM(email)) = ?', [$tokenRecord->email_normalized])
            ->where('course_id', $courseId)
            ->first();
        if (!$participant) {
            return response()->json(['error' => 'Participant not found for course'], 404);
        }

        $certificate = Certificate::where('participant_id', $participant->id)
            ->where('course_id', $courseId)
            ->first();
        if (!$certificate) {
            return response()->json(['error' => 'Certificate not found'], 404);
        }

        $now = now();
        $updates = [
            'download_count' => DB::raw('download_count + 1'),
            'last_downloaded_at' => $now,
        ];
        if (empty($certificate->first_downloaded_at)) {
            $updates['first_downloaded_at'] = $now;
        }

        Certificate::where('id', $certificate->id)->update($updates);

        return response()->json(['success' => true]);
    }

    /**
     * Oznacza pobranie zaświadczenia kursu online (statystyki).
     */
    public function markOnlineDownloaded(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'online_course_enrollment_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $enrollmentId = (int) $request->input('online_course_enrollment_id');
        $certificate = Certificate::query()
            ->where('online_course_enrollment_id', $enrollmentId)
            ->first();

        if (! $certificate) {
            return response()->json(['error' => 'Certificate not found'], 404);
        }

        $now = now();
        $updates = [
            'download_count' => DB::raw('download_count + 1'),
            'last_downloaded_at' => $now,
        ];
        if (empty($certificate->first_downloaded_at)) {
            $updates['first_downloaded_at'] = $now;
        }

        Certificate::where('id', $certificate->id)->update($updates);

        return response()->json(['success' => true]);
    }
}

