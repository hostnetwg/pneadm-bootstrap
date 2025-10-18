<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZamowieniaProdController extends Controller
{
    /**
     * Wyświetla listę produktów do formularzy zamówień z tabeli zamowienia_PROD.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search', '');
        
        // Budujemy zapytanie
        $query = DB::connection('mysql_certgen')->table('zamowienia_PROD');
        
        // Dodajemy wyszukiwanie jeśli podano frazę
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nazwa', 'LIKE', "%{$search}%")
                  ->orWhere('promocja', 'LIKE', "%{$search}%")
                  ->orWhere('idProdPubligo', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }
        
        // Pobieramy dane z paginacją
        if ($perPage === 'all') {
            $zamowienia = $query->orderByDesc('id')->get();
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

        return view('certgen.zamowienia_prod.index', compact('zamowienia', 'perPage', 'search'));
    }

    /**
     * Wyświetla szczegóły produktu formularza zamówienia wraz z wariantami cenowymi.
     */
    public function show($id)
    {
        $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_PROD')
            ->where('id', $id)
            ->first();

        if (!$zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        // Pobieramy poprzednie i następne zamówienie
        $prevOrder = DB::connection('mysql_certgen')->table('zamowienia_PROD')
            ->where('id', '<', $id)
            ->orderByDesc('id')
            ->first();

        $nextOrder = DB::connection('mysql_certgen')->table('zamowienia_PROD')
            ->where('id', '>', $id)
            ->orderBy('id')
            ->first();

        return view('certgen.zamowienia_prod.show', compact('zamowienie', 'prevOrder', 'nextOrder'));
    }

    /**
     * Wyświetla formularz dodawania nowego produktu.
     */
    public function create()
    {
        return view('certgen.zamowienia_prod.create');
    }

    /**
     * Zapisuje nowy produkt wraz z wariantami cenowymi.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nazwa' => 'required|string|max:500',
            'idProdPubligo' => 'nullable|integer',
            'price_id_ProdPubligo' => 'nullable|integer',
            'status' => 'required|boolean',
            'promocja' => 'nullable|string|max:255',
            'warianty' => 'nullable|array',
            'warianty.*.lp' => 'required|integer',
            'warianty.*.opis' => 'required|string|max:255',
            'warianty.*.cena' => 'required|numeric|min:0',
            'warianty.*.cena_prom' => 'nullable|numeric|min:0',
            'warianty.*.data_p_start' => 'nullable|date',
            'warianty.*.data_p_end' => 'nullable|date|after_or_equal:warianty.*.data_p_start',
            'warianty.*.status' => 'required|boolean',
        ]);

        try {
            // Dodaj produkt
            $produktId = DB::connection('mysql_certgen')->table('zamowienia_PROD')->insertGetId([
                'idProdPubligo' => $request->idProdPubligo,
                'price_id_ProdPubligo' => $request->price_id_ProdPubligo,
                'status' => $request->status,
                'nazwa' => $request->nazwa,
                'promocja' => $request->promocja,
            ]);

            // Dodaj warianty cenowe
            if ($request->has('warianty') && is_array($request->warianty)) {
                foreach ($request->warianty as $wariant) {
                    DB::connection('mysql_certgen')->table('zamowienia_PROD_warianty')->insert([
                        'id_PROD' => $produktId,
                        'lp' => $wariant['lp'],
                        'opis' => $wariant['opis'],
                        'cena' => $wariant['cena'],
                        'cena_prom' => $wariant['cena_prom'] ?? null,
                        'data_p_start' => $wariant['data_p_start'] ?? null,
                        'data_p_end' => $wariant['data_p_end'] ?? null,
                        'status' => $wariant['status'],
                    ]);
                }
            }

            return redirect()->route('certgen.zamowienia_prod.show', $produktId)
                ->with('success', 'Produkt został dodany pomyślnie!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas dodawania produktu: ' . $e->getMessage());
        }
    }

    /**
     * Wyświetla formularz edycji produktu.
     */
    public function edit($id)
    {
        $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_PROD')
            ->where('id', $id)
            ->first();

        if (!$zamowienie) {
            abort(404, 'Produkt nie został znaleziony.');
        }

        // Pobierz warianty cenowe
        $warianty = DB::connection('mysql_certgen')
            ->table('zamowienia_PROD_warianty')
            ->where('id_PROD', $id)
            ->orderBy('lp')
            ->get();

        return view('certgen.zamowienia_prod.edit', compact('zamowienie', 'warianty'));
    }

    /**
     * Aktualizuje produkt wraz z wariantami cenowymi.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nazwa' => 'required|string|max:500',
            'idProdPubligo' => 'nullable|integer',
            'price_id_ProdPubligo' => 'nullable|integer',
            'status' => 'required|boolean',
            'promocja' => 'nullable|string|max:255',
            'warianty' => 'nullable|array',
            'warianty.*.lp' => 'required|integer',
            'warianty.*.opis' => 'required|string|max:255',
            'warianty.*.cena' => 'required|numeric|min:0',
            'warianty.*.cena_prom' => 'nullable|numeric|min:0',
            'warianty.*.data_p_start' => 'nullable|date',
            'warianty.*.data_p_end' => 'nullable|date|after_or_equal:warianty.*.data_p_start',
            'warianty.*.status' => 'required|boolean',
        ]);

        try {
            // Aktualizuj produkt
            DB::connection('mysql_certgen')->table('zamowienia_PROD')
                ->where('id', $id)
                ->update([
                    'idProdPubligo' => $request->idProdPubligo,
                    'price_id_ProdPubligo' => $request->price_id_ProdPubligo,
                    'status' => $request->status,
                    'nazwa' => $request->nazwa,
                    'promocja' => $request->promocja,
                ]);

            // Usuń stare warianty
            DB::connection('mysql_certgen')
                ->table('zamowienia_PROD_warianty')
                ->where('id_PROD', $id)
                ->delete();

            // Dodaj nowe warianty
            if ($request->has('warianty') && is_array($request->warianty)) {
                foreach ($request->warianty as $wariant) {
                    DB::connection('mysql_certgen')->table('zamowienia_PROD_warianty')->insert([
                        'id_PROD' => $id,
                        'lp' => $wariant['lp'],
                        'opis' => $wariant['opis'],
                        'cena' => $wariant['cena'],
                        'cena_prom' => $wariant['cena_prom'] ?? null,
                        'data_p_start' => $wariant['data_p_start'] ?? null,
                        'data_p_end' => $wariant['data_p_end'] ?? null,
                        'status' => $wariant['status'],
                    ]);
                }
            }

            return redirect()->route('certgen.zamowienia_prod.show', $id)
                ->with('success', 'Produkt został zaktualizowany pomyślnie!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas aktualizacji produktu: ' . $e->getMessage());
        }
    }

    /**
     * Usuwa produkt wraz z wariantami cenowymi.
     */
    public function destroy($id)
    {
        try {
            // Usuń warianty cenowe
            DB::connection('mysql_certgen')
                ->table('zamowienia_PROD_warianty')
                ->where('id_PROD', $id)
                ->delete();

            // Usuń produkt
            $deleted = DB::connection('mysql_certgen')
                ->table('zamowienia_PROD')
                ->where('id', $id)
                ->delete();

            if ($deleted) {
                return redirect()->route('certgen.zamowienia_prod.index')
                    ->with('success', 'Produkt został usunięty pomyślnie!');
            } else {
                return redirect()->route('certgen.zamowienia_prod.index')
                    ->with('error', 'Nie udało się usunąć produktu.');
            }

        } catch (\Exception $e) {
            return redirect()->route('certgen.zamowienia_prod.index')
                ->with('error', 'Wystąpił błąd podczas usuwania produktu: ' . $e->getMessage());
        }
    }
}
