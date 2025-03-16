<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Publigo;
use App\Models\Instructor;

class PubligoController extends Controller
{
    public function index(Request $request)
    {
        // Pobranie parametrów sortowania
        $sortBy = $request->query('sort', 'start_date'); // Domyślnie sortujemy po start_date
        $order = $request->query('order', 'desc'); // Domyślnie od najnowszego do najstarszego
    
        // Pobranie wszystkich instruktorów z bazy `pneadm` i zapisanie w tablicy [id => imię i nazwisko]
        $instructors = DB::connection('mysql')->table('instructors')
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"))
            ->pluck('full_name', 'id'); // Pluck zwróci tablicę [id_instruktora => "Imię Nazwisko"]
    
        // Pobranie szkoleń z `certgen.publigo`
        $szkolenia = DB::connection('mysql_certgen')->table('publigo')
            ->orderBy($sortBy, $order)
            ->paginate(10);
    
        return view('archiwum.certgen_publigo.index', compact('szkolenia', 'instructors'));
    }
    
    public function create()
    {
        $instructors = Instructor::all(); // Pobranie listy instruktorów
        return view('archiwum.certgen_publigo.create', compact('instructors'));
    }
    
    public function store(Request $request)
    {
        // Walidacja danych
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_paid' => 'required|boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'certificate_format' => 'nullable|string',
            'platform' => 'nullable|string',
            'meeting_link' => 'nullable|string',
            'meeting_password' => 'nullable|string',
            'location_name' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'post_office' => 'nullable|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
        ]);
    
        // Tworzenie nowego szkolenia
        Publigo::create($request->all());
    
        return redirect()->route('archiwum.certgen_publigo.index')->with('success', 'Szkolenie zostało dodane.');
    }
    public function destroy($id)
    {
        try {
            // Znajdź szkolenie po ID
            $szkolenie = Publigo::findOrFail($id);
    
            // Usuń szkolenie
            $szkolenie->delete();
    
            return redirect()->route('archiwum.certgen_publigo.index')->with('success', 'Szkolenie zostało usunięte.');
        } catch (\Exception $e) {
            return redirect()->route('archiwum.certgen_publigo.index')->with('error', 'Wystąpił błąd podczas usuwania: ' . $e->getMessage());
        }
    }
    public function edit($id)
    {
        $szkolenie = Publigo::findOrFail($id);
        $instructors = Instructor::all(); // Pobranie listy instruktorów
        return view('archiwum.certgen_publigo.edit', compact('szkolenie', 'instructors'));
    }
             
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'is_paid' => 'boolean',
            'type' => 'required|in:online,offline',
            'category' => 'required|in:open,closed',
            'instructor_id' => 'nullable|exists:instructors,id',
            'certificate_format' => 'nullable|string|max:255',
        ]);
    
        $szkolenie = Publigo::findOrFail($id);
        $szkolenie->update($validated);
    
        return redirect()->route('archiwum.certgen_publigo.index')->with('success', 'Szkolenie zostało zaktualizowane.');
    }
    
    
}
