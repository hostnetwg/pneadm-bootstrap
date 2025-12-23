<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;
use App\Models\CertificateTemplate;
use App\Models\CourseSeries;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class CoursesController extends Controller
{
    /**
     * Wyświetlanie listy kursów
     */

     public function index(Request $request)
     {
         // Zwiększenie limitu czasu dla dużych zbiorów danych
         set_time_limit(120); // 2 minuty
         
         $query = Course::query();
     
        // Pobieranie listy instruktorów do widoku
        $instructors = Instructor::orderBy('last_name')->get();
        
        // Pobieranie listy serii do widoku
        $series = CourseSeries::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        
        // Pobieranie opcji dla source_id_old
        $sourceIdOldOptions = Course::whereNotNull('source_id_old')
                                  ->where('source_id_old', '!=', '')
                                  ->distinct()
                                  ->orderBy('source_id_old')
                                  ->pluck('source_id_old');
    
        // Pobieranie wartości filtra "date_filter"
        $dateFilter = $request->query('date_filter', 'upcoming');
        
        // Pobieranie opcji paginacji
        $perPage = $request->query('per_page', 10);
        $isAll = false;
        if ($perPage === 'all') {
            $perPage = 1000; // Limit dla "all"
            $isAll = true;
        } else {
            $perPage = (int) $perPage;
        }
    
        // Pobieranie wartości filtrów
        $filters = [
            'is_paid' => $request->input('is_paid'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'is_active' => $request->input('is_active'),
            'date_filter' => $dateFilter,
            'instructor_id' => $request->input('instructor_id'),
            'source_id_old' => $request->input('source_id_old'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'per_page' => $request->input('per_page', 10),
        ];
        
        // Filtracja według serii (relacja many-to-many) - użycie join dla lepszej wydajności
        if ($request->filled('course_series_id')) {
            $seriesId = $request->input('course_series_id');
            $query->join('course_series_course', 'courses.id', '=', 'course_series_course.course_id')
                  ->where('course_series_course.course_series_id', $seriesId)
                  ->select('courses.*') // Wybierz tylko kolumny z tabeli courses, aby uniknąć konfliktów
                  ->distinct(); // Unikaj duplikatów jeśli kurs jest w wielu seriach
        }
     
         // Określenie domyślnego sortowania
         $sortColumn = $request->query('sort', 'start_date');
         $sortDirection = $request->query('direction', ($dateFilter === 'upcoming' ? 'asc' : 'desc'));
     
        // Filtracja kursów według daty
        if ($dateFilter === 'upcoming') {
            $query->where('end_date', '>=', now());
        } elseif ($dateFilter === 'past') {
            $query->where('end_date', '<', now());
        }

        // Filtracja według zakresu dat
        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from') . ' 00:00:00';
            $query->where('start_date', '>=', $dateFrom);
        }
        
        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to') . ' 23:59:59';
            $query->where('end_date', '<=', $dateTo);
        }
     
        // Filtracja według pozostałych pól
        foreach ($filters as $key => $value) {
            if (!is_null($value) && $value !== '' && !in_array($key, ['date_filter', 'date_from', 'date_to', 'per_page'])) { // Pomijamy filtry dat i paginacji, bo już są przetwarzane powyżej
                $query->where($key, $value);
            }
        }
        
        // Obsługa wyszukiwania
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('instructor', function($instructorQuery) use ($searchTerm) {
                      $instructorQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('title', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('location', function($locationQuery) use ($searchTerm) {
                      $locationQuery->where('location_name', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('post_office', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('onlineDetails', function($onlineQuery) use ($searchTerm) {
                      $onlineQuery->where('platform', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }
        
        // Liczenie rekordów przed paginacją
        $filteredCount = $query->count();
        
        // Liczenie wszystkich rekordów w bazie (bez filtrów)
        $totalCount = Course::count();
    
        // Pobranie wyników z dynamicznym sortowaniem i paginacją
        // Ograniczony eager loading - dla "all" jeszcze bardziej ograniczony
        $eagerLoads = [
            'instructor:id,first_name,last_name,title',
            'location:id,course_id,location_name,address,postal_code,post_office',
            'onlineDetails:id,course_id,platform,meeting_link',
        ];
        
        // Dla większych zbiorów danych, ograniczamy relacje które są używane tylko do liczenia
        if (!$isAll || $filteredCount < 300) {
            $eagerLoads['participants'] = function($query) {
                $query->select('id', 'course_id', 'first_name', 'last_name', 'birth_date', 'birth_place');
            };
            $eagerLoads['certificates'] = function($query) {
                $query->select('id', 'course_id');
            };
            $eagerLoads['surveys'] = function($query) {
                $query->orderBy('id', 'desc')->limit(1)->select('id', 'course_id');
            };
            $eagerLoads['videos'] = function($query) {
                $query->select('id', 'course_id', 'video_url', 'platform', 'title', 'order')->orderBy('order');
            };
            $eagerLoads['priceVariants'] = function($query) {
                $query->where('is_active', true)->select('id', 'course_id', 'name', 'price', 'is_active', 'is_promotion', 'promotion_price', 'promotion_type', 'promotion_start', 'promotion_end');
            };
        }
        
        $courses = $query->with($eagerLoads)
                        ->orderBy($sortColumn, $sortDirection)
                        ->paginate($perPage)
                        ->appends($filters + ['sort' => $sortColumn, 'direction' => $sortDirection]);
        
        // Dla większych zbiorów, ładuj relacje do liczenia osobno (lazy loading)
        if ($isAll && $filteredCount >= 300) {
            $courses->getCollection()->loadCount(['participants', 'certificates']);
            $courses->getCollection()->load(['participants' => function($query) {
                $query->select('id', 'course_id', 'first_name', 'last_name', 'birth_date', 'birth_place');
            }]);
            $courses->getCollection()->load(['surveys' => function($query) {
                $query->orderBy('id', 'desc')->limit(1)->select('id', 'course_id');
            }]);
            $courses->getCollection()->load(['videos' => function($query) {
                $query->select('id', 'course_id', 'video_url', 'platform', 'title', 'order')->orderBy('order');
            }]);
            $courses->getCollection()->load(['priceVariants' => function($query) {
                $query->where('is_active', true)->select('id', 'course_id', 'name', 'price', 'is_active', 'is_promotion', 'promotion_price', 'promotion_type', 'promotion_start', 'promotion_end');
            }]);
        }

        // Dodanie liczby zamówień bez numeru faktury i ze statusem niezakończonym
        // Optymalizacja: pobierz wszystkie zamówienia jednym zapytaniem zamiast dla każdego kursu osobno
        $courseIdsWithPubligo = $courses->getCollection()
            ->filter(function($course) {
                return $course->source_id_old === 'certgen_Publigo' && $course->id_old;
            })
            ->pluck('id_old', 'id')
            ->toArray();
        
        if (!empty($courseIdsWithPubligo)) {
            $ordersCounts = DB::connection('mysql')
                ->table('form_orders')
                ->whereIn('publigo_product_id', array_values($courseIdsWithPubligo))
                ->whereNull('deleted_at')
                ->where(function($query) {
                    $query->whereNull('invoice_number')
                          ->orWhere('invoice_number', '')
                          ->orWhere('invoice_number', '0');
                })
                ->where(function($query) {
                    $query->whereNull('status_completed')
                          ->orWhere('status_completed', 0);
                })
                ->select('publigo_product_id', DB::raw('count(*) as count'))
                ->groupBy('publigo_product_id')
                ->pluck('count', 'publigo_product_id')
                ->toArray();
            
            // Przypisz liczniki do kursów
            $courses->getCollection()->transform(function($course) use ($courseIdsWithPubligo, $ordersCounts) {
                if (isset($courseIdsWithPubligo[$course->id])) {
                    $idOld = $courseIdsWithPubligo[$course->id];
                    $course->orders_count = $ordersCounts[$idOld] ?? 0;
                } else {
                    $course->orders_count = 0;
                }
                return $course;
            });
        } else {
            // Jeśli nie ma kursów z Publigo, ustaw wszystkim 0
            $courses->getCollection()->transform(function($course) {
                $course->orders_count = 0;
                return $course;
            });
        }
    
        return view('courses.index', compact('courses', 'instructors', 'series', 'sourceIdOldOptions', 'filters', 'filteredCount', 'totalCount'));
     }

    /**
     * Generowanie PDF z listą kursów
     */
    public function generatePdf(Request $request)
    {
        try {
            // Zwiększenie limitu czasu dla tej operacji
            set_time_limit(120); // 2 minuty
            \Log::info("PDF - START: generatePdf wywołana");
            $query = Course::query();
        
        // Filtrowanie tylko aktywnych szkoleń
        $query->where('is_active', 1);
        
        // Pobieranie wartości filtra "date_filter"
        $dateFilter = $request->query('date_filter', 'upcoming');
        
        // Pobieranie wartości filtrów
        $filters = [
            'is_paid' => $request->input('is_paid'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'is_active' => $request->input('is_active'),
            'date_filter' => $dateFilter,
            'instructor_id' => $request->input('instructor_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Określenie domyślnego sortowania
        $sortColumn = $request->query('sort', 'start_date');
        $sortDirection = $request->query('direction', ($dateFilter === 'upcoming' ? 'asc' : 'desc'));

        // Filtracja kursów według daty
        if ($dateFilter === 'upcoming') {
            $query->where('end_date', '>=', now());
        } elseif ($dateFilter === 'past') {
            $query->where('end_date', '<', now());
        }

        // Filtracja według zakresu dat
        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from') . ' 00:00:00';
            $query->where('start_date', '>=', $dateFrom);
        }
        
        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to') . ' 23:59:59';
            $query->where('end_date', '<=', $dateTo);
        }

        // Filtracja według pozostałych pól
        foreach ($filters as $key => $value) {
            if (!is_null($value) && $value !== '' && !in_array($key, ['date_filter', 'date_from', 'date_to'])) {
                $query->where($key, $value);
            }
        }
        
        // Obsługa wyszukiwania
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('instructor', function($instructorQuery) use ($searchTerm) {
                      $instructorQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('title', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('location', function($locationQuery) use ($searchTerm) {
                      $locationQuery->where('location_name', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('post_office', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('onlineDetails', function($onlineQuery) use ($searchTerm) {
                      $onlineQuery->where('platform', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Sprawdzenie liczby kursów przed generowaniem PDF
        \Log::info("PDF - Sprawdzanie liczby kursów...");
        $totalCourses = $query->count();
        \Log::info("PDF - Liczba kursów: {$totalCourses}");
        
        if ($totalCourses === 0) {
            \Log::info("PDF - BŁĄD: Brak kursów");
            return redirect()->route('courses.index')->with('error', 'Brak szkoleń spełniających kryteria filtrowania.');
        }
        
        // Prosta walidacja - jeśli za dużo kursów, blokuj
        if ($totalCourses > 1000) {
            \Log::info("PDF - BLOKADA: Za dużo kursów ({$totalCourses})");
            return redirect()->route('courses.index')->with('error', 
                '<strong><i class="fas fa-exclamation-triangle me-2"></i>Zbyt duża liczba szkoleń do przetworzenia!</strong><br><br>' .
                '<div class="mt-2">' .
                '📊 <strong>Liczba szkoleń:</strong> ' . $totalCourses . '<br>' .
                '⚠️ <strong>Limit:</strong> maksymalnie 1000 szkoleń<br><br>' .
                '<strong>Rozwiązanie:</strong> Proszę zastosować bardziej szczegółowe filtry:<br>' .
                '• <strong>Zakres dat</strong> (np. jeden rok lub kwartał)<br>' .
                '• <strong>Instruktor</strong> (konkretna osoba)<br>' .
                '• <strong>Typ szkolenia</strong> (online/stacjonarne)<br>' .
                '• <strong>Kategoria</strong> (otwarte/zamknięte)' .
                '</div>'
            );
        }
        
        // Pobranie wszystkich kursów bez paginacji (dla PDF)
        $courses = $query->with(['instructor', 'location', 'onlineDetails', 'participants'])
                        ->orderBy($sortColumn, $sortDirection)
                        ->get();

        // Przygotowanie informacji o zastosowanych filtrach
        $appliedFilters = [];
        
        if ($request->filled('date_filter') && $request->input('date_filter') !== 'all') {
            $appliedFilters['termin'] = $request->input('date_filter') === 'upcoming' ? 'Nadchodzące' : 'Archiwalne';
        }
        
        if ($request->filled('date_from')) {
            $appliedFilters['data od'] = $request->input('date_from');
        }
        
        if ($request->filled('date_to')) {
            $appliedFilters['data do'] = $request->input('date_to');
        }
        
        if ($request->filled('is_paid')) {
            $appliedFilters['płatność'] = $request->input('is_paid') == '1' ? 'Płatne' : 'Bezpłatne';
        }
        
        if ($request->filled('type')) {
            $appliedFilters['rodzaj'] = $request->input('type') === 'offline' ? 'Stacjonarne' : ucfirst($request->input('type'));
        }
        
        if ($request->filled('category')) {
            $appliedFilters['kategoria'] = $request->input('category') === 'open' ? 'Otwarte' : 'Zamknięte';
        }
        
        if ($request->filled('instructor_id')) {
            $instructor = Instructor::find($request->input('instructor_id'));
            if ($instructor) {
                $appliedFilters['instruktor'] = $instructor->first_name . ' ' . $instructor->last_name;
            }
        }

        // Generowanie PDF
        $pdf = Pdf::loadView('courses.pdf', [
            'courses' => $courses,
            'appliedFilters' => $appliedFilters
        ])->setPaper('A4', 'landscape')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans',
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => true,
              'isPhpEnabled' => true,
              'defaultPaperSize' => 'a4',
              'defaultPaperOrientation' => 'landscape'
          ]);

        $filename = 'lista_szkolen_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
        
        } catch (\Exception $e) {
            \Log::error("PDF - BŁĄD: " . $e->getMessage());
            \Log::error("PDF - Stack trace: " . $e->getTraceAsString());
            return redirect()->route('courses.index')->with('error', 
                'Wystąpił błąd podczas generowania PDF: ' . $e->getMessage()
            );
        }
    }

    /**
     * Generowanie statystyk szkoleń w PDF
     */
    public function generateCourseStatistics(Request $request)
    {
        try {
            // Zwiększenie limitu czasu dla tej operacji
            set_time_limit(120); // 2 minuty
            \Log::info("Statystyki - START: generateCourseStatistics wywołana");
            $query = Course::query();
        
        // Filtrowanie tylko aktywnych szkoleń
        $query->where('is_active', 1);
        
        // Pobieranie wartości filtra "date_filter"
        $dateFilter = $request->query('date_filter', 'upcoming');
        
        // Pobieranie wartości filtrów
        $filters = [
            'is_paid' => $request->input('is_paid'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'is_active' => $request->input('is_active'),
            'date_filter' => $dateFilter,
            'instructor_id' => $request->input('instructor_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Filtracja kursów według daty
        if ($dateFilter === 'upcoming') {
            $query->where('end_date', '>=', now());
        } elseif ($dateFilter === 'past') {
            $query->where('end_date', '<', now());
        }

        // Filtracja według zakresu dat
        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from') . ' 00:00:00';
            $query->where('start_date', '>=', $dateFrom);
        }
        
        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to') . ' 23:59:59';
            $query->where('end_date', '<=', $dateTo);
        }
     
        // Filtracja według pozostałych pól
        foreach ($filters as $key => $value) {
            if (!is_null($value) && $value !== '' && !in_array($key, ['date_filter', 'date_from', 'date_to'])) {
                $query->where($key, $value);
            }
        }
        
        // Obsługa wyszukiwania
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('instructor', function($instructorQuery) use ($searchTerm) {
                      $instructorQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('title', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('location', function($locationQuery) use ($searchTerm) {
                      $locationQuery->where('location_name', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('post_office', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('onlineDetails', function($onlineQuery) use ($searchTerm) {
                      $onlineQuery->where('platform', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Sprawdzenie, czy są jakieś kursy
        \Log::info("Statystyki - Sprawdzanie liczby kursów...");
        $totalCourses = $query->count();
        \Log::info("Statystyki - Liczba kursów: {$totalCourses}");
        
        if ($totalCourses === 0) {
            \Log::info("Statystyki - BŁĄD: Brak kursów");
            return redirect()->route('courses.index')->with('error', 'Brak szkoleń spełniających kryteria filtrowania.');
        }
        
        // Prosta walidacja - jeśli za dużo kursów, blokuj
        if ($totalCourses > 1000) {
            \Log::info("Statystyki - BLOKADA: Za dużo kursów ({$totalCourses})");
            return redirect()->route('courses.index')->with('error', 
                '<strong><i class="fas fa-exclamation-triangle me-2"></i>Zbyt duża liczba szkoleń do przetworzenia!</strong><br><br>' .
                '<div class="mt-2">' .
                '📊 <strong>Liczba szkoleń:</strong> ' . $totalCourses . '<br>' .
                '⚠️ <strong>Limit:</strong> maksymalnie 1000 szkoleń<br><br>' .
                '<strong>Rozwiązanie:</strong> Proszę zastosować bardziej szczegółowe filtry:<br>' .
                '• <strong>Zakres dat</strong> (np. jeden rok lub kwartał)<br>' .
                '• <strong>Instruktor</strong> (konkretna osoba)<br>' .
                '• <strong>Typ szkolenia</strong> (online/stacjonarne)<br>' .
                '• <strong>Kategoria</strong> (otwarte/zamknięte)' .
                '</div>'
            );
        }
        
        // Inteligentne wykrywanie przekroczenia czasu - pomiar próbki i ekstrapolacja
        $maxExecutionTime = 25; // Margines bezpieczeństwa (30s limit PHP - 5s zapas)
        $sampleSize = min(20, $totalCourses); // Zwiększona próbka dla lepszej dokładności
        
        // Pomiar czasu ładowania próbki z pełnymi relacjami
        $startTime = microtime(true);
        $sampleCourses = (clone $query)
            ->with(['instructor', 'participants', 'certificates'])
            ->limit($sampleSize)
            ->get();
        $sampleLoadTime = microtime(true) - $startTime;
        
        // Ekstrapolacja czasu dla wszystkich kursów
        $estimatedTimePerCourse = $sampleLoadTime / $sampleSize;
        $estimatedTotalTime = $estimatedTimePerCourse * $totalCourses;
        
        // Dodatkowy czas na generowanie PDF (zwiększone oszacowanie: 0.05s na kurs)
        $estimatedPdfTime = $totalCourses * 0.05;
        $totalEstimatedTime = $estimatedTotalTime + $estimatedPdfTime;
        
        // Debug - loguj informacje
        \Log::info("Statystyki - Debug:", [
            'total_courses' => $totalCourses,
            'sample_size' => $sampleSize,
            'sample_load_time' => round($sampleLoadTime, 3),
            'estimated_time_per_course' => round($estimatedTimePerCourse, 3),
            'estimated_total_time' => round($estimatedTotalTime, 3),
            'estimated_pdf_time' => round($estimatedPdfTime, 3),
            'total_estimated_time' => round($totalEstimatedTime, 3),
            'max_execution_time' => $maxExecutionTime
        ]);
        
        // Jeśli szacowany czas przekracza limit - zatrzymaj i poinformuj
        if ($totalEstimatedTime > $maxExecutionTime) {
            $estimatedSeconds = round($totalEstimatedTime, 1);
            \Log::info("Statystyki - BLOKADA: Szacowany czas {$estimatedSeconds}s przekracza limit {$maxExecutionTime}s");
            return redirect()->route('courses.index')->with('error', 
                '<strong><i class="fas fa-clock me-2"></i>Szacowany czas generowania raportu przekracza dostępny limit!</strong><br><br>' .
                '<div class="mt-2">' .
                '📊 <strong>Liczba szkoleń:</strong> ' . $totalCourses . '<br>' .
                '⏱️ <strong>Szacowany czas:</strong> ~' . $estimatedSeconds . ' sekund (limit: 30s)<br><br>' .
                '<strong>Rozwiązanie:</strong> Proszę zastosować bardziej szczegółowe filtry:<br>' .
                '• <strong>Zakres dat</strong> (np. jeden rok lub kwartał)<br>' .
                '• <strong>Instruktor</strong> (konkretna osoba)<br>' .
                '• <strong>Typ szkolenia</strong> (online/stacjonarne)<br>' .
                '• <strong>Kategoria</strong> (otwarte/zamknięte)' .
                '</div>'
            );
        }
        
        // Ostrzeżenie dla średnich zbiorów (50-80% limitu czasu)
        if ($totalEstimatedTime > ($maxExecutionTime * 0.5)) {
            $estimatedSeconds = round($totalEstimatedTime, 1);
            session()->flash('warning', 
                '<strong><i class="fas fa-info-circle me-2"></i>Uwaga:</strong> ' .
                'Generowanie raportu dla <strong>' . $totalCourses . ' szkoleń</strong> może potrwać około <strong>' . $estimatedSeconds . ' sekund</strong>. ' .
                'Dla szybszego działania rozważ użycie bardziej szczegółowych filtrów.'
            );
        }

        // Obliczanie statystyk używając SQL agregacji (znacznie szybsze)
        $statistics = [
            'total_courses' => $totalCourses,
            'paid_courses' => (clone $query)->where('is_paid', true)->count(),
            'free_courses' => (clone $query)->where('is_paid', false)->count(),
            'online_courses' => (clone $query)->where('type', 'online')->count(),
            'offline_courses' => (clone $query)->where('type', 'offline')->count(),
            'open_courses' => (clone $query)->where('category', 'open')->count(),
            'closed_courses' => (clone $query)->where('category', 'closed')->count(),
            'total_participants' => DB::table('participants')
                ->whereIn('course_id', (clone $query)->pluck('id'))
                ->count(),
            'total_certificates' => DB::table('certificates')
                ->whereIn('course_id', (clone $query)->pluck('id'))
                ->count(),
        ];
        
        // Obliczanie godzin szkoleń
        $paidCoursesData = (clone $query)->where('is_paid', true)
            ->select('start_date', 'end_date')
            ->get();
        
        $freeCoursesData = (clone $query)->where('is_paid', false)
            ->select('start_date', 'end_date')
            ->get();
        
        $totalHoursPaid = $paidCoursesData->sum(function($course) {
            return $course->start_date->diffInMinutes($course->end_date) / 60;
        });
        
        $totalHoursFree = $freeCoursesData->sum(function($course) {
            return $course->start_date->diffInMinutes($course->end_date) / 60;
        });
        
        $statistics['total_hours_paid'] = round($totalHoursPaid, 2);
        $statistics['total_hours_free'] = round($totalHoursFree, 2);
        
        // Certyfikaty dla płatnych i bezpłatnych kursów
        $paidCourseIds = (clone $query)->where('is_paid', true)->pluck('id');
        $freeCourseIds = (clone $query)->where('is_paid', false)->pluck('id');
        
        $statistics['certificates_paid_courses'] = DB::table('certificates')
            ->whereIn('course_id', $paidCourseIds)
            ->count();
            
        $statistics['certificates_free_courses'] = DB::table('certificates')
            ->whereIn('course_id', $freeCourseIds)
            ->count();
        
        // Pobierz wszystkie przefiltrowane kursy dla szczegółowej listy w PDF
        $courses = (clone $query)
            ->with(['instructor', 'participants', 'certificates'])
            ->orderBy('start_date', 'desc')
            ->get();

        // Przygotowanie danych dla raportu
        $reportData = [
            'courses' => $courses,
            'statistics' => $statistics,
            'filters_applied' => $request->only(['search', 'is_paid', 'type', 'category', 'instructor_id', 'date_from', 'date_to', 'date_filter']),
            'generated_at' => now(),
        ];

        // Generowanie PDF
        $pdf = Pdf::loadView('courses.statistics', $reportData)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ]);

        $filename = 'statystyki_szkolen_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
        
        } catch (\Exception $e) {
            \Log::error("Statystyki - BŁĄD: " . $e->getMessage());
            \Log::error("Statystyki - Stack trace: " . $e->getTraceAsString());
            return redirect()->route('courses.index')->with('error', 
                'Wystąpił błąd podczas generowania statystyk: ' . $e->getMessage()
            );
        }
    }

    /**
     * Wyświetlanie szczegółów kursu
     */
    public function show($id)
    {
        $course = Course::with(['instructor', 'location', 'onlineDetails', 'participants', 'surveys', 'priceVariants', 'videos' => function($query) {
            $query->orderBy('order');
        }])->findOrFail($id);
        
        // Pobierz również usunięte warianty cenowe (dla przywracania)
        $deletedVariants = \App\Models\CoursePriceVariant::withTrashed()
            ->where('course_id', $course->id)
            ->whereNotNull('deleted_at')
            ->get();
        
        // Pobranie poprzedniego szkolenia (według daty, pokazuj również nieaktywne)
        $previousCourse = Course::where('start_date', '<', $course->start_date)
                               ->orderBy('start_date', 'desc')
                               ->first();
        
        // Pobranie następnego szkolenia (według daty, pokazuj również nieaktywne)
        $nextCourse = Course::where('start_date', '>', $course->start_date)
                           ->orderBy('start_date', 'asc')
                           ->first();
        
        return view('courses.show', compact('course', 'previousCourse', 'nextCourse', 'deletedVariants'));
    }

    /**
     * Formularz dodawania nowego kursu
     */
    public function create()
    {
        // Pobranie listy instruktorów do formularza
        $instructors = Instructor::all();
        
        // Pobranie listy aktywnych szablonów certyfikatów
        $certificateTemplates = CertificateTemplate::where('is_active', true)
                                                   ->orderBy('name')
                                                   ->get();
        
        return view('courses.create', compact('instructors', 'certificateTemplates'));
    }

    public function store(Request $request)
    {
        \Log::info('Dane formularza:', $request->all());
    
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'offer_summary' => 'nullable|string|max:500',
            'offer_description_html' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'issue_date_certyficates' => 'nullable|date',
            'is_paid' => 'required|boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_format' => 'nullable|string|max:255',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'id_old' => 'nullable|string|max:255',
            'source_id_old' => 'nullable|string|max:255',
            'notatki' => 'nullable|string',
        ]);
        $validated['certificate_format'] = $validated['certificate_format'] ?? '{nr}/{course_id}/{year}/PNE'; //    
        
        // ✅ Sanityzacja HTML - dozwolone tagi Bootstrap 5 i standardowe HTML
        if (!empty($validated['offer_description_html'])) {
            $validated['offer_description_html'] = strip_tags($validated['offer_description_html'], 
                '<p><br><br/><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><a><img><div><span>' .
                '<section><article><header><footer><nav><main><aside>' .
                '<hr><hr/><button><code><pre><small><mark><del><ins><sub><sup>' .
                '<table><thead><tbody><tfoot><tr><td><th><caption><colgroup><col>' .
                '<blockquote><cite><abbr><dfn><time><address><q><samp><var><kbd>' .
                '<dl><dt><dd><fieldset><legend><label><input><select><option><textarea><form>' .
                '<details><summary><dialog><menu><menuitem><output><progress><meter>' .
                '<svg><canvas><audio><video><source><track><embed><object><param><iframe>' .
                '<style><link><meta><noscript><script><template><slot>');
        }
        
        try {
            DB::beginTransaction();
    
            // Dodanie is_active
            $validated['is_active'] = $request->has('is_active');
    
            \Log::info('Przed utworzeniem kursu:', $validated);
    
            // ✅ Tworzymy kurs **bez grafiki**, grafikę dodamy później
            $course = Course::create($validated);
    
            // ✅ Tworzenie folderu `courses/images`, jeśli nie istnieje
            $storageDirectory = storage_path('app/public/courses/images');
            if (!file_exists($storageDirectory)) {
                mkdir($storageDirectory, 0777, true);
                \Log::info("Utworzono folder: {$storageDirectory}");
            }
    
            // ✅ Obsługa przesłanego pliku obrazka
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension(); // Pobieramy oryginalne rozszerzenie pliku
    
                // Generowanie nowej nazwy pliku
                $randomSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
                $imageFileName = "course_{$course->id}_{$randomSuffix}.{$extension}";
                $imagePath = "courses/images/{$imageFileName}"; // ✅ Ścieżka w bazie
    
                // ✅ Zapis pliku w `storage/app/public/courses/images`
                $saved = $file->move($storageDirectory, $imageFileName);
    
                if ($saved) {
                    // ✅ Aktualizacja rekordu kursu o ścieżkę do pliku
                    $course->update(['image' => $imagePath]);
                    \Log::info("Plik zapisany jako: {$imagePath}");
                } else {
                    \Log::error("Błąd zapisu pliku: {$imageFileName}");
                }
            }
    
            // ✅ Dla kursu stacjonarnego
            if ($request->type === 'offline') {
                $locationData = [
                    'course_id' => $course->id,                    
                    'location_name' => $request->location_name,
                    'address' => $request->address,
                    'postal_code' => $request->postal_code,
                    'post_office' => $request->post_office,
                    'country' => $request->country ?? 'Polska'
                ];
                
                \Log::info('Dane lokalizacji:', $locationData);
                
                CourseLocation::create($locationData);
            }
    
            // ✅ Dla kursu online
            if ($request->type === 'online') {
                $onlineData = [
                    'course_id' => $course->id,
                    'platform' => $request->platform,
                    'meeting_link' => $request->meeting_link,
                    'meeting_password' => $request->meeting_password,
                ];
                
                \Log::info('Dane kursu online:', $onlineData);
                
                CourseOnlineDetails::create($onlineData);
            }
    
            DB::commit();
            \Log::info('Transakcja zatwierdzona');
    
            return redirect()->route('courses.index')
                ->with('success', 'Szkolenie zostało dodane!');
                
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Błąd zapisu kursu: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
    
            return back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas zapisywania kursu: ' . $e->getMessage());
        }
    }
         
    
    public function edit($id)
    {
        $course = Course::with(['location', 'onlineDetails', 'participants'])->findOrFail($id);
        $instructors = Instructor::all();
        
        // Pobranie listy aktywnych szablonów certyfikatów
        $certificateTemplates = CertificateTemplate::where('is_active', true)
                                                   ->orderBy('name')
                                                   ->get();
        
        return view('courses.edit', compact('course', 'instructors', 'certificateTemplates'));
    }
    
    public function destroy($id)
    {
        $course = Course::findOrFail($id);
    
        // Sprawdzenie, czy instruktor ma zdjęcie
        if ($course->image) {
            $photoPath = public_path('storage/' . $course->image);
    
            // Usunięcie pliku, jeśli istnieje
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
        // Usunięcie kursu z bazy danych
        $course->delete();
    
        return redirect()->route('courses.index')->with('success', 'Szkolenie usunięte.');
    }
    
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);
    
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'offer_summary' => 'nullable|string|max:500',
            'offer_description_html' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'issue_date_certyficates' => 'nullable|date',
            'is_paid' => 'required|boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|string',
            'certificate_format' => 'nullable|string|max:255',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'id_old' => 'nullable|string|max:255',
            'source_id_old' => 'nullable|string|max:255',
            'notatki' => 'nullable|string',
        ]);

        $validated['certificate_format'] = $validated['certificate_format'] ?? '{nr}/{course_id}/{year}/PNE'; //        
        // ✅ Poprawna obsługa `is_active`
        $validated['is_active'] = $request->has('is_active');
        
        // ✅ Sanityzacja HTML - dozwolone tagi Bootstrap 5 i standardowe HTML
        if (!empty($validated['offer_description_html'])) {
            $validated['offer_description_html'] = strip_tags($validated['offer_description_html'], 
                '<p><br><br/><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><a><img><div><span>' .
                '<section><article><header><footer><nav><main><aside>' .
                '<hr><hr/><button><code><pre><small><mark><del><ins><sub><sup>' .
                '<table><thead><tbody><tfoot><tr><td><th><caption><colgroup><col>' .
                '<blockquote><cite><abbr><dfn><time><address><q><samp><var><kbd>' .
                '<dl><dt><dd><fieldset><legend><label><input><select><option><textarea><form>' .
                '<details><summary><dialog><menu><menuitem><output><progress><meter>' .
                '<svg><canvas><audio><video><source><track><embed><object><param><iframe>' .
                '<style><link><meta><noscript><script><template><slot>');
        }
    
        // ✅ Tworzymy folder `courses/images`, jeśli nie istnieje
        $storageDirectory = storage_path('app/public/courses/images');
        if (!file_exists($storageDirectory)) {
            mkdir($storageDirectory, 0777, true);
        }
    
        // ✅ Usunięcie starego obrazka, jeśli użytkownik zaznaczył "Usuń obrazek"
        if ($request->has('remove_image')) {
            if ($course->image && \Storage::disk('public')->exists($course->image)) {
                \Storage::disk('public')->delete($course->image);
            }
            $validated['image'] = null; // Usunięcie ścieżki pliku w bazie danych
        }
    
        // ✅ Obsługa nowego pliku graficznego
        if ($request->hasFile('image')) {
            // ✅ Usunięcie starego pliku, jeśli istnieje
            if ($course->image && \Storage::disk('public')->exists($course->image)) {
                \Storage::disk('public')->delete($course->image);
            }
    
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension(); // Pobieramy rozszerzenie pliku
            $randomSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $imageFileName = "course_{$course->id}_{$randomSuffix}.{$extension}";
            $imagePath = "courses/images/{$imageFileName}";
    
            // ✅ Zapis pliku na serwerze
            $saved = $file->move($storageDirectory, $imageFileName);
            if ($saved) {
                $validated['image'] = $imagePath; // Zapis do bazy tylko jeśli zapis pliku się powiódł
            }
        }
    
        // ✅ Aktualizacja kursu
        $course->update($validated);
    
        // ✅ Aktualizacja lokalizacji kursu offline
        if ($request->type === 'offline') {
            CourseLocation::updateOrCreate(
                ['course_id' => $course->id],
                [
                    'location_name' => $request->location_name,
                    'address' => $request->address,
                    'postal_code' => $request->postal_code,
                    'post_office' => $request->post_office,
                    'country' => $request->country ?? 'Polska',
                ]
            );
    
            // Jeśli kurs zmieniono na offline, usuwamy dane online
            CourseOnlineDetails::where('course_id', $course->id)->delete();
        }
    
        // ✅ Aktualizacja danych kursu online
        if ($request->type === 'online') {
            CourseOnlineDetails::updateOrCreate(
                ['course_id' => $course->id],
                [
                    'platform' => $request->platform,
                    'meeting_link' => $request->meeting_link,
                    'meeting_password' => $request->meeting_password,
                ]
            );
    
            // Jeśli kurs zmieniono na online, usuwamy lokalizację
            CourseLocation::where('course_id', $course->id)->delete();
        }
    
        // Zachowaj parametry filtrów przekazane z formularza
        $queryParams = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'filter_') && !empty($value)) {
                $filterKey = str_replace('filter_', '', $key);
                $queryParams[$filterKey] = $value;
            }
        }
        
        return redirect()->route('courses.index', $queryParams)->with('success', 'Szkolenie zaktualizowane!');
    }
    

/*--------importFromPubligo------------*/

    public function importFromPubligo()
    {
        // Pobranie kursów z bazy certgen
        $szkolenia = DB::connection('mysql_certgen')->table('publigo')->get();

        $importedCount = 0;
        $skippedCount = 0;

        foreach ($szkolenia as $szkolenie) {
            // Sprawdzamy, czy kurs już istnieje w docelowej bazie
            $exists = DB::connection('mysql')->table('courses')
                ->where('id_old', $szkolenie->id_old)
                ->where('source_id_old', 'certgen_Publigo')
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            // Oczyszczanie wartości `title` i `description`
            $title = strip_tags($szkolenie->title);
            $description = strip_tags($szkolenie->description);

            // Określenie, czy kurs jest online czy offline
            $courseType = ($szkolenie->type == 'online') ? 'online' : 'offline';

            // Wstawienie nowego kursu do `courses`
            $courseId = DB::connection('mysql')->table('courses')->insertGetId([
                'title' => $title,
                'description' => $description,
                'start_date' => $szkolenie->start_date,
                'end_date' => $szkolenie->end_date,
                'is_paid' => $szkolenie->is_paid,
                'type' => $courseType,
                'category' => $szkolenie->category,
                'instructor_id' => $szkolenie->instructor_id,
                'image' => $szkolenie->image,
                'is_active' => $szkolenie->is_active,
                'certificate_format' => $szkolenie->certificate_format,
                'id_old' => $szkolenie->id_old,
                'source_id_old' => 'certgen_Publigo', // Identyfikator źródła
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Jeśli kurs jest online, zapisujemy do `course_online_details`
            if ($szkolenie->type == 'online') {
                DB::connection('mysql')->table('course_online_details')->insert([
                    'course_id' => $courseId,
                    'platform' => $szkolenie->platform ?? 'ClickMeeting',
                    'meeting_link' => $szkolenie->meeting_link,
                    'meeting_password' => $szkolenie->meeting_password,
                ]);
            } 
            // Jeśli kurs jest offline, zapisujemy do `course_locations`
            else {
                DB::connection('mysql')->table('course_locations')->insert([
                    'course_id' => $courseId,
                    'location_name' => $szkolenie->location_name,
                    'postal_code' => $szkolenie->postal_code,
                    'post_office' => $szkolenie->post_office,
                    'address' => $szkolenie->address,
                    'country' => $szkolenie->country ?? 'Polska',
                ]);
            }

            $importedCount++;
        }

        // Komunikat zwrotny
        $message = "Zaimportowano <b>{$importedCount}</b> kursów.";
        if ($skippedCount > 0) {
            $message .= " <br>Pominięto <b>{$skippedCount}</b>, ponieważ już istnieją.";
        }

        return redirect()->route('courses.index')->with('success', $message);
    }


/*=========importFromPubligo===========*/
    
}
