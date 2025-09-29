<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;
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
         $query = Course::query();
     
        // Pobieranie listy instruktorów do widoku
        $instructors = Instructor::orderBy('last_name')->get();
        
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
        if ($perPage === 'all') {
            $perPage = 999999; // Bardzo duża liczba, żeby wyświetlić wszystkie
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
            $query->where('start_date', '>=', $request->input('date_from'));
        }
        
        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->input('date_to'));
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
        
        // Liczenie wszystkich aktywnych rekordów w bazie
        $totalCount = Course::where('is_active', true)->count();
        
        // Obliczanie statystyk dla przefiltrowanych szkoleń
        $filteredCourses = $query->with(['participants', 'certificates'])->get();
        
        $statistics = [
            'total_participants' => $filteredCourses->sum(function($course) {
                return $course->participants->count();
            }),
            'total_certificates' => $filteredCourses->sum(function($course) {
                return $course->certificates->count();
            }),
            'online_courses' => $filteredCourses->where('type', 'online')->count(),
            'offline_courses' => $filteredCourses->where('type', 'offline')->count(),
            'paid_courses' => $filteredCourses->where('is_paid', true)->count(),
            'free_courses' => $filteredCourses->where('is_paid', false)->count(),
            'open_courses' => $filteredCourses->where('category', 'open')->count(),
            'closed_courses' => $filteredCourses->where('category', 'closed')->count(),
        ];
    
        // Pobranie wyników z dynamicznym sortowaniem i paginacją
        $courses = $query->with(['instructor', 'location', 'onlineDetails', 'participants', 'certificates'])
                        ->orderBy($sortColumn, $sortDirection)
                        ->paginate($perPage)
                        ->appends($filters + ['sort' => $sortColumn, 'direction' => $sortDirection]);
    
        return view('courses.index', compact('courses', 'instructors', 'sourceIdOldOptions', 'filters', 'filteredCount', 'totalCount', 'statistics'));
     }

    /**
     * Generowanie PDF z listą kursów
     */
    public function generatePdf(Request $request)
    {
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
            $query->where('start_date', '>=', $request->input('date_from'));
        }
        
        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->input('date_to'));
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
    }

    /**
     * Generowanie statystyk szkoleń w PDF
     */
    public function generateCourseStatistics(Request $request)
    {
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
            $query->where('start_date', '>=', $request->input('date_from'));
        }
        
        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->input('date_to'));
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

        // Pobierz wszystkie przefiltrowane kursy
        $courses = $query->with(['participants', 'certificates', 'instructor'])->get();

        if ($courses->isEmpty()) {
            return redirect()->route('courses.index')->with('error', 'Brak szkoleń spełniających kryteria filtrowania.');
        }

        // Obliczanie statystyk
        $paidCourses = $courses->where('is_paid', true);
        $freeCourses = $courses->where('is_paid', false);
        
        // Obliczanie godzin szkoleń
        $totalHoursPaid = $paidCourses->sum(function($course) {
            return $course->start_date->diffInMinutes($course->end_date) / 60;
        });
        
        $totalHoursFree = $freeCourses->sum(function($course) {
            return $course->start_date->diffInMinutes($course->end_date) / 60;
        });
        
        $statistics = [
            'total_courses' => $courses->count(),
            'paid_courses' => $paidCourses->count(),
            'free_courses' => $freeCourses->count(),
            'online_courses' => $courses->where('type', 'online')->count(),
            'offline_courses' => $courses->where('type', 'offline')->count(),
            'total_participants' => $courses->sum(function($course) {
                return $course->participants->count();
            }),
            'total_hours_paid' => round($totalHoursPaid, 2),
            'total_hours_free' => round($totalHoursFree, 2),
            'certificates_paid_courses' => $paidCourses->sum(function($course) {
                return $course->certificates->count();
            }),
            'certificates_free_courses' => $freeCourses->sum(function($course) {
                return $course->certificates->count();
            }),
            'total_certificates' => $courses->sum(function($course) {
                return $course->certificates->count();
            }),
        ];

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
    }

    /**
     * Wyświetlanie szczegółów kursu
     */
    public function show($id)
    {
        $course = Course::with(['instructor', 'location', 'onlineDetails', 'participants', 'surveys'])
                        ->findOrFail($id);
        
        // Pobranie poprzedniego szkolenia (według daty, pokazuj również nieaktywne)
        $previousCourse = Course::where('start_date', '<', $course->start_date)
                               ->orderBy('start_date', 'desc')
                               ->first();
        
        // Pobranie następnego szkolenia (według daty, pokazuj również nieaktywne)
        $nextCourse = Course::where('start_date', '>', $course->start_date)
                           ->orderBy('start_date', 'asc')
                           ->first();
        
        return view('courses.show', compact('course', 'previousCourse', 'nextCourse'));
    }

    /**
     * Formularz dodawania nowego kursu
     */
    public function create()
    {
        // Pobranie listy instruktorów do formularza
        $instructors = Instructor::all();
        return view('courses.create', compact('instructors'));
    }

    public function store(Request $request)
    {
        \Log::info('Dane formularza:', $request->all());
    
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_paid' => 'required|boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_format' => 'nullable|string|max:255',
            'id_old' => 'nullable|string|max:255',
            'source_id_old' => 'nullable|string|max:255',
        ]);
        $validated['certificate_format'] = $validated['certificate_format'] ?? '{nr}/{course_id}/{year}/PNE'; //    
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
        
        return view('courses.edit', compact('course', 'instructors'));
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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_paid' => 'required|boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|string',
            'certificate_format' => 'nullable|string|max:255',
            'id_old' => 'nullable|string|max:255',
            'source_id_old' => 'nullable|string|max:255',
        ]);

        $validated['certificate_format'] = $validated['certificate_format'] ?? '{nr}/{course_id}/{year}/PNE'; //        
        // ✅ Poprawna obsługa `is_active`
        $validated['is_active'] = $request->has('is_active');
    
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
    
        return redirect()->route('courses.index')->with('success', 'Szkolenie zaktualizowane!');
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
