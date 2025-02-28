<?php

namespace App\Http\Controllers;
use App\Models\Instructor;
use Illuminate\Http\Request;

class InstructorsController extends Controller
{
    public function index()
    {
        //$instructors = Instructor::all();  // Pobieranie wszystkich instruktorów
        $instructors = Instructor::paginate(10); // 10 instruktorów na stronę  
        return view('courses.instructors.index', compact('instructors'));
    }

    // Metoda do dodawania nowego instruktora
    public function store(Request $request)
    {
        // Test: Wyświetl przesłane dane
        //dd($request->all());
    
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:instructors,email',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|string', // Sprawdzamy, czy is_active jest przesyłane jako string
        ]);
    
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('instructors', 'public');
        }
    
        Instructor::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'bio' => $request->input('bio'),
            'photo' => $photoPath,
            'is_active' => $request->has('is_active'),
        ]);
    
        return redirect()->route('courses.instructors.index')->with('success', 'Instruktor został dodany.');
    }

    public function create()
    {
        return view('courses.instructors.create');
    }
    

    public function edit($id)
    {
        $instructor = Instructor::findOrFail($id);
        return view('courses.instructors.edit', compact('instructor'));
    }
    
    public function update(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);
    
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:instructors,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|string',
        ]);
    
        // Jeśli przesłano nowe zdjęcie, zamień stare
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('instructors', 'public');
            $instructor->photo = $photoPath;
        }
    
        $instructor->first_name = $request->input('first_name');
        $instructor->last_name = $request->input('last_name');
        $instructor->email = $request->input('email');
        $instructor->phone = $request->input('phone');
        $instructor->bio = $request->input('bio');
        $instructor->is_active = $request->has('is_active');
    
        $instructor->save();
    
        return redirect()->route('courses.instructors.index')->with('success', 'Instruktor został zaktualizowany.');
    }
    public function destroy($id)
    {
        $instructor = Instructor::findOrFail($id);
    
        // Sprawdzenie, czy instruktor ma zdjęcie
        if ($instructor->photo) {
            $photoPath = public_path('storage/' . $instructor->photo);
    
            // Usunięcie pliku, jeśli istnieje
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    
        // Usunięcie rekordu z bazy danych
        $instructor->delete();
    
        return redirect()->route('courses.instructors.index')->with('success', 'Instruktor został usunięty.');
    }
            
    
}
