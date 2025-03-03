<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Course;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    /**
     * Wyświetla listę uczestników dla danego kursu.
     */
    public function index(Request $request, Course $course)
    {
        $query = Participant::where('course_id', $course->id);
    
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
        $participants = $query->orderBy('order')->paginate(10);
    
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
        ]);
    
        // Pobranie ostatniego numeru porządkowego w danym kursie
        $lastOrder = $course->participants()->max('order') ?? 0;
    
        // Tworzenie nowego uczestnika z przypisanym numerem porządkowym
        $course->participants()->create(array_merge(
            $request->all(),
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
        ]);

        $participant->update($request->all());

        return redirect()->route('participants.index', $course)->with('success', 'Uczestnik zaktualizowany.');
    }

    /**
     * Usunięcie uczestnika.
     */
    public function destroy(Course $course, Participant $participant)
    {
        $participant->delete();

        return redirect()->route('participants.index', $course)->with('success', 'Uczestnik usunięty.');
    }
}

