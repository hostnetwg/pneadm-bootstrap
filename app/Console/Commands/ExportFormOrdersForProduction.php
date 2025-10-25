<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportFormOrdersForProduction extends Command
{
    protected $signature = 'export:form-orders 
                            {--file=form_orders_production.sql : Nazwa pliku wyjściowego}';

    protected $description = 'Eksportuje tabelę form_orders do pliku SQL z prawidłową strefą czasową dla importu na produkcji';

    public function handle()
    {
        $filename = $this->option('file');
        $this->info('🚀 Rozpoczynam export tabeli form_orders...');
        
        // Pobierz wszystkie dane
        $records = DB::table('form_orders')->orderBy('id')->get();
        $this->info("📊 Znaleziono {$records->count()} rekordów");
        
        // Otwórz plik do zapisu
        $file = fopen(base_path($filename), 'w');
        
        // Nagłówek SQL
        fwrite($file, "-- Export tabeli form_orders dla produkcji\n");
        fwrite($file, "-- Generated: " . now() . "\n");
        fwrite($file, "-- Records: {$records->count()}\n\n");
        
        // WAŻNE: Ustaw strefę czasową na UTC
        fwrite($file, "SET time_zone = '+00:00';\n");
        fwrite($file, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        
        // Wyczyść tabelę
        fwrite($file, "-- Wyczyść tabelę przed importem\n");
        fwrite($file, "TRUNCATE TABLE `form_orders`;\n\n");
        
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();
        
        // Eksportuj rekordy
        foreach ($records as $record) {
            $columns = [];
            $values = [];
            
            foreach ($record as $column => $value) {
                $columns[] = "`{$column}`";
                
                if (is_null($value)) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    // Escape single quotes
                    $escapedValue = str_replace("'", "''", $value);
                    $values[] = "'{$escapedValue}'";
                }
            }
            
            $sql = "INSERT INTO `form_orders` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            fwrite($file, $sql);
            
            $bar->advance();
        }
        
        fwrite($file, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($file);
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Export zakończony!");
        $this->info("📄 Plik: " . base_path($filename));
        $this->newLine();
        
        $this->table(
            ['Statystyka', 'Wartość'],
            [
                ['Wyeksportowanych rekordów', $records->count()],
                ['Rozmiar pliku', $this->formatBytes(filesize(base_path($filename)))],
                ['Ścieżka', base_path($filename)],
            ]
        );
        
        $this->newLine();
        $this->warn('📋 Jak zaimportować na produkcji:');
        $this->line('1. Skopiuj plik ' . $filename . ' na serwer produkcyjny');
        $this->line('2. Przez phpMyAdmin → SQL lub przez terminal:');
        $this->line('   mysql -u username -p database_name < ' . $filename);
        
        return 0;
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}




