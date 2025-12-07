<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Certificate\CertificateGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CertificateApiController extends Controller
{
    public function __construct(
        private CertificateGeneratorService $certificateGenerator
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

