<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Education;

class EducationController extends Controller
{
    /**
     * Wyświetla listę szkoleń z drugiej bazy danych.
     */
    public function index(Request $request)
    {
        // pobranie wartości filtra "type" z URL-a, jeśli nie podano, domyślnie null
        $type = $request->input('type');
    
        // rozpoczęcie budowania zapytania z połączenia mysql_certgen
        $query = Education::on('mysql_certgen');
    
        // dodanie filtrowania, jeśli parametr został przekazany
        if ($type) {
            $query->where('type', $type);
        }
    
        // wykonanie zapytania z wybranymi polami
        $educations = $query->get(['id', 'lp', 'title', 'zagadnienia', 'data', 'type']);

    // Pobierz liczbę uczestników dla każdego kursu z tabeli students
    $educations = $educations->map(function($education) {
        $participantsCount = DB::connection('mysql_certgen')
            ->table('students')
            ->where('id_education', $education->id)
            ->count();

        // Dodaj liczbę uczestników do każdego rekordu
        $education->participants_count = $participantsCount;

        return $education;
    });        
    
        // zwrócenie danych do widoku razem z aktualnym filtrem
        return view('education.index', compact('educations', 'type'));
    }

    public function exportParticipants($id)
    {
        // Pobieramy kurs z bazy źródłowej
        $education = Education::on('mysql_certgen')->findOrFail($id);
    
        // Sprawdzenie czy kurs istnieje w bazie docelowej
        $course = DB::connection('mysql')->table('courses')
            ->where('id_old', $education->id)
            ->where('source_id_old', 'BD:Certgen-education')
            ->first();
    
        if (!$course) {
            return redirect()->route('education.index')->with('error', 'Kurs nie został znaleziony w bazie docelowej.');
        }
    
        $students = DB::connection('mysql_certgen')->table('students')
            ->where('id_education', $education->id)
            ->get();
    
        $importedCount = 0;
        $skippedCount = 0; // ✅ Inicjalizacja zmiennej, aby uniknąć błędu
        $skippedIds = []; // tutaj zapiszemy ID uczestników, którzy zostali pominięci
    
        foreach ($students as $student) {
            // Sprawdzamy, czy uczestnik już istnieje w tabeli `participants` dla danego kursu na podstawie `course_id + email`
            $exists = DB::connection('mysql')->table('participants')
                ->where('course_id', $course->id)
                ->where('email', $student->email)
                ->exists();
    
            if ($exists) {
                $skippedIds[] = $student->id;
                $skippedCount++; // ✅ Zwiększamy licznik pominiętych rekordów
                continue;
            }
    
            try {
                // Wstawiamy nowego uczestnika, jeśli go nie było
                DB::connection('mysql')->table('participants')->insert([
                    'course_id' => $course->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email ?? null, // Email jest wymagany, ale może być null
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $importedCount++;
            } catch (\Exception $e) {
                // Jeśli wystąpi błąd, dodajemy ID do pominiętych
                $skippedIds[] = $student->id;
                $skippedCount++; // ✅ Upewniamy się, że liczymy również błędy wstawiania
            }
        }
    
        // Tworzymy komunikat podsumowujący
        if (!empty($skippedIds)) {
            $skippedString = implode(', ', $skippedIds);
            $message = "Zaimportowano {$importedCount} uczestników dla kursu '{$education->title}'.<br>"
                     . "Pominięto {$skippedCount} uczestników, ponieważ już istnieją.<br>"
                     . "ID pominiętych rekordów: {$skippedString}.";
        } else {
            $message = "Zaimportowano wszystkich uczestników ({$importedCount}) dla kursu '{$education->title}'.";
        }
    
        return redirect()->route('education.index')->with('success', $message);
    }
    
    public function exportToCourses(Request $request)
    {
        // Opcjonalnie filtruj eksport po "type"
        $type = $request->input('type');
    
        $query = Education::on('mysql_certgen');
    
        if ($type) {
            $query->where('type', $type);
        }
    
        $educations = $query->get();
    
        $importedCount = 0;
        $skippedCount = 0;
    
        // ✅ Tworzymy folder `courses/images`, jeśli nie istnieje
        $storageDirectory = storage_path('app/public/courses/images');
        if (!file_exists($storageDirectory)) {
            mkdir($storageDirectory, 0777, true);
        }
    
        foreach ($educations as $education) {
            // Sprawdzamy, czy kurs już istnieje w bazie docelowej
            $existingCourse = DB::connection('mysql')->table('courses')
                ->where('id_old', $education->id)
                ->where('source_id_old', 'BD:Certgen-education')
                ->exists();
    
            if ($existingCourse) {
                $skippedCount++;
                continue; // Pomijamy ten kurs, ponieważ już istnieje
            }
    
            // ✅ Oczyszczanie `title` i `description` z HTML
            $title = strip_tags($education->title);
            $description = strip_tags($education->zagadnienia);
    
            // ✅ Wstawienie rekordu do tabeli courses i pobranie jego ID
            $courseId = DB::connection('mysql')->table('courses')->insertGetId([
                'title' => $title,
                'description' => $description,
                'start_date' => $education->data,
                'end_date' => Carbon::parse($education->data)->addHours(2)->format('Y-m-d H:i:s'),
                'is_paid' => 0,
                'type' => 'online',
                'category' => 'open',
                'instructor_id' => 1,
                'is_active' => 1,
                'id_old' => $education->id, // ← dodajemy tutaj id z tabeli education
                'source_id_old' => 'BD:Certgen-education',
                'image' => null, // Tymczasowo puste, zaktualizujemy po pobraniu grafiki
            ]);
    
            // ✅ Tworzenie losowej nazwy pliku
            $randomSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $imageFileName = "course_{$courseId}_{$randomSuffix}.jpg";
            $imagePath = "courses/images/{$imageFileName}"; // ✅ Ścieżka w bazie
    
            // ✅ Pobieranie grafiki **po zapisaniu kursu**, żeby nie blokować bazy
            $formattedId = str_pad($education->id, 3, '0', STR_PAD_LEFT);
            $imageUrl = "https://zdalna-lekcja.pl/grafika/Live%20TIK%20miniatury/Live%20TIK%20miniatura%20{$formattedId}.jpg";
    
            try {
                $imageContents = @file_get_contents($imageUrl); // Pobieramy obraz
                if ($imageContents !== false) {
                    file_put_contents("{$storageDirectory}/{$imageFileName}", $imageContents); // Zapisujemy plik
    
                    // ✅ Aktualizacja rekordu kursu w bazie o ścieżkę do grafiki
                    DB::connection('mysql')->table('courses')->where('id', $courseId)->update([
                        'image' => $imagePath
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Nie udało się pobrać obrazu dla kursu ID {$education->id}: " . $e->getMessage());
            }
    
            // ✅ Wstawiamy powiązany rekord do `course_online_details`
            DB::connection('mysql')->table('course_online_details')->insert([
                'course_id' => $courseId,
                'platform' => 'YouTube',
                'meeting_link' => $education->link, // pole link z tabeli education
                'meeting_password' => null, // jeżeli nie ma hasła lub nie używasz
            ]);
    
            $importedCount++;
        }
    
        // Powrót z komunikatem sukcesu
        return redirect()->route('education.index')->with('success', "Eksport zakończony: <b>{$importedCount}</b> kursów dodanych, <b>{$skippedCount}</b> pominiętych.");
    }
    
    
}
