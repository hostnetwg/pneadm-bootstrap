<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesController extends Controller
{
    /**
     * Wyświetla listę nowych zamówień z tabeli zamowienia_FORM.
     */
    public function index(Request $request)
    {
        // Liczba rekordów na stronę (domyślnie 50)
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search', '');
        $filter = $request->get('filter', ''); // nowy parametr dla filtra
        
        // Budujemy zapytanie
        $query = DB::connection('mysql_certgen')->table('zamowienia_FORM');
        
        // Dodajemy filtr dla nowych zamówień (bez numeru faktury i niezakończone)
        if ($filter === 'new') {
            $query->where(function($q) {
                $q->whereNull('nr_fakury')
                  ->orWhere('nr_fakury', '')
                  ->orWhere('nr_fakury', '0');
            })->where(function($q) {
                $q->whereNull('status_zakonczone')
                  ->orWhere('status_zakonczone', '!=', 1);
            });
        }
        
        // Dodajemy wyszukiwanie jeśli podano frazę
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('konto_imie_nazwisko', 'LIKE', "%{$search}%")
                  ->orWhere('konto_email', 'LIKE', "%{$search}%")
                  ->orWhere('produkt_nazwa', 'LIKE', "%{$search}%")
                  ->orWhere('nr_fakury', 'LIKE', "%{$search}%")
                  ->orWhere('notatki', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }
        
        // Pobieramy dane z paginacją lub wszystkie rekordy
        if ($perPage === 'all') {
            $zamowienia = $query->orderByDesc('id')->get();
            // Tworzymy własny obiekt paginacji dla wszystkich rekordów
            $zamowienia = new \Illuminate\Pagination\LengthAwarePaginator(
                $zamowienia,
                $zamowienia->count(),
                $zamowienia->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $zamowienia = $query->orderByDesc('id')->paginate($perPage);
        }

        return view('sales.index', compact('zamowienia', 'perPage', 'search', 'filter'));
    }

    /**
     * Wyświetla szczegóły zamówienia.
     */
    public function show($id)
    {
        $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_FORM')
            ->where('id', $id)
            ->first();

        if (!$zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        return view('sales.show', compact('zamowienie'));
    }

    /**
     * Oznacza zamówienie jako przetworzone.
     */
    public function markAsProcessed($id)
    {
        $updated = DB::connection('mysql_certgen')->table('zamowienia_FORM')
            ->where('id', $id)
            ->update([
                'przetworzone' => 1,
                'data_przetworzenia' => Carbon::now()
            ]);

        if ($updated) {
            return redirect()->route('sales.index')->with('success', 'Zamówienie zostało oznaczone jako przetworzone.');
        }

        return redirect()->route('sales.index')->with('error', 'Nie udało się przetworzyć zamówienia.');
    }
}
