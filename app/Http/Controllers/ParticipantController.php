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
    public function index(Course $course)
    {
        $participants = $course->participants()->paginate(10);
        return view('participants.index', compact('course', 'participants'));
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

        $course->participants()->create($request->all());

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

