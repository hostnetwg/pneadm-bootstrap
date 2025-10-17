<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FormOrder;
use Carbon\Carbon;
use Exception;
use App\Services\PubligoApiService;

class FormOrdersController extends Controller
{
    /**
     * Wyświetla listę zamówień z tabeli form_orders (baza pneadm)
     */
    public function index(Request $request)
    {
        // Liczba rekordów na stronę (domyślnie 50)
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search', '');
        $filter = $request->get('filter', ''); // nowy parametr dla filtra
        
        // Budujemy zapytanie używając modelu Eloquent
        $query = FormOrder::query();
        
        // Dodajemy filtr dla nowych zamówień (bez numeru faktury i niezakończone)
        if ($filter === 'new') {
            $query->new(); // Używamy scope z modelu
        }
        
        // Dodajemy wyszukiwanie jeśli podano frazę
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('participant_name', 'LIKE', "%{$search}%")
                  ->orWhere('participant_email', 'LIKE', "%{$search}%")
                  ->orWhere('product_name', 'LIKE', "%{$search}%")
                  ->orWhere('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
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

        return view('form-orders.index', compact('zamowienia', 'perPage', 'search', 'filter'));
    }

    /**
     * Wyświetla szczegóły zamówienia.
     */
    public function show($id)
    {
        $zamowienie = FormOrder::find($id);

        if (!$zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        // Pobieramy poprzednie i następne zamówienie
        $prevOrder = FormOrder::where('id', '<', $id)
            ->orderByDesc('id')
            ->first();

        $nextOrder = FormOrder::where('id', '>', $id)
            ->orderBy('id')
            ->first();

        return view('form-orders.show', compact('zamowienie', 'prevOrder', 'nextOrder'));
    }

    /**
     * Aktualizuje zamówienie.
     */
    public function update(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);
            
            if (!$zamowienie) {
                return redirect()->route('form-orders.index')->with('error', 'Zamówienie nie zostało znalezione.');
            }
            
            // Aktualizacja danych
            $zamowienie->invoice_number = $request->input('invoice_number');
            $zamowienie->notes = $request->input('notes');
            $zamowienie->status_completed = $request->has('status_completed') ? 1 : 0;
            $zamowienie->updated_manually_at = now();
            $zamowienie->save();
            
            // Sprawdzamy, skąd użytkownik przyszedł
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            
            // Jeśli nie ma ukrytego pola, sprawdzamy referer (fallback)
            if (!$isFromShowPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            if ($isFromShowPage) {
                return redirect()->route('form-orders.show', $id)->with('success', 'Zamówienie zostało zaktualizowane.');
            } else {
                // Wracamy do listy z zachowaniem parametrów
                $redirectParams = [];
                if ($request->has('per_page')) $redirectParams['per_page'] = $request->input('per_page');
                if ($request->has('search')) $redirectParams['search'] = $request->input('search');
                if ($request->has('filter')) $redirectParams['filter'] = $request->input('filter');
                if ($request->has('page')) $redirectParams['page'] = $request->input('page');
                
                return redirect()->route('form-orders.index', $redirectParams)->with('success', 'Zamówienie zostało zaktualizowane.');
            }
        } catch (Exception $e) {
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            
            if (!$isFromShowPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            if ($isFromShowPage) {
                return redirect()->route('form-orders.show', $id)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            } else {
                $redirectParams = [];
                if ($request->has('per_page')) $redirectParams['per_page'] = $request->input('per_page');
                if ($request->has('search')) $redirectParams['search'] = $request->input('search');
                if ($request->has('filter')) $redirectParams['filter'] = $request->input('filter');
                if ($request->has('page')) $redirectParams['page'] = $request->input('page');
                
                return redirect()->route('form-orders.index', $redirectParams)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia.');
            }
        }
    }

    /**
     * Tworzy zamówienie w Publigo API na podstawie zamówienia z bazy
     */
    public function createPubligoOrder(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Sprawdzenie czy zamówienie ma dane Publigo
            if (empty($zamowienie->publigo_product_id) || empty($zamowienie->publigo_price_id)) {
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
                    'sent_at' => $zamowienie->publigo_sent_at ? $zamowienie->publigo_sent_at->format('d.m.Y H:i') : 'Nieznana data'
                ], 400);
            }

            // Sprawdzenie czy ma wszystkie wymagane dane
            $requiredFields = ['participant_email', 'participant_name', 'recipient_address', 'recipient_postal_code', 'recipient_city'];
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

            // Przygotowanie obiektu zgodnego z oczekiwaniami PubligoApiService
            // Musimy zmapować pola z nowego formatu na stary
            $orderDataForService = (object)[
                'konto_email' => $zamowienie->participant_email,
                'konto_imie_nazwisko' => $zamowienie->participant_name,
                'odb_nazwa' => $zamowienie->recipient_name,
                'odb_adres' => $zamowienie->recipient_address,
                'odb_kod' => $zamowienie->recipient_postal_code,
                'odb_poczta' => $zamowienie->recipient_city,
                'idProdPubligo' => $zamowienie->publigo_product_id,
                'price_idProdPubligo' => $zamowienie->publigo_price_id,
            ];

            // Przygotowanie i wysłanie zamówienia do Publigo
            $publigoService = new PubligoApiService();
            $orderData = $publigoService->prepareOrderData($orderDataForService);
            $result = $publigoService->createOrder($orderData);

            // Zwrócenie odpowiedzi
            if ($result['success']) {
                // Aktualizacja statusu zamówienia po udanym wysłaniu
                $zamowienie->publigo_sent = 1;
                $zamowienie->publigo_sent_at = now();
                $zamowienie->save();

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
            $zamowienie = FormOrder::find($id);

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Resetowanie statusu Publigo
            $zamowienie->publigo_sent = 0;
            $zamowienie->publigo_sent_at = null;
            $updated = $zamowienie->save();

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
