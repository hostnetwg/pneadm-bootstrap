<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
        
        if ($cache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Renderuj szablon
        $html = $this->templateRenderer->render($data);
        
        // Generuj PDF
        $pdf = $this->pdfGenerator->generate($html, $data['settings']);
        
        // Cache jeśli włączony
        if ($cache) {
            Cache::put($cacheKey, $pdf, 86400); // 24h
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
                'certificate_templates.config as template_config',
                'certificate_templates.slug as template_slug',
                'certificate_templates.updated_at as template_updated_at',
                'instructors.id as instructor_id',
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
        $startDateTime = Carbon::parse($certificate->start_date);
        $endDateTime = Carbon::parse($certificate->end_date);
        $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
        
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
                'first_name' => $certificate->instructor_first_name,
                'last_name' => $certificate->instructor_last_name,
                'gender' => $certificate->instructor_gender,
                'signature' => $certificate->instructor_signature,
            ] : null,
            'duration_minutes' => $durationMinutes,
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
     * Zapisuje PDF do storage
     *
     * @param \Barryvdh\DomPDF\PDF $pdf
     * @param string $certificateNumber
     * @param int $courseId
     * @param string|null $connection Nazwa połączenia bazy danych
     * @return string Ścieżka do zapisanego pliku
     */
    protected function saveToStorage($pdf, string $certificateNumber, int $courseId, ?string $connection = null): string
    {
        $courseFolder = "certificates/{$courseId}";
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = "{$courseFolder}/{$fileName}";
        
        // Utwórz katalog jeśli nie istnieje
        if (!Storage::disk('public')->exists($courseFolder)) {
            Storage::disk('public')->makeDirectory($courseFolder, 0777, true);
        }
        
        // Zapisz plik
        Storage::disk('public')->put($filePath, $pdf->output());
        
        // Zaktualizuj ścieżkę w bazie (użyj tego samego połączenia co w getCertificateData)
        $query = $connection ? DB::connection($connection)->table('certificates') : DB::table('certificates');
        $query->where('certificate_number', $certificateNumber)
            ->update([
                'file_path' => 'storage/' . $filePath,
                'generated_at' => now(),
            ]);
        
        return $filePath;
    }
}







