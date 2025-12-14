<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Course;
use App\Models\ParticipantEmail;
use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ParticipantController extends Controller
{
    /**
     * Wyświetla listę wszystkich uczestników ze wszystkich kursów.
     */
    public function all(Request $request)
    {
        $query = Participant::with('course');
        
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

        // Filtr: Email
        if ($request->filled('filter_email')) {
            if ($request->get('filter_email') === 'has') {
                $query->whereNotNull('email')->where('email', '!=', '');
            } elseif ($request->get('filter_email') === 'missing') {
                $query->where(function($q) {
                    $q->whereNull('email')->orWhere('email', '');
                });
            }
        }

        // Filtr: Data urodzenia
        if ($request->filled('filter_birth_date')) {
            if ($request->get('filter_birth_date') === 'has') {
                $query->whereNotNull('birth_date');
            } elseif ($request->get('filter_birth_date') === 'missing') {
                $query->whereNull('birth_date');
            }
        }

        // Filtr: Miejsce urodzenia
        if ($request->filled('filter_birth_place')) {
            if ($request->get('filter_birth_place') === 'has') {
                $query->whereNotNull('birth_place')->where('birth_place', '!=', '');
            } elseif ($request->get('filter_birth_place') === 'missing') {
                $query->where(function($q) {
                    $q->whereNull('birth_place')->orWhere('birth_place', '');
                });
            }
        }
        
        // Sortowanie
        $sortBy = $request->get('sort_by', 'last_name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        if ($sortBy === 'last_name') {
            $query->orderByRaw("CONVERT(last_name USING utf8mb4) COLLATE utf8mb4_polish_ci {$sortDirection}");
        } elseif ($sortBy === 'first_name') {
            $query->orderByRaw("CONVERT(first_name USING utf8mb4) COLLATE utf8mb4_polish_ci {$sortDirection}");
        } elseif ($sortBy === 'birth_place') {
            $query->orderByRaw("CONVERT(birth_place USING utf8mb4) COLLATE utf8mb4_polish_ci {$sortDirection}");
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
        
        // Paginacja
        $perPage = $request->get('per_page', 50);
        
        if ($perPage === 'all') {
            $participants = $query->get();
            $participants = new \Illuminate\Pagination\LengthAwarePaginator(
                $participants,
                $participants->count(),
                $participants->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $participants = $query->paginate($perPage)->withQueryString();
        }
        
        // Eager load relacje dla modali
        $participants->load(['course', 'certificate']);
        
        return view('participants.all', compact('participants'));
    }

    /**
     * Wyświetla listę wszystkich unikalnych e-maili z tabeli participant_emails
     */
    public function emailsList(Request $request)
    {
        // Usunięto nieużywane zapytanie coursesCount - dane są obliczane w subquery
        
        // Usunięto eager loading 'participants.course' - nie jest używane w głównej liście, tylko w modalu
        $query = ParticipantEmail::with('firstParticipant')
            ->select('participant_emails.*')
            ->selectRaw('COALESCE((
                SELECT COUNT(DISTINCT course_id) 
                FROM participants 
                WHERE participants.email = participant_emails.email 
                AND participants.deleted_at IS NULL
            ), 0) as courses_count')
            ->selectRaw('COALESCE((
                SELECT COUNT(DISTINCT p.course_id) 
                FROM participants p
                INNER JOIN courses c ON c.id = p.course_id
                WHERE p.email = participant_emails.email 
                AND p.deleted_at IS NULL
                AND c.is_paid = 1
                AND c.deleted_at IS NULL
            ), 0) as paid_courses_count')
            ->selectRaw('COALESCE((
                SELECT COUNT(DISTINCT p.course_id) 
                FROM participants p
                INNER JOIN courses c ON c.id = p.course_id
                WHERE p.email = participant_emails.email 
                AND p.deleted_at IS NULL
                AND c.is_paid = 0
                AND c.deleted_at IS NULL
            ), 0) as free_courses_count');
        
        // Obsługa wyszukiwania
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where('email', 'LIKE', "%{$searchTerm}%");
        }

        // Filtr: Status aktywności
        if ($request->filled('filter_active')) {
            if ($request->get('filter_active') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->get('filter_active') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filtr: Weryfikacja
        if ($request->filled('filter_verified')) {
            if ($request->get('filter_verified') === 'verified') {
                $query->where('is_verified', true);
            } elseif ($request->get('filter_verified') === 'unverified') {
                $query->where(function($q) {
                    $q->where('is_verified', false)->orWhereNull('is_verified');
                });
            }
        }

        // Filtr: Typ szkoleń (płatne/bezpłatne)
        if ($request->filled('filter_course_type')) {
            if ($request->get('filter_course_type') === 'paid') {
                // Tylko e-maile które mają płatne szkolenia - użyj subquery zamiast kolumny z selectRaw
                $query->whereRaw('(
                    SELECT COUNT(DISTINCT p.course_id) 
                    FROM participants p
                    INNER JOIN courses c ON c.id = p.course_id
                    WHERE p.email = participant_emails.email 
                    AND p.deleted_at IS NULL
                    AND c.is_paid = 1
                    AND c.deleted_at IS NULL
                ) > 0');
            } elseif ($request->get('filter_course_type') === 'free') {
                // Tylko e-maile które mają bezpłatne szkolenia - użyj subquery zamiast kolumny z selectRaw
                $query->whereRaw('(
                    SELECT COUNT(DISTINCT p.course_id) 
                    FROM participants p
                    INNER JOIN courses c ON c.id = p.course_id
                    WHERE p.email = participant_emails.email 
                    AND p.deleted_at IS NULL
                    AND c.is_paid = 0
                    AND c.deleted_at IS NULL
                ) > 0');
            }
        }

        // Filtr: Nieprawidłowe adresy e-mail
        // Zoptymalizowano: użycie MySQL REGEXP zamiast pobierania wszystkich rekordów do PHP
        // MySQL REGEXP jest szybsze dla dużych zbiorów danych
        if ($request->filled('filter_invalid_email')) {
            if ($request->get('filter_invalid_email') === 'invalid') {
                // Wyświetl tylko nieprawidłowe e-maile
                // Użyj REGEXP do walidacji - wykrywa również e-maile z BOM na początku
                $query->where(function($q) {
                    // Nieprawidłowy format e-maila
                    $q->whereRaw("email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'")
                      // Lub e-maile z BOM (UTF-8 BOM: EFBBBF) - sprawdź pierwsze 3 bajty
                      ->orWhereRaw("HEX(LEFT(email, 1)) = 'EF' AND HEX(SUBSTRING(email, 2, 1)) = 'BB' AND HEX(SUBSTRING(email, 3, 1)) = 'BF'");
                });
            } elseif ($request->get('filter_invalid_email') === 'valid') {
                // Wyświetl tylko prawidłowe e-maile (bez BOM)
                $query->whereRaw("email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'");
                $query->whereRaw("NOT (HEX(LEFT(email, 1)) = 'EF' AND HEX(SUBSTRING(email, 2, 1)) = 'BB' AND HEX(SUBSTRING(email, 3, 1)) = 'BF')");
            }
        }

        // Sortowanie - domyślnie sortuj malejąco według ID
        $sortBy = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Obsługa sortowania po courses_count, paid_courses_count, free_courses_count
        if (in_array($sortBy, ['courses_count', 'paid_courses_count', 'free_courses_count'])) {
            $query->orderByRaw("{$sortBy} {$sortDirection}");
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
        
        // Paginacja - zawsze używaj paginacji dla wydajności
        $perPage = $request->get('per_page', 50);
        
        // Ograniczenie maksymalnej liczby rekordów na stronę dla bezpieczeństwa
        $maxPerPage = 500;
        if ($perPage === 'all' || $perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }
        
        $emails = $query->paginate($perPage)->withQueryString();
        
        return view('participants.emails-list', compact('emails'));
    }

    /**
     * Zbiera unikalne adresy e-mail z tabeli participants i zapisuje je w participant_emails
     */
    public function collectEmails()
    {
        try {
            // Zwiększ limit czasu wykonania do 5 minut
            set_time_limit(300);
            
            // Pobierz wszystkie unikalne e-maile z uczestników (tylko niepuste) używając surowego zapytania SQL dla lepszej wydajności
            $uniqueEmails = DB::table('participants')
                ->select('email', DB::raw('MIN(id) as first_participant_id'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('deleted_at')
                ->groupBy('email')
                ->get();

            if ($uniqueEmails->isEmpty()) {
                return redirect()->route('participants.all')->with('info', 'Brak e-maili do zebrania.');
            }

            $added = 0;
            $updated = 0;
            $skipped = 0;

            // Funkcja pomocnicza do normalizacji e-maila
            $normalizeEmail = function($email) {
                // Usuń BOM (Byte Order Mark) UTF-8
                $email = ltrim($email, "\xEF\xBB\xBF");
                // Trim i lowercase
                $email = trim(strtolower($email));
                return $email;
            };

            // Użyj upsert dla każdego e-maila (w partiach po 500 dla wydajności)
            $emailsToUpsert = [];
            
            foreach ($uniqueEmails as $emailData) {
                $rawEmail = $emailData->email;
                $normalizedEmail = $normalizeEmail($rawEmail);
                
                // Pomiń puste e-maile po normalizacji
                if (empty($normalizedEmail)) {
                    $skipped++;
                    continue;
                }
                
                $firstParticipantId = $emailData->first_participant_id;
                $count = $emailData->count;

                $emailsToUpsert[] = [
                    'email' => $normalizedEmail,
                    'first_participant_id' => $firstParticipantId,
                    'participants_count' => $count,
                    'is_active' => true,
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk upsert (w partiach po 500)
            if (!empty($emailsToUpsert)) {
                foreach (array_chunk($emailsToUpsert, 500) as $chunk) {
                    DB::transaction(function () use ($chunk, &$added, &$updated, &$skipped) {
                        foreach ($chunk as $emailData) {
                            try {
                                // Sprawdź czy rekord już istnieje (tylko aktywne, bez soft deleted)
                                $existing = ParticipantEmail::where('email', $emailData['email'])
                                    ->whereNull('deleted_at')
                                    ->first();
                                
                                if ($existing) {
                                    // Aktualizuj istniejący rekord
                                    $existing->update([
                                        'participants_count' => $emailData['participants_count'],
                                        'updated_at' => now(),
                                    ]);
                                    $updated++;
                                } else {
                                    // Sprawdź czy istnieje jako soft deleted
                                    $softDeleted = ParticipantEmail::withTrashed()
                                        ->where('email', $emailData['email'])
                                        ->whereNotNull('deleted_at')
                                        ->first();
                                    
                                    if ($softDeleted) {
                                        // Przywróć i zaktualizuj soft deleted rekord
                                        $softDeleted->restore();
                                        $softDeleted->update([
                                            'first_participant_id' => $emailData['first_participant_id'],
                                            'participants_count' => $emailData['participants_count'],
                                            'is_active' => $emailData['is_active'],
                                            'is_verified' => $emailData['is_verified'],
                                            'updated_at' => now(),
                                        ]);
                                        $added++; // Traktuj przywrócenie jako dodanie
                                    } else {
                                        // Utwórz nowy rekord używając updateOrCreate dla bezpieczeństwa
                                        // updateOrCreate automatycznie obsługuje duplikaty
                                        $result = ParticipantEmail::updateOrCreate(
                                            ['email' => $emailData['email']],
                                            [
                                                'first_participant_id' => $emailData['first_participant_id'],
                                                'participants_count' => $emailData['participants_count'],
                                                'is_active' => $emailData['is_active'],
                                                'is_verified' => $emailData['is_verified'],
                                                'created_at' => $emailData['created_at'],
                                                'updated_at' => $emailData['updated_at'],
                                            ]
                                        );
                                        
                                        // Sprawdź czy został utworzony nowy rekord czy zaktualizowany
                                        if ($result->wasRecentlyCreated) {
                                            $added++;
                                        } else {
                                            $updated++;
                                        }
                                    }
                                }
                            } catch (\Illuminate\Database\QueryException $e) {
                                // Obsługa błędu duplikatu (na wypadek race condition w transakcji)
                                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                                    // E-mail już istnieje - spróbuj zaktualizować
                                    $existing = ParticipantEmail::where('email', $emailData['email'])
                                        ->whereNull('deleted_at')
                                        ->first();
                                    
                                    if ($existing) {
                                        $existing->update([
                                            'participants_count' => $emailData['participants_count'],
                                            'updated_at' => now(),
                                        ]);
                                        $updated++;
                                    } else {
                                        // Jeśli nie udało się znaleźć, pomiń (prawdopodobnie race condition)
                                        $skipped++;
                                    }
                                } else {
                                    // Inny błąd - rzuć dalej
                                    throw $e;
                                }
                            }
                        }
                    });
                }
            }

            // Przygotuj komunikat zależnie od wyników
            if ($added == 0 && $updated == 0 && $skipped == 0) {
                $message = "Nie znaleziono żadnych e-maili do przetworzenia.";
                $messageType = 'info';
            } elseif ($added == 0 && $updated == 0) {
                $message = "Zaimportowano 0 rekordów. Wszystkie e-maile z tabeli participants już znajdują się w bazie participant_emails.";
                if ($skipped > 0) {
                    $message .= " Pominięto {$skipped} nieprawidłowych e-maili.";
                }
                $messageType = 'info';
            } else {
                $message = "Zebrano bazę e-mail. Dodano: {$added}, zaktualizowano: {$updated}";
                if ($skipped > 0) {
                    $message .= ", pominięto: {$skipped}";
                }
                $message .= ".";
                $messageType = 'success';
            }
            
            return redirect()->route('participants.all')->with($messageType, $message);
        } catch (\Exception $e) {
            \Log::error('Błąd podczas zbierania e-maili: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('participants.all')->with('error', 'Błąd podczas zbierania e-maili: ' . $e->getMessage());
        }
    }

    /**
     * Wyświetla listę uczestników dla danego kursu.
     */
    public function index(Request $request, Course $course)
    {
        $query = Participant::with('certificate')->where('participants.course_id', $course->id);
        
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
    
        $sortCertificate = $request->get('sort_certificate');
        $sortCertificate = in_array($sortCertificate, ['asc', 'desc']) ? $sortCertificate : null;

        if ($sortCertificate) {
            $numericExpr = "CAST(IFNULL(NULLIF(REGEXP_SUBSTR(certificates.certificate_number, '[0-9]+'), ''), '0') AS UNSIGNED)";
            $direction = strtoupper($sortCertificate);

            $query->leftJoin('certificates', function ($join) use ($course) {
                $join->on('certificates.participant_id', '=', 'participants.id')
                    ->where('certificates.course_id', $course->id);
            })
            ->select('participants.*')
            ->orderByRaw("CASE WHEN certificates.certificate_number IS NULL OR certificates.certificate_number = '' THEN 1 ELSE 0 END")
            ->orderByRaw("{$numericExpr} {$direction}")
            ->orderBy('participants.order');
        } else {
            $query->orderBy('order');
        }

        // Pobranie uczestników z wybraną kolejnością
        $perPage = $request->get('per_page', 50);
        
        // Obsługa opcji "wszyscy" - jeśli per_page to 'all', pobierz wszystkie rekordy
        if ($perPage === 'all') {
            $participants = $query->get();
            // Konwersja kolekcji na paginator dla kompatybilności z widokiem
            $participants = new \Illuminate\Pagination\LengthAwarePaginator(
                $participants,
                $participants->count(),
                $participants->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $participants = $query->paginate($perPage);
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

        return back()->with('success', 'Uczestnik usunięty.');
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

    /**
     * Generowanie PDF z rejestrem zaświadczeń
     */
    public function downloadCertificateRegistry(Request $request, Course $course)
    {
        // Filtrowanie uczestników z pełnymi danymi
        $query = Participant::where('course_id', $course->id)
            ->whereNotNull('first_name')->where('first_name', '!=', '')
            ->whereNotNull('last_name')->where('last_name', '!=', '')
            ->whereNotNull('birth_date')
            ->whereNotNull('birth_place')->where('birth_place', '!=', '');

        // Pobranie uczestników
        $participants = $query->with('certificate')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        
        $course->load('instructor');

        // Generowanie PDF
        $pdf = Pdf::loadView('participants.pdf-registry', [
            'course' => $course,
            'participants' => $participants,
        ])->setPaper('A4', 'landscape')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans',
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => false,
              'isPhpEnabled' => true,
              'enable_font_subsetting' => true,
          ]);

        // Nazwa pliku
        $courseDate = $course->start_date ? Carbon::parse($course->start_date)->format('Y-m-d') : date('Y-m-d');
        
        $polishChars = ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
                       'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z'];
        
        $courseTitle = strtr($course->title, $polishChars);
        $fileName = 'Rejestr_zaswiadczen_' . $courseDate . '_' . $courseTitle . '.pdf';
        $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

        return $pdf->download($fileName);
    }

    /**
     * Aktualizuje adres e-mail we wszystkich powiązanych tabelach
     */
    public function updateEmail(Request $request, ParticipantEmail $participantEmail)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
            ],
        ], [
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email' => 'Adres e-mail ma nieprawidłowy format.',
            'email.max' => 'Adres e-mail nie może być dłuższy niż 255 znaków.',
        ]);

        if ($validator->fails()) {
            // Zachowaj filtry przy powrocie
            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)
                ->withErrors($validator)
                ->withInput();
        }

        $oldEmail = $participantEmail->email;
        // Usuń BOM (Byte Order Mark) UTF-8 i inne niewidoczne znaki z początku
        $newEmail = trim(strtolower($request->input('email')));
        // Usuń UTF-8 BOM (EF BB BF)
        $newEmail = ltrim($newEmail, "\xEF\xBB\xBF");
        $newEmail = trim($newEmail);

        // Jeśli e-mail się nie zmienił, nie rób nic
        if ($oldEmail === $newEmail) {
            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)
                ->with('info', 'Adres e-mail nie uległ zmianie.');
        }

        // Sprawdź czy nowy e-mail już istnieje w participant_emails
        $existingEmailRecord = ParticipantEmail::where('email', $newEmail)
            ->where('id', '!=', $participantEmail->id)
            ->whereNull('deleted_at')
            ->first();

        try {
            DB::beginTransaction();

            // Przygotuj stare e-maile do wyszukiwania (z BOM i bez BOM, żeby znaleźć wszystkie warianty)
            $oldEmailClean = ltrim($oldEmail, "\xEF\xBB\xBF");
            $oldEmailClean = trim($oldEmailClean);
            $oldEmailsToSearch = array_unique([$oldEmail, $oldEmailClean]);
            $oldEmailsToSearch = array_filter($oldEmailsToSearch);

            // Jeśli nowy e-mail już istnieje w participant_emails, scal rekordy
            if ($existingEmailRecord) {
                // SCALANIE REKORDÓW - nowy e-mail już istnieje
                
                // 1. Zaktualizuj wszystkie tabele używając obu wariantów e-maila (stary błędny i nowy poprawny)
                //    Wszystkie wystąpienia starego e-maila zostaną zaktualizowane na nowy
                
                // 2. Aktualizuj participants (używając obu wariantów starego e-maila)
                $participantsUpdated = DB::table('participants')
                    ->whereIn('email', $oldEmailsToSearch)
                    ->whereNull('deleted_at')
                    ->update(['email' => $newEmail]);

                // 3. Aktualizuj form_orders
                $formOrdersUpdated = 0;
                $formOrdersToUpdate = DB::table('form_orders')
                    ->where(function($query) use ($oldEmailsToSearch) {
                        $query->whereIn('participant_email', $oldEmailsToSearch)
                              ->orWhereIn('orderer_email', $oldEmailsToSearch);
                    })
                    ->get();

                foreach ($formOrdersToUpdate as $order) {
                    $updateData = ['updated_at' => now()];
                    
                    if (in_array($order->participant_email, $oldEmailsToSearch)) {
                        $updateData['participant_email'] = $newEmail;
                    }
                    
                    if (in_array($order->orderer_email, $oldEmailsToSearch)) {
                        $updateData['orderer_email'] = $newEmail;
                    }
                    
                    DB::table('form_orders')
                        ->where('id', $order->id)
                        ->update($updateData);
                    
                    $formOrdersUpdated++;
                }

                // 4. Aktualizuj form_order_participants
                $formOrderParticipantsUpdated = DB::table('form_order_participants')
                    ->whereIn('participant_email', $oldEmailsToSearch)
                    ->whereNull('deleted_at')
                    ->update([
                        'participant_email' => $newEmail,
                        'updated_at' => now(),
                    ]);

                // 5. Zaktualizuj istniejący rekord participant_emails (scal z błędnym)
                //    - Zwiększ participants_count o liczbę z błędnego rekordu
                //    - Zachowaj lepsze wartości dla is_verified i is_active (jeśli istniejący ma lepsze)
                $existingEmailRecord->participants_count = $existingEmailRecord->participants_count + $participantEmail->participants_count;
                // Jeśli istniejący rekord nie ma first_participant_id, użyj z błędnego
                if (!$existingEmailRecord->first_participant_id && $participantEmail->first_participant_id) {
                    $existingEmailRecord->first_participant_id = $participantEmail->first_participant_id;
                }
                // Zachowaj lepsze wartości dla is_verified i is_active
                if (!$existingEmailRecord->is_verified && $participantEmail->is_verified) {
                    $existingEmailRecord->is_verified = true;
                }
                if (!$existingEmailRecord->is_active && $participantEmail->is_active) {
                    $existingEmailRecord->is_active = true;
                }
                // Scal notatki jeśli istnieją
                if ($participantEmail->notes && !$existingEmailRecord->notes) {
                    $existingEmailRecord->notes = $participantEmail->notes;
                } elseif ($participantEmail->notes && $existingEmailRecord->notes) {
                    $existingEmailRecord->notes = $existingEmailRecord->notes . "\n\n[Merged from: {$oldEmail}]\n" . $participantEmail->notes;
                }
                $existingEmailRecord->save();

                // 6. Usuń błędny rekord (soft delete)
                $participantEmail->delete();
                
                $merged = true;
            } else {
                // NORMALNA AKTUALIZACJA - nowy e-mail nie istnieje
                
                // 1. Aktualizuj participant_emails (upewnij się, że nie ma BOM)
                $participantEmail->email = $newEmail;
                $participantEmail->save();
                
                $merged = false;

                // 2. Aktualizuj participants (szukaj zarówno z BOM jak i bez BOM)
                $participantsUpdated = DB::table('participants')
                    ->whereIn('email', $oldEmailsToSearch)
                    ->whereNull('deleted_at')
                    ->update(['email' => $newEmail]);

                // 3. Aktualizuj form_orders (participant_email i orderer_email) - szukaj zarówno z BOM jak i bez BOM
                $formOrdersUpdated = 0;
                $formOrdersToUpdate = DB::table('form_orders')
                    ->where(function($query) use ($oldEmailsToSearch) {
                        $query->whereIn('participant_email', $oldEmailsToSearch)
                              ->orWhereIn('orderer_email', $oldEmailsToSearch);
                    })
                    ->get();

                foreach ($formOrdersToUpdate as $order) {
                    $updateData = ['updated_at' => now()];
                    
                    // Sprawdź czy participant_email pasuje do któregoś ze starych e-maili
                    if (in_array($order->participant_email, $oldEmailsToSearch)) {
                        $updateData['participant_email'] = $newEmail;
                    }
                    
                    // Sprawdź czy orderer_email pasuje do któregoś ze starych e-maili
                    if (in_array($order->orderer_email, $oldEmailsToSearch)) {
                        $updateData['orderer_email'] = $newEmail;
                    }
                    
                    DB::table('form_orders')
                        ->where('id', $order->id)
                        ->update($updateData);
                    
                    $formOrdersUpdated++;
                }

                // 4. Aktualizuj form_order_participants - szukaj zarówno z BOM jak i bez BOM
                $formOrderParticipantsUpdated = DB::table('form_order_participants')
                    ->whereIn('participant_email', $oldEmailsToSearch)
                    ->whereNull('deleted_at')
                    ->update([
                        'participant_email' => $newEmail,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();

            \Log::info('E-mail zaktualizowany', [
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'merged' => $merged ?? false,
                'participants_updated' => $participantsUpdated ?? 0,
                'form_orders_updated' => $formOrdersUpdated ?? 0,
                'form_order_participants_updated' => $formOrderParticipantsUpdated ?? 0,
            ]);

            if ($merged) {
                $message = sprintf(
                    'Adres e-mail został scalony: "%s" → "%s". Błędny rekord został usunięty, a wszystkie powiązania przeniesione do istniejącego poprawnego rekordu. Zaktualizowano: %d uczestników, %d zamówień, %d uczestników zamówień.',
                    $oldEmail,
                    $newEmail,
                    $participantsUpdated ?? 0,
                    $formOrdersUpdated ?? 0,
                    $formOrderParticipantsUpdated ?? 0
                );
            } else {
                $message = sprintf(
                    'Adres e-mail został zaktualizowany z "%s" na "%s". Zaktualizowano: %d uczestników, %d zamówień, %d uczestników zamówień.',
                    $oldEmail,
                    $newEmail,
                    $participantsUpdated ?? 0,
                    $formOrdersUpdated ?? 0,
                    $formOrderParticipantsUpdated ?? 0
                );
            }

            // Zachowaj filtry z query string (nie z formularza)
            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Błąd podczas aktualizacji e-maila', [
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)
                ->with('error', 'Błąd podczas aktualizacji e-maila: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Usuwa adres e-mail (soft delete)
     */
    public function destroyEmail(Request $request, ParticipantEmail $participantEmail)
    {
        try {
            $email = $participantEmail->email;
            $participantEmail->delete();

            \Log::info('E-mail usunięty (soft delete)', [
                'email' => $email,
                'participant_email_id' => $participantEmail->id,
            ]);

            // Zachowaj filtry z query string
            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)
                ->with('success', "Adres e-mail \"{$email}\" został usunięty.");

        } catch (\Exception $e) {
            \Log::error('Błąd podczas usuwania e-maila', [
                'email' => $participantEmail->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $queryParams = $request->only(['search', 'filter_active', 'filter_verified', 'filter_invalid_email', 'filter_course_type', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== null && $value !== '';
            });
            return redirect()->route('participants.emails-list', $queryParams)
                ->with('error', 'Błąd podczas usuwania e-maila: ' . $e->getMessage());
        }
    }
}

