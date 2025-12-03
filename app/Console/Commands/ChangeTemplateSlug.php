<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateTemplate;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ChangeTemplateSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'template:change-slug {template_id=5} {new_slug=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zmienia slug szablonu certyfikatu i aktualizuje powiązania';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $templateId = $this->argument('template_id');
        $newSlug = $this->argument('new_slug');
        
        $this->info("🔄 Zmiana slug szablonu ID={$templateId} na '{$newSlug}'");
        $this->line(str_repeat("=", 60));
        $this->newLine();
        
        // 1. Sprawdź szablon
        $template = CertificateTemplate::find($templateId);
        if (!$template) {
            $this->error("❌ BŁĄD: Szablon o ID={$templateId} nie istnieje!");
            return 1;
        }
        
        $oldSlug = $template->slug;
        
        if ($oldSlug === $newSlug) {
            $this->info("✅ Szablon już ma slug '{$newSlug}' - brak zmian.");
            return 0;
        }
        
        $this->info("📋 Obecny stan szablonu:");
        $this->line("   ID: {$template->id}");
        $this->line("   Nazwa: {$template->name}");
        $this->line("   Slug: {$oldSlug} → {$newSlug}");
        $this->line("   Aktywny: " . ($template->is_active ? 'Tak' : 'Nie'));
        $this->line("   Domyślny: " . ($template->is_default ? 'Tak' : 'Nie'));
        $this->newLine();
        
        // 2. Sprawdź czy istnieje już szablon z nowym slugiem
        $existingTemplate = CertificateTemplate::where('slug', $newSlug)
            ->where('id', '!=', $templateId)
            ->first();
        
        if ($existingTemplate) {
            $this->warn("⚠️  UWAGA: Istnieje już szablon z slug '{$newSlug}' (ID: {$existingTemplate->id})");
            $this->line("   Nazwa: {$existingTemplate->name}");
            
            if (!$this->confirm('Czy chcesz kontynuować?', true)) {
                $this->info('Anulowano.');
                return 0;
            }
        }
        
        // 3. Sprawdź kursy używające tego szablonu
        $coursesUsing = Course::where('certificate_template_id', $templateId)->get();
        $this->info("📚 Kursy używające tego szablonu: {$coursesUsing->count()}");
        if ($coursesUsing->count() > 0) {
            foreach ($coursesUsing as $course) {
                $this->line("   - ID: {$course->id}, Tytuł: {$course->title}");
            }
        }
        $this->newLine();
        
        // 4. Sprawdź pliki blade
        $packagePath = base_path('../pne-certificate-generator');
        $oldBladeFile = $packagePath . '/resources/views/certificates/' . $oldSlug . '.blade.php';
        $newBladeFile = $packagePath . '/resources/views/certificates/' . $newSlug . '.blade.php';
        
        $this->info("📁 Pliki blade:");
        $this->line("   Stary: {$oldSlug}.blade.php - " . (File::exists($oldBladeFile) ? "✅ Istnieje" : "❌ Nie istnieje"));
        $this->line("   Nowy: {$newSlug}.blade.php - " . (File::exists($newBladeFile) ? "⚠️  Już istnieje" : "✅ Będzie utworzony"));
        $this->newLine();
        
        // 5. Potwierdź zmianę
        if (!$this->confirm('Czy chcesz kontynuować zmianę?', true)) {
            $this->info('Anulowano.');
            return 0;
        }
        
        // 6. Wykonaj zmianę
        DB::beginTransaction();
        try {
            // Zmień slug w bazie
            $template->slug = $newSlug;
            $template->save();
            
            $this->info("✅ Slug zmieniony w bazie danych");
            
            // Zmień nazwę pliku blade (jeśli istnieje)
            if (File::exists($oldBladeFile)) {
                if (File::exists($newBladeFile)) {
                    // Backup istniejącego pliku
                    $backupFile = $newBladeFile . '.backup.' . time();
                    File::copy($newBladeFile, $backupFile);
                    $this->info("✅ Utworzono backup istniejącego pliku: " . basename($backupFile));
                }
                
                File::move($oldBladeFile, $newBladeFile);
                $this->info("✅ Plik blade zmieniony: {$oldSlug}.blade.php → {$newSlug}.blade.php");
            } else {
                $this->warn("⚠️  Plik {$oldSlug}.blade.php nie istnieje - pominięto zmianę nazwy pliku");
            }
            
            DB::commit();
            
            $this->newLine();
            $this->info("✅ Zmiana zakończona pomyślnie!");
            $this->newLine();
            $this->info("📋 Podsumowanie:");
            $this->line("   - Slug w bazie: '{$oldSlug}' → '{$newSlug}'");
            $this->line("   - Plik blade: '{$oldSlug}.blade.php' → '{$newSlug}.blade.php'");
            $this->line("   - Kursy używające szablonu: {$coursesUsing->count()} (bez zmian)");
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ BŁĄD: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
