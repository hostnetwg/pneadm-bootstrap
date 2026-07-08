<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CertificateGeneratorService
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private PDFGenerator $pdfGenerator,
        private CertificateNumberGenerator $numberGenerator
    ) {}

    /**
     * Generuje PDF zaświadczenia
     *
     * @param int $participantId ID uczestnika
     * @param array $options Opcje generowania (connection, save_to_storage, cache)
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdf(int $participantId, array $options = [])
    {
        $saveToStorage = $options['save_to_storage'] ?? false;
        $cache = $options['cache'] ?? true;
        $connection = $options['connection'] ?? null;
        
        // Pobierz dane certyfikatu
        $data = $this->getCertificateData($participantId, $connection);
        
        // Cache key z wersją szablonu
        $cacheKey = $this->getCacheKey($participantId, $data['template_version']);
        
        if ($cache) {
            $cachedPdf = $this->getCachedPdf($cacheKey);
            if ($cachedPdf !== null) {
                return $cachedPdf;
            }
        }
        
        // Renderuj szablon
        $html = $this->templateRenderer->render($data);
        
        // Generuj PDF
        $pdf = $this->pdfGenerator->generate($html, $data['settings']);
        
        // Cache jeśli włączony (surowe bajty — obiekt DomPDF nie jest serializowalny)
        if ($cache) {
            $this->putCachedPdf($cacheKey, $pdf);
        }
        
        // Zapisz do storage jeśli wymagane
        if ($saveToStorage) {
            $courseId = $data['course_id'] ?? $data['course']->id ?? null;
            if (!$courseId) {
                throw new \Exception("Course ID not found in certificate data for participant: {$participantId}");
            }
            $this->saveToStorage($pdf, $data['certificate_number'], $courseId, $connection);
        }
        
        return $pdf;
    }

    /**
     * Generuje PDF on-demand (bez zapisu do storage)
     *
     * @param int $participantId ID uczestnika
     * @param bool $cache Czy używać cache
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateOnDemand(int $participantId, bool $cache = true)
    {
        return $this->generatePdf($participantId, [
            'save_to_storage' => false,
            'cache' => $cache
        ]);
    }

    /**
     * Pobiera dane certyfikatu z bazy
     *
     * @param int $participantId ID uczestnika
     * @param string|null $connection Nazwa połączenia bazy danych (domyślnie: null = domyślne połączenie)
     * @return array
     */
    public function getCertificateData(int $participantId, ?string $connection = null): array
    {
        // Użyj tego samego połączenia dla wszystkich tabel
        $db = $connection ? DB::connection($connection) : DB::connection();
        
        $certificate = $db->table('certificates')
            ->join('participants', 'certificates.participant_id', '=', 'participants.id')
            ->join('courses', 'certificates.course_id', '=', 'courses.id')
            ->leftJoin('certificate_templates', 'courses.certificate_template_id', '=', 'certificate_templates.id')
            ->leftJoin('instructors', 'courses.instructor_id', '=', 'instructors.id')
            ->where('certificates.participant_id', $participantId)
            ->select(
                'certificates.*',
                'participants.first_name',
                'participants.last_name',
                'participants.email',
                'participants.birth_date',
                'participants.birth_place',
                'courses.id as course_id',
                'courses.title as course_title',
                'courses.start_date',
                'courses.end_date',
                'courses.description as course_description',
                'courses.certificate_template_id as course_certificate_template_id',
                'courses.issue_date_certyficates',
                'certificate_templates.config as template_config',
                'certificate_templates.slug as template_slug',
                'certificate_templates.updated_at as template_updated_at',
                'instructors.id as instructor_id',
                'instructors.title as instructor_title',
                'instructors.first_name as instructor_first_name',
                'instructors.last_name as instructor_last_name',
                'instructors.gender as instructor_gender',
                'instructors.signature as instructor_signature'
            )
            ->first();
            
        if (!$certificate) {
            throw new \Exception("Certificate not found for participant: {$participantId}");
        }
        
        // Oblicz czas trwania kursu
        $durationMinutes = 0;
        if (! empty($certificate->start_date) && ! empty($certificate->end_date)) {
            $startDateTime = Carbon::parse($certificate->start_date);
            $endDateTime = Carbon::parse($certificate->end_date);
            $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
        }

        $effectiveCompletionDate = $this->resolveEffectiveCompletionDate($certificate);
        
        // Obsługa null dla template_config (gdy kurs nie ma przypisanego szablonu)
        $templateSlug = $certificate->template_slug ?? null;
        $templateConfig = null;
        
        if (empty($templateSlug)) {
            // Pobierz domyślny szablon z bazy
            $defaultTemplate = $db->table('certificate_templates')
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
            
            if ($defaultTemplate) {
                $templateSlug = $defaultTemplate->slug;
                $templateConfigRaw = $defaultTemplate->config;
                if (!empty($templateConfigRaw)) {
                    $decoded = json_decode($templateConfigRaw, true);
                    $templateConfig = is_array($decoded) ? $decoded : [];
                } else {
                    $templateConfig = [];
                }
            } else {
                // Fallback - użyj pustej konfiguracji
                $templateSlug = 'default';
                $templateConfig = [];
            }
        } else {
            // Użyj szablonu przypisanego do kursu
            if (!empty($certificate->template_config)) {
                $decoded = json_decode($certificate->template_config, true);
                $templateConfig = is_array($decoded) ? $decoded : [];
            } else {
                $templateConfig = [];
            }
        }
        
        $settings = $templateConfig['settings'] ?? [];
        $blocksRaw = $templateConfig['blocks'] ?? [];
        
        // Konwertuj blocks z obiektu na tablicę (jeśli jest obiektem)
        $blocks = [];
        if (is_array($blocksRaw)) {
            // Sprawdź czy to obiekt (associative array) czy tablica numeryczna
            if (array_keys($blocksRaw) !== range(0, count($blocksRaw) - 1)) {
                // To jest obiekt (associative array) - konwertuj na tablicę
                $blocks = array_values($blocksRaw);
            } else {
                // To już jest tablica numeryczna
                $blocks = $blocksRaw;
            }
        }
        
        // Wyodrębnij stałe elementy (instructor_signature i footer) - zawsze na końcu
        $regularBlocks = [];
        $instructorSignatureBlock = null;
        $footerBlock = null;
        
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'instructor_signature') {
                $instructorSignatureBlock = $block;
            } elseif ($type === 'footer') {
                $footerBlock = $block;
            } else {
                $regularBlocks[] = $block;
            }
        }
        
        // Sortuj regularne bloki według pola 'order'
        usort($regularBlocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        // Wyciągnięcie wartości z konfiguracji bloków (dla kompatybilności wstecznej)
        $headerConfig = null;
        $courseInfoConfig = null;
        $footerConfig = null;
        
        foreach ($regularBlocks as $block) {
            $type = $block['type'] ?? '';
            $blockConfig = $block['config'] ?? [];
            
            if ($type === 'header') {
                $headerConfig = $blockConfig;
            } elseif ($type === 'course_info') {
                $courseInfoConfig = $blockConfig;
            }
        }
        
        if ($footerBlock) {
            $footerConfig = $footerBlock['config'] ?? [];
        }
        
        return [
            'certificate' => $certificate,
            'certificate_number' => $certificate->certificate_number,
            'course_id' => $certificate->course_id,
            'course_certificate_template_id' => $certificate->course_certificate_template_id ?? null,
            'participant' => (object) [
                'id' => $certificate->participant_id,
                'first_name' => $certificate->first_name,
                'last_name' => $certificate->last_name,
                'email' => $certificate->email,
                'birth_date' => $certificate->birth_date,
                'birth_place' => $certificate->birth_place,
            ],
            'course' => (object) [
                'id' => $certificate->course_id,
                'title' => $certificate->course_title,
                'start_date' => $certificate->start_date,
                'end_date' => $certificate->end_date,
                'description' => $certificate->course_description,
                'certificate_format' => $certificate->certificate_format ?? null,
                'certificate_template_id' => $certificate->course_certificate_template_id ?? null,
            ],
            'instructor' => $certificate->instructor_id ? (object) [
                'id' => $certificate->instructor_id,
                'title' => $certificate->instructor_title,
                'first_name' => $certificate->instructor_first_name,
                'last_name' => $certificate->instructor_last_name,
                'gender' => $certificate->instructor_gender,
                'signature' => $certificate->instructor_signature,
            ] : null,
            'duration_minutes' => $durationMinutes,
            'effective_completion_date' => $effectiveCompletionDate,
            'template_config' => $templateConfig,
            'template_slug' => $templateSlug ?? 'default',
            'template_version' => $certificate->template_updated_at ? 
                md5($certificate->template_updated_at) : 'default',
            'settings' => $settings,
            'sorted_blocks' => $regularBlocks,
            'instructor_signature_block' => $instructorSignatureBlock,
            'footer_block' => $footerBlock,
            'header_config' => $headerConfig,
            'course_info_config' => $courseInfoConfig,
            'footer_config' => $footerConfig,
            'is_pdf_mode' => true,
        ];
    }

    /**
     * Generuje numer certyfikatu dla uczestnika
     *
     * @param object|array $course Obiekt kursu
     * @param object|array $participant Obiekt uczestnika
     * @return string Numer certyfikatu
     */
    public function generateCertificateNumber($course, $participant): string
    {
        $courseYear = $this->numberGenerator->resolveCourseYear($course);
        $nextSequence = $this->numberGenerator->determineNextSequence($course, $courseYear);
        
        return $this->numberGenerator->formatCertificateNumber($course, $nextSequence, $courseYear);
    }

    /**
     * Generuje klucz cache dla PDF
     *
     * @param int $participantId ID uczestnika
     * @param string $templateVersion Wersja szablonu
     * @return string
     */
    protected function getCacheKey(int $participantId, string $templateVersion): string
    {
        return "certificate:pdf:{$participantId}:{$templateVersion}";
    }

    /**
     * @return object|null Obiekt z metodą output() — DomPDF lub cache bajtów
     */
    protected function getCachedPdf(string $cacheKey): ?object
    {
        if (! Cache::has($cacheKey)) {
            return null;
        }

        $cached = Cache::get($cacheKey);
        if (! is_string($cached) || $cached === '') {
            Cache::forget($cacheKey);

            return null;
        }

        $decoded = base64_decode($cached, true);
        if ($decoded === false || $decoded === '') {
            Cache::forget($cacheKey);

            return null;
        }

        return $this->wrapPdfBytes($decoded);
    }

    protected function putCachedPdf(string $cacheKey, \Barryvdh\DomPDF\PDF $pdf, int $ttlSeconds = 86400): void
    {
        Cache::put($cacheKey, base64_encode($pdf->output()), $ttlSeconds);
    }

    protected function wrapPdfBytes(string $bytes): object
    {
        return new class($bytes)
        {
            public function __construct(private readonly string $bytes) {}

            public function output(): string
            {
                return $this->bytes;
            }
        };
    }

    /**
     * Zapisuje PDF do storage
     *
     * @param \Barryvdh\DomPDF\PDF $pdf
     * @param string $certificateNumber
     * @param int $courseId
     * @param string|null $connection Nazwa połączenia bazy danych
     * @return string Ścieżka do zapisanego pliku
     */
    protected function saveToStorage($pdf, string $certificateNumber, int $courseId, ?string $connection = null, string $folderPrefix = ''): string
    {
        $courseFolder = 'certificates/'.$folderPrefix.$courseId;
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = "{$courseFolder}/{$fileName}";
        $absoluteDir = storage_path('app/public/' . $courseFolder);
        $absolutePath = $absoluteDir . '/' . $fileName;

        if (! File::isDirectory($absoluteDir) && ! File::makeDirectory($absoluteDir, 0775, true)) {
            throw new \RuntimeException(
                "Nie można utworzyć katalogu na zaświadczenia: {$absoluteDir}. "
                . 'Sprawdź uprawnienia katalogu storage pakietu pne-certificate-generator (775, właściciel www-data/sail).'
            );
        }

        if (file_put_contents($absolutePath, $pdf->output()) === false) {
            throw new \RuntimeException(
                "Nie można zapisać pliku PDF: {$absolutePath}. "
                . 'Sprawdź uprawnienia katalogu storage pakietu pne-certificate-generator (775, właściciel www-data/sail).'
            );
        }

        // Zaktualizuj ścieżkę w bazie (użyj tego samego połączenia co w getCertificateData)
        $query = $connection ? DB::connection($connection)->table('certificates') : DB::table('certificates');
        $query->where('certificate_number', $certificateNumber)
            ->update([
                'file_path' => 'storage/' . $filePath,
                'generated_at' => now(),
            ]);

        return $filePath;
    }

    /**
     * Generuje PDF zaświadczenia dla zapisu na kurs online.
     */
    public function generatePdfForEnrollment(int $enrollmentId, array $options = []): \Barryvdh\DomPDF\PDF
    {
        $saveToStorage = $options['save_to_storage'] ?? false;
        $cache = $options['cache'] ?? true;
        $connection = $options['connection'] ?? null;

        $data = $this->getCertificateDataForEnrollment($enrollmentId, $connection);

        $cacheKey = "certificate:pdf:enrollment:{$enrollmentId}:".($data['template_version'] ?? 'default');

        if ($cache) {
            $cachedPdf = $this->getCachedPdf($cacheKey);
            if ($cachedPdf !== null) {
                return $cachedPdf;
            }
        }

        $html = $this->templateRenderer->render($data);
        $pdf = $this->pdfGenerator->generate($html, $data['settings']);

        if ($cache) {
            $this->putCachedPdf($cacheKey, $pdf);
        }

        if ($saveToStorage) {
            $onlineCourseId = (int) ($data['online_course_id'] ?? 0);
            if ($onlineCourseId <= 0) {
                throw new \Exception("Online course ID not found for enrollment: {$enrollmentId}");
            }
            $this->saveToStorage($pdf, $data['certificate_number'], $onlineCourseId, $connection, 'online-');
        }

        return $pdf;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCertificateDataForEnrollment(int $enrollmentId, ?string $connection = null): array
    {
        $db = $connection ? DB::connection($connection) : DB::connection();

        $row = $db->table('certificates')
            ->join('online_course_enrollments', 'certificates.online_course_enrollment_id', '=', 'online_course_enrollments.id')
            ->join('online_courses', 'certificates.online_course_id', '=', 'online_courses.id')
            ->leftJoin('certificate_templates', 'online_courses.certificate_template_id', '=', 'certificate_templates.id')
            ->leftJoin('instructors', 'online_courses.instructor_id', '=', 'instructors.id')
            ->where('online_course_enrollments.id', $enrollmentId)
            ->select(
                'certificates.*',
                'online_course_enrollments.email as enrollment_email',
                'online_courses.id as online_course_id',
                'online_courses.title as course_title',
                'online_courses.description as course_description',
                'online_courses.training_scope as course_training_scope',
                'online_courses.certificate_template_id as course_certificate_template_id',
                'online_courses.certificate_format',
                'online_courses.certificate_issue_date',
                'online_courses.certificate_duration_minutes',
                'certificate_templates.config as template_config',
                'certificate_templates.slug as template_slug',
                'certificate_templates.updated_at as template_updated_at',
                'instructors.id as instructor_id',
                'instructors.title as instructor_title',
                'instructors.first_name as instructor_first_name',
                'instructors.last_name as instructor_last_name',
                'instructors.gender as instructor_gender',
                'instructors.signature as instructor_signature'
            )
            ->first();

        if (! $row) {
            throw new \Exception("Certificate not found for online enrollment: {$enrollmentId}");
        }

        [$templateSlug, $templateConfig, $settings, $regularBlocks, $instructorSignatureBlock, $footerBlock, $headerConfig, $courseInfoConfig, $footerConfig] =
            $this->resolveTemplateBlocks($db, $row);

        $effectiveCompletionDate = $this->resolveEffectiveCompletionDate($row);

        return [
            'certificate' => $row,
            'certificate_number' => $row->certificate_number,
            'online_course_id' => (int) $row->online_course_id,
            'course_id' => (int) $row->online_course_id,
            'course_certificate_template_id' => $row->course_certificate_template_id ?? null,
            'participant' => (object) [
                'id' => null,
                'first_name' => $row->holder_first_name,
                'last_name' => $row->holder_last_name,
                'email' => $row->enrollment_email,
                'birth_date' => $row->holder_birth_date,
                'birth_place' => $row->holder_birth_place,
            ],
            'course' => (object) [
                'id' => $row->online_course_id,
                'title' => $row->course_title,
                'start_date' => $effectiveCompletionDate,
                'end_date' => null,
                'description' => $this->resolveOnlineCourseCertificateDescription(
                    $row->course_training_scope ?? null,
                    $row->course_description ?? null
                ),
                'certificate_format' => $row->certificate_format ?? null,
                'certificate_template_id' => $row->course_certificate_template_id ?? null,
            ],
            'instructor' => $row->instructor_id ? (object) [
                'id' => $row->instructor_id,
                'title' => $row->instructor_title,
                'first_name' => $row->instructor_first_name,
                'last_name' => $row->instructor_last_name,
                'gender' => $row->instructor_gender,
                'signature' => $row->instructor_signature,
            ] : null,
            'duration_minutes' => max(0, (int) ($row->certificate_duration_minutes ?? 0)),
            'effective_completion_date' => $effectiveCompletionDate,
            'template_config' => $templateConfig,
            'template_slug' => $templateSlug ?? 'default',
            'template_version' => $row->template_updated_at ?
                md5($row->template_updated_at) : 'default',
            'settings' => $settings,
            'sorted_blocks' => $regularBlocks,
            'instructor_signature_block' => $instructorSignatureBlock,
            'footer_block' => $footerBlock,
            'header_config' => $headerConfig,
            'course_info_config' => $courseInfoConfig,
            'footer_config' => $footerConfig,
            'is_pdf_mode' => true,
        ];
    }

    /**
     * @return array{0: ?string, 1: array, 2: array, 3: array, 4: ?array, 5: ?array, 6: ?array, 7: ?array, 8: ?array}
     */
    private function resolveTemplateBlocks($db, object $certificateRow): array
    {
        $templateSlug = $certificateRow->template_slug ?? null;
        $templateConfig = [];

        if (empty($templateSlug)) {
            $defaultTemplate = $db->table('certificate_templates')
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($defaultTemplate) {
                $templateSlug = $defaultTemplate->slug;
                $templateConfigRaw = $defaultTemplate->config;
                if (! empty($templateConfigRaw)) {
                    $decoded = json_decode($templateConfigRaw, true);
                    $templateConfig = is_array($decoded) ? $decoded : [];
                }
            } else {
                $templateSlug = 'default';
            }
        } elseif (! empty($certificateRow->template_config)) {
            $decoded = json_decode($certificateRow->template_config, true);
            $templateConfig = is_array($decoded) ? $decoded : [];
        }

        $settings = $templateConfig['settings'] ?? [];
        $blocksRaw = $templateConfig['blocks'] ?? [];
        $blocks = [];
        if (is_array($blocksRaw)) {
            if (array_keys($blocksRaw) !== range(0, count($blocksRaw) - 1)) {
                $blocks = array_values($blocksRaw);
            } else {
                $blocks = $blocksRaw;
            }
        }

        $regularBlocks = [];
        $instructorSignatureBlock = null;
        $footerBlock = null;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'instructor_signature') {
                $instructorSignatureBlock = $block;
            } elseif ($type === 'footer') {
                $footerBlock = $block;
            } else {
                $regularBlocks[] = $block;
            }
        }

        usort($regularBlocks, fn ($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));

        $headerConfig = null;
        $courseInfoConfig = null;
        $footerConfig = null;

        foreach ($regularBlocks as $block) {
            $type = $block['type'] ?? '';
            $blockConfig = $block['config'] ?? [];
            if ($type === 'header') {
                $headerConfig = $blockConfig;
            } elseif ($type === 'course_info') {
                $courseInfoConfig = $blockConfig;
            }
        }

        if ($footerBlock) {
            $footerConfig = $footerBlock['config'] ?? [];
        }

        return [$templateSlug, $templateConfig, $settings, $regularBlocks, $instructorSignatureBlock, $footerBlock, $headerConfig, $courseInfoConfig, $footerConfig];
    }

    private function resolveOnlineCourseCertificateDescription(?string $trainingScope, ?string $description): ?string
    {
        $scope = trim((string) $trainingScope);
        if ($scope !== '') {
            return $scope;
        }

        $fallback = trim((string) $description);

        return $fallback !== '' ? $fallback : null;
    }

    private function resolveEffectiveCompletionDate(object $row): string
    {
        if (! empty($row->issue_date)) {
            return Carbon::parse($row->issue_date)->toDateString();
        }

        if (! empty($row->certificate_issue_date)) {
            return Carbon::parse($row->certificate_issue_date)->toDateString();
        }

        if (! empty($row->issue_date_certyficates)) {
            return Carbon::parse($row->issue_date_certyficates)->toDateString();
        }

        return Carbon::now()->toDateString();
    }
}








