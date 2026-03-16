<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\Certificate\CertificateGeneratorService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout pojedynczego zadania (generowanie jednego PDF z obróbką obrazu może trwać).
     */
    public int $timeout = 120;

    /**
     * Liczba prób przy błędzie.
     */
    public int $tries = 2;

    public function __construct(
        public int $participantId
    ) {}

    public function handle(CertificateGeneratorService $certificateGenerator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        set_time_limit($this->timeout);

        // Idempotentność: jeśli plik już istnieje na serwerze, pomiń (np. przy ponownym kliknięciu)
        $certificate = Certificate::where('participant_id', $this->participantId)->first();
        if ($certificate && !empty($certificate->file_path)) {
            $storagePath = Str::replaceFirst('storage/', '', $certificate->file_path);
            if (Storage::disk('public')->exists($storagePath)) {
                return;
            }
        }

        try {
            $certificateGenerator->generatePdf($this->participantId, [
                'save_to_storage' => true,
                'cache' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('GenerateCertificatePdfJob: błąd', [
                'participant_id' => $this->participantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
