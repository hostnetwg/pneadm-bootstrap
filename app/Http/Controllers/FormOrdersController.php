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
                  ->orWhere('id', 'LIKE', "%{$search}%")
                  ->orWhere('publigo_product_id', 'LIKE', "%{$search}%"); // Dodano wyszukiwanie po Publigo ID
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
     * Wyświetla formularz tworzenia nowego zamówienia.
     */
    public function create()
    {
        // Pobierz kursy z Publigo (source_id_old = 'certgen_Publigo')
        $courses = \App\Models\Course::where('source_id_old', 'certgen_Publigo')
            ->orderBy('start_date', 'desc')
            ->get();
            
        return view('form-orders.create', compact('courses'));
    }

    /**
     * Zapisuje nowe zamówienie w bazie danych.
     */
    public function store(Request $request)
    {
        try {
            // Walidacja danych
            $request->validate([
                // Dane produktu/szkolenia
                'course_id' => 'required|exists:courses,id',
                'product_name' => 'nullable|string|max:255',
                'product_price' => 'nullable|numeric|min:0',
                'product_description' => 'nullable|string',
                
                // Dane uczestnika
                'participant_firstname' => 'required|string|max:100',
                'participant_lastname' => 'required|string|max:100',
                'participant_email' => 'required|email|max:255',
                
                // Dane zamawiającego
                'orderer_name' => 'required|string|max:255',
                'orderer_phone' => 'required|string|max:20',
                'orderer_email' => 'required|email|max:255',
                
                // Dane nabywcy
                'buyer_name' => 'required|string|max:255',
                'buyer_address' => 'required|string|max:255',
                'buyer_postal_code' => 'required|string|max:10',
                'buyer_city' => 'required|string|max:100',
                'buyer_nip' => 'nullable|string|max:20',
                
                // Dane odbiorcy
                'recipient_name' => 'nullable|string|max:255',
                'recipient_address' => 'nullable|string|max:255',
                'recipient_postal_code' => 'nullable|string|max:10',
                'recipient_city' => 'nullable|string|max:100',
                'recipient_nip' => 'nullable|string|max:20',
                
                // Dane Publigo (opcjonalne)
                'publigo_product_id' => 'nullable|integer',
                'publigo_price_id' => 'nullable|integer',
                
                // Uwagi do faktury
                'invoice_notes' => 'nullable|string',
                'invoice_payment_delay' => 'nullable|integer|min:0|max:31',
                
                // Notatki
                'notes' => 'nullable|string',
            ]);

            // Pobierz dane kursu
            $course = \App\Models\Course::findOrFail($request->course_id);
            
            // Ustaw dane Publigo na podstawie kursu
            $publigoProductId = $course->id_old; // ID produktu z Publigo
            $publigoPriceId = 1; // Domyślny price_id

            // Tworzenie nowego zamówienia
            $formOrder = FormOrder::create([
                'order_date' => now(),
                'product_id' => $course->id, // ID kursu z bazy
                'product_name' => $course->title,
                'product_price' => $request->product_price ?? 0, // Można ustawić ręcznie
                'product_description' => $course->description,
                'participant_name' => $request->participant_firstname . ' ' . $request->participant_lastname,
                'participant_email' => $request->participant_email,
                'orderer_name' => $request->orderer_name,
                'orderer_phone' => $request->orderer_phone,
                'orderer_email' => $request->orderer_email,
                'buyer_name' => $request->buyer_name,
                'buyer_address' => $request->buyer_address,
                'buyer_postal_code' => $request->buyer_postal_code,
                'buyer_city' => $request->buyer_city,
                'buyer_nip' => $request->buyer_nip,
                'recipient_name' => $request->recipient_name,
                'recipient_address' => $request->recipient_address,
                'recipient_postal_code' => $request->recipient_postal_code,
                'recipient_city' => $request->recipient_city,
                'recipient_nip' => $request->recipient_nip,
                'publigo_product_id' => $publigoProductId,
                'publigo_price_id' => $publigoPriceId,
                'invoice_notes' => $request->invoice_notes,
                'invoice_payment_delay' => $request->invoice_payment_delay,
                'notes' => $request->notes,
                'ip_address' => $request->ip(),
            ]);

            // Tworzenie uczestnika w tabeli form_order_participants
            \App\Models\FormOrderParticipant::create([
                'form_order_id' => $formOrder->id,
                'participant_firstname' => $request->participant_firstname,
                'participant_lastname' => $request->participant_lastname,
                'participant_email' => $request->participant_email,
                'is_primary' => true,
            ]);

            return redirect()->route('form-orders.show', $formOrder->id)
                ->with('success', 'Zamówienie zostało pomyślnie utworzone.');

        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas tworzenia zamówienia: ' . $e->getMessage());
        }
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
            
            // Sprawdzamy, skąd użytkownik przyszedł
            $isFromShowPage = $request->has('from_show_page') && $request->input('from_show_page') == '1';
            $isFromEditPage = $request->has('from_edit_page') && $request->input('from_edit_page') == '1';
            
            // Jeśli przychodzi z pełnej strony edycji, aktualizuj wszystkie pola
            if ($isFromEditPage) {
                // Łączymy imię i nazwisko dla pola participant_name w form_orders
                $participantName = trim($request->input('participant_firstname') . ' ' . $request->input('participant_lastname'));
                
                $zamowienie->fill([
                    'product_name' => $request->input('product_name'),
                    'product_price' => $request->input('product_price'),
                    'participant_name' => $participantName,
                    'participant_email' => $request->input('participant_email'),
                    'orderer_name' => $request->input('orderer_name'),
                    'orderer_phone' => $request->input('orderer_phone'),
                    'orderer_email' => $request->input('orderer_email'),
                    'buyer_name' => $request->input('buyer_name'),
                    'buyer_nip' => $request->input('buyer_nip'),
                    'buyer_address' => $request->input('buyer_address'),
                    'buyer_postal_code' => $request->input('buyer_postal_code'),
                    'buyer_city' => $request->input('buyer_city'),
                    'recipient_name' => $request->input('recipient_name'),
                    'recipient_address' => $request->input('recipient_address'),
                    'recipient_postal_code' => $request->input('recipient_postal_code'),
                    'recipient_city' => $request->input('recipient_city'),
                    'recipient_nip' => $request->input('recipient_nip'),
                    'invoice_number' => $request->input('invoice_number'),
                    'invoice_payment_delay' => $request->input('invoice_payment_delay'),
                    'invoice_notes' => $request->input('invoice_notes'),
                    'notes' => $request->input('notes'),
                    'status_completed' => $request->has('status_completed') ? 1 : 0,
                ]);
            } else {
                // Aktualizacja podstawowych danych (z listy lub show)
                $zamowienie->invoice_number = $request->input('invoice_number');
                $zamowienie->notes = $request->input('notes');
                $zamowienie->status_completed = $request->has('status_completed') ? 1 : 0;
            }
            
            $zamowienie->updated_manually_at = now();
            $zamowienie->save();
            
            // Aktualizuj dane uczestnika w tabeli form_order_participants
            if ($isFromEditPage) {
                $participant = \App\Models\FormOrderParticipant::where('form_order_id', $id)
                    ->where('is_primary', true)
                    ->first();
                
                if ($participant) {
                    $participant->update([
                        'participant_firstname' => $request->input('participant_firstname'),
                        'participant_lastname' => $request->input('participant_lastname'),
                        'participant_email' => $request->input('participant_email'),
                    ]);
                }
            }
            
            // Jeśli nie ma ukrytego pola, sprawdzamy referer (fallback)
            if (!$isFromShowPage && !$isFromEditPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            // Przekierowanie w zależności od źródła
            if ($isFromEditPage) {
                return redirect()->route('form-orders.show', $id)->with('success', 'Zamówienie zostało zaktualizowane.');
            } elseif ($isFromShowPage) {
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
            $isFromEditPage = $request->has('from_edit_page') && $request->input('from_edit_page') == '1';
            
            if (!$isFromShowPage && !$isFromEditPage) {
                $referer = $request->header('referer');
                if ($referer) {
                    $refererPath = parse_url($referer, PHP_URL_PATH);
                    $showPagePath = '/form-orders/' . $id;
                    $isFromShowPage = $refererPath === $showPagePath;
                }
            }
            
            if ($isFromEditPage) {
                return redirect()->route('form-orders.edit', $id)->with('error', 'Wystąpił błąd podczas aktualizacji zamówienia: ' . $e->getMessage());
            } elseif ($isFromShowPage) {
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
                'id' => $zamowienie->id, // Dodanie brakującego pola ID
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

    /**
     * Wyświetla formularz edycji zamówienia
     */
    public function edit($id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);
            
            // Pobierz dane uczestnika z tabeli form_order_participants
            $participant = \App\Models\FormOrderParticipant::where('form_order_id', $id)
                ->where('is_primary', true)
                ->first();
            
            return view('form-orders.edit', compact('zamowienie', 'participant'));
        } catch (Exception $e) {
            return redirect()->route('form-orders.index')->with('error', 'Zamówienie nie zostało znalezione.');
        }
    }

    /**
     * Usuwa zamówienie (soft delete)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);
            
            // Soft delete (przeniesienie do kosza)
            $zamowienie->delete();
            
            // Parametry przekierowania
            $redirectParams = [];
            if ($request->has('per_page')) $redirectParams['per_page'] = $request->input('per_page');
            if ($request->has('search')) $redirectParams['search'] = $request->input('search');
            if ($request->has('filter')) $redirectParams['filter'] = $request->input('filter');
            if ($request->has('page')) $redirectParams['page'] = $request->input('page');
            
            return redirect()->route('form-orders.index', $redirectParams)
                ->with('success', 'Zamówienie zostało usunięte i przeniesione do kosza.');
        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Wystąpił błąd podczas usuwania zamówienia: ' . $e->getMessage());
        }
    }
}
