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
     
         // Filtracja według pozostałych pól
         foreach ($filters as $key => $value) {
             if (!is_null($value) && $value !== '' && $key !== 'date_filter') { // Pomijamy filtr terminu, bo już jest przetwarzany powyżej
                 $query->where($key, $value);
             }
         }
     
         // Pobranie wyników z dynamicznym sortowaniem i paginacją
         $courses = $query->orderBy($sortColumn, $sortDirection)->paginate(10)->appends($filters + ['sort' => $sortColumn, 'direction' => $sortDirection]);
     
         return view('courses.index', compact('courses', 'instructors', 'filters'));
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

    /**
     * Obsługa dodawania kursu
     */
    public function store(Request $request)
    {

        //dd($request->all()); // 👈 Sprawdźmy, jakie dane faktycznie są przesyłane
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Obsługa pliku obrazka
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('courses', 'public');
            }
            
            // Dodanie is_active
            $validated['is_active'] = $request->has('is_active');
            
            \Log::info('Przed utworzeniem kursu:', $validated);
            
            $course = Course::create($validated);
            
           // \Log::info('Po utworzeniu kursu. ID:', $course->id);
            
            // Dla kursu stacjonarnego
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

            // Dla kursu online
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
        $course = Course::with(['location', 'onlineDetails'])->findOrFail($id);
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

        //dd($request->all()); // Wyświetli wszystkie dane przesłane w formularzu edycji        
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
            'is_active' => 'nullable|string', // Sprawdzamy, czy is_active jest przesyłane jako string
        ]);
        
        // ✅ Poprawna obsługa `is_active`
        $validated['is_active'] = $request->has('is_active');
    
        // Jeśli zaznaczono checkbox "Usuń obrazek", usuwamy go
        if ($request->has('remove_image')) {
            if ($course->image && \Storage::disk('public')->exists($course->image)) {
                \Storage::disk('public')->delete($course->image);
            }
            $course->image = null; // Usunięcie ścieżki pliku w bazie danych
        }

        // Obsługa pliku obrazka
        if ($request->hasFile('image')) {
            // Usunięcie starego obrazka, jeśli istnieje
            if ($course->image && \Storage::disk('public')->exists($course->image)) {
                \Storage::disk('public')->delete($course->image);
            }
            // Zapis nowego obrazka
            $validated['image'] = $request->file('image')->store('courses', 'public');
        }        
    
        $course->update($validated);
    
        // 🔹 Aktualizacja lokalizacji kursu offline
        if ($request->type === 'offline') {
            CourseLocation::updateOrCreate(
                ['course_id' => $course->id],
                [
                    'location_name' => $request->location_name,
                    'address' => $request->address,
                    'postal_code' => $request->postal_code,
                    'post_office' => $request->post_office,
                    'country' => $request->country,
                ]
            );
    
            // Jeśli kurs zmieniono na offline, usuwamy dane online
            CourseOnlineDetails::where('course_id', $course->id)->delete();
        }
    
        // 🔹 Aktualizacja danych kursu online
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

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);
    
        $file = $request->file('csv_file');
        $handle = fopen($file, "r");
        $header = fgetcsv($handle, 1000, ","); // Pobranie nagłówka
    
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Walidacja liczby kolumn
            if (count($row) < 17) { // Upewniamy się, że mamy wystarczająco dużo danych
                continue; // Pomijamy błędne wiersze
            }
    
            // Pobieranie i konwersja dat
            $startDate = \Carbon\Carbon::parse($row[2]); // Data rozpoczęcia
            $endDate = \Carbon\Carbon::parse($row[3]); // Data zakończenia
    
            // ✅ Sprawdzenie i poprawienie daty zakończenia
            if ($endDate <= $startDate) {
                $endDate = $startDate->copy()->addHours(2); // Automatyczna korekta daty zakończenia
            }
    
            // Tworzenie kursu
            $course = Course::create([
                'title' => $row[0], // Tytuł kursu
                'description' => $row[1], // Opis
                'start_date' => $startDate, // Data rozpoczęcia
                'end_date' => $endDate, // Data zakończenia (poprawiona, jeśli była błędna)
                'type' => $row[4], // Typ kursu (online/offline)
                'category' => $row[5], // Kategoria (open/closed)
                'instructor_id' => is_numeric($row[6]) ? $row[6] : null, // ID instruktora (lub null)
                'image' => $row[7] ?? null, // Opcjonalna ścieżka do obrazu
                'is_active' => filter_var($row[8], FILTER_VALIDATE_BOOLEAN), // Konwersja wartości na boolean
            ]);
    
            // ✅ Obsługa kursów offline (dodanie lokalizacji)
            if ($row[4] === 'offline') {
                CourseLocation::create([
                    'course_id' => $course->id,
                    'location_name' => $row[9] ?? null, // Nazwa lokalizacji
                    'address' => $row[10] ?? null, // Adres
                    'city' => $row[11] ?? null, // Miasto
                    'country' => $row[12] ?? null, // Kraj
                    'post_office' => $row[13] ?? null, // Poczta
                    'postal_code' => $row[14] ?? null, // Kod pocztowy
                ]);
            }
    
            // ✅ Obsługa kursów online (dodanie platformy i linków)
            if ($row[4] === 'online') {
                CourseOnlineDetails::create([
                    'course_id' => $course->id,
                    'platform' => $row[15] ?? 'ClickMeeting', // Platforma (domyślnie ClickMeeting)
                    'meeting_link' => $row[16] ?? null, // Link do spotkania
                    'meeting_password' => $row[17] ?? null, // Hasło do spotkania
                ]);
            }
        }
    
        fclose($handle);
    
        return redirect()->route('courses.index')->with('success', 'Kursy zaimportowane!');
    }
    
    
}
