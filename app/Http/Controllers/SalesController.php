<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use App\Services\PubligoApiService;

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
                  ->orWhere('id', 'LIKE', "%{$search}%")
                  ->orWhere('idProdPubligo', 'LIKE', "%{$search}%"); // Dodano wyszukiwanie po Publigo ID
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
    public function show(Request $request, $id)
    {
        $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_FORM')
            ->where('id', $id)
            ->first();

        if (!$zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        // Sprawdzamy czy mamy filtrować tylko niewprowadzone zamówienia
        $filterNew = $request->has('filter_new') && $request->input('filter_new') == '1';

        // Pobieramy poprzednie i następne zamówienie
        if ($filterNew) {
            // Filtrujemy tylko niewprowadzone zamówienia (bez numeru faktury i niezakończone)
            $prevOrder = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', '<', $id)
                ->where(function($q) {
                    $q->whereNull('nr_fakury')
                      ->orWhere('nr_fakury', '')
                      ->orWhere('nr_fakury', '0');
                })
                ->where(function($q) {
                    $q->whereNull('status_zakonczone')
                      ->orWhere('status_zakonczone', '!=', 1);
                })
                ->orderByDesc('id')
                ->first();

            $nextOrder = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', '>', $id)
                ->where(function($q) {
                    $q->whereNull('nr_fakury')
                      ->orWhere('nr_fakury', '')
                      ->orWhere('nr_fakury', '0');
                })
                ->where(function($q) {
                    $q->whereNull('status_zakonczone')
                      ->orWhere('status_zakonczone', '!=', 1);
                })
                ->orderBy('id')
                ->first();
        } else {
            // Standardowe pobieranie wszystkich zamówień
            $prevOrder = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', '<', $id)
                ->orderByDesc('id')
                ->first();

            $nextOrder = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', '>', $id)
                ->orderBy('id')
                ->first();
        }

        // Debug: sprawdźmy czy zmienna jest ustawiona
        \Log::info('FilterNew value: ' . ($filterNew ? 'true' : 'false'));
        
        return view('sales.show', compact('zamowienie', 'prevOrder', 'nextOrder', 'filterNew'));
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

    /**
     * Aktualizuje zamówienie.
     */
    public function update(Request $request, $id)
    {
        try {
            $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_FORM')->find($id);
            
            if (!$zamowienie) {
                return redirect()->route('sales.index')->with('error', 'Zamówienie nie zostało znalezione.');
            }
            
            $data = [
                'nr_fakury' => $request->input('nr_fakury'),
                'notatki' => $request->input('notatki'),
                'status_zakonczone' => $request->has('status_zakonczone') ? 1 : 0,
                'data_update' => now()
            ];
            
            DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', $id)
                ->update($data);
            
            // Sprawdzamy, skąd użytkownik przyszedł
            // Najpierw sprawdzamy ukryte pole z formularza (najbardziej niezawodne)
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            
            // Jeśli nie ma ukrytego pola, sprawdzamy referer (fallback dla starszych formularzy)
            if (!$isFromShowPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/sales/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            if ($isFromShowPage) {
                // Jeśli użytkownik był na stronie szczegółów, wracamy tam
                return redirect()->route('sales.show', $id)->with('success', 'Zamówienie zostało zaktualizowane.');
            } else {
                // Jeśli użytkownik był na liście, wracamy do listy z zachowaniem parametrów
                $redirectParams = [];
                if ($request->has('per_page')) $redirectParams['per_page'] = $request->input('per_page');
                if ($request->has('search')) $redirectParams['search'] = $request->input('search');
                if ($request->has('filter')) $redirectParams['filter'] = $request->input('filter');
                if ($request->has('page')) $redirectParams['page'] = $request->input('page');
                
                return redirect()->route('sales.index', $redirectParams)->with('success', 'Zamówienie zostało zaktualizowane.');
            }
        } catch (Exception $e) {
            // W przypadku błędu, sprawdzamy skąd użytkownik przyszedł
            // Najpierw sprawdzamy ukryte pole z formularza (najbardziej niezawodne)
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            
            // Jeśli nie ma ukrytego pola, sprawdzamy referer (fallback dla starszych formularzy)
            if (!$isFromShowPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/sales/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            if ($isFromShowPage) {
                return redirect()->route('sales.show', $id)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            } else {
                // Przekierowanie z powrotem do listy z zachowaniem parametrów
                $redirectParams = [];
                if ($request->has('per_page')) $redirectParams['per_page'] = $request->input('per_page');
                if ($request->has('search')) $redirectParams['search'] = $request->input('search');
                if ($request->has('filter')) $redirectParams['filter'] = $request->input('filter');
                if ($request->has('page')) $redirectParams['page'] = $request->input('page');
                
                return redirect()->route('sales.index', $redirectParams)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            }
        }
    }

    /**
     * Tworzy zamówienie w Publigo API na podstawie zamówienia z bazy
     */
    public function createPubligoOrder(Request $request, $id)
    {
        try {
            // Pobranie zamówienia z bazy
            $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', $id)
                ->first();

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Sprawdzenie czy zamówienie ma dane Publigo
            if (empty($zamowienie->idProdPubligo) || empty($zamowienie->price_idProdPubligo)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu Publigo. Zamówienie nie może być przesłane do Publigo.'
                ], 400);
            }

            // Sprawdzenie czy zamówienie już zostało wysłane do Publigo
            if ($zamowienie->publigo_sent == 1) {
                return response()->json([
                    'success' => false,
                    'error' => 'To zamówienie zostało już wysłane do Publigo.',
                    'sent_at' => $zamowienie->publigo_sent_at ? \Carbon\Carbon::parse($zamowienie->publigo_sent_at)->format('d.m.Y H:i') : 'Nieznana data'
                ], 400);
            }

            // Sprawdzenie czy ma wszystkie wymagane dane
            $requiredFields = ['konto_email', 'konto_imie_nazwisko', 'odb_adres', 'odb_kod', 'odb_poczta'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($zamowienie->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak wymaganych danych: ' . implode(', ', $missingFields)
                ], 400);
            }

            // Przygotowanie i wysłanie zamówienia do Publigo
            $publigoService = new PubligoApiService();
            $orderData = $publigoService->prepareOrderData($zamowienie);
            $result = $publigoService->createOrder($orderData);

            // Zwrócenie odpowiedzi
            if ($result['success']) {
                // Aktualizacja statusu zamówienia po udanym wysłaniu
                DB::connection('mysql_certgen')->table('zamowienia_FORM')
                    ->where('id', $id)
                    ->update([
                        'publigo_sent' => 1,
                        'publigo_sent_at' => now()
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'order_data' => $orderData,
                    'publigo_response' => $result['response'],
                    'sent_at' => now()->format('d.m.Y H:i')
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'order_data' => $orderData,
                    'publigo_response' => $result['response'],
                    'http_code' => $result['http_code']
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resetuje status Publigo dla zamówienia (tylko dla administratorów)
     */
    public function resetPubligoStatus(Request $request, $id)
    {
        // Sprawdzenie uprawnień - tylko admin i super_admin
        if (!auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Brak uprawnień do resetowania statusu Publigo.'
            ], 403);
        }

        try {
            // Pobranie zamówienia z bazy
            $zamowienie = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', $id)
                ->first();

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Resetowanie statusu Publigo
            $updated = DB::connection('mysql_certgen')->table('zamowienia_FORM')
                ->where('id', $id)
                ->update([
                    'publigo_sent' => 0,
                    'publigo_sent_at' => null
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status Publigo został zresetowany. Zamówienie może być ponownie wysłane.',
                    'reset_at' => now()->format('d.m.Y H:i')
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie udało się zresetować statusu Publigo.'
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas resetowania: ' . $e->getMessage()
            ], 500);
        }
    }
}
