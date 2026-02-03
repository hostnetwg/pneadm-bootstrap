# 🔄 Plan implementacji Wariantu 3: Hybrid (Szablony w bazie + API)

## 📋 Spis treści
1. [Przegląd zmian](#przegląd-zmian)
2. [Faza 1: Przygotowanie w adm.pnedu.pl](#faza-1-przygotowanie-w-admpnedupl)
3. [Faza 2: Implementacja API](#faza-2-implementacja-api)
4. [Faza 3: Renderowanie z JSON](#faza-3-renderowanie-z-json)
5. [Faza 4: Klient API w pnedu.pl](#faza-4-klient-api-w-pnedupl)
6. [Faza 5: Migracja i testy](#faza-5-migracja-i-testy)
7. [Faza 6: Wdrożenie](#faza-6-wdrożenie)

---

## 📊 Przegląd zmian

### Co się zmienia:
- ✅ **adm.pnedu.pl**: Zawiera całą logikę generowania + API endpoint
- ✅ **pnedu.pl**: Tylko klient API, nie generuje samodzielnie
- ✅ **Szablony**: Tylko w bazie (JSON), bez plików `.blade.php`
- ✅ **Pakiet pne-certificate-generator**: Można usunąć lub zostawić dla kompatybilności

### Co zostaje:
- ✅ Edytor szablonów w `adm.pnedu.pl` (już istnieje)
- ✅ Baza danych `certificate_templates` (już istnieje)
- ✅ Model `CertificateTemplate` (już istnieje)

---

## 🎯 Faza 1: Przygotowanie w adm.pnedu.pl

### Krok 1.1: Przeniesienie serwisów z pakietu

**Lokalizacja:** `pneadm-bootstrap/app/Services/`

#### 1.1.1: Skopiuj `TemplateRenderer` (zmodyfikowany)

```php
// app/Services/Certificate/TemplateRenderer.php
<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\View;

class TemplateRenderer
{
    /**
     * Renderuje szablon z konfiguracji JSON (bez plików Blade)
     */
    public function render(array $data): string
    {
        $config = $data['template_config'] ?? [];
        $blocks = $config['blocks'] ?? [];
        $settings = $config['settings'] ?? [];
        
        // Buduj HTML bezpośrednio z JSON
        return $this->buildHtmlFromConfig($blocks, $settings, $data);
    }
    
    /**
     * Buduje HTML z konfiguracji JSON
     */
    protected function buildHtmlFromConfig(array $blocks, array $settings, array $data): string
    {
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"pl\">\n";
        $html .= "<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <title>Zaświadczenie</title>\n";
        $html .= $this->buildStyles($settings);
        $html .= "</head>\n";
        $html .= "<body>\n";
        
        // Sortuj bloki według order
        usort($blocks, function($a, $b) {
            return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
        });
        
        // Renderuj bloki
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $settings, $data);
        }
        
        $html .= "</body>\n";
        $html .= "</html>\n";
        
        return $html;
    }
    
    /**
     * Renderuje pojedynczy blok
     */
    protected function renderBlock(array $block, array $settings, array $data): string
    {
        $type = $block['type'] ?? '';
        $config = $block['config'] ?? [];
        
        switch ($type) {
            case 'header':
                return $this->renderHeader($config);
            case 'participant_info':
                return $this->renderParticipantInfo($config, $data);
            case 'course_info':
                return $this->renderCourseInfo($config, $settings, $data);
            case 'instructor_signature':
                return $this->renderInstructorSignature($config, $data);
            case 'footer':
                return $this->renderFooter($config, $data);
            case 'custom_text':
                return $this->renderCustomText($config);
            default:
                return '';
        }
    }
    
    // ... metody renderujące poszczególne bloki (kopiuj z TemplateBuilderService)
}
```

#### 1.1.2: Skopiuj `PDFGenerator`

```php
// app/Services/Certificate/PDFGenerator.php
<?php

namespace App\Services\Certificate;

use Barryvdh\DomPDF\Facade\Pdf;

class PDFGenerator
{
    public function generate(string $html, array $settings = []): \Barryvdh\DomPDF\PDF
    {
        $orientation = $settings['orientation'] ?? 'portrait';
        $fontFamily = $settings['font_family'] ?? 'DejaVu Sans';
        
        return Pdf::loadHTML($html)
            ->setPaper('A4', $orientation)
            ->setOptions([
                'defaultFont' => $fontFamily,
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);
    }
}
```

#### 1.1.3: Utwórz `CertificateGeneratorService`

```php
// app/Services/Certificate/CertificateGeneratorService.php
<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CertificateGeneratorService
{
    public function __construct(
        private TemplateRenderer $templateRenderer,
        private PDFGenerator $pdfGenerator
    ) {}
    
    /**
     * Generuje PDF zaświadczenia
     */
    public function generatePdf(int $participantId, array $options = []): \Barryvdh\DomPDF\PDF
    {
        $saveToStorage = $options['save_to_storage'] ?? false;
        
        // Pobierz dane certyfikatu
        $data = $this->getCertificateData($participantId);
        
        // Renderuj szablon z JSON
        $html = $this->templateRenderer->render($data);
        
        // Generuj PDF
        $pdf = $this->pdfGenerator->generate($html, $data['settings']);
        
        // Zapisz do storage jeśli wymagane
        if ($saveToStorage) {
            $this->saveToStorage($pdf, $data['certificate_number'], $data['course_id']);
        }
        
        return $pdf;
    }
    
    /**
     * Pobiera dane certyfikatu z bazy
     */
    public function getCertificateData(int $participantId): array
    {
        $certificate = Certificate::with(['participant.course.certificateTemplate', 'participant.course.instructor'])
            ->where('participant_id', $participantId)
            ->firstOrFail();
        
        $participant = $certificate->participant;
        $course = $participant->course;
        $instructor = $course->instructor;
        
        // Pobierz szablon
        $template = $course->certificateTemplate;
        if (!$template) {
            // Użyj domyślnego szablonu
            $template = \App\Models\CertificateTemplate::where('is_default', true)
                ->where('is_active', true)
                ->firstOrFail();
        }
        
        $config = $template->config ?? [];
        $blocks = $config['blocks'] ?? [];
        $settings = $config['settings'] ?? [];
        
        // Oblicz czas trwania
        $startDateTime = Carbon::parse($course->start_date);
        $endDateTime = Carbon::parse($course->end_date);
        $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
        
        return [
            'certificate_number' => $certificate->certificate_number,
            'course_id' => $course->id,
            'participant' => (object) [
                'id' => $participant->id,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'email' => $participant->email,
                'birth_date' => $participant->birth_date,
                'birth_place' => $participant->birth_place,
            ],
            'course' => (object) [
                'id' => $course->id,
                'title' => $course->title,
                'start_date' => $course->start_date,
                'end_date' => $course->end_date,
                'description' => $course->description,
            ],
            'instructor' => $instructor ? (object) [
                'id' => $instructor->id,
                'first_name' => $instructor->first_name,
                'last_name' => $instructor->last_name,
                'gender' => $instructor->gender,
                'signature' => $instructor->signature,
            ] : null,
            'duration_minutes' => $durationMinutes,
            'template_config' => $config,
            'template_slug' => $template->slug,
            'settings' => $settings,
            'is_pdf_mode' => true,
        ];
    }
    
    /**
     * Zapisuje PDF do storage
     */
    protected function saveToStorage($pdf, string $certificateNumber, int $courseId): string
    {
        $courseFolder = "certificates/{$courseId}";
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = "{$courseFolder}/{$fileName}";
        
        if (!Storage::disk('public')->exists($courseFolder)) {
            Storage::disk('public')->makeDirectory($courseFolder, 0777, true);
        }
        
        Storage::disk('public')->put($filePath, $pdf->output());
        
        // Zaktualizuj ścieżkę w bazie
        Certificate::where('certificate_number', $certificateNumber)
            ->update([
                'file_path' => 'storage/' . $filePath,
                'generated_at' => now(),
            ]);
        
        return $filePath;
    }
}
```

### Krok 1.2: Aktualizacja relacji w modelach

**Sprawdź czy modele mają odpowiednie relacje:**

```php
// app/Models/Certificate.php
public function participant()
{
    return $this->belongsTo(Participant::class);
}

// app/Models/Participant.php
public function course()
{
    return $this->belongsTo(Course::class);
}

// app/Models/Course.php
public function certificateTemplate()
{
    return $this->belongsTo(CertificateTemplate::class);
}

public function instructor()
{
    return $this->belongsTo(Instructor::class);
}
```

---

## 🎯 Faza 2: Implementacja API

### Krok 2.1: Konfiguracja API

#### 2.1.1: Dodaj klucz API do `.env`

```env
# .env w adm.pnedu.pl
PNEADM_API_TOKEN=your-secret-api-token-here
```

#### 2.1.2: Dodaj konfigurację

```php
// config/services.php
return [
    // ... istniejące konfiguracje
    
    'pneadm' => [
        'api_token' => env('PNEADM_API_TOKEN'),
        'api_url' => env('APP_URL'), // adm.pnedu.pl
    ],
];
```

### Krok 2.2: Middleware dla API

```php
// app/Http/Middleware/VerifyApiToken.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');
        $validToken = config('services.pneadm.api_token');
        
        if (!$token || $token !== $validToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token'
            ], 401);
        }
        
        return $next($request);
    }
}
```

**Zarejestruj middleware:**

```php
// app/Http/Kernel.php (Laravel 10) lub bootstrap/app.php (Laravel 11)
protected $middlewareAliases = [
    // ...
    'api.token' => \App\Http\Middleware\VerifyApiToken::class,
];
```

### Krok 2.3: API Controller

```php
// app/Http/Controllers/Api/CertificateApiController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Certificate\CertificateGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertificateApiController extends Controller
{
    public function __construct(
        private CertificateGeneratorService $generatorService
    ) {}
    
    /**
     * Generuje PDF zaświadczenia
     * 
     * POST /api/certificates/generate
     * Headers: Authorization: Bearer {token}
     * Body: { "participant_id": 123 }
     */
    public function generate(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|integer|exists:participants,id'
        ]);
        
        try {
            $participantId = $request->input('participant_id');
            
            // Generuj PDF
            $pdf = $this->generatorService->generatePdf($participantId, [
                'save_to_storage' => true
            ]);
            
            // Zwróć PDF jako response
            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
                
        } catch (\Exception $e) {
            Log::error('Certificate generation failed via API', [
                'participant_id' => $request->input('participant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Certificate generation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Pobiera istniejący certyfikat
     * 
     * GET /api/certificates/download/{participantId}
     */
    public function download(int $participantId)
    {
        try {
            $certificate = \App\Models\Certificate::where('participant_id', $participantId)
                ->firstOrFail();
            
            if (!$certificate->file_path) {
                // Jeśli nie ma pliku, wygeneruj
                $pdf = $this->generatorService->generatePdf($participantId, [
                    'save_to_storage' => true
                ]);
                
                return response($pdf->output(), 200)
                    ->header('Content-Type', 'application/pdf');
            }
            
            // Zwróć istniejący plik
            $filePath = storage_path('app/public/' . str_replace('storage/', '', $certificate->file_path));
            
            if (!file_exists($filePath)) {
                // Jeśli plik nie istnieje, wygeneruj ponownie
                $pdf = $this->generatorService->generatePdf($participantId, [
                    'save_to_storage' => true
                ]);
                
                return response($pdf->output(), 200)
                    ->header('Content-Type', 'application/pdf');
            }
            
            return response()->download($filePath);
            
        } catch (\Exception $e) {
            Log::error('Certificate download failed via API', [
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Certificate not found',
                'message' => config('app.debug') ? $e->getMessage() : 'Certificate not found'
            ], 404);
        }
    }
}
```

### Krok 2.4: Routing API

```php
// routes/api.php
<?php

use App\Http\Controllers\Api\CertificateApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('certificates')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::post('/generate', [CertificateApiController::class, 'generate']);
    Route::get('/download/{participantId}', [CertificateApiController::class, 'download']);
});
```

---

## 🎯 Faza 3: Renderowanie z JSON

### Krok 3.1: Implementacja metod renderujących bloki

**W `TemplateRenderer.php` dodaj metody:**

```php
// app/Services/Certificate/TemplateRenderer.php

protected function renderHeader(array $config): string
{
    $title = $config['title'] ?? 'ZAŚWIADCZENIE';
    return "    <h1 class=\"certificate-title\">{$title}</h1>\n";
}

protected function renderParticipantInfo(array $config, array $data): string
{
    $participant = $data['participant'];
    $html = "    <p>Pan/i</p>\n";
    $html .= "    <h2 class=\"participant-name\">{$participant->first_name} {$participant->last_name}</h2>\n\n";
    
    if (!empty($config['show_birth_info'])) {
        if (!empty($participant->birth_date) && !empty($participant->birth_place)) {
            $birthDate = \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y');
            $html .= "    <p>urodzony/a: {$birthDate}r. w miejscowości {$participant->birth_place}</p>\n";
        } else {
            $html .= "    <p>&nbsp;</p>\n";
        }
    }
    
    return $html;
}

protected function renderCourseInfo(array $config, array $settings, array $data): string
{
    $course = $data['course'];
    $completionText = $config['completion_text'] ?? 'ukończył/a szkolenie';
    $eventText = $config['event_text'] ?? 'zorganizowanym w dniu';
    
    $html = "    <p>{$completionText}</p>\n";
    
    $startDate = \Carbon\Carbon::parse($course->start_date)->format('d.m.Y');
    $html .= "    <p>{$eventText} {$startDate}r. ";
    
    if (!empty($config['show_duration'])) {
        $html .= "w wymiarze {$data['duration_minutes']} minut, ";
    }
    
    $html .= "przez</p>\n\n";
    
    $organizerName = $config['organizer_name'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji';
    $html .= "    <p class=\"bold\">{$organizerName}</p>\n\n";
    
    $subjectLabel = $config['subject_label'] ?? 'TEMAT SZKOLENIA';
    $html .= "    <h3>{$subjectLabel}</h3>\n";
    $html .= "    <h2 class=\"course-title\">{$course->title}</h2>\n\n";
    
    if (!empty($config['show_description']) && !empty($course->description)) {
        $description = trim($course->description);
        // Renderuj opis (obsługa listy numerowanej lub zwykłego tekstu)
        $html .= $this->renderDescription($description);
    }
    
    return $html;
}

protected function renderInstructorSignature(array $config, array $data): string
{
    $course = $data['course'];
    $instructor = $data['instructor'];
    $certificateNumber = $data['certificate_number'];
    $settings = $data['settings'] ?? [];
    
    $endDate = \Carbon\Carbon::parse($course->end_date)->format('d.m.Y');
    
    $html = "    <div class=\"date-section\">\n";
    $html .= "        <p style=\"margin: 0;\">Data, {$endDate}r.";
    
    if ($settings['show_certificate_number'] ?? true) {
        $html .= "<br>\n        Nr rejestru: {$certificateNumber}";
    }
    
    $html .= "</p>\n";
    $html .= "    </div>\n\n";
    
    if ($instructor) {
        $html .= "    <div class=\"instructor-section\">\n";
        $html .= "        <p>\n";
        
        $title = match($instructor->gender ?? 'prefer_not_to_say') {
            'male' => 'prowadzący:',
            'female' => 'prowadząca:',
            'other' => 'trener:',
            default => 'prowadzący/a:'
        };
        
        $html .= "            {$title}<br>\n";
        $html .= "            <span class=\"bold\">{$instructor->first_name} {$instructor->last_name}</span>\n";
        $html .= "        </p>\n";
        
        if (!empty($instructor->signature)) {
            $html .= $this->renderSignatureImage($instructor->signature);
        }
        
        $html .= "    </div>\n\n";
    }
    
    return $html;
}

protected function renderFooter(array $config, array $data): string
{
    $footerText = $config['text'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -';
    
    $html = "    <div class=\"footer\">\n";
    
    if (!empty($config['show_logo']) && !empty($config['logo_path'])) {
        $logoSize = $config['logo_size'] ?? 120;
        $logoPath = $config['logo_path'];
        $logoPosition = $config['logo_position'] ?? 'center';
        
        $html .= "        <div style=\"text-align: {$logoPosition}; margin-bottom: 15px;\">\n";
        $html .= $this->renderLogo($logoPath);
        $html .= "        </div>\n";
    }
    
    $html .= "        {$footerText}\n";
    $html .= "    </div>\n";
    
    return $html;
}

protected function renderCustomText(array $config): string
{
    $text = $config['text'] ?? '';
    $align = $config['align'] ?? 'center';
    return "    <p style=\"text-align: {$align};\">{$text}</p>\n";
}

// ... pozostałe metody pomocnicze (renderSignatureImage, renderLogo, renderDescription, buildStyles)
```

### Krok 3.2: Usuń generowanie plików Blade

**W `TemplateBuilderService.php`:**

```php
// app/Services/TemplateBuilderService.php

public function generateBladeFile($config, $slug)
{
    // NIE GENERUJEMY już plików Blade!
    // Szablony są tylko w bazie (JSON)
    
    \Log::info('Template saved to database only (no Blade file)', [
        'slug' => $slug
    ]);
    
    return true; // Zwróć true dla kompatybilności
}
```

---

## 🎯 Faza 4: Klient API w pnedu.pl

### Krok 4.1: Utwórz klienta API

```php
// pnedu/app/Services/CertificateApiClient.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CertificateApiClient
{
    protected string $apiUrl;
    protected string $apiToken;
    
    public function __construct()
    {
        $this->apiUrl = config('services.pneadm.api_url');
        $this->apiToken = config('services.pneadm.api_token');
    }
    
    /**
     * Generuje PDF zaświadczenia przez API
     */
    public function generatePdf(int $participantId): string
    {
        try {
            $response = Http::timeout(30)
                ->withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/certificates/generate", [
                    'participant_id' => $participantId
                ]);
            
            if ($response->successful()) {
                return $response->body();
            }
            
            Log::error('Certificate API error', [
                'participant_id' => $participantId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            throw new \Exception('Failed to generate certificate via API');
            
        } catch (\Exception $e) {
            Log::error('Certificate API request failed', [
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Pobiera istniejący certyfikat przez API
     */
    public function downloadPdf(int $participantId): string
    {
        try {
            $response = Http::timeout(30)
                ->withToken($this->apiToken)
                ->get("{$this->apiUrl}/api/certificates/download/{$participantId}");
            
            if ($response->successful()) {
                return $response->body();
            }
            
            throw new \Exception('Failed to download certificate via API');
            
        } catch (\Exception $e) {
            Log::error('Certificate API download failed', [
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
```

### Krok 4.2: Konfiguracja w pnedu.pl

```php
// pnedu/config/services.php
return [
    // ... istniejące konfiguracje
    
    'pneadm' => [
        'api_url' => env('PNEADM_API_URL', 'https://adm.pnedu.pl'),
        'api_token' => env('PNEADM_API_TOKEN'),
    ],
];
```

```env
# pnedu/.env
PNEADM_API_URL=https://adm.pnedu.pl
PNEADM_API_TOKEN=your-secret-api-token-here
```

### Krok 4.3: Aktualizacja CertificateController w pnedu.pl

```php
// pnedu/app/Http/Controllers/CertificateController.php

use App\Services\CertificateApiClient;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateApiClient $apiClient
    ) {}
    
    public function generate($courseId)
    {
        try {
            // ... istniejący kod weryfikacji użytkownika i uczestnika ...
            
            // Generuj PDF przez API
            $pdfContent = $this->apiClient->generatePdf($participant->id);
            
            // Zwróć PDF
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
                
        } catch (\Exception $e) {
            Log::error('Certificate generation failed', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Wystąpił błąd podczas generowania zaświadczenia.');
        }
    }
}
```

### Krok 4.4: Usuń zależność od pakietu (opcjonalnie)

```json
// pnedu/composer.json
{
    "require": {
        // Usuń: "pne/certificate-generator": "dev-main"
    },
    "repositories": {
        // Usuń: path repository dla pne-certificate-generator
    }
}
```

---

## 🎯 Faza 5: Migracja i testy

### Krok 5.1: Migracja istniejących szablonów

**Sprawdź czy wszystkie szablony mają poprawną konfigurację JSON:**

```php
// app/Console/Commands/ValidateCertificateTemplates.php
<?php

namespace App\Console\Commands;

use App\Models\CertificateTemplate;
use Illuminate\Console\Command;

class ValidateCertificateTemplates extends Command
{
    protected $signature = 'certificates:validate-templates';
    
    public function handle()
    {
        $templates = CertificateTemplate::all();
        
        foreach ($templates as $template) {
            $config = $template->config;
            
            if (empty($config)) {
                $this->error("Template {$template->id} ({$template->name}) has empty config");
                continue;
            }
            
            if (!isset($config['blocks']) || !is_array($config['blocks'])) {
                $this->error("Template {$template->id} ({$template->name}) has invalid blocks");
                continue;
            }
            
            if (!isset($config['settings']) || !is_array($config['settings'])) {
                $this->error("Template {$template->id} ({$template->name}) has invalid settings");
                continue;
            }
            
            $this->info("✓ Template {$template->id} ({$template->name}) is valid");
        }
    }
}
```

### Krok 5.2: Testy jednostkowe

```php
// tests/Feature/Api/CertificateApiTest.php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Certificate;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CertificateApiTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_generate_certificate_via_api()
    {
        $participant = Participant::factory()->create();
        Certificate::factory()->create(['participant_id' => $participant->id]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . config('services.pneadm.api_token'))
            ->postJson('/api/certificates/generate', [
                'participant_id' => $participant->id
            ]);
        
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
```

### Krok 5.3: Testy integracyjne

1. **Test w dev (Docker):**
   ```bash
   # W adm.pnedu.pl
   sail artisan test
   
   # W pnedu.pl
   sail artisan test
   ```

2. **Test ręczny:**
   - Utwórz szablon w `adm.pnedu.pl`
   - Wygeneruj certyfikat w `pnedu.pl`
   - Sprawdź czy PDF jest poprawny

---

## 🎯 Faza 6: Wdrożenie

### Krok 6.1: Wdrożenie na produkcję - adm.pnedu.pl

```bash
# 1. Backup bazy danych
mysqldump -u user -p pneadm > backup_$(date +%Y%m%d).sql

# 2. Wdróż kod
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Wyczyść cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Ustaw token API w .env
# PNEADM_API_TOKEN=wygeneruj-bezpieczny-token

# 5. Sprawdź czy API działa
curl -X POST https://adm.pnedu.pl/api/certificates/generate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"participant_id": 1}'
```

### Krok 6.2: Wdrożenie na produkcję - pnedu.pl

```bash
# 1. Wdróż kod
git pull origin main
composer install --no-dev --optimize-autoloader

# 2. Ustaw konfigurację API w .env
# PNEADM_API_URL=https://adm.pnedu.pl
# PNEADM_API_TOKEN=same-token-as-in-adm

# 3. Wyczyść cache
php artisan config:clear
php artisan cache:clear

# 4. Usuń pakiet (opcjonalnie)
composer remove pne/certificate-generator
```

### Krok 6.3: Monitoring

**Dodaj logowanie:**

```php
// W CertificateApiController
Log::info('Certificate generated via API', [
    'participant_id' => $participantId,
    'timestamp' => now()
]);
```

**Sprawdź logi:**

```bash
tail -f storage/logs/laravel.log | grep "Certificate"
```

---

## ✅ Checklist wdrożenia

### Przed wdrożeniem:
- [ ] Wszystkie testy przechodzą
- [ ] Backup bazy danych wykonany
- [ ] Token API wygenerowany i zapisany w obu projektach
- [ ] Dokumentacja zaktualizowana

### Po wdrożeniu:
- [ ] Test generowania certyfikatu w `adm.pnedu.pl` działa
- [ ] Test generowania certyfikatu w `pnedu.pl` działa
- [ ] API endpoint odpowiada poprawnie
- [ ] Logi nie pokazują błędów
- [ ] Stare pliki Blade można usunąć (opcjonalnie)

---

## 🔄 Rollback plan

Jeśli coś pójdzie nie tak:

1. **Przywróć backup bazy danych**
2. **Przywróć poprzednią wersję kodu** (`git revert`)
3. **Przywróć pakiet w pnedu.pl** (jeśli został usunięty)

---

## 📝 Notatki

- **Token API**: Użyj bezpiecznego tokena (min. 32 znaki, losowy)
- **Rate limiting**: API ma throttle 60 requestów/minutę
- **Timeout**: Klient API ma timeout 30 sekund
- **Cache**: Można dodać cache dla PDF (opcjonalnie)

---

## 🎉 Po wdrożeniu

System będzie:
- ✅ Prostszy (jedna lokalizacja logiki)
- ✅ Bardziej niezawodny (brak problemów z plikami)
- ✅ Łatwiejszy w utrzymaniu (jeden kod)
- ✅ Bezpieczniejszy (API z tokenem)
- ✅ Lepszy do skalowania (możliwość cache'owania)








