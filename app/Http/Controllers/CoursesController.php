<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\CourseLocation;
use App\Models\CourseOnlineDetails;
use Illuminate\Support\Facades\DB;

class CoursesController extends Controller
{
    /**
     * Wyświetlanie listy kursów
     */
    public function index()
    {
        // Pobranie wszystkich szkoleń z bazy z paginacją
        $courses = Course::with(['location', 'onlineDetails', 'instructor'])
        ->orderBy('start_date', 'desc') // Sortowanie według daty rozpoczęcia
        ->paginate(5); // Paginacja co 5 kursów        
        return view('courses.index', compact('courses'));
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
            'end_date' => 'required|date|after_or_equal:start_date',
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
                    'postal_code' => $request->postal_code,
                    'post_office' => $request->post_office,
                    'address' => $request->address,
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
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|string', // Sprawdzamy, czy is_active jest przesyłane jako string
        ]);
        
        // ✅ Poprawna obsługa `is_active`
        $validated['is_active'] = $request->has('is_active');
    
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('courses', 'public');
        }
    
        $course->update($validated);
    
        // 🔹 Aktualizacja lokalizacji kursu offline
        if ($request->type === 'offline') {
            CourseLocation::updateOrCreate(
                ['course_id' => $course->id],
                [
                    'postal_code' => $request->postal_code,
                    'post_office' => $request->post_office,
                    'address' => $request->address,
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
        
}
