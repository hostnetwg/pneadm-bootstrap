<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Publigo;

class PubligoController extends Controller
{
    public function index(Request $request)
    {
        // Pobranie parametrów sortowania
        $sortBy = $request->query('sort', 'start_date'); // Domyślnie sortujemy po start_date
        $order = $request->query('order', 'desc'); // Domyślnie od najnowszego do najstarszego
    
        // Pobranie danych z bazy
        $szkolenia = Publigo::orderBy($sortBy, $order)->paginate(10);
    
        return view('archiwum.certgen_publigo.index', compact('szkolenia'));
    }
    public function create()
    {
        return view('archiwum.certgen_publigo.create');
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
        return view('archiwum.certgen_publigo.edit', compact('szkolenie'));
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
            'platform' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string|max:255',
            'meeting_password' => 'nullable|string|max:255',
        ]);
    
        $szkolenie = Publigo::findOrFail($id);
        $szkolenie->update($validated);
    
        return redirect()->route('archiwum.certgen_publigo.index')->with('success', 'Szkolenie zostało zaktualizowane.');
    }
    
}
