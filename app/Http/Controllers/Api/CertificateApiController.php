<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use App\Services\Certificate\CertificateGeneratorService;
use App\Services\Certificate\CertificateNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CertificateApiController extends Controller
{
    public function __construct(
        private CertificateGeneratorService $certificateGenerator,
        private CertificateNumberGenerator $numberGenerator
    ) {}

    /**
     * Generuje PDF certyfikatu dla uczestnika
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'participant_id' => 'required|integer|min:1',
            'connection' => 'nullable|string',
            'save_to_storage' => 'nullable|boolean',
            'cache' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $participantId = $request->input('participant_id');
            $connection = $this->normalizeConnection($request->input('connection'));
            $saveToStorage = $request->input('save_to_storage', false);
            $cache = $request->input('cache', true);

            // Jeśli plik PDF jest już zapisany na serwerze, zwróć go (mniejsze obciążenie i ryzyko alertów AV)
            $certificate = Certificate::where('participant_id', $participantId)->first();
            if ($certificate && !empty($certificate->file_path)) {
                $storagePath = Str::replaceFirst('storage/', '', $certificate->file_path);
                if (Storage::disk('public')->exists($storagePath)) {
                    Log::info('Certificate API: Serving existing PDF from storage', ['participant_id' => $participantId]);
                    return response(Storage::disk('public')->get($storagePath), 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
                }
            }

            Log::info('Certificate API: Generating PDF', [
                'participant_id' => $participantId,
                'connection' => $connection,
                'save_to_storage' => $saveToStorage,
                'cache' => $cache,
            ]);

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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Certificate generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tworzy rekord certyfikatu jeśli nie istnieje (odpowiednik GET participants/{id}/certificate).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ensure(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'participant_id' => 'required|integer|min:1',
            'connection' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        try {
            $participantId = $request->input('participant_id');
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

            Certificate::create([
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
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
        $validator = Validator::make($request->all(), [
            'participant_id' => 'required|integer|min:1',
            'connection' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $participantId = $request->input('participant_id');
            $connection = $this->normalizeConnection($request->input('connection'));

            $data = $this->certificateGenerator->getCertificateData($participantId, $connection);

            // Usuń wrażliwe dane przed zwróceniem (opcjonalnie)
            unset($data['certificate']);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate API: Error getting certificate data', [
                'participant_id' => $request->input('participant_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get certificate data',
                'message' => $e->getMessage()
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
}

