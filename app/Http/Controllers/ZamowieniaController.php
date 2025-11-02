<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Zamowienia;
use Illuminate\Support\Facades\DB;

class ZamowieniaController extends Controller
{
    public function index(Request $request)
    {
        // Pobranie parametrów sortowania
        $sortBy = $request->query('sort', 'id_zam');
        $order = $request->query('order', 'desc');

        try {
            // Używamy modelu Eloquent
            $zamowienia = Zamowienia::orderBy($sortBy, $order)->paginate(15);
        } catch (\Exception $e) {
            // Jeśli sortowanie nie działa, używamy domyślnego sortowania po ID
            try {
                $zamowienia = Zamowienia::orderBy('id_zam', 'desc')->paginate(15);
            } catch (\Exception $e2) {
                // Jeśli nawet to nie działa, zwracamy pustą kolekcję z paginacją
                $zamowienia = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    15,
                    1
                );
                session()->flash('error', 'Nie można połączyć się z bazą danych Certgen: ' . $e2->getMessage());
            }
        }

        return view('certgen.zamowienia.index', compact('zamowienia'));
    }

    public function show($id)
    {
        try {
            $zamowienie = Zamowienia::findOrFail($id);
            
            // Pobieranie poprzedniego i następnego rekordu
            $previous = Zamowienia::where('id', '<', $id)->orderBy('id', 'desc')->first();
            $next = Zamowienia::where('id', '>', $id)->orderBy('id', 'asc')->first();
            
            return view('certgen.zamowienia.show', compact('zamowienie', 'previous', 'next'));
        } catch (\Exception $e) {
            return redirect()->route('certgen.zamowienia.index')
                ->with('error', 'Rekord nie został znaleziony lub wystąpił błąd: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $zamowienie = Zamowienia::findOrFail($id);
            return view('certgen.zamowienia.edit', compact('zamowienie'));
        } catch (\Exception $e) {
            return redirect()->route('certgen.zamowienia.index')
                ->with('error', 'Rekord nie został znaleziony lub wystąpił błąd: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $zamowienie = Zamowienia::findOrFail($id);
            
            $validated = $request->validate([
                'id_zam' => 'nullable|string|max:255',
                'data_wplaty' => 'nullable|date',
                'imie' => 'nullable|string|max:255',
                'nazwisko' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'kod' => 'nullable|string|max:255',
                'poczta' => 'nullable|string|max:255',
                'adres' => 'nullable|string|max:500',
                'produkt_id' => 'nullable|string|max:255',
                'produkt_nazwa' => 'nullable|string|max:500',
                'produkt_cena' => 'nullable|numeric|min:0',
                'wysylka' => 'nullable|integer',
                'id_edu' => 'nullable|integer',
                'NR' => 'nullable|string|max:255',
            ]);

            // Usuwamy tylko NULL i puste stringi, ale zachowujemy 0 i '0'
            $dataToUpdate = array_filter($validated, function($value) {
                return $value !== null && $value !== '';
            });

            $zamowienie->update($dataToUpdate);

            return redirect()->route('certgen.zamowienia.show', $id)
                ->with('success', 'Zakup został zaktualizowany.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('certgen.zamowienia.edit', $id)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('certgen.zamowienia.edit', $id)
                ->with('error', 'Wystąpił błąd podczas aktualizacji: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $zamowienie = Zamowienia::findOrFail($id);
            $zamowienie->delete();

            return redirect()->route('certgen.zamowienia.index')
                ->with('success', 'Zakup został usunięty.');
        } catch (\Exception $e) {
            return redirect()->route('certgen.zamowienia.index')
                ->with('error', 'Wystąpił błąd podczas usuwania: ' . $e->getMessage());
        }
    }
}
