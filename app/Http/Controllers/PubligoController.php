<?php

namespace App\Http\Controllers;

use League\Csv\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class PubligoController extends Controller
{
    public function showImportForm()
    {
        return view('publigo.import');
    }

    public function import(Request $request)
    {
        try {
            // Walidacja pliku CSV
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            ]);

            // Folder docelowy w storage/app/public/tmp/
            $storageFolder = 'tmp';

            // Sprawdzenie i utworzenie katalogu 'tmp' w storage/app/public/, jeśli nie istnieje
            if (!Storage::disk('public')->exists($storageFolder)) {
                Storage::disk('public')->makeDirectory($storageFolder, 0777, true);
            }

            // Przechowywanie pliku tymczasowego na dysku public
            $filePath = $request->file('csv_file')->store($storageFolder, 'public');

            // Pełna ścieżka do pliku (dla League CSV)
            $absoluteFilePath = storage_path('app/public/' . $filePath);

            // Sprawdzenie, czy plik rzeczywiście istnieje
            if (!file_exists($absoluteFilePath)) {
                return response()->json([
                    'success' => false,
                    'message' => "Plik nie istnieje: " . $absoluteFilePath,
                ], 500);
            }

            // Odczytanie pliku CSV
            $csv = Reader::createFromPath($absoluteFilePath, 'r');
            $csv->setDelimiter(';'); // Separator CSV
            $csv->setHeaderOffset(0); // Pierwszy wiersz jako nagłówki

            $records = $csv->getRecords();
            $importedCount = 0;

            foreach ($records as $record) {
                DB::table('publigo')->insert([
                    'id_old'              => $record['id_old'],
                    'title'              => $record['title'],
                    'description'        => $record['description'],
                    'start_date'         => $record['start_date'],
                    'end_date'           => $record['end_date'],
                    'is_paid'            => $record['is_paid'] == '1',
                    'type'               => strtolower($record['type']) == 'online' ? 'online' : 'offline',
                    'category'           => strtolower($record['category']) == 'open' ? 'open' : 'closed',
                    'instructor_id'      => $record['instructor_id'] ?? null,
                    'image'              => $record['image'] ?? null,
                    'is_active'          => $record['is_active'] == '1',
                    'certificate_format' => $record['certificate_format'] ?? null,
                    'platform'           => $record['platform'] ?? null,
                    'meeting_link'       => $record['meeting_link'] ?? null,
                    'meeting_password'   => $record['meeting_password'] ?? null,
                    'location_name'      => $record['location_name'] ?? null,
                    'postal_code'        => $record['postal_code'] ?? null,
                    'post_office'        => $record['post_office'] ?? null,
                    'address'            => $record['address'] ?? null,
                    'country'            => $record['country'] ?? 'Polska',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                $importedCount++;
            }

            // Usunięcie pliku po imporcie
            Storage::disk('public')->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => "Zaimportowano $importedCount rekordów!"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd: ' . $e->getMessage(),
            ], 500);
        }
    }
}
