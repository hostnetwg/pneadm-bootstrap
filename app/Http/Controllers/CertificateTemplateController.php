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
        
        return view('admin.certificate-templates.create', compact('availableBlocks', 'availableLogos'));
    }

    /**
     * Zapisuje nowy szablon
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

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
        
        // Sortuj bloki według pola 'order' przed zapisem
        uasort($blocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        $config = [
            'blocks' => $blocks,
            'settings' => [
                'font_family' => $request->input('font_family', 'DejaVu Sans'),
                'orientation' => $request->input('orientation', 'portrait'),
                'title_size' => $request->input('title_size', 38),
                'title_color' => $request->input('title_color', '#000000'),
                'course_title_size' => $request->input('course_title_size', 32)
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
        
        return view('admin.certificate-templates.edit', compact('certificateTemplate', 'availableBlocks', 'availableLogos'));
    }

    /**
     * Aktualizuje szablon
     */
    public function update(Request $request, CertificateTemplate $certificateTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Przygotowanie konfiguracji
        $blocks = $request->input('blocks', []);
        
        // Sortuj bloki według pola 'order' przed zapisem
        uasort($blocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        $config = [
            'blocks' => $blocks,
            'settings' => [
                'font_family' => $request->input('font_family', 'DejaVu Sans'),
                'orientation' => $request->input('orientation', 'portrait'),
                'title_size' => $request->input('title_size', 38),
                'title_color' => $request->input('title_color', '#000000'),
                'course_title_size' => $request->input('course_title_size', 32)
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
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($templateView, [
            'participant' => $participant,
            'certificateNumber' => $certificateNumber,
            'course' => $course,
            'instructor' => $instructor,
            'durationMinutes' => $durationMinutes,
            'isPdfMode' => $isPdfMode,
        ])->setPaper('A4', 'portrait')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans',
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
     */
    protected function getAvailableLogos()
    {
        $logosPath = 'certificates/logos';
        
        if (!Storage::disk('public')->exists($logosPath)) {
            Storage::disk('public')->makeDirectory($logosPath);
        }
        
        $files = Storage::disk('public')->files($logosPath);
        
        $logos = [];
        foreach ($files as $file) {
            $logos[] = [
                'path' => $file,
                'url' => asset('storage/' . $file), // Prawidłowa ścieżka publiczna
                'name' => basename($file),
                'size' => Storage::disk('public')->size($file)
            ];
        }
        
        return $logos;
    }

    /**
     * Upload nowego logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            $path = $file->storeAs('certificates/logos', $filename, 'public');
            
            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => asset('storage/' . $path), // Prawidłowa ścieżka publiczna
                'name' => $filename
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nie przesłano pliku'
        ], 400);
    }

    /**
     * Usuwa logo
     */
    public function deleteLogo(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = $request->input('path');
        
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            
            return response()->json([
                'success' => true,
                'message' => 'Logo zostało usunięte'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Plik nie istnieje'
        ], 404);
    }
}
