<?php

namespace App\Http\Controllers;

use App\Models\CourseSeries;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CourseSeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $series = CourseSeries::withCount('courses')->orderBy('sort_order')->orderBy('name')->get();
        return view('courses.series.index', compact('series'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('courses.series.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Generowanie sluga z nazwy
        $validated['slug'] = Str::slug($validated['name']);
        
        // Sprawdzenie unikalności sluga (proste zabezpieczenie, można rozbudować)
        if (CourseSeries::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] .= '-' . time();
        }

        // Obsługa przesyłania obrazka
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('course_series', 'public');
        } else {
            unset($validated['image']);
        }

        CourseSeries::create($validated);

        return redirect()->route('courses.series.index')->with('success', 'Seria została utworzona.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CourseSeries $series)
    {
        // Pobierz kursy przypisane do serii (już posortowane przez relację w modelu)
        $courses = $series->courses;
        
        // Pobierz ID kursów już przypisanych do serii
        $assignedCourseIds = $courses->pluck('id')->toArray();
        
        // Pobierz wszystkie kursy do wyboru (do formularza dodawania w widoku show)
        // Sortowanie po dacie rozpoczęcia od najnowszych, z instruktorem i wszystkimi potrzebnymi polami
        // Wykluczamy kursy już przypisane do serii
        $allCourses = Course::with('instructor')
            ->whereNotIn('id', $assignedCourseIds)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'title', 'start_date', 'instructor_id', 'is_active', 'is_paid', 'type', 'category', 'source_id_old']);
        
        // Pobierz opcje dla source_id_old
        $sourceIdOldOptions = Course::whereNotNull('source_id_old')
                                  ->where('source_id_old', '!=', '')
                                  ->distinct()
                                  ->orderBy('source_id_old')
                                  ->pluck('source_id_old');
        
        return view('courses.series.show', compact('series', 'courses', 'allCourses', 'sourceIdOldOptions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CourseSeries $series)
    {
        return view('courses.series.edit', compact('series'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseSeries $series)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'remove_image' => 'nullable|string',
        ]);

        // Aktualizacja sluga tylko jeśli zmieniła się nazwa (opcjonalnie)
        if ($series->name !== $validated['name']) {
             $validated['slug'] = Str::slug($validated['name']);
             if (CourseSeries::where('slug', $validated['slug'])->where('id', '!=', $series->id)->exists()) {
                $validated['slug'] .= '-' . time();
             }
        }

        // Usunięcie obrazka, jeśli użytkownik zaznaczył "Usuń obrazek"
        if ($request->has('remove_image')) {
            if ($series->image && Storage::disk('public')->exists($series->image)) {
                Storage::disk('public')->delete($series->image);
            }
            $validated['image'] = null;
        }

        // Obsługa przesyłania nowego obrazka
        if ($request->hasFile('image')) {
            // Usunięcie poprzedniego obrazka, jeśli istnieje
            if ($series->image && Storage::disk('public')->exists($series->image)) {
                Storage::disk('public')->delete($series->image);
            }
            
            // Zapis nowego obrazka
            $validated['image'] = $request->file('image')->store('course_series', 'public');
        } else {
            // Jeśli nie przesyłamy nowego obrazka i nie usuwamy, zachowujemy stary
            unset($validated['image']);
        }

        $series->update($validated);
        
        return redirect()->route('courses.series.index')->with('success', 'Seria została zaktualizowana.');
    }

    /**
     * Update courses assigned to the series.
     */
    public function updateCourses(Request $request, CourseSeries $series)
    {
        // Oczekujemy formatu: ['courses' => [id1, id2, id3]] (kolejność ma znaczenie)
        if ($request->has('courses')) {
             $syncData = [];
             foreach ($request->input('courses', []) as $index => $courseId) {
                 $syncData[$courseId] = ['order_in_series' => $index + 1];
             }
             $series->courses()->sync($syncData);
        } else {
            // Jeśli tablica pusta, usuwamy wszystkie przypisania
            $series->courses()->detach();
        }

        return redirect()->route('courses.series.show', $series)->with('success', 'Lista kursów została zaktualizowana.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseSeries $series)
    {
        $series->delete();
        return redirect()->route('courses.series.index')->with('success', 'Seria została usunięta.');
    }
}

