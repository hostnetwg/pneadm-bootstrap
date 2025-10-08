<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Survey;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MigrateSurveyFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:migrate-files 
                           {--dry-run : Run without actually moving files}
                           {--force : Force migration even if target file exists}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Migrate survey files from survey_files to surveys/imports directory and update database paths';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info('=== Migration Survey Files ===');
        $this->info('From: storage/app/private/survey_files/*');
        $this->info('To: storage/app/private/surveys/imports/*');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No actual changes will be made');
        }
        
        // Sprawdź czy katalog survey_files istnieje
        if (!Storage::disk('private')->exists('survey_files')) {
            $this->info('Directory survey_files does not exist. Nothing to migrate.');
            return 0;
        }
        
        // Utwórz katalog docelowy jeśli nie istnieje
        if (!Storage::disk('private')->exists('surveys/imports')) {
            $this->info('Creating target directory: surveys/imports');
            if (!$isDryRun) {
                Storage::disk('private')->makeDirectory('surveys/imports');
            }
        }
        
        // Pobierz wszystkie ankiety z ścieżkami do starych plików
        $surveysWithOldPaths = Survey::where('original_file_path', 'LIKE', 'survey_files/%')
                                   ->orWhere('original_file_path', 'LIKE', '%survey_files%')
                                   ->get();
        
        $this->info("Found {$surveysWithOldPaths->count()} surveys with old file paths in database");
        
        // Pobierz wszystkie pliki z katalogu survey_files
        $oldFiles = Storage::disk('private')->files('survey_files');
        $this->info("Found " . count($oldFiles) . " files in survey_files directory");
        
        $movedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        
        // Przenieś pliki fizycznie
        foreach ($oldFiles as $oldFilePath) {
            $fileName = basename($oldFilePath);
            $newFilePath = 'surveys/imports/' . $fileName;
            
            $this->line("Processing file: {$fileName}");
            
            // Sprawdź czy plik docelowy już istnieje
            if (Storage::disk('private')->exists($newFilePath) && !$force) {
                $this->warn("  Target file already exists: {$newFilePath} (use --force to overwrite)");
                continue;
            }
            
            if (!$isDryRun) {
                try {
                    // Przenieś plik
                    if (Storage::disk('private')->exists($newFilePath) && $force) {
                        Storage::disk('private')->delete($newFilePath);
                        $this->warn("  Overwriting existing file: {$newFilePath}");
                    }
                    
                    Storage::disk('private')->move($oldFilePath, $newFilePath);
                    $this->info("  ✓ Moved to: {$newFilePath}");
                    $movedCount++;
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to move {$oldFilePath}: " . $e->getMessage());
                    Log::error("Failed to move survey file: " . $e->getMessage(), [
                        'old_path' => $oldFilePath,
                        'new_path' => $newFilePath
                    ]);
                    $errorCount++;
                }
            } else {
                $this->info("  → Would move to: {$newFilePath}");
                $movedCount++;
            }
        }
        
        // Zaktualizuj ścieżki w bazie danych
        foreach ($surveysWithOldPaths as $survey) {
            $oldPath = $survey->original_file_path;
            
            // Konwertuj starą ścieżkę na nową
            $newPath = $this->convertPath($oldPath);
            
            $this->line("Survey ID {$survey->id}: {$oldPath} → {$newPath}");
            
            if (!$isDryRun) {
                try {
                    $survey->update(['original_file_path' => $newPath]);
                    $this->info("  ✓ Database updated");
                    $updatedCount++;
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to update database: " . $e->getMessage());
                    Log::error("Failed to update survey path in database: " . $e->getMessage(), [
                        'survey_id' => $survey->id,
                        'old_path' => $oldPath,
                        'new_path' => $newPath
                    ]);
                    $errorCount++;
                }
            } else {
                $this->info("  → Would update database");
                $updatedCount++;
            }
        }
        
        // Usuń pusty katalog survey_files (tylko jeśli nie ma błędów)
        if (!$isDryRun && $errorCount === 0 && Storage::disk('private')->exists('survey_files')) {
            $remainingFiles = Storage::disk('private')->files('survey_files');
            if (empty($remainingFiles)) {
                Storage::disk('private')->deleteDirectory('survey_files');
                $this->info("✓ Removed empty survey_files directory");
            } else {
                $this->warn("! survey_files directory not removed - contains " . count($remainingFiles) . " files");
            }
        }
        
        // Podsumowanie
        $this->info('');
        $this->info('=== Migration Summary ===');
        $this->info("Files moved: {$movedCount}");
        $this->info("Database records updated: {$updatedCount}");
        
        if ($errorCount > 0) {
            $this->error("Errors encountered: {$errorCount}");
            $this->warn("Check logs for details");
            return 1;
        }
        
        if ($isDryRun) {
            $this->warn('This was a DRY RUN - no actual changes were made');
            $this->info('Run without --dry-run to perform the actual migration');
        } else {
            $this->info('✓ Migration completed successfully!');
        }
        
        return 0;
    }
    
    /**
     * Convert old file path to new format
     */
    private function convertPath(string $oldPath): string
    {
        // survey_files/123_filename.csv → surveys/imports/123_filename.csv
        if (strpos($oldPath, 'survey_files/') === 0) {
            return str_replace('survey_files/', 'surveys/imports/', $oldPath);
        }
        
        // Jeśli ścieżka zawiera survey_files w środku
        if (strpos($oldPath, 'survey_files') !== false) {
            return str_replace('survey_files', 'surveys/imports', $oldPath);
        }
        
        // Jeśli ścieżka nie zawiera survey_files, sprawdź czy to stary format
        $fileName = basename($oldPath);
        if (preg_match('/^\d+_.*\.csv$/', $fileName)) {
            return 'surveys/imports/' . $fileName;
        }
        
        // W przypadku wątpliwości, umieść w nowym katalogu
        return 'surveys/imports/' . basename($oldPath);
    }
}
