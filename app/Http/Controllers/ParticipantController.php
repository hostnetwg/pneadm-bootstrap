<?php

namespace App\Http\Controllers;

use App\Jobs\SendAccessExpiryReminderEmailJob;
use App\Jobs\SendCertificateLinkEmailJob;
use App\Jobs\SendCourseAccessEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEmailLog;
use App\Models\Course;
use App\Models\CourseFileLink;
use App\Models\CourseVideo;
use App\Models\Participant;
use App\Models\ParticipantDownloadToken;
use App\Models\ParticipantEmail;
use App\Models\PneduUser;
use App\Services\Mail\SystemMailDiagnostics;
use App\Services\ParticipantAccessExpiryReminderService;
use App\Services\ParticipantAccessExpiryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('birth_place', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('notes', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filtr: Email
        if ($request->filled('filter_email')) {
            if ($request->get('filter_email') === 'has') {
                $query->whereNotNull('email')->where('email', '!=', '');
            } elseif ($request->get('filter_email') === 'missing') {
                $query->where(function ($q) {
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
                $query->where(function ($q) {
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
                $query->where(function ($q) {
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
                $query->where(function ($q) {
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
     * Eksport listy emaili do CSV dla SENDY
     */
    public function exportToCsv(Request $request)
    {
        try {
            // Zwiększ limit czasu dla dużych zbiorów danych
            set_time_limit(300);

            // Użyj tych samych filtrów co w emailsList
            $query = ParticipantEmail::query();

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
                    $query->where(function ($q) {
                        $q->where('is_verified', false)->orWhereNull('is_verified');
                    });
                }
            }

            // Filtr: Typ szkoleń (płatne/bezpłatne)
            if ($request->filled('filter_course_type')) {
                if ($request->get('filter_course_type') === 'paid') {
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
            if ($request->filled('filter_invalid_email')) {
                if ($request->get('filter_invalid_email') === 'invalid') {
                    $query->where(function ($q) {
                        $q->whereRaw("email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'")
                            ->orWhereRaw("HEX(LEFT(email, 1)) = 'EF' AND HEX(SUBSTRING(email, 2, 1)) = 'BB' AND HEX(SUBSTRING(email, 3, 1)) = 'BF'");
                    });
                } elseif ($request->get('filter_invalid_email') === 'valid') {
                    $query->whereRaw("email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'");
                    $query->whereRaw("NOT (HEX(LEFT(email, 1)) = 'EF' AND HEX(SUBSTRING(email, 2, 1)) = 'BB' AND HEX(SUBSTRING(email, 3, 1)) = 'BF')");
                }
            }

            // Pobierz wszystkie emaile (bez paginacji dla eksportu)
            $emails = $query->get();

            // Przygotuj dane CSV
            $csvData = [];

            // Nagłówek CSV
            $csvData[] = ['Name', 'Email', 'Sername', 'data', 'id_szkolenia'];

            foreach ($emails as $emailRecord) {
                // Znajdź ostatniego uczestnika (najnowszy po start_date kursu lub created_at)
                $lastParticipant = DB::table('participants')
                    ->where('participants.email', $emailRecord->email)
                    ->whereNull('participants.deleted_at')
                    ->join('courses', 'participants.course_id', '=', 'courses.id')
                    ->whereNull('courses.deleted_at')
                    ->orderBy('courses.start_date', 'desc')
                    ->orderBy('participants.created_at', 'desc')
                    ->select('participants.first_name', 'participants.last_name', 'courses.id as course_id', 'courses.start_date')
                    ->first();

                // Pobierz dane (obsługa przypadku gdy nie ma uczestnika)
                $firstName = $lastParticipant ? ($lastParticipant->first_name ?? '') : '';
                $lastName = $lastParticipant ? ($lastParticipant->last_name ?? '') : '';
                $courseDate = '';
                $courseId = '';

                if ($lastParticipant) {
                    if ($lastParticipant->start_date) {
                        // Konwertuj datę na format Y-m-d
                        $courseDate = is_string($lastParticipant->start_date)
                            ? date('Y-m-d', strtotime($lastParticipant->start_date))
                            : (is_object($lastParticipant->start_date)
                                ? $lastParticipant->start_date->format('Y-m-d')
                                : '');
                    }
                    $courseId = $lastParticipant->course_id ?? '';
                }

                // Dodaj wiersz do CSV
                $csvData[] = [
                    $firstName,                    // Name - imię
                    $emailRecord->email,           // Email
                    $lastName,                     // Sername - nazwisko
                    $courseDate,                   // data - data ostatniego szkolenia (RRRR-MM-DD)
                    $courseId,                      // id_szkolenia - ID ostatniego szkolenia
                ];
            }

            // Generuj plik CSV
            $filename = 'participants_export_'.date('Y-m-d_His').'.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            // Funkcja do generowania CSV z właściwym formatowaniem (z cudzysłowami)
            $callback = function () use ($csvData) {
                $file = fopen('php://output', 'w');

                // Dodaj BOM dla UTF-8 (żeby Excel poprawnie otwierał polskie znaki)
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                foreach ($csvData as $row) {
                    // Formatuj każdy wiersz z cudzysłowami i escapowaniem
                    fputcsv($file, $row, ',', '"');
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            \Log::error('Error exporting participants to CSV', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('participants.emails-list', $request->query())
                ->with('error', 'Błąd podczas eksportu do CSV: '.$e->getMessage());
        }
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
            $normalizeEmail = function ($email) {
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
            if (! empty($emailsToUpsert)) {
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
                $message = 'Nie znaleziono żadnych e-maili do przetworzenia.';
                $messageType = 'info';
            } elseif ($added == 0 && $updated == 0) {
                $message = 'Zaimportowano 0 rekordów. Wszystkie e-maile z tabeli participants już znajdują się w bazie participant_emails.';
                if ($skipped > 0) {
                    $message .= " Pominięto {$skipped} nieprawidłowych e-maili.";
                }
                $messageType = 'info';
            } else {
                $message = "Zebrano bazę e-mail. Dodano: {$added}, zaktualizowano: {$updated}";
                if ($skipped > 0) {
                    $message .= ", pominięto: {$skipped}";
                }
                $message .= '.';
                $messageType = 'success';
            }

            return redirect()->route('participants.all')->with($messageType, $message);
        } catch (\Exception $e) {
            \Log::error('Błąd podczas zbierania e-maili: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('participants.all')->with('error', 'Błąd podczas zbierania e-maili: '.$e->getMessage());
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
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('birth_place', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('notes', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->query('sort') === 'asc') {
            // Pobranie posortowanych uczestników
            $sortedParticipants = $query
                ->orderByRaw('CONVERT(last_name USING utf8mb4) COLLATE utf8mb4_polish_ci')
                ->orderByRaw('CONVERT(first_name USING utf8mb4) COLLATE utf8mb4_polish_ci')
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

        $pneduFrontendUrl = rtrim(config('services.pnedu_frontend_url', 'http://localhost:8081'), '/');
        $downloadTokensByEmail = [];
        foreach ($participants as $p) {
            $email = $p->email;
            if ($email !== null && $email !== '') {
                $norm = ParticipantDownloadToken::normalizeEmail($email);
                if (! isset($downloadTokensByEmail[$norm])) {
                    $downloadTokensByEmail[$norm] = ParticipantDownloadToken::getOrCreateTokenForEmail($email);
                }
            }
        }

        $certificatePdfGenerationCompletedAt = null;
        $cacheKey = 'certificate_pdf_generation_finished_'.$course->id;
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $certificatePdfGenerationCompletedAt = \Illuminate\Support\Facades\Cache::get($cacheKey);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $totalCertificates = Certificate::where('course_id', $course->id)->count();
        $downloadedCertificates = Certificate::where('course_id', $course->id)->where('download_count', '>', 0)->count();

        $participantIds = $participants->getCollection()->pluck('id')->filter()->values();
        $trainingPageViewsByParticipantId = [];
        if ($participantIds->isNotEmpty()) {
            $rows = DB::table('participant_training_page_views')
                ->whereIn('participant_id', $participantIds)
                ->get(['participant_id', 'open_count', 'first_opened_at', 'last_opened_at']);

            foreach ($rows as $r) {
                $trainingPageViewsByParticipantId[(int) $r->participant_id] = [
                    'open_count' => (int) ($r->open_count ?? 0),
                    'first_opened_at' => $r->first_opened_at ? Carbon::parse($r->first_opened_at) : null,
                    'last_opened_at' => $r->last_opened_at ? Carbon::parse($r->last_opened_at) : null,
                ];
            }
        }

        $courseAccessHasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $courseAccessHasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $courseAccessHasCertificate = ($course->certificate_download_status === 'download_enabled');
        $courseAccessCanSendEmail = $courseAccessHasVideos || $courseAccessHasMaterials || $courseAccessHasCertificate;

        $participantEmailsForLookup = $participants->getCollection()
            ->pluck('email')
            ->filter(fn ($e) => $e !== null && trim((string) $e) !== '')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->unique()
            ->values();

        $pneduAccountByEmail = [];
        if ($participantEmailsForLookup->isNotEmpty()) {
            $matched = PneduUser::query()
                ->selectRaw('LOWER(TRIM(email)) as email_norm')
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $participantEmailsForLookup->all())
                ->pluck('email_norm');
            $pneduAccountByEmail = array_fill_keys($matched->all(), true);
        }

        $courseParticipantsCount = Participant::query()->where('course_id', $course->id)->count();

        $participantsWithEmailCount = Participant::query()
            ->where('course_id', $course->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $courseEmailDeliveryStats = $this->buildCourseEmailDeliveryStats($course->id);
        $emailStatusByParticipantId = $this->buildEmailStatusByParticipantIds(
            $course->id,
            $participants->getCollection()->pluck('id')->filter()->values()
        );
        $courseAccessEmailLabel = $this->buildCourseAccessEmailLabel(
            $courseAccessHasVideos,
            $courseAccessHasMaterials,
            $courseAccessHasCertificate
        );

        $mailSystemConfig = SystemMailDiagnostics::currentConfig();

        $expiryReminderService = app(ParticipantAccessExpiryReminderService::class);
        $expiryReminderSchedule = $expiryReminderService->scheduleSummary();
        $accessExpiryReminderCanBulkSend = ($course->is_paid ?? false)
            && ($courseAccessHasVideos || $courseAccessHasMaterials);
        $accessExpiryReminderEligibleCount = 0;
        $accessExpiryReminderUnsentCount = 0;
        if ($accessExpiryReminderCanBulkSend) {
            $eligibleForReminder = $expiryReminderService->eligibleParticipantsForCourse(
                $course,
                $courseAccessHasVideos,
                $courseAccessHasMaterials
            );
            $accessExpiryReminderEligibleCount = $eligibleForReminder->count();
            $accessExpiryReminderUnsentCount = $eligibleForReminder->filter(function (Participant $p) use ($course) {
                return ! CertificateEmailLog::query()
                    ->where('participant_id', $p->id)
                    ->where('course_id', $course->id)
                    ->where('type', CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER)
                    ->where('status', CertificateEmailLog::STATUS_SENT)
                    ->exists();
            })->count();
        }
        $accessExpiryReminderEligibilityByParticipantId = [];
        foreach ($participants->getCollection() as $p) {
            $eligibility = $expiryReminderService->eligibilityForParticipant(
                $p,
                $course,
                $courseAccessHasVideos,
                $courseAccessHasMaterials
            );
            if ($eligibility['eligible']) {
                $eligibility['days_until'] = $expiryReminderService->daysUntilExpiry($p);
            }
            $accessExpiryReminderEligibilityByParticipantId[(int) $p->id] = $eligibility;
        }

        return view('participants.index', compact(
            'participants',
            'course',
            'pneduFrontendUrl',
            'downloadTokensByEmail',
            'certificatePdfGenerationCompletedAt',
            'totalCertificates',
            'downloadedCertificates',
            'trainingPageViewsByParticipantId',
            'courseAccessHasVideos',
            'courseAccessHasMaterials',
            'courseAccessHasCertificate',
            'courseAccessCanSendEmail',
            'pneduAccountByEmail',
            'courseParticipantsCount',
            'participantsWithEmailCount',
            'courseEmailDeliveryStats',
            'emailStatusByParticipantId',
            'courseAccessEmailLabel',
            'mailSystemConfig',
            'expiryReminderSchedule',
            'accessExpiryReminderCanBulkSend',
            'accessExpiryReminderEligibleCount',
            'accessExpiryReminderUnsentCount',
            'accessExpiryReminderEligibilityByParticipantId'
        ));
    }

    /**
     * Ustawia tę samą datę wygaśnięcia dostępu (lub usuwa ją) dla wszystkich uczestników kursu.
     */
    public function bulkSetAccessExpires(Request $request, Course $course)
    {
        $clear = $request->boolean('clear_access_expires');

        $request->validate([
            'access_expires_at' => [$clear ? 'nullable' : 'required', 'date'],
            'clear_access_expires' => 'sometimes|boolean',
        ], [
            'access_expires_at.required' => 'Podaj datę i godzinę wygaśnięcia dostępu albo zaznacz „dostęp bezterminowy”.',
        ]);

        $query = Participant::query()->where('course_id', $course->id);

        if ($clear) {
            $count = (clone $query)->update(['access_expires_at' => null]);

            return redirect()->route('participants.index', $course)->with(
                'success',
                "Usunięto datę wygaśnięcia dostępu dla {$count} uczestników (dostęp bezterminowy)."
            );
        }

        $localTime = Carbon::createFromFormat(
            'Y-m-d\TH:i',
            (string) $request->input('access_expires_at'),
            'Europe/Warsaw'
        );

        $count = (clone $query)->update(['access_expires_at' => $localTime]);

        $formatted = $localTime->copy()->timezone('Europe/Warsaw')->format('d.m.Y H:i');

        return redirect()->route('participants.index', $course)->with(
            'success',
            "Ustawiono datę wygaśnięcia dostępu ({$formatted}) dla {$count} uczestników tego szkolenia."
        );
    }

    /**
     * Wysyła e-mail z linkiem do listy zaświadczeń (pnedu) na adres uczestnika.
     */
    public function sendCertificateLink(Course $course, Participant $participant)
    {
        if ((int) $participant->course_id !== (int) $course->id) {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie należy do tego kursu.');
        }

        $email = $participant->email;
        if ($email === null || trim($email) === '') {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie ma podanego adresu e-mail.');
        }

        $createdBy = Auth::id();

        try {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_LIST_LINK,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
            ]);

            SendCertificateLinkEmailJob::dispatchSync(
                $course->id,
                $participant->id,
                CertificateEmailLog::TYPE_LIST_LINK,
                $log->id
            );
        } catch (\Throwable $e) {
            return redirect()->route('participants.index', $course)->with('error', 'Nie udało się wysłać e-maila: '.$e->getMessage());
        }

        return redirect()->route('participants.index', $course)->with('success', 'E-mail z linkiem do zaświadczeń został wysłany na adres '.$email);
    }

    /**
     * Wysyła e-mail z linkiem do konkretnego zaświadczenia (pnedu) dla jednego uczestnika.
     */
    public function sendSingleCertificateLink(Course $course, Participant $participant)
    {
        if ((int) $participant->course_id !== (int) $course->id) {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie należy do tego kursu.');
        }

        $email = $participant->email;
        if ($email === null || trim($email) === '') {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie ma podanego adresu e-mail.');
        }

        $createdBy = Auth::id();

        try {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_SINGLE_CERTIFICATE,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
            ]);

            SendCertificateLinkEmailJob::dispatchSync(
                $course->id,
                $participant->id,
                CertificateEmailLog::TYPE_SINGLE_CERTIFICATE,
                $log->id
            );
        } catch (\Throwable $e) {
            return redirect()->route('participants.index', $course)->with('error', 'Nie udało się wysłać e-maila: '.$e->getMessage());
        }

        return redirect()->route('participants.index', $course)->with('success', 'E-mail z linkiem do tego zaświadczenia został wysłany na adres '.$email);
    }

    /**
     * Wysyła e-mail o dostępnych zasobach kursu na pnedu.pl (wymaga logowania).
     */
    public function sendCourseAccessEmail(Course $course, Participant $participant)
    {
        if ((int) $participant->course_id !== (int) $course->id) {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie należy do tego kursu.');
        }

        $email = $participant->email;
        if ($email === null || trim($email) === '') {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie ma podanego adresu e-mail.');
        }

        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $hasCertificate = ($course->certificate_download_status === 'download_enabled');

        if (! $hasVideos && ! $hasMaterials && ! $hasCertificate) {
            return redirect()->route('participants.index', $course)->with(
                'info',
                'Nie wysłano e-maila: dla tego szkolenia nie ma nagrań, materiałów ani aktywnego zaświadczenia.'
            );
        }

        $createdBy = Auth::id();

        try {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_COURSE_ACCESS,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
                'meta' => [
                    'has_videos' => $hasVideos,
                    'has_materials' => $hasMaterials,
                    'has_certificate' => $hasCertificate,
                ],
            ]);

            // Pojedyncza wysyłka synchronicznie — działa na dev bez queue:work; natychmiastowy błąd SMTP/BD zamiast „zlecenia w próżnię”.
            SendCourseAccessEmailJob::dispatchSync(
                $course->id,
                $participant->id,
                $log->id
            );
        } catch (\Throwable $e) {
            return redirect()->route('participants.index', $course)->with('error', 'Nie udało się wysłać e-maila: '.$e->getMessage());
        }

        return redirect()->route('participants.index', $course)->with('success', 'E-mail o dostępie do szkolenia został wysłany na adres '.$email);
    }

    /**
     * Ręczne wysłanie przypomnienia o zbliżającym się wygaśnięciu dostępu (nagrania / materiały).
     */
    public function sendAccessExpiryReminder(Course $course, Participant $participant)
    {
        if ((int) $participant->course_id !== (int) $course->id) {
            return redirect()->route('participants.index', $course)->with('error', 'Uczestnik nie należy do tego kursu.');
        }

        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $reminderService = app(ParticipantAccessExpiryReminderService::class);
        $eligibility = $reminderService->eligibilityForParticipant($participant, $course, $hasVideos, $hasMaterials);

        if (! $eligibility['eligible']) {
            return redirect()->route('participants.index', $course)->with(
                'error',
                'Nie wysłano przypomnienia: '.($eligibility['reason'] ?? 'Uczestnik nie spełnia warunków.')
            );
        }

        $email = trim((string) $participant->email);
        $daysBefore = $reminderService->daysUntilExpiry($participant);
        if ($daysBefore === null) {
            return redirect()->route('participants.index', $course)->with('error', 'Nie wysłano przypomnienia: brak aktywnej daty wygaśnięcia dostępu.');
        }

        $createdBy = Auth::id();

        try {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
                'meta' => [
                    'days_before' => $daysBefore,
                    'manual' => true,
                    'triggered_at' => now()->toIso8601String(),
                ],
            ]);

            SendAccessExpiryReminderEmailJob::dispatchSync(
                (int) $course->id,
                (int) $participant->id,
                $daysBefore,
                (int) $log->id
            );
        } catch (\Throwable $e) {
            return redirect()->route('participants.index', $course)->with('error', 'Nie udało się wysłać przypomnienia: '.$e->getMessage());
        }

        return redirect()->route('participants.index', $course)->with(
            'success',
            'Wysłano przypomnienie o wygaśnięciu dostępu na adres '.$email.'.'
        );
    }

    /**
     * Masowa wysyłka przypomnień o zbliżającym się wygaśnięciu dostępu (nagrania / materiały).
     */
    public function sendAccessExpiryRemindersBulk(Request $request, Course $course)
    {
        $request->validate([
            'mode' => 'required|string|in:eligible,unsent',
        ]);

        $mode = $request->string('mode')->toString();
        $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
        $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
        $reminderService = app(ParticipantAccessExpiryReminderService::class);

        if (! $course->is_paid) {
            return redirect()->route('participants.index', $course)->with(
                'error',
                'Nie wysłano przypomnień: automatyczne i ręczne przypomnienia dotyczą tylko płatnych szkoleń.'
            );
        }

        if (! $hasVideos && ! $hasMaterials) {
            return redirect()->route('participants.index', $course)->with(
                'info',
                'Nie wysłano przypomnień: brak nagrań i materiałów do przypomnienia.'
            );
        }

        $participants = $reminderService->eligibleParticipantsForCourse($course, $hasVideos, $hasMaterials);

        if ($mode === 'unsent') {
            $participants = $participants->filter(function (Participant $participant) use ($course) {
                return ! CertificateEmailLog::query()
                    ->where('participant_id', $participant->id)
                    ->where('course_id', $course->id)
                    ->where('type', CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER)
                    ->where('status', CertificateEmailLog::STATUS_SENT)
                    ->exists();
            })->values();
        }

        if ($participants->isEmpty()) {
            return redirect()->route('participants.index', $course)->with(
                'info',
                $mode === 'unsent'
                    ? 'Brak uczestników kwalifikujących się do przypomnienia, do których jeszcze nie wysłano.'
                    : 'Brak uczestników spełniających warunki wysyłki przypomnienia (e-mail, aktywna data wygaśnięcia dostępu).'
            );
        }

        $createdBy = Auth::id();
        $jobs = [];
        $logIds = [];

        foreach ($participants as $participant) {
            $daysBefore = $reminderService->daysUntilExpiry($participant);
            if ($daysBefore === null) {
                continue;
            }

            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
                'meta' => [
                    'days_before' => $daysBefore,
                    'manual' => true,
                    'bulk' => true,
                    'triggered_at' => now()->toIso8601String(),
                ],
            ]);
            $logIds[] = $log->id;

            $jobs[] = new SendAccessExpiryReminderEmailJob(
                (int) $course->id,
                (int) $participant->id,
                $daysBefore,
                (int) $log->id
            );
        }

        if ($jobs === []) {
            return redirect()->route('participants.index', $course)->with(
                'info',
                'Brak uczestników spełniających warunki wysyłki przypomnienia.'
            );
        }

        try {
            $batch = Bus::batch($jobs)
                ->name($this->emailBatchName($course, CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER))
                ->dispatch();

            CertificateEmailLog::whereIn('id', $logIds)->update(['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            CertificateEmailLog::whereIn('id', $logIds)->update([
                'status' => CertificateEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->route('participants.index', $course)->with(
                'error',
                'Nie udało się zlecić zbiorczej wysyłki przypomnień: '.$e->getMessage()
            );
        }

        return redirect()->route('participants.index', $course)->with(
            'success',
            'Zlecono wysyłkę '.$participants->count().' przypomnień o wygaśnięciu dostępu. Wysyłka odbywa się w tle (wymaga działającego workera kolejki).'
        );
    }

    /**
     * Masowa wysyłka e-maili (kolejka).
     * Typy: list_link | single_certificate | course_access
     * Tryby:
     * - list_link/single_certificate: unsent | resend_all | not_downloaded
     * - course_access: unsent | resend_all | not_opened
     */
    public function sendCertificateLinksBulk(Request $request, Course $course)
    {
        $request->validate([
            'type' => 'required|string|in:'.CertificateEmailLog::TYPE_LIST_LINK.','.CertificateEmailLog::TYPE_SINGLE_CERTIFICATE.','.CertificateEmailLog::TYPE_COURSE_ACCESS,
            'mode' => 'required|string|in:unsent,resend_all,not_downloaded,not_opened',
        ]);

        $type = $request->string('type')->toString();
        $mode = $request->string('mode')->toString();
        $createdBy = Auth::id();

        if ($type === CertificateEmailLog::TYPE_COURSE_ACCESS && ! in_array($mode, ['unsent', 'resend_all', 'not_opened'], true)) {
            return redirect()->route('participants.index', $course)->with('error', 'Nieprawidłowy tryb wysyłki dla e-maila nagraniowego.');
        }

        if (in_array($type, [CertificateEmailLog::TYPE_LIST_LINK, CertificateEmailLog::TYPE_SINGLE_CERTIFICATE], true)
            && ! in_array($mode, ['unsent', 'resend_all', 'not_downloaded'], true)) {
            return redirect()->route('participants.index', $course)->with('error', 'Nieprawidłowy tryb wysyłki.');
        }

        $participantsQuery = Participant::query()
            ->where('participants.course_id', $course->id)
            ->whereNotNull('participants.email')
            ->where('participants.email', '!=', '');

        if ($mode === 'unsent') {
            $participantsQuery->whereNotExists(function ($q) use ($course, $type) {
                $q->selectRaw('1')
                    ->from('certificate_email_logs')
                    ->whereColumn('certificate_email_logs.participant_id', 'participants.id')
                    ->where('certificate_email_logs.course_id', $course->id)
                    ->where('certificate_email_logs.type', $type)
                    ->where('certificate_email_logs.status', CertificateEmailLog::STATUS_SENT);
            });
        } elseif ($mode === 'not_downloaded') {
            $participantsQuery->leftJoin('certificates', function ($join) use ($course) {
                $join->on('certificates.participant_id', '=', 'participants.id')
                    ->where('certificates.course_id', '=', $course->id);
            })->whereRaw('COALESCE(certificates.download_count, 0) = 0')
                ->select('participants.*');
        } elseif ($mode === 'not_opened') {
            $participantsQuery->leftJoin('participant_training_page_views as tpv', function ($join) {
                $join->on('tpv.participant_id', '=', 'participants.id');
            })->whereRaw('COALESCE(tpv.open_count, 0) = 0')
                ->select('participants.*');
        }

        if ($type === CertificateEmailLog::TYPE_COURSE_ACCESS) {
            $hasVideos = CourseVideo::query()->where('course_id', $course->id)->exists();
            $hasMaterials = CourseFileLink::query()->where('course_id', $course->id)->exists();
            $hasCertificate = ($course->certificate_download_status === 'download_enabled');

            if (! $hasVideos && ! $hasMaterials && ! $hasCertificate) {
                return redirect()->route('participants.index', $course)->with(
                    'info',
                    'Nie wysłano e-maili: dla tego szkolenia nie ma nagrań, materiałów ani aktywnego zaświadczenia.'
                );
            }
        }

        $participants = $participantsQuery->get();
        if ($participants->isEmpty()) {
            return redirect()->route('participants.index', $course)->with('info', 'Brak uczestników spełniających warunki wysyłki.');
        }

        // Utwórz logi (queued) i joby
        $jobs = [];
        $logIds = [];
        foreach ($participants as $participant) {
            $log = CertificateEmailLog::create([
                'course_id' => $course->id,
                'participant_id' => $participant->id,
                'type' => $type,
                'status' => CertificateEmailLog::STATUS_QUEUED,
                'created_by' => $createdBy,
                'queued_at' => now(),
            ]);
            $logIds[] = $log->id;

            if ($type === CertificateEmailLog::TYPE_COURSE_ACCESS) {
                $jobs[] = new SendCourseAccessEmailJob(
                    $course->id,
                    $participant->id,
                    $log->id
                );
            } else {
                $jobs[] = new SendCertificateLinkEmailJob(
                    $course->id,
                    $participant->id,
                    $type,
                    $log->id
                );
            }
        }

        $batch = Bus::batch($jobs)
            ->name($this->emailBatchName($course, $type))
            ->dispatch();

        CertificateEmailLog::whereIn('id', $logIds)->update(['batch_id' => $batch->id]);

        return redirect()->route('participants.index', $course)->with(
            'success',
            "Zlecono wysyłkę {$participants->count()} e-maili ({$type}). Wysyłka odbywa się w tle."
        );
    }

    /**
     * Status/progress aktywnej wysyłki e-maili (batch) dla kursu i typu.
     */
    public function certificateEmailBatchStatus(Request $request, Course $course)
    {
        $request->validate([
            'type' => 'required|string|in:'.implode(',', $this->certificateEmailBatchTypes()),
        ]);

        $type = $request->string('type')->toString();
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');
        $name = $this->emailBatchName($course, $type);

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $name)
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return response()->json([
                'active' => false,
                'state' => 'none',
                'type' => $type,
                'total' => 0,
                'processed' => 0,
                'pending' => 0,
                'failed' => 0,
            ]);
        }

        $total = (int) ($row->total_jobs ?? 0);
        $pending = (int) ($row->pending_jobs ?? 0);
        $failed = (int) ($row->failed_jobs ?? 0);
        $processed = max(0, $total - $pending);

        $state = 'active';
        $active = true;
        if (! empty($row->cancelled_at)) {
            $state = 'cancelled';
            $active = false;
        } elseif (! empty($row->finished_at)) {
            $state = 'finished';
            $active = false;
        }

        return response()->json([
            'active' => $active,
            'state' => $state,
            'batch_id' => $row->id,
            'type' => $type,
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'failed' => $failed,
        ]);
    }

    /**
     * Anuluje aktywną wysyłkę e-maili (batch) dla kursu i typu.
     */
    public function cancelCertificateEmailBatch(Request $request, Course $course)
    {
        $request->validate([
            'type' => 'required|string|in:'.implode(',', $this->certificateEmailBatchTypes()),
        ]);

        $type = $request->string('type')->toString();
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');
        $name = $this->emailBatchName($course, $type);

        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $name)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Brak aktywnej wysyłki.'], 404);
        }

        $batch = Bus::findBatch($row->id);
        if ($batch && ! $batch->cancelled()) {
            $batch->cancel();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Formularz dodawania nowego uczestnika do kursu.
     */
    public function create(Course $course)
    {
        $course->loadMissing('instructor');
        $defaultAccessExpiresAt = app(ParticipantAccessExpiryService::class)
            ->defaultExpiresAtFromCourseEnd($course);

        return view('participants.create', compact('course', 'defaultAccessExpiresAt'));
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
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:10000',
            'access_expires_at' => 'nullable|date',
        ]);

        $data = $request->all();

        // Obsługa daty wygaśnięcia dostępu
        if (! empty($data['access_expires_at'])) {
            // Input datetime-local zwraca format: YYYY-MM-DDTHH:MM
            // Traktujemy wprowadzony czas jako czas lokalny (Europe/Warsaw)
            $localTime = Carbon::createFromFormat('Y-m-d\TH:i', $data['access_expires_at'], 'Europe/Warsaw');
            // NIE konwertujemy na UTC - Laravel zrobi to automatycznie
            $data['access_expires_at'] = $localTime;
        }

        $this->assertNoDuplicateEmailInCourse($course, $request->input('email'));

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
        $normalizedEmailAnchor = Participant::normalizeEmail($participant->email);

        $duplicateParticipants = collect();
        if ($normalizedEmailAnchor !== null) {
            $duplicateParticipants = Participant::query()
                ->with(['course:id,title,start_date'])
                ->whereKeyNot($participant->id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmailAnchor])
                ->orderByDesc('updated_at')
                ->get();
        }

        $duplicateNameMismatchAmongOthers = false;
        if ($duplicateParticipants->isNotEmpty()) {
            $selfFn = Participant::normalizeNamePart($participant->first_name);
            $selfLn = Participant::normalizeNamePart($participant->last_name);
            foreach ($duplicateParticipants as $other) {
                if (Participant::normalizeNamePart($other->first_name) !== $selfFn
                    || Participant::normalizeNamePart($other->last_name) !== $selfLn
                ) {
                    $duplicateNameMismatchAmongOthers = true;
                    break;
                }
            }
        }

        $course->loadMissing('instructor');

        return view('participants.edit', compact(
            'course',
            'participant',
            'duplicateParticipants',
            'duplicateNameMismatchAmongOthers'
        ));
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
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:10000',
            'access_expires_at' => 'nullable|date',
            'sync_duplicate_email_profiles' => 'sometimes|boolean',
            'sync_duplicate_email_confirm_mismatch' => 'sometimes|boolean',
        ]);

        $anchorNormalized = Participant::normalizeEmail($participant->email);
        $wantsProfileSync = $request->boolean('sync_duplicate_email_profiles');

        if ($wantsProfileSync && $anchorNormalized === null) {
            throw ValidationException::withMessages([
                'sync_duplicate_email_profiles' => 'Synchronizacja jest możliwa tylko gdy uczestnik ma zapisany adres e-mail (przed zapisem).',
            ]);
        }

        $otherIdsForSync = collect();
        if ($wantsProfileSync && $anchorNormalized !== null) {
            $otherIdsForSync = Participant::query()
                ->whereKeyNot($participant->id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$anchorNormalized])
                ->pluck('id');

            if ($otherIdsForSync->isNotEmpty()) {
                $reqFn = Participant::normalizeNamePart($request->input('first_name'));
                $reqLn = Participant::normalizeNamePart($request->input('last_name'));
                $hasNameMismatch = Participant::query()
                    ->whereIn('id', $otherIdsForSync)
                    ->get(['id', 'first_name', 'last_name'])
                    ->contains(fn (Participant $other) => Participant::normalizeNamePart($other->first_name) !== $reqFn
                        || Participant::normalizeNamePart($other->last_name) !== $reqLn);

                if ($hasNameMismatch && ! $request->boolean('sync_duplicate_email_confirm_mismatch')) {
                    throw ValidationException::withMessages([
                        'sync_duplicate_email_confirm_mismatch' => 'Inni uczestnicy z tym e-mailem mają inne imię lub nazwisko — zaznacz potwierdzenie poniżej, aby mimo to zsynchronizować pola profilowe we wszystkich rekordach.',
                    ]);
                }
            }
        }

        $data = $request->except(['sync_duplicate_email_profiles', 'sync_duplicate_email_confirm_mismatch']);

        // Obsługa daty wygaśnięcia dostępu
        if (! empty($data['access_expires_at'])) {
            // Input datetime-local zwraca format: YYYY-MM-DDTHH:MM
            // Traktujemy wprowadzony czas jako czas lokalny (Europe/Warsaw)
            $localTime = Carbon::createFromFormat('Y-m-d\TH:i', $data['access_expires_at'], 'Europe/Warsaw');
            // NIE konwertujemy na UTC - Laravel zrobi to automatycznie
            $data['access_expires_at'] = $localTime;
        }

        $this->assertNoDuplicateEmailInCourse($course, $request->input('email'), $participant);

        $profilePayload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => ! empty($data['email']) ? $data['email'] : null,
            'phone' => ! empty($data['phone']) ? $data['phone'] : null,
            'birth_date' => ! empty($data['birth_date']) ? $data['birth_date'] : null,
            'birth_place' => ! empty($data['birth_place']) ? $data['birth_place'] : null,
        ];

        $syncedCount = 0;
        DB::transaction(function () use ($participant, $data, $wantsProfileSync, $otherIdsForSync, $profilePayload, &$syncedCount) {
            $participant->update($data);

            if ($wantsProfileSync && $otherIdsForSync->isNotEmpty()) {
                $rows = Participant::query()->whereIn('id', $otherIdsForSync)->get();
                foreach ($rows as $row) {
                    $row->update($profilePayload);
                }
                $syncedCount = $rows->count();
            }
        });

        $message = 'Uczestnik zaktualizowany.';
        if ($wantsProfileSync && $syncedCount > 0) {
            $message .= sprintf(
                ' Zsynchronizowano dane profilowe (imię, nazwisko, e-mail, telefon, data i miejsce urodzenia) w %d %s.',
                $syncedCount,
                $syncedCount === 1 ? 'innym rekordzie' : 'innych rekordach'
            );
        }

        return redirect()->route('participants.index', $course)->with('success', $message);
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

            // Pomiń nagłówek i znormalizuj nazwy kolumn (małe litery, bez cudzysłowów)
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new \RuntimeException('Plik CSV jest pusty lub nieczytelny.');
            }

            $normalizedHeader = array_map(function ($column) {
                return Str::of($column)
                    ->lower()
                    ->replace('"', '')
                    ->trim()
                    ->toString();
            }, $header);

            while (($row = fgetcsv($handle)) !== false) {
                try {
                    if (count($row) !== count($normalizedHeader)) {
                        $errors[] = 'Błąd w wierszu: '.implode(',', $row).' - Nieprawidłowa liczba kolumn.';
                        $skippedCount++;

                        continue;
                    }

                    // Mapowanie kolumn CSV do ujednoliconych kluczy
                    $csvData = array_combine($normalizedHeader, $row);
                    if ($csvData === false) {
                        $errors[] = 'Błąd w wierszu: '.implode(',', $row).' - Nie udało się sparsować wiersza.';
                        $skippedCount++;

                        continue;
                    }

                    // Obsługa różnych nazw kolumn z eksportu Publigo
                    $email = $csvData['e-mail uczestnika'] ?? $csvData['email'] ?? null;
                    if (empty($email)) {
                        throw new \RuntimeException('Brak kolumny E-mail uczestnika/Email lub pusty email.');
                    }

                    // Sprawdź czy uczestnik już istnieje
                    $existingParticipant = Participant::where('email', $email)
                        ->where('course_id', $course->id)
                        ->first();

                    if ($existingParticipant) {
                        $skippedCount++;

                        continue;
                    }

                    // Parsowanie imienia i nazwiska
                    $fullName = trim(
                        $csvData['imię i nazwisko'] ?? $csvData['imie i nazwisko'] ?? $csvData['name'] ?? '',
                        '"'
                    );
                    $nameParts = explode(' ', $fullName, 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';

                    // Pobierz wcześniej zapisane dane urodzenia dla tego e-maila (jeśli istnieją)
                    $birthDate = null;
                    $birthPlace = null;
                    $existingProfile = Participant::where('email', $email)
                        ->where(function ($q) {
                            $q->whereNotNull('birth_date')
                                ->orWhereNotNull('birth_place');
                        })
                        ->orderByDesc('updated_at')
                        ->first();

                    if ($existingProfile) {
                        $birthDate = $existingProfile->birth_date;
                        $birthPlace = $existingProfile->birth_place;
                    }

                    // Parsowanie daty wygaśnięcia dostępu
                    $accessExpiresAt = null;
                    $expiresDateRaw = $csvData['dostęp wygasa'] ?? $csvData['dostep wygasa'] ?? null;
                    if (! empty($expiresDateRaw)) {
                        $expiresDate = trim($expiresDateRaw, '"');
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
                    } elseif (! empty($course->start_date)) {
                        // Domyślnie: 2 miesiące od daty rozpoczęcia szkolenia
                        $accessExpiresAt = $course->start_date->copy()->addMonthsNoOverflow(2);
                    }

                    $phoneRaw = $csvData['telefon'] ?? $csvData['telephone'] ?? $csvData['phone'] ?? $csvData['numer telefonu'] ?? $csvData['nr telefonu'] ?? null;
                    $phone = null;
                    if (! empty($phoneRaw)) {
                        $phone = mb_substr(trim($phoneRaw, "\" \t"), 0, 50) ?: null;
                    }

                    $notesRaw = $csvData['notatki'] ?? $csvData['notes'] ?? $csvData['uwagi'] ?? null;
                    $notes = null;
                    if (! empty($notesRaw)) {
                        $notesTrimmed = trim($notesRaw, "\" \t");
                        $notes = mb_substr($notesTrimmed, 0, 10000) ?: null;
                    }

                    // Pobranie ostatniego numeru porządkowego
                    $lastOrder = $course->participants()->max('order') ?? 0;

                    // Tworzenie uczestnika
                    Participant::create([
                        'course_id' => $course->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'birth_date' => $birthDate,
                        'birth_place' => $birthPlace,
                        'phone' => $phone,
                        'notes' => $notes,
                        'access_expires_at' => $accessExpiresAt,
                        'order' => $lastOrder + 1,
                    ]);

                    $importedCount++;

                } catch (\Exception $e) {
                    $errors[] = 'Błąd w wierszu: '.implode(',', $row).' - '.$e->getMessage();
                    $skippedCount++;
                }
            }

            fclose($handle);

            $message = "Zaimportowano {$importedCount} uczestników.";
            if ($skippedCount > 0) {
                $message .= " Pominięto {$skippedCount} uczestników (już istnieją lub błąd).";
            }
            if (! empty($errors)) {
                $message .= ' Błędy: '.implode('; ', array_slice($errors, 0, 5));
            }

            return redirect()->route('participants.index', $course)
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->route('participants.index', $course)
                ->with('error', 'Błąd podczas importu: '.$e->getMessage());
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
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('birth_place', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('notes', 'LIKE', "%{$searchTerm}%");
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
            'totalCount' => $participants->count(),
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
        $searchInfo = $request->filled('search') ? '_WYSZUKIWANIE_'.Str::slug($request->get('search')) : '';

        $fileName = $courseDate.'_Lista_uczestnikow_'.$courseTitle.$searchInfo.'.pdf';
        $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

        return $pdf->download($fileName);
    }

    /**
     * Generowanie PDF z rejestrem zaświadczeń
     */
    public function downloadCertificateRegistry(Request $request, Course $course)
    {
        // Ta sama kolejność co przy ?sort_certificate=asc: numerycznie wg pierwszego segmentu numeru (np. 1,2,…10,11),
        // brak numeru na końcu, potem stabilnie wg participants.order.
        $numericExpr = "CAST(IFNULL(NULLIF(REGEXP_SUBSTR(certificates.certificate_number, '[0-9]+'), ''), '0') AS UNSIGNED)";

        $query = Participant::where('participants.course_id', $course->id)
            ->whereNotNull('participants.first_name')->where('participants.first_name', '!=', '')
            ->whereNotNull('participants.last_name')->where('participants.last_name', '!=', '')
            ->whereNotNull('participants.birth_date')
            ->whereNotNull('participants.birth_place')->where('participants.birth_place', '!=', '');

        $participants = $query->with('certificate')
            ->leftJoin('certificates', function ($join) use ($course) {
                $join->on('certificates.participant_id', '=', 'participants.id')
                    ->where('certificates.course_id', $course->id);
            })
            ->select('participants.*')
            ->orderByRaw("CASE WHEN certificates.certificate_number IS NULL OR certificates.certificate_number = '' THEN 1 ELSE 0 END")
            ->orderByRaw("{$numericExpr} ASC")
            ->orderBy('participants.order')
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
        $fileName = 'Rejestr_zaswiadczen_'.$courseDate.'_'.$courseTitle.'.pdf';
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
            $queryParams = array_filter($queryParams, function ($value) {
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
            $queryParams = array_filter($queryParams, function ($value) {
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

                // 3. Aktualizuj form_orders (tylko orderer_email — e-mail uczestnika jest w form_order_participants)
                $formOrdersUpdated = (int) DB::table('form_orders')
                    ->whereIn('orderer_email', $oldEmailsToSearch)
                    ->update(['orderer_email' => $newEmail, 'updated_at' => now()]);

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
                if (! $existingEmailRecord->first_participant_id && $participantEmail->first_participant_id) {
                    $existingEmailRecord->first_participant_id = $participantEmail->first_participant_id;
                }
                // Zachowaj lepsze wartości dla is_verified i is_active
                if (! $existingEmailRecord->is_verified && $participantEmail->is_verified) {
                    $existingEmailRecord->is_verified = true;
                }
                if (! $existingEmailRecord->is_active && $participantEmail->is_active) {
                    $existingEmailRecord->is_active = true;
                }
                // Scal notatki jeśli istnieją
                if ($participantEmail->notes && ! $existingEmailRecord->notes) {
                    $existingEmailRecord->notes = $participantEmail->notes;
                } elseif ($participantEmail->notes && $existingEmailRecord->notes) {
                    $existingEmailRecord->notes = $existingEmailRecord->notes."\n\n[Merged from: {$oldEmail}]\n".$participantEmail->notes;
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

                // 3. Aktualizuj form_orders (tylko orderer_email)
                $formOrdersUpdated = (int) DB::table('form_orders')
                    ->whereIn('orderer_email', $oldEmailsToSearch)
                    ->update(['orderer_email' => $newEmail, 'updated_at' => now()]);

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
            $queryParams = array_filter($queryParams, function ($value) {
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
            $queryParams = array_filter($queryParams, function ($value) {
                return $value !== null && $value !== '';
            });

            return redirect()->route('participants.emails-list', $queryParams)
                ->with('error', 'Błąd podczas aktualizacji e-maila: '.$e->getMessage())
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
            $queryParams = array_filter($queryParams, function ($value) {
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
            $queryParams = array_filter($queryParams, function ($value) {
                return $value !== null && $value !== '';
            });

            return redirect()->route('participants.emails-list', $queryParams)
                ->with('error', 'Błąd podczas usuwania e-maila: '.$e->getMessage());
        }
    }

    /**
     * Statystyki wysyłki e-maili dla całego szkolenia (niezależnie od paginacji / wyszukiwania).
     *
     * @return array<string, array{sent: int, queued: int, failed_without_sent: int}>
     */
    private function buildCourseEmailDeliveryStats(int $courseId): array
    {
        return [
            CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK => $this->buildCourseEmailDeliveryStatsForTypes(
                $courseId,
                CertificateEmailLog::certificateLinkTypes()
            ),
            CertificateEmailLog::TYPE_COURSE_ACCESS => $this->buildCourseEmailDeliveryStatsForTypes(
                $courseId,
                [CertificateEmailLog::TYPE_COURSE_ACCESS]
            ),
            CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER => $this->buildCourseEmailDeliveryStatsForTypes(
                $courseId,
                [CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER]
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function certificateEmailBatchTypes(): array
    {
        return [
            CertificateEmailLog::TYPE_LIST_LINK,
            CertificateEmailLog::TYPE_SINGLE_CERTIFICATE,
            CertificateEmailLog::TYPE_COURSE_ACCESS,
            CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
        ];
    }

    private function emailBatchName(Course $course, string $type): string
    {
        return "certificate-emails-{$type}-course-{$course->id}";
    }

    /**
     * @param  list<string>  $types
     * @return array{sent: int, queued: int, failed_without_sent: int}
     */
    private function buildCourseEmailDeliveryStatsForTypes(int $courseId, array $types): array
    {
        $base = CertificateEmailLog::query()
            ->where('course_id', $courseId)
            ->whereIn('type', $types);

        return [
            'sent' => (int) (clone $base)
                ->where('status', CertificateEmailLog::STATUS_SENT)
                ->distinct()
                ->count('participant_id'),
            'queued' => (int) (clone $base)
                ->where('status', CertificateEmailLog::STATUS_QUEUED)
                ->distinct()
                ->count('participant_id'),
            'failed_without_sent' => (int) Participant::query()
                ->where('course_id', $courseId)
                ->whereExists(function ($q) use ($courseId, $types) {
                    $q->selectRaw('1')
                        ->from('certificate_email_logs')
                        ->whereColumn('certificate_email_logs.participant_id', 'participants.id')
                        ->where('certificate_email_logs.course_id', $courseId)
                        ->whereIn('certificate_email_logs.type', $types)
                        ->where('certificate_email_logs.status', CertificateEmailLog::STATUS_FAILED);
                })
                ->whereNotExists(function ($q) use ($courseId, $types) {
                    $q->selectRaw('1')
                        ->from('certificate_email_logs')
                        ->whereColumn('certificate_email_logs.participant_id', 'participants.id')
                        ->where('certificate_email_logs.course_id', $courseId)
                        ->whereIn('certificate_email_logs.type', $types)
                        ->where('certificate_email_logs.status', CertificateEmailLog::STATUS_SENT);
                })
                ->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string>  $participantIds
     * @return array<int, array<string, array{sent_count: int, last_sent_at: ?Carbon, has_queued: bool, has_failed: bool, last_error: ?string}>>
     */
    private function buildEmailStatusByParticipantIds(int $courseId, $participantIds): array
    {
        $participantIds = $participantIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($participantIds->isEmpty()) {
            return [];
        }

        $emptyType = [
            'sent_count' => 0,
            'last_sent_at' => null,
            'has_queued' => false,
            'has_failed' => false,
            'last_error' => null,
            'last_delivery' => null,
            'sent_without_real_delivery' => false,
        ];

        $byParticipant = [];
        foreach ($participantIds as $pid) {
            $byParticipant[$pid] = [
                CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK => $emptyType,
                CertificateEmailLog::TYPE_COURSE_ACCESS => $emptyType,
                CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER => $emptyType,
            ];
        }

        $logs = CertificateEmailLog::query()
            ->where('course_id', $courseId)
            ->whereIn('participant_id', $participantIds->all())
            ->whereIn('type', array_merge(
                CertificateEmailLog::certificateLinkTypes(),
                [
                    CertificateEmailLog::TYPE_COURSE_ACCESS,
                    CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER,
                ]
            ))
            ->orderBy('id')
            ->get(['participant_id', 'type', 'status', 'sent_at', 'failed_at', 'error_message', 'meta']);

        foreach ($logs as $log) {
            $pid = (int) $log->participant_id;
            $bucket = in_array($log->type, CertificateEmailLog::certificateLinkTypes(), true)
                ? CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK
                : $log->type;

            if (! isset($byParticipant[$pid][$bucket])) {
                continue;
            }

            $ref = &$byParticipant[$pid][$bucket];

            if ($log->status === CertificateEmailLog::STATUS_SENT) {
                $ref['sent_count']++;
                if ($log->sent_at !== null) {
                    $current = $ref['last_sent_at'];
                    if ($current === null || $log->sent_at->gt($current)) {
                        $ref['last_sent_at'] = $log->sent_at;
                        $delivery = SystemMailDiagnostics::deliveryMetaFromLog(is_array($log->meta) ? $log->meta : null);
                        $ref['last_delivery'] = $delivery;
                        $ref['sent_without_real_delivery'] = $delivery !== null
                            && ($delivery['real_delivery'] ?? true) === false;
                    }
                }
            } elseif ($log->status === CertificateEmailLog::STATUS_QUEUED) {
                $ref['has_queued'] = true;
            } elseif ($log->status === CertificateEmailLog::STATUS_FAILED) {
                $ref['has_failed'] = true;
                if ($log->error_message !== null && $log->error_message !== '') {
                    $ref['last_error'] = $log->error_message;
                }
            }
        }

        foreach ($byParticipant as $pid => $buckets) {
            $cert = $buckets[CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK];
            if ($cert['sent_count'] > 0) {
                $cert['has_queued'] = false;
                $cert['has_failed'] = false;
            } elseif ($cert['has_queued']) {
                $cert['has_failed'] = false;
            }
            $byParticipant[$pid][CertificateEmailLog::AGGREGATE_CERTIFICATE_LINK] = $cert;

            $access = $buckets[CertificateEmailLog::TYPE_COURSE_ACCESS];
            if ($access['sent_count'] > 0) {
                $access['has_queued'] = false;
                $access['has_failed'] = false;
            } elseif ($access['has_queued']) {
                $access['has_failed'] = false;
            }
            $byParticipant[$pid][CertificateEmailLog::TYPE_COURSE_ACCESS] = $access;

            $reminder = $buckets[CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER];
            if ($reminder['sent_count'] > 0) {
                $reminder['has_queued'] = false;
                $reminder['has_failed'] = false;
            } elseif ($reminder['has_queued']) {
                $reminder['has_failed'] = false;
            }
            $byParticipant[$pid][CertificateEmailLog::TYPE_ACCESS_EXPIRY_REMINDER] = $reminder;
        }

        return $byParticipant;
    }

    private function buildCourseAccessEmailLabel(bool $hasVideos, bool $hasMaterials, bool $hasCertificate): string
    {
        $parts = [];
        if ($hasVideos) {
            $parts[] = 'nagranie';
        }
        if ($hasMaterials) {
            $parts[] = 'materiały';
        }
        if ($hasCertificate) {
            $parts[] = 'zaświadczenie';
        }

        if ($parts === []) {
            return 'E-mail: dostęp do szkolenia';
        }

        return ucfirst(implode(', ', $parts));
    }

    /**
     * @throws ValidationException
     */
    private function assertNoDuplicateEmailInCourse(Course $course, ?string $email, ?Participant $except = null): void
    {
        $duplicate = Participant::findDuplicateInCourse(
            (int) $course->id,
            $email,
            $except?->id
        );

        if ($duplicate === null) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Uczestnik z adresem %s jest już zapisany na tym szkoleniu (%s %s).',
                trim((string) $email),
                $duplicate->first_name,
                $duplicate->last_name
            ),
        ]);
    }
}
