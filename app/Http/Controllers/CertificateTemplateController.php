<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Services\TemplateBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CertificateTemplateController extends Controller
{
    protected $templateBuilder;

    public function __construct(TemplateBuilderService $templateBuilder)
    {
        $this->templateBuilder = $templateBuilder;
    }

    /**
     * Wyświetla listę szablonów
     */
    public function index()
    {
        $templates = CertificateTemplate::orderBy('created_at', 'desc')->get();
        
        return view('admin.certificate-templates.index', compact('templates'));
    }

    /**
     * Formularz tworzenia nowego szablonu
     */
    public function create()
    {
        $availableBlocks = $this->templateBuilder->getAvailableBlocks();
        $availableLogos = $this->getAvailableLogos();
        $availableBackgrounds = $this->getAvailableBackgrounds();
        
        return view('admin.certificate-templates.create', compact('availableBlocks', 'availableLogos', 'availableBackgrounds'));
    }

    /**
     * Zapisuje nowy szablon
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'background_image' => 'nullable' // Może być text (z galerii) lub file (upload)
        ]);
        
        // Dodatkowa walidacja dla pliku, jeśli został przesłany
        if ($request->hasFile('background_image')) {
            $request->validate([
                'background_image' => 'image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB
            ]);
        }

        // Generowanie slug z nazwy
        $slug = Str::slug($request->name);
        
        // Sprawdzenie czy slug jest unikalny
        $originalSlug = $slug;
        $counter = 1;
        while (CertificateTemplate::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Przygotowanie konfiguracji
        $blocks = $request->input('blocks', []);
        
        // Obsługa stałych elementów (instructor_signature i footer)
        // Jeśli checkbox jest zaznaczony, upewnij się że blok istnieje
        if ($request->has('show_instructor_signature')) {
            // Sprawdź czy blok już istnieje
            $instructorBlockExists = false;
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'instructor_signature') {
                    $instructorBlockExists = true;
                    // Ustaw order na 9999, aby był zawsze na końcu
                    $blocks[$blockId]['order'] = 9999;
                    break;
                }
            }
            // Jeśli nie istnieje, dodaj nowy
            if (!$instructorBlockExists) {
                $blocks['instructor_signature_new'] = [
                    'type' => 'instructor_signature',
                    'order' => 9999,
                    'config' => []
                ];
            }
        } else {
            // Usuń blok jeśli checkbox nie jest zaznaczony
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'instructor_signature') {
                    unset($blocks[$blockId]);
                }
            }
        }
        
        if ($request->has('show_footer')) {
            // Sprawdź czy blok już istnieje
            $footerBlockExists = false;
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'footer') {
                    $footerBlockExists = true;
                    // Ustaw order na 9999, aby był zawsze na końcu
                    $blocks[$blockId]['order'] = 9999;
                    break;
                }
            }
            // Jeśli nie istnieje, dodaj nowy
            if (!$footerBlockExists) {
                $blocks['footer_new'] = [
                    'type' => 'footer',
                    'order' => 9999,
                    'config' => []
                ];
            }
        } else {
            // Usuń blok jeśli checkbox nie jest zaznaczony
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'footer') {
                    unset($blocks[$blockId]);
                }
            }
        }
        
        // Sortuj bloki według pola 'order' przed zapisem
        uasort($blocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        // Obsługa usuwania logo z bloków
        foreach ($blocks as $blockId => &$block) {
            $config = $block['config'] ?? [];
            if (!empty($config['remove_logo'])) {
                // Usuń logo_path z konfiguracji
                unset($config['logo_path']);
                unset($config['remove_logo']);
                $block['config'] = $config;
            }
        }
        unset($block);
        
        // Obsługa tła - może być wybrane z galerii (text input) lub wgrane (file)
        $backgroundImagePath = null;
        // Jeśli wybrano tło z galerii (text input)
        if ($request->has('background_image') && !$request->hasFile('background_image')) {
            $backgroundImagePath = $request->input('background_image') ?: null;
        }
        // Jeśli przesłano nowe tło (file upload)
        elseif ($request->hasFile('background_image')) {
            // Zapisz TYLKO w pakiecie
            $file = $request->file('background_image');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            $packagePath = $this->getPackagePath();
            if (!$packagePath) {
                throw new \Exception('Nie można znaleźć pakietu pne-certificate-generator. Sprawdź konfigurację Docker volume.');
            }
            
            $packageStoragePath = $packagePath . '/storage/certificates/backgrounds';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                $backgroundImagePath = 'certificates/backgrounds/' . $filename;
                
                \Log::info('Background saved to package during template update', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save background to package during template update', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Nie udało się zapisać tła w pakiecie: ' . $e->getMessage());
            }
        }
        
        $config = [
            'blocks' => $blocks,
            'settings' => [
                'font_family' => $request->input('font_family', 'DejaVu Sans'),
                'orientation' => $request->input('orientation', 'portrait'),
                'title_size' => $request->input('title_size', 38),
                'title_color' => $request->input('title_color', '#000000'),
                'course_title_size' => $request->input('course_title_size', 32),
                'participant_name_size' => $request->input('participant_name_size', 24),
                'participant_name_font' => $request->input('participant_name_font', 'DejaVu Sans'),
                'participant_name_italic' => $request->has('participant_name_italic'),
                'show_certificate_number' => $request->has('show_certificate_number'),
                'margin_top' => $request->input('margin_top', 10),
                'margin_bottom' => $request->input('margin_bottom', 10),
                'margin_left' => $request->input('margin_left', 50),
                'margin_right' => $request->input('margin_right', 50),
                'background_image' => $backgroundImagePath,
                'show_background' => $request->has('show_background')
            ]
        ];

        // Tworzenie szablonu w bazie
        $template = CertificateTemplate::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'config' => $config,
            'is_active' => $request->has('is_active')
        ]);

        // Generowanie pliku blade
        try {
            $this->templateBuilder->generateBladeFile($config, $slug);
            
            return redirect()
                ->route('admin.certificate-templates.index')
                ->with('success', "Szablon \"{$template->name}\" został utworzony.");
        } catch (\Exception $e) {
            // Usunięcie szablonu z bazy jeśli generowanie nie powiodło się
            $template->delete();
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Błąd podczas generowania pliku szablonu: ' . $e->getMessage());
        }
    }

    /**
     * Wyświetla szczegóły szablonu
     */
    public function show(CertificateTemplate $certificateTemplate)
    {
        return view('admin.certificate-templates.show', compact('certificateTemplate'));
    }

    /**
     * Formularz edycji szablonu
     */
    public function edit(CertificateTemplate $certificateTemplate)
    {
        $availableBlocks = $this->templateBuilder->getAvailableBlocks();
        $availableLogos = $this->getAvailableLogos();
        $availableBackgrounds = $this->getAvailableBackgrounds();
        
        return view('admin.certificate-templates.edit', compact('certificateTemplate', 'availableBlocks', 'availableLogos', 'availableBackgrounds'));
    }

    /**
     * Aktualizuje szablon
     */
    public function update(Request $request, CertificateTemplate $certificateTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'background_image' => 'nullable' // Może być text (z galerii) lub file (upload)
        ]);
        
        // Dodatkowa walidacja dla pliku, jeśli został przesłany
        if ($request->hasFile('background_image')) {
            $request->validate([
                'background_image' => 'image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB
            ]);
        }

        // Przygotowanie konfiguracji
        $blocks = $request->input('blocks', []);
        
        // Obsługa stałych elementów (instructor_signature i footer)
        // Jeśli checkbox jest zaznaczony, upewnij się że blok istnieje
        if ($request->has('show_instructor_signature')) {
            // Sprawdź czy blok już istnieje
            $instructorBlockExists = false;
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'instructor_signature') {
                    $instructorBlockExists = true;
                    // Ustaw order na 9999, aby był zawsze na końcu
                    $blocks[$blockId]['order'] = 9999;
                    break;
                }
            }
            // Jeśli nie istnieje, dodaj nowy
            if (!$instructorBlockExists) {
                $blocks['instructor_signature_new'] = [
                    'type' => 'instructor_signature',
                    'order' => 9999,
                    'config' => []
                ];
            }
        } else {
            // Usuń blok jeśli checkbox nie jest zaznaczony
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'instructor_signature') {
                    unset($blocks[$blockId]);
                }
            }
        }
        
        if ($request->has('show_footer')) {
            // Sprawdź czy blok już istnieje
            $footerBlockExists = false;
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'footer') {
                    $footerBlockExists = true;
                    // Ustaw order na 9999, aby był zawsze na końcu
                    $blocks[$blockId]['order'] = 9999;
                    break;
                }
            }
            // Jeśli nie istnieje, dodaj nowy
            if (!$footerBlockExists) {
                $blocks['footer_new'] = [
                    'type' => 'footer',
                    'order' => 9999,
                    'config' => []
                ];
            }
        } else {
            // Usuń blok jeśli checkbox nie jest zaznaczony
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'footer') {
                    unset($blocks[$blockId]);
                }
            }
        }
        
        // Sortuj bloki według pola 'order' przed zapisem
        uasort($blocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        // Obsługa usuwania logo z bloków
        foreach ($blocks as $blockId => &$block) {
            $config = $block['config'] ?? [];
            if (!empty($config['remove_logo'])) {
                // Usuń logo_path z konfiguracji
                unset($config['logo_path']);
                unset($config['remove_logo']);
                $block['config'] = $config;
            }
        }
        unset($block);
        
        // Obsługa tła - może być wybrane z galerii (text input) lub wgrane (file)
        $backgroundImagePath = $certificateTemplate->config['settings']['background_image'] ?? null;
        
        // Jeśli zaznaczono usunięcie tła
        if ($request->has('remove_background')) {
            $backgroundImagePath = null;
        }
        // Jeśli wybrano tło z galerii (text input)
        elseif ($request->has('background_image') && !$request->hasFile('background_image')) {
            $backgroundImagePath = $request->input('background_image') ?: null;
        }
        // Jeśli przesłano nowe tło (file upload)
        elseif ($request->hasFile('background_image')) {
            // Zapisz TYLKO w pakiecie
            $file = $request->file('background_image');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            $packagePath = $this->getPackagePath();
            if (!$packagePath) {
                throw new \Exception('Nie można znaleźć pakietu pne-certificate-generator. Sprawdź konfigurację Docker volume.');
            }
            
            $packageStoragePath = $packagePath . '/storage/certificates/backgrounds';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                $backgroundImagePath = 'certificates/backgrounds/' . $filename;
                
                \Log::info('Background saved to package during template update', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save background to package during template update', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Nie udało się zapisać tła w pakiecie: ' . $e->getMessage());
            }
        }
        
        $config = [
            'blocks' => $blocks,
            'settings' => [
                'font_family' => $request->input('font_family', 'DejaVu Sans'),
                'orientation' => $request->input('orientation', 'portrait'),
                'title_size' => $request->input('title_size', 38),
                'title_color' => $request->input('title_color', '#000000'),
                'course_title_size' => $request->input('course_title_size', 32),
                'participant_name_size' => $request->input('participant_name_size', 24),
                'participant_name_font' => $request->input('participant_name_font', 'DejaVu Sans'),
                'participant_name_italic' => $request->has('participant_name_italic'),
                'show_certificate_number' => $request->has('show_certificate_number'),
                'margin_top' => $request->input('margin_top', 10),
                'margin_bottom' => $request->input('margin_bottom', 10),
                'margin_left' => $request->input('margin_left', 50),
                'margin_right' => $request->input('margin_right', 50),
                'background_image' => $backgroundImagePath,
                'show_background' => $request->has('show_background')
            ]
        ];

        // Aktualizacja w bazie
        $certificateTemplate->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'config' => $config,
            'is_active' => $request->has('is_active')
        ]);

        // Ponowne generowanie pliku blade
        try {
            $this->templateBuilder->generateBladeFile($config, $certificateTemplate->slug);
            
            return redirect()
                ->route('admin.certificate-templates.index')
                ->with('success', "Szablon \"{$certificateTemplate->name}\" został zaktualizowany.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Błąd podczas aktualizacji pliku szablonu: ' . $e->getMessage());
        }
    }

    /**
     * Usuwa szablon (soft delete)
     */
    public function destroy(CertificateTemplate $certificateTemplate)
    {
        // Sprawdzenie czy szablon nie jest używany przez kursy
        if ($certificateTemplate->courses()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', 'Nie można usunąć szablonu, który jest używany przez kursy.');
        }

        $name = $certificateTemplate->name;
        
        // Soft delete - nie usuwamy pliku blade, tylko oznaczamy jako usunięty
        $certificateTemplate->delete();

        return redirect()
            ->route('admin.certificate-templates.index')
            ->with('success', "Szablon \"{$name}\" został przeniesiony do kosza.");
    }

    /**
     * Przywraca usunięty szablon
     */
    public function restore($id)
    {
        $certificateTemplate = CertificateTemplate::onlyTrashed()->findOrFail($id);
        $name = $certificateTemplate->name;
        
        $certificateTemplate->restore();

        return redirect()
            ->route('admin.certificate-templates.index')
            ->with('success', "Szablon \"{$name}\" został przywrócony z kosza.");
    }

    /**
     * Podgląd szablonu (generuje przykładowy certyfikat jako PDF)
     */
    public function preview(CertificateTemplate $certificateTemplate)
    {
        // Dane testowe do podglądu
        $participant = (object) [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'birth_date' => '1990-01-15',
            'birth_place' => 'Warszawa'
        ];

        $instructor = (object) [
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'gender' => 'female',
            'signature' => 'instructors/L320ili8mrep3FLUQSqQvNkDpYuMLpWAqBesHfFv.jpg' // Przykładowy instruktor z podpisem
        ];

        $course = (object) [
            'title' => 'Przykładowe Szkolenie',
            'description' => 'Opis przykładowego szkolenia obejmujący różne zagadnienia edukacyjne.',
            'start_date' => now()->subDays(7),
            'end_date' => now()
        ];

        $durationMinutes = 420; // 7 godzin
        $certificateNumber = '1/2025/PRZYKŁAD';
        $isPdfMode = true; // Generujemy PDF dla podglądu!

        // Używamy tego samego mechanizmu co w CertificateController
        $templateView = $certificateTemplate->blade_path;
        
        // Przygotowanie danych konfiguracji dla widoku (z fallbackami)
        $config = $certificateTemplate->config ?? [];
        $blocksRaw = $config['blocks'] ?? [];
        $settings = $config['settings'] ?? [];
        
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
        
        // Pobierz orientację i czcionkę z konfiguracji szablonu
        $orientation = $settings['orientation'] ?? 'portrait';
        $fontFamily = $settings['font_family'] ?? 'DejaVu Sans';
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($templateView, [
            'participant' => $participant,
            'certificateNumber' => $certificateNumber,
            'course' => $course,
            'instructor' => $instructor,
            'durationMinutes' => $durationMinutes,
            'isPdfMode' => $isPdfMode,
            // Konfiguracja szablonu
            'templateConfig' => $config,
            'templateSettings' => $settings,
            'headerConfig' => $headerConfig,
            'courseInfoConfig' => $courseInfoConfig,
            'footerConfig' => $footerConfig,
            // Posortowane bloki do renderowania w odpowiedniej kolejności
            'sortedBlocks' => $regularBlocks,
            'instructorSignatureBlock' => $instructorSignatureBlock,
            'footerBlock' => $footerBlock,
        ])->setPaper('A4', $orientation)
          ->setOptions([
              'defaultFont' => $fontFamily,
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => true
          ]);

        return $pdf->stream('podglad-szablonu-' . $certificateTemplate->slug . '.pdf');
    }

    /**
     * Klonuje szablon
     */
    public function clone(CertificateTemplate $certificateTemplate)
    {
        $newSlug = $certificateTemplate->slug . '-kopia';
        $counter = 1;
        while (CertificateTemplate::where('slug', $newSlug)->exists()) {
            $newSlug = $certificateTemplate->slug . '-kopia-' . $counter;
            $counter++;
        }

        $newTemplate = CertificateTemplate::create([
            'name' => $certificateTemplate->name . ' (Kopia)',
            'slug' => $newSlug,
            'description' => $certificateTemplate->description,
            'config' => $certificateTemplate->config,
            'is_active' => false
        ]);

        // Kopiowanie pliku blade
        $this->templateBuilder->generateBladeFile($certificateTemplate->config, $newSlug);

        return redirect()
            ->route('admin.certificate-templates.edit', $newTemplate)
            ->with('success', "Szablon został sklonowany. Możesz teraz edytować kopię.");
    }

    /**
     * Pobiera listę dostępnych logo
     * Sprawdza TYLKO pakiet - pliki nie są już przechowywane lokalnie
     */
    protected function getAvailableLogos()
    {
        $logos = [];
        
        // Sprawdź TYLKO pakiet
        $packagePath = $this->getPackagePath();
        if ($packagePath) {
            $packageLogosPath = $packagePath . '/storage/certificates/logos';
            if (File::exists($packageLogosPath)) {
                $packageFiles = File::files($packageLogosPath);
                foreach ($packageFiles as $file) {
                    $filename = $file->getFilename();
                    $logos[] = [
                        'path' => 'certificates/logos/' . $filename,
                        'url' => asset('storage/certificates/logos/' . $filename), // URL przez symlink
                        'name' => $filename,
                        'size' => $file->getSize()
                    ];
                }
            }
        } else {
            \Log::warning('Package path not found when getting available logos');
        }
        
        return $logos;
    }

    /**
     * Pobiera listę dostępnych tła
     * Sprawdza TYLKO pakiet - pliki nie są już przechowywane lokalnie
     */
    protected function getAvailableBackgrounds()
    {
        $backgrounds = [];
        
        // Sprawdź TYLKO pakiet
        $packagePath = $this->getPackagePath();
        if ($packagePath) {
            $packageBackgroundsPath = $packagePath . '/storage/certificates/backgrounds';
            if (File::exists($packageBackgroundsPath)) {
                $packageFiles = File::files($packageBackgroundsPath);
                foreach ($packageFiles as $file) {
                    $filename = $file->getFilename();
                    $backgrounds[] = [
                        'path' => 'certificates/backgrounds/' . $filename,
                        'url' => asset('storage/certificates/backgrounds/' . $filename), // URL przez symlink
                        'name' => $filename,
                        'size' => $file->getSize()
                    ];
                }
            }
        } else {
            \Log::warning('Package path not found when getting available backgrounds');
        }
        
        return $backgrounds;
    }

    /**
     * Upload nowego logo
     * Zapisuje TYLKO w pakiecie pne-certificate-generator (wspólny dla obu projektów)
     * NIE tworzy lokalnych kopii
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            // Zapisuj TYLKO w pakiecie
            $packagePath = $this->getPackagePath();
            if (!$packagePath) {
                \Log::error('Package path not found for logo upload');
                return response()->json([
                    'success' => false,
                    'message' => 'Nie można znaleźć pakietu pne-certificate-generator. Sprawdź konfigurację Docker volume.'
                ], 500);
            }
            
            $packageStoragePath = $packagePath . '/storage/certificates/logos';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                
                \Log::info('Logo saved to package', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
                
                // URL do pliku z pakietu (przez symlink lub bezpośredni dostęp)
                // W Docker volume plik jest dostępny bezpośrednio
                $url = asset('storage/certificates/logos/' . $filename);
                
                return response()->json([
                    'success' => true,
                    'path' => 'certificates/logos/' . $filename, // Względna ścieżka
                    'url' => $url,
                    'name' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save logo to package', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się zapisać logo w pakiecie: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Nie przesłano pliku'
        ], 400);
    }
    
    /**
     * Pobiera ścieżkę do pakietu pne-certificate-generator
     */
    protected function getPackagePath(): ?string
    {
        // Opcja 1: Przez Docker volume (w kontenerze)
        $dockerPath = '/var/www/pne-certificate-generator';
        if (File::exists($dockerPath)) {
            return $dockerPath;
        }
        
        // Opcja 2: Relatywna ścieżka z pneadm-bootstrap
        $relativePath = base_path('../pne-certificate-generator');
        if (File::exists($relativePath)) {
            return $relativePath;
        }
        
        // Opcja 3: Przez vendor (jeśli pakiet jest zainstalowany przez Composer)
        $vendorPath = base_path('vendor/pne/certificate-generator');
        if (File::exists($vendorPath)) {
            return $vendorPath;
        }
        
        return null;
    }

    /**
     * Usuwa logo
     * Usuwa TYLKO z pakietu - pliki nie są już przechowywane lokalnie
     */
    public function deleteLogo(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = $request->input('path');
        
        // Normalizuj ścieżkę (usuń ewentualne prefiksy)
        $normalizedPath = ltrim($path, '/');
        if (strpos($normalizedPath, 'storage/') === 0) {
            $normalizedPath = substr($normalizedPath, 8); // Usuń 'storage/'
        }
        
        // Usuń TYLKO z pakietu
        $packagePath = $this->getPackagePath();
        if (!$packagePath) {
            \Log::error('Package path not found when deleting logo', ['path' => $path]);
            return response()->json([
                'success' => false,
                'message' => 'Nie można znaleźć pakietu pne-certificate-generator.'
            ], 500);
        }
        
        $packagePaths = [
            $packagePath . '/storage/' . $normalizedPath,
            $packagePath . '/storage/certificates/logos/' . basename($normalizedPath), // Jeśli tylko nazwa pliku
        ];
        
        foreach ($packagePaths as $packageFilePath) {
            if (File::exists($packageFilePath)) {
                try {
                    File::delete($packageFilePath);
                    \Log::info('Logo deleted from package', ['path' => $packageFilePath]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Logo zostało usunięte'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to delete logo from package', [
                        'path' => $packageFilePath,
                        'error' => $e->getMessage()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Nie udało się usunąć logo: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        \Log::warning('Logo deletion failed - file not found in package', [
            'requested_path' => $path,
            'normalized_path' => $normalizedPath,
            'package_path' => $packagePath,
            'checked_paths' => $packagePaths
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Plik nie istnieje w pakiecie.'
        ], 404);
    }

    /**
     * Upload nowego tła
     * Zapisuje TYLKO w pakiecie pne-certificate-generator (wspólny dla obu projektów)
     * NIE tworzy lokalnych kopii
     */
    public function uploadBackground(Request $request)
    {
        $request->validate([
            'background' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB
        ]);

        if ($request->hasFile('background')) {
            $file = $request->file('background');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            // Zapisuj TYLKO w pakiecie
            $packagePath = $this->getPackagePath();
            if (!$packagePath) {
                \Log::error('Package path not found for background upload');
                return response()->json([
                    'success' => false,
                    'message' => 'Nie można znaleźć pakietu pne-certificate-generator. Sprawdź konfigurację Docker volume.'
                ], 500);
            }
            
            $packageStoragePath = $packagePath . '/storage/certificates/backgrounds';
            $packageFilePath = $packageStoragePath . '/' . $filename;
            
            if (!File::exists($packageStoragePath)) {
                File::makeDirectory($packageStoragePath, 0755, true);
            }
            
            try {
                File::put($packageFilePath, file_get_contents($file->getRealPath()));
                
                \Log::info('Background saved to package', [
                    'package_path' => $packageFilePath,
                    'filename' => $filename
                ]);
                
                // URL do pliku z pakietu (przez symlink lub bezpośredni dostęp)
                $url = asset('storage/certificates/backgrounds/' . $filename);
                
                return response()->json([
                    'success' => true,
                    'path' => 'certificates/backgrounds/' . $filename, // Względna ścieżka
                    'url' => $url,
                    'name' => $filename
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to save background to package', [
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się zapisać tła w pakiecie: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Nie przesłano pliku'
        ], 400);
    }

    /**
     * Usuwa tło
     * Usuwa TYLKO z pakietu - pliki nie są już przechowywane lokalnie
     */
    public function deleteBackground(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = $request->input('path');
        
        // Normalizuj ścieżkę (usuń ewentualne prefiksy i zamień stare ścieżki)
        $normalizedPath = ltrim($path, '/');
        if (strpos($normalizedPath, 'storage/') === 0) {
            $normalizedPath = substr($normalizedPath, 8); // Usuń 'storage/'
        }
        // Zamień stare ścieżki na nowe
        $normalizedPath = str_replace('certificate-backgrounds/', 'certificates/backgrounds/', $normalizedPath);
        
        // Usuń TYLKO z pakietu
        $packagePath = $this->getPackagePath();
        if (!$packagePath) {
            \Log::error('Package path not found when deleting background', ['path' => $path]);
            return response()->json([
                'success' => false,
                'message' => 'Nie można znaleźć pakietu pne-certificate-generator.'
            ], 500);
        }
        
        $packagePaths = [
            $packagePath . '/storage/' . $normalizedPath,
            $packagePath . '/storage/certificates/backgrounds/' . basename($normalizedPath), // Jeśli tylko nazwa pliku
        ];
        
        foreach ($packagePaths as $packageFilePath) {
            if (File::exists($packageFilePath)) {
                try {
                    File::delete($packageFilePath);
                    \Log::info('Background deleted from package', ['path' => $packageFilePath]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Tło zostało usunięte'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to delete background from package', [
                        'path' => $packageFilePath,
                        'error' => $e->getMessage()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Nie udało się usunąć tła: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        \Log::warning('Background deletion failed - file not found in package', [
            'requested_path' => $path,
            'normalized_path' => $normalizedPath,
            'package_path' => $packagePath,
            'checked_paths' => $packagePaths
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Plik nie istnieje w pakiecie.'
        ], 404);
    }
}
