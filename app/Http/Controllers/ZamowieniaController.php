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
