<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ImportPubligoData extends Command
{
    protected $signature = 'import:publigo {file}';
    protected $description = 'Importuje dane z pliku CSV do tabeli publigo';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Plik nie istnieje: $filePath");
            return;
        }

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0); // Pierwszy wiersz jako nagłówki

        $records = $csv->getRecords();

        $insertedCount = 0;

        foreach ($records as $record) {
            DB::table('publigo')->insert([
                'title' => $record['title'],
                'description' => $record['description'],
                'start_date' => $record['start_date'],
                'end_date' => $record['end_date'],
                'is_paid' => $record['is_paid'] == '1' ? true : false,
                'type' => strtolower($record['type']) == 'online' ? 'online' : 'offline',
                'category' => strtolower($record['category']) == 'open' ? 'open' : 'closed',
                'instructor_id' => $record['instructor_id'] ?? null,
                'image' => $record['image'] ?? null,
                'is_active' => $record['is_active'] == '1' ? true : false,
                'certificate_format' => $record['certificate_format'] ?? null,
                'platform' => $record['platform'] ?? null,
                'meeting_link' => $record['meeting_link'] ?? null,
                'meeting_password' => $record['meeting_password'] ?? null,
                'location_name' => $record['location_name'] ?? null,
                'postal_code' => $record['postal_code'] ?? null,
                'post_office' => $record['post_office'] ?? null,
                'address' => $record['address'] ?? null,
                'country' => $record['country'] ?? 'Polska',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $insertedCount++;
        }

        $this->info("Zaimportowano $insertedCount rekordów do tabeli publigo.");
    }
}
