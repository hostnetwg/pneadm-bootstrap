<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Course;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ParticipantController extends Controller
{
    /**
     * Wyświetla listę uczestników dla danego kursu.
     */
    public function index(Request $request, Course $course)
    {
        $query = Participant::where('course_id', $course->id);
        
        // Obsługa wyszukiwania
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('birth_place', 'LIKE', "%{$searchTerm}%");
            });
        }
    
        if ($request->query('sort') === 'asc') {
            // Pobranie posortowanych uczestników
            $sortedParticipants = $query
                ->orderByRaw("CONVERT(last_name USING utf8mb4) COLLATE utf8mb4_polish_ci")
                ->orderByRaw("CONVERT(first_name USING utf8mb4) COLLATE utf8mb4_polish_ci")
                ->get();
    
            // Aktualizacja numeracji w bazie danych
            foreach ($sortedParticipants as $index => $participant) {
                $participant->update(['order' => $index + 1]);
            }
    
            // Przekierowanie na stronę bez parametru sort, aby uniknąć ponownego sortowania
            return redirect()->route('participants.index', ['course' => $course->id])
                ->with('success', 'Lista uczestników została posortowana i zapisana.');
        }
    
        // Pobranie uczestników według zapisanej kolejności
        $perPage = $request->get('per_page', 50);
        
        // Obsługa opcji "wszyscy" - jeśli per_page to 'all', pobierz wszystkie rekordy
        if ($perPage === 'all') {
            $participants = $query->orderBy('order')->get();
            // Konwersja kolekcji na paginator dla kompatybilności z widokiem
            $participants = new \Illuminate\Pagination\LengthAwarePaginator(
                $participants,
                $participants->count(),
                $participants->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $participants = $query->orderBy('order')->paginate($perPage);
        }
    
        return view('participants.index', compact('participants', 'course'));
    }
    
    

    /**
     * Formularz dodawania nowego uczestnika do kursu.
     */
    public function create(Course $course)
    {
        return view('participants.create', compact('course'));
    }

    /**
     * Zapisuje nowego uczestnika w bazie danych.
     */
    public function store(Request $request, Course $course)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'access_expires_at' => 'nullable|date',
        ]);
    
        $data = $request->all();
        
        // Obsługa daty wygaśnięcia dostępu
        if (!empty($data['access_expires_at'])) {
            // Input datetime-local zwraca format: YYYY-MM-DDTHH:MM
            // Traktujemy wprowadzony czas jako czas lokalny (Europe/Warsaw)
            $localTime = Carbon::createFromFormat('Y-m-d\TH:i', $data['access_expires_at'], 'Europe/Warsaw');
            // NIE konwertujemy na UTC - Laravel zrobi to automatycznie
            $data['access_expires_at'] = $localTime;
        }
    
        // Pobranie ostatniego numeru porządkowego w danym kursie
        $lastOrder = $course->participants()->max('order') ?? 0;
    
        // Tworzenie nowego uczestnika z przypisanym numerem porządkowym
        $course->participants()->create(array_merge(
            $data,
            ['order' => $lastOrder + 1]
        ));
    
        return redirect()->route('participants.index', $course)->with('success', 'Uczestnik dodany.');
    }
    

    /**
     * Formularz edycji uczestnika.
     */
    public function edit(Course $course, Participant $participant)
    {
        return view('participants.edit', compact('course', 'participant'));
    }

    /**
     * Aktualizacja danych uczestnika.
     */
    public function update(Request $request, Course $course, Participant $participant)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'access_expires_at' => 'nullable|date',
        ]);

        $data = $request->all();
        
        // Obsługa daty wygaśnięcia dostępu
        if (!empty($data['access_expires_at'])) {
            // Input datetime-local zwraca format: YYYY-MM-DDTHH:MM
            // Traktujemy wprowadzony czas jako czas lokalny (Europe/Warsaw)
            $localTime = Carbon::createFromFormat('Y-m-d\TH:i', $data['access_expires_at'], 'Europe/Warsaw');
            // NIE konwertujemy na UTC - Laravel zrobi to automatycznie
            $data['access_expires_at'] = $localTime;
        }

        $participant->update($data);

        return redirect()->route('participants.index', $course)->with('success', 'Uczestnik zaktualizowany.');
    }

    /**
     * Import uczestników z pliku CSV.
     */
    public function import(Request $request, Course $course)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        try {
            $handle = fopen($file->getPathname(), 'r');
            
            // Pomiń nagłówek
            $header = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    // Mapowanie kolumn CSV
                    $csvData = array_combine($header, $row);
                    
                    // Sprawdź czy uczestnik już istnieje
                    $existingParticipant = Participant::where('email', $csvData['E-mail uczestnika'])
                                                     ->where('course_id', $course->id)
                                                     ->first();
                    
                    if ($existingParticipant) {
                        $skippedCount++;
                        continue;
                    }

                    // Parsowanie imienia i nazwiska
                    $fullName = trim($csvData['Imię i nazwisko'], '"');
                    $nameParts = explode(' ', $fullName, 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';

                    // Parsowanie daty wygaśnięcia dostępu
                    $accessExpiresAt = null;
                    if (!empty($csvData['Dostęp wygasa'])) {
                        $expiresDate = trim($csvData['Dostęp wygasa'], '"');
                        if ($expiresDate && $expiresDate !== '') {
                            // Próbuj różne formaty daty
                            $formats = ['Y-m-d H:i:s', 'd.m.Y H:i', 'Y-m-d H:i'];
                            foreach ($formats as $format) {
                                try {
                                    $accessExpiresAt = Carbon::createFromFormat($format, $expiresDate);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }
                    }

                    // Pobranie ostatniego numeru porządkowego
                    $lastOrder = $course->participants()->max('order') ?? 0;

                    // Tworzenie uczestnika
                    Participant::create([
                        'course_id' => $course->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $csvData['E-mail uczestnika'],
                        'birth_date' => null,
                        'birth_place' => null,
                        'access_expires_at' => $accessExpiresAt,
                        'order' => $lastOrder + 1,
                    ]);

                    $importedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Błąd w wierszu: " . implode(',', $row) . " - " . $e->getMessage();
                    $skippedCount++;
                }
            }
            
            fclose($handle);

            $message = "Zaimportowano {$importedCount} uczestników.";
            if ($skippedCount > 0) {
                $message .= " Pominięto {$skippedCount} uczestników (już istnieją lub błąd).";
            }
            if (!empty($errors)) {
                $message .= " Błędy: " . implode('; ', array_slice($errors, 0, 5));
            }

            return redirect()->route('participants.index', $course)
                           ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->route('participants.index', $course)
                           ->with('error', 'Błąd podczas importu: ' . $e->getMessage());
        }
    }

    /**
     * Usunięcie uczestnika.
     */
    public function destroy(Course $course, Participant $participant)
    {
        $participant->delete();

        return redirect()->route('participants.index', $course)->with('success', 'Uczestnik usunięty.');
    }

    /**
     * Generowanie PDF z listą uczestników
     */
    public function downloadParticipantsList(Request $request, Course $course)
    {
        $query = Participant::where('course_id', $course->id);
        
        // Obsługa wyszukiwania (jeśli jest aktywne)
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('birth_place', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Pobranie uczestników z ich certyfikatami i instruktorem kursu
        $participants = $query->with('certificate')->orderBy('order')->get();
        $course->load('instructor');

        // Generowanie PDF
        $pdf = Pdf::loadView('participants.pdf-list', [
            'course' => $course,
            'participants' => $participants,
            'searchTerm' => $request->get('search'),
            'totalCount' => $participants->count()
        ])->setPaper('A4', 'portrait')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans',
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => false,
              'isPhpEnabled' => true,
              'defaultPaperSize' => 'a4',
              'defaultPaperOrientation' => 'portrait',
              'enable_font_subsetting' => true,
              'dpi' => 150,
              'isFontSubsettingEnabled' => true,
          ]);

        // Nazwa pliku
        $courseDate = $course->start_date ? Carbon::parse($course->start_date)->format('Y-m-d_H_i') : date('Y-m-d_H_i');
        
        // Zamiana polskich znaków na odpowiedniki bez ogonków
        $polishChars = ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
                       'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z'];
        
        $courseTitle = strtr($course->title, $polishChars);
        $searchInfo = $request->filled('search') ? "_WYSZUKIWANIE_" . Str::slug($request->get('search')) : "";
        
        $fileName = $courseDate . '_Lista_uczestnikow_' . $courseTitle . $searchInfo . '.pdf';
        $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

        return $pdf->download($fileName);
    }
}

