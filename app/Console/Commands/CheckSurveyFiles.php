<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Survey;
use Illuminate\Support\Facades\Storage;

class CheckSurveyFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:check-files 
                           {--show-missing : Show surveys with missing files}
                           {--show-old-paths : Show surveys with old file paths}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Check survey file paths and their existence on disk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $showMissing = $this->option('show-missing');
        $showOldPaths = $this->option('show-old-paths');
        
        $this->info('=== Survey Files Check ===');
        
        // Sprawdź katalogi
        $this->checkDirectories();
        
        // Sprawdź ankiety w bazie danych
        $this->checkDatabasePaths($showMissing, $showOldPaths);
        
        return 0;
    }
    
    private function checkDirectories()
    {
        $this->info('');
        $this->info('=== Directory Structure ===');
        
        $directories = [
            'surveys/imports',
            'survey_files'
        ];
        
        foreach ($directories as $dir) {
            if (Storage::disk('private')->exists($dir)) {
                $files = Storage::disk('private')->files($dir);
                $this->info("✓ {$dir}: " . count($files) . " files");
                
                if (count($files) > 0) {
                    foreach (array_slice($files, 0, 3) as $file) {
                        $this->line("  - " . basename($file));
                    }
                    if (count($files) > 3) {
                        $this->line("  ... and " . (count($files) - 3) . " more");
                    }
                }
            } else {
                $this->warn("✗ {$dir}: does not exist");
            }
        }
    }
    
    private function checkDatabasePaths($showMissing, $showOldPaths)
    {
        $this->info('');
        $this->info('=== Database Analysis ===');
        
        // Wszystkie ankiety z plikami
        $surveysWithFiles = Survey::whereNotNull('original_file_path')->get();
        $this->info("Total surveys with files: " . $surveysWithFiles->count());
        
        // Grupuj według ścieżek
        $pathGroups = $surveysWithFiles->groupBy(function ($survey) {
            $path = $survey->original_file_path;
            if (strpos($path, 'surveys/imports/') === 0) {
                return 'surveys/imports';
            } elseif (strpos($path, 'survey_files/') === 0) {
                return 'survey_files';
            } else {
                return 'other';
            }
        });
        
        foreach ($pathGroups as $type => $surveys) {
            $this->info("{$type}: " . $surveys->count() . " surveys");
        }
        
        // Sprawdź pliki na dysku
        $missingFiles = [];
        $existingFiles = 0;
        
        foreach ($surveysWithFiles as $survey) {
            if ($survey->original_file_path && Storage::disk('private')->exists($survey->original_file_path)) {
                $existingFiles++;
            } else {
                $missingFiles[] = $survey;
            }
        }
        
        $this->info("Files existing on disk: {$existingFiles}");
        $this->info("Files missing on disk: " . count($missingFiles));
        
        // Pokaż ankiety ze starymi ścieżkami
        if ($showOldPaths) {
            $oldPathSurveys = $surveysWithFiles->filter(function ($survey) {
                return strpos($survey->original_file_path, 'survey_files/') === 0;
            });
            
            if ($oldPathSurveys->count() > 0) {
                $this->info('');
                $this->info('=== Surveys with Old Paths ===');
                
                foreach ($oldPathSurveys as $survey) {
                    $exists = Storage::disk('private')->exists($survey->original_file_path) ? '✓' : '✗';
                    $this->line("Survey {$survey->id}: {$exists} {$survey->original_file_path}");
                }
            }
        }
        
        // Pokaż brakujące pliki
        if ($showMissing && count($missingFiles) > 0) {
            $this->info('');
            $this->info('=== Surveys with Missing Files ===');
            
            foreach ($missingFiles as $survey) {
                $this->line("Survey {$survey->id}: {$survey->original_file_path}");
            }
        }
        
        // Sprawdź pliki bez odpowiadających rekordów w bazie
        $this->checkOrphanedFiles();
    }
    
    private function checkOrphanedFiles()
    {
        $this->info('');
        $this->info('=== Orphaned Files Check ===');
        
        $directories = ['surveys/imports', 'survey_files'];
        $dbPaths = Survey::whereNotNull('original_file_path')
                        ->pluck('original_file_path')
                        ->toArray();
        
        $orphanedFiles = [];
        
        foreach ($directories as $dir) {
            if (Storage::disk('private')->exists($dir)) {
                $files = Storage::disk('private')->files($dir);
                
                foreach ($files as $file) {
                    if (!in_array($file, $dbPaths)) {
                        $orphanedFiles[] = $file;
                    }
                }
            }
        }
        
        if (count($orphanedFiles) > 0) {
            $this->warn("Orphaned files (no database record): " . count($orphanedFiles));
            foreach (array_slice($orphanedFiles, 0, 5) as $file) {
                $this->line("  - {$file}");
            }
            if (count($orphanedFiles) > 5) {
                $this->line("  ... and " . (count($orphanedFiles) - 5) . " more");
            }
        } else {
            $this->info("✓ No orphaned files found");
        }
    }
}
