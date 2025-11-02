<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FormOrder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\PubligoApiService;
use App\Services\IfirmaApiService;

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
            $zamowienia = $query->with('marketingCampaign.sourceType')->orderByDesc('id')->get();
            // Tworzymy własny obiekt paginacji dla wszystkich rekordów
            $zamowienia = new \Illuminate\Pagination\LengthAwarePaginator(
                $zamowienia,
                $zamowienia->count(),
                $zamowienia->count(),
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $zamowienia = $query->with('marketingCampaign.sourceType')->orderByDesc('id')->paginate($perPage);
        }

        // Pobierz informacje o duplikatach dla wyświetlanych zamówień
        $duplicateInfo = [];
        $duplicateGroups = FormOrder::duplicates()->get();
        foreach ($duplicateGroups as $group) {
            $orderIds = explode(',', $group->order_ids);
            foreach ($orderIds as $orderId) {
                $duplicateInfo[$orderId] = [
                    'count' => $group->duplicate_count,
                    'is_duplicate' => true,
                    'group_email' => $group->participant_email,
                    'group_product_id' => $group->publigo_product_id
                ];
            }
        }

        // Policz grupy wymagające interwencji (needs-action + multiple-invoices)
        $urgentDuplicatesCount = 0;
        foreach ($duplicateGroups as $duplicate) {
            $orderIds = array_map('trim', explode(',', $duplicate->order_ids));
            $orders = FormOrder::whereIn('id', $orderIds)->get();
            
            $activeCount = 0;
            $mainCount = 0;
            
            foreach ($orders as $order) {
                if ($order->has_invoice) {
                    $mainCount++;
                } else if (!$order->is_completed) {
                    $activeCount++;
                }
            }
            
            // Wymaga akcji = więcej niż 1 aktywne LUB ma fakturę ale są jeszcze aktywne duplikaty
            $needsAction = ($activeCount > 1) || ($mainCount > 0 && $activeCount > 0);
            // Za dużo faktur = więcej niż 1 zamówienie z fakturą w grupie
            $hasMultipleInvoices = ($mainCount > 1);
            
            if ($needsAction || $hasMultipleInvoices) {
                $urgentDuplicatesCount++;
            }
        }

        return view('form-orders.index', compact('zamowienia', 'perPage', 'search', 'filter', 'duplicateInfo', 'urgentDuplicatesCount'));
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
    public function show(Request $request, $id)
    {
        $zamowienie = FormOrder::with('marketingCampaign.sourceType')->find($id);

        if (!$zamowienie) {
            abort(404, 'Zamówienie nie zostało znalezione.');
        }

        // Sprawdzamy czy mamy filtrować tylko niewprowadzone zamówienia
        $filterNew = $request->has('filter_new') && $request->input('filter_new') == '1';
        
        // Sprawdzamy czy mamy filtrować po ID szkolenia
        $courseId = $request->input('course_id');

        // Pobieramy poprzednie i następne zamówienie
        if ($filterNew || $courseId) {
            // Filtrujemy zamówienia
            $prevQuery = FormOrder::where('id', '<', $id);
            $nextQuery = FormOrder::where('id', '>', $id);
            
            // Dodajemy filtr dla niewprowadzonych zamówień
            if ($filterNew) {
                $prevQuery->where(function($q) {
                    $q->whereNull('invoice_number')
                      ->orWhere('invoice_number', '')
                      ->orWhere('invoice_number', '0');
                })->where(function($q) {
                    $q->where('status_completed', '!=', 1)
                      ->orWhereNull('status_completed');
                });
                
                $nextQuery->where(function($q) {
                    $q->whereNull('invoice_number')
                      ->orWhere('invoice_number', '')
                      ->orWhere('invoice_number', '0');
                })->where(function($q) {
                    $q->where('status_completed', '!=', 1)
                      ->orWhereNull('status_completed');
                });
            }
            
            // Dodajemy filtr po ID szkolenia
            if ($courseId) {
                $prevQuery->where('publigo_product_id', $courseId);
                $nextQuery->where('publigo_product_id', $courseId);
            }
            
            $prevOrder = $prevQuery->orderByDesc('id')->first();
            $nextOrder = $nextQuery->orderBy('id')->first();
        } else {
            // Standardowe pobieranie wszystkich zamówień
            $prevOrder = FormOrder::where('id', '<', $id)
                ->orderByDesc('id')
                ->first();

            $nextOrder = FormOrder::where('id', '>', $id)
                ->orderBy('id')
                ->first();
        }

        return view('form-orders.show', compact('zamowienie', 'prevOrder', 'nextOrder', 'filterNew'));
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
                // Zachowujemy parametry filtrów przy przekierowaniu
                $redirectParams = [];
                if ($request->has('filter_new')) $redirectParams['filter_new'] = $request->input('filter_new');
                if ($request->has('course_id')) $redirectParams['course_id'] = $request->input('course_id');
                
                return redirect()->route('form-orders.show', array_merge(['id' => $id], $redirectParams))->with('success', 'Zamówienie zostało zaktualizowane.');
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
     * Wystawia fakturę pro forma w iFirma.pl na podstawie zamówienia
     */
    public function createIfirmaProForma(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.'
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.'
                ], 400);
            }

            // Przygotowanie uwag do faktury
            // Sprawdź, czy użytkownik przesłał niestandardowe uwagi (edytowane w formularzu)
            $uwagi = $request->input('custom_remarks', '');
            
            if (empty(trim($uwagi))) {
                // Jeśli nie ma niestandardowych uwag, generuj automatycznie dane odbiorcy
                $recipientData = [];
                if (!empty($zamowienie->recipient_name)) {
                    $recipientData[] = $zamowienie->recipient_name;
                }
                if (!empty($zamowienie->recipient_address)) {
                    $recipientData[] = $zamowienie->recipient_address;
                }
                if (!empty($zamowienie->recipient_postal_code) && !empty($zamowienie->recipient_city)) {
                    $recipientData[] = $zamowienie->recipient_postal_code . ' ' . $zamowienie->recipient_city;
                }

                $uwagi = "ODBIORCA:\n";
                if (!empty($recipientData)) {
                    $uwagi .= implode("\n", $recipientData);
                }
            }
            
            // ZAWSZE na końcu dodaj identyfikator zamówienia (bez "---")
            // Dzięki temu każda faktura pro-forma będzie miała powiązanie z zamówieniem
            if (!empty(trim($uwagi))) {
                $uwagi .= "\n\npnedu.pl #{$zamowienie->id}";
            } else {
                $uwagi = "pnedu.pl #{$zamowienie->id}";
            }

            // Przygotowanie danych kontrahenta - tylko pola z wartościami
            $kontrahent = [
                'Nazwa' => $zamowienie->buyer_name,
                'Kraj' => 'PL'
            ];
            
            // Dodajemy tylko pola, które mają wartości (nie wysyłamy pustych stringów)
            if (!empty($zamowienie->buyer_address)) {
                $kontrahent['Ulica'] = $zamowienie->buyer_address;
            }
            
            if (!empty($zamowienie->buyer_postal_code)) {
                $kontrahent['KodPocztowy'] = $zamowienie->buyer_postal_code;
            }
            
            if (!empty($zamowienie->buyer_city)) {
                $kontrahent['Miejscowosc'] = $zamowienie->buyer_city;
            }
            
            // NIP tylko jeśli jest podany (nie wysyłamy pustego string)
            if (!empty($zamowienie->buyer_nip)) {
                $nip = preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip);
                if (!empty($nip)) {
                    $kontrahent['NIP'] = $nip;
                }
            }

            // Przygotowanie pozycji faktury
            // Zgodnie z dokumentacją API iFirma - pozycja powinna zawierać:
            // - NazwaPelna (wymagane) - nazwa produktu/usługi
            // - Ilosc (wymagane) - ilość jako float
            // - CenaJednostkowa (wymagane) - cena jednostkowa netto
            // - Jednostka (opcjonalne) - jednostka miary
            // - StawkaVat (wymagane jeśli podatnik VAT) - stawka VAT jako float (0.23 dla 23%)
            // - TypStawkiVat (wymagane) - typ stawki: 'PRC' dla procent, 'ZW' dla zwolnionego
            // Przygotowanie pozycji faktury pro forma
            // Zgodnie z dokumentacją API iFirma - próba z różnymi wariantami nazw pól
            $pozycja = [
                'NazwaPelna' => $zamowienie->product_name,
                'Ilosc' => 1.0,
                'CenaJednostkowa' => round((float)$zamowienie->product_price, 2),
                'Jednostka' => 'sztuk',
                'StawkaVat' => 0.23,
                'TypStawkiVat' => 'PRC'
            ];

            // Jeśli konto jest zwolnione z VAT (nievatowiec) – dostosuj stawkę
            if (config('services.ifirma.vat_exempt')) {
                // Zgodnie z dokumentacją dla zwolnienia: TypStawkiVat = 'ZW', usuń/wyzeruj StawkaVat i dodaj podstawę prawną
                unset($pozycja['StawkaVat']);
                $pozycja['TypStawkiVat'] = 'ZW';
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
            }

            // Przygotowanie danych faktury pro forma dla iFirma
            // Zgodnie z dokumentacją API: https://api.ifirma.pl/wystawianie-faktury-proforma/
            // Wymagane pola dla PRO FORMA (NIE są takie same jak dla zwykłej faktury!):
            // - LiczOd (NET/BRT) - TAK
            // - TypFakturyKrajowej (SPRZ/BUD/ZAL) - TAK
            // - DataWystawienia - TAK
            // - SposobZaplaty - TAK (wartości: GTK, POB, PRZ, KAR, PZA, CZK, KOM, BAR, DOT, PAL, ALG, P24, TPA, ELE)
            // - RodzajPodpisuOdbiorcy (OUP/UPO/BPO/BWO) - TAK
            // UWAGA: DataSprzedazy NIE jest używana w fakturach pro forma (tylko w zwykłych fakturach)!
            
            $invoiceData = [
                'LiczOd' => 'NET',
                'TypFakturyKrajowej' => 'SPRZ', // SPRZ = krajowa sprzedaż
                'DataWystawienia' => now()->format('Y-m-d'),
                // DataSprzedazy - NIE używamy dla pro forma (powoduje błąd walidacji)
                'SposobZaplaty' => 'PRZ', // PRZ = przelew
                'RodzajPodpisuOdbiorcy' => 'BWO', // brak podpisu odbiorcy i wystawcy
                'NumerZamowienia' => (string)$zamowienie->id,
                'Kontrahent' => $kontrahent,
                'Pozycje' => [$pozycja]
            ];
            
            // Termin płatności - opcjonalne
            $invoiceData['TerminPlatnosci'] = now()->addDays(
                !empty($zamowienie->invoice_payment_delay) ? (int)$zamowienie->invoice_payment_delay : 14
            )->format('Y-m-d');

            // Uwagi - dodajemy tylko jeśli są (nie pusty string)
            if (!empty(trim($uwagi ?? ''))) {
                $invoiceData['Uwagi'] = trim($uwagi);
            }

            // Numer konta bankowego - dodajemy tylko jeśli jest skonfigurowany
            $bankAccount = config('services.ifirma.bank_account', '');
            if (!empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            // Logowanie danych przed wysłaniem do API
            Log::info('iFirma Pro Forma Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'invoice_data_json' => json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ]);

            // Wystawienie faktury pro forma przez API iFirma
            $ifirmaService = new IfirmaApiService();
            $result = $ifirmaService->createProFormaInvoice($invoiceData);

            // Logowanie pełnej odpowiedzi dla debugowania
            Log::info('iFirma Pro Forma Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
                'full_response' => $result
            ]);

            // Zwrócenie odpowiedzi
            if ($result['status'] === 'success') {
                // Pobierz Identyfikator z odpowiedzi
                $invoiceId = null;
                if (isset($result['data']['response']['Identyfikator'])) {
                    $invoiceId = $result['data']['response']['Identyfikator'];
                } elseif (isset($result['data']['Identyfikator'])) {
                    $invoiceId = $result['data']['Identyfikator'];
                }

                // Pobierz pełny numer faktury (np. "1/11/2025/ProForma") zamiast samego ID
                $invoiceNumber = null;
                $fullInvoiceData = null;
                
                if (!empty($invoiceId)) {
                    try {
                        // Pobierz szczegóły faktury z iFirma, aby uzyskać PelnyNumer
                        $invoiceDetails = $ifirmaService->getProFormaInvoice($invoiceId);
                        
                        if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                            $fullInvoiceData = $invoiceDetails['data'];
                            
                            // Pełny numer faktury jest w polu "PelnyNumer"
                            if (isset($fullInvoiceData['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                            } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                            }
                        }
                        
                        Log::info('iFirma Pro Forma - szczegóły pobrane', [
                            'invoice_id' => $invoiceId,
                            'invoice_number' => $invoiceNumber,
                            'details' => $fullInvoiceData
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                            'invoice_id' => $invoiceId,
                            'error' => $e->getMessage()
                        ]);
                        // Jeśli nie udało się pobrać, użyj Identyfikatora jako fallback
                        $invoiceNumber = $invoiceId;
                    }
                }

                // Aktualizacja numeru faktury w zamówieniu (jeśli nie istnieje)
                if (!empty($invoiceNumber) && empty($zamowienie->invoice_number)) {
                    $zamowienie->invoice_number = $invoiceNumber;
                    $zamowienie->save();
                }

                // Wysyłka e-mailem (jeśli zaznaczono checkbox)
                $sendEmail = $request->input('send_email', false);
                $emailsSent = [];
                $emailErrors = [];
                
                if ($sendEmail && !empty($invoiceId)) {
                    // Zbierz unikalne adresy e-mail (małe litery, bez duplikatów)
                    $emails = [];
                    
                    // E-mail zamawiającego
                    if (!empty($zamowienie->orderer_email)) {
                        $emails[] = strtolower(trim($zamowienie->orderer_email));
                    }
                    
                    // E-mail uczestnika (jeśli różny od zamawiającego)
                    if (!empty($zamowienie->participant_email)) {
                        $participantEmail = strtolower(trim($zamowienie->participant_email));
                        if (!in_array($participantEmail, $emails)) {
                            $emails[] = $participantEmail;
                        }
                    }
                    
                    // Wysyłka do wszystkich adresów
                    foreach ($emails as $email) {
                        try {
                            $sendResult = $ifirmaService->sendProFormaByEmail(
                                $invoiceId, 
                                $email, 
                                $invoiceNumber, 
                                $zamowienie->id
                            );
                            
                            if ($sendResult['status'] === 'success') {
                                $emailsSent[] = $email;
                                Log::info('Faktura pro forma wysłana e-mailem', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email
                                ]);
                            } else {
                                $emailErrors[] = [
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd'
                                ];
                                Log::warning('Błąd wysyłki faktury pro forma', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd'
                                ]);
                            }
                        } catch (Exception $e) {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $e->getMessage()
                            ];
                            Log::error('Exception podczas wysyłki faktury pro forma', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'exception' => $e->getMessage()
                            ]);
                        }
                    }
                }

                // Przygotuj wiadomość dla użytkownika
                $message = 'Faktura pro forma została pomyślnie wystawiona w iFirma.pl';
                if (!empty($emailsSent)) {
                    $message .= ' i wysłana na: ' . implode(', ', $emailsSent);
                }
                if (!empty($emailErrors)) {
                    $message .= ' (Błędy wysyłki: ' . count($emailErrors) . ')';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['data'] ?? $result['raw_response'] ?? null,
                    'emails_sent' => $emailsSent,
                    'email_errors' => $emailErrors,
                    'created_at' => now()->format('d.m.Y H:i')
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury pro forma',
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['raw_response'] ?? null,
                    'status_code' => $result['status_code'] ?? null
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Wystawia fakturę krajową (nie pro-forma) w iFirma.pl na podstawie zamówienia
     */
    public function createIfirmaInvoice(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::find($id);

            if (!$zamowienie) {
                return response()->json([
                    'success' => false,
                    'error' => 'Zamówienie nie zostało znalezione.'
                ], 404);
            }

            // Sprawdzenie czy zamówienie ma wymagane dane nabywcy
            if (empty($zamowienie->buyer_name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych nabywcy. Nie można wystawić faktury.'
                ], 400);
            }

            // Sprawdzenie czy zamówienie ma produkt i cenę
            if (empty($zamowienie->product_name) || empty($zamowienie->product_price)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Brak danych produktu lub ceny. Nie można wystawić faktury.'
                ], 400);
            }

            // Przygotowanie uwag do faktury
            $uwagi = $request->input('custom_remarks', '');
            
            if (empty(trim($uwagi))) {
                // Jeśli nie ma niestandardowych uwag, generuj automatycznie dane odbiorcy
                $recipientData = [];
                if (!empty($zamowienie->recipient_name)) {
                    $recipientData[] = $zamowienie->recipient_name;
                }
                if (!empty($zamowienie->recipient_address)) {
                    $recipientData[] = $zamowienie->recipient_address;
                }
                if (!empty($zamowienie->recipient_postal_code) && !empty($zamowienie->recipient_city)) {
                    $recipientData[] = $zamowienie->recipient_postal_code . ' ' . $zamowienie->recipient_city;
                }

                $uwagi = "ODBIORCA:\n";
                if (!empty($recipientData)) {
                    $uwagi .= implode("\n", $recipientData);
                }
            }
            
            // ZAWSZE na końcu dodaj identyfikator zamówienia
            if (!empty(trim($uwagi))) {
                $uwagi .= "\n\npnedu.pl #{$zamowienie->id}";
            } else {
                $uwagi = "pnedu.pl #{$zamowienie->id}";
            }

            // Przygotowanie danych kontrahenta
            // KLUCZOWE ZMIANY na podstawie działającego kodu PHP:
            // 1. Kraj: "Polska" (nie "PL"!)
            // 2. Dodane: PrefiksUE, OsobaFizyczna, Email
            // 3. NIP jako null jeśli pusty (nie pomijamy pola)
            $kontrahent = [
                'Nazwa' => $zamowienie->buyer_name,
                'NIP' => null,
                'Ulica' => '',
                'KodPocztowy' => '',
                'Miejscowosc' => '',
                'Kraj' => 'Polska', // WAŻNE: "Polska", nie "PL"!
                'PrefiksUE' => 'PL',
                'OsobaFizyczna' => false,
                'Email' => null,
            ];
            
            // NIP
            if (!empty($zamowienie->buyer_nip)) {
                $nip = preg_replace('/[^0-9]/', '', $zamowienie->buyer_nip);
                if (!empty($nip)) {
                    $kontrahent['NIP'] = $nip;
                }
            }
            
            // Adres
            if (!empty($zamowienie->buyer_address)) {
                $kontrahent['Ulica'] = $zamowienie->buyer_address;
            }
            if (!empty($zamowienie->buyer_postal_code)) {
                $kontrahent['KodPocztowy'] = $zamowienie->buyer_postal_code;
            }
            if (!empty($zamowienie->buyer_city)) {
                $kontrahent['Miejscowosc'] = $zamowienie->buyer_city;
            }
            
            // Email
            if (!empty($zamowienie->buyer_email)) {
                $kontrahent['Email'] = strtolower(trim($zamowienie->buyer_email));
            }

            // Sprawdzenie, czy konto jest na RYCZAŁCIE
            $isLumpSum = config('services.ifirma.is_lump_sum', false);
            $vatExempt = config('services.ifirma.vat_exempt', false);
            
            // Przygotowanie pozycji faktury krajowej
            // OBSERWACJA: W formularzu iFirma.pl (UI) NIE MA pola dla StawkaRyczaltu w pozycji faktury
            // To sugeruje, że dla nievatowców na ryczałcie StawkaRyczaltu jest automatycznie
            // pobierane z konfiguracji konta i NIE powinno być podawane explicite w pozycji!
            // Zgodnie z dokumentacją API iFirma dla nievatowców:
            // https://api.ifirma.pl/wystawianie-faktury-sprzedazy-krajowej-dla-nievatowca/
            
            // Przygotowanie pozycji faktury
            // WAŻNE: Na podstawie działającego kodu PHP kolejność pól jest kluczowa!
            // Kolejność z działającego kodu:
            // 1. PodstawaPrawna (jeśli VAT exempt)
            // 2. StawkaVat (null jeśli VAT exempt)
            // 3. Ilosc
            // 4. CenaJednostkowa
            // 5. NazwaPelna
            // 6. Jednostka
            // 7. TypStawkiVat (na końcu!)
            
            $cenaJednostkowa = (float) round((float)$zamowienie->product_price, 2);
            
            $pozycja = [];
            
            // Dla zwolnionych z VAT: NAJPIERW PodstawaPrawna, POTEM StawkaVat = null
            if ($vatExempt) {
                $pozycja['PodstawaPrawna'] = (string) config('services.ifirma.vat_exemption_basis', 'Art. 43 ust. 1 pkt 29 lit. b)');
                $pozycja['StawkaVat'] = null; // EXPLICITE null (nie brak pola!)
            } else {
                $pozycja['StawkaVat'] = 0.23;
                if ($isLumpSum) {
                    $pozycja['StawkaRyczaltu'] = (float) config('services.ifirma.lump_sum_rate', 0.085);
                }
            }
            
            // Pozostałe pola w dokładnej kolejności jak w działającym kodzie
            $pozycja['Ilosc'] = (float) 1.0;
            $pozycja['CenaJednostkowa'] = $cenaJednostkowa;
            $pozycja['NazwaPelna'] = $zamowienie->product_name;
            $pozycja['Jednostka'] = 'sztuk';
            
            // TypStawkiVat NA KOŃCU!
            $pozycja['TypStawkiVat'] = $vatExempt ? 'ZW' : 'PRC';
            

            // Przygotowanie danych faktury krajowej dla iFirma
            // Zgodnie z dokumentacją: https://api.ifirma.pl/wystawianie-faktury-krajowej/
            // RÓŻNICE vs PRO-FORMA:
            // - Endpoint: fakturakraj.json (nie fakturaproformakraj.json)
            // - DataSprzedazy jest WYMAGANA (w pro-forma jej nie ma)
            // - BRAK pola TypFakturyKrajowej (to pole jest TYLKO dla pro-forma!)
            // - RodzajPodpisuOdbiorcy może być opcjonalne
            
            // Przygotowanie danych faktury - DOKŁADNA KOLEJNOŚĆ z działającego kodu PHP!
            // WAŻNE: Na podstawie działającego kodu:
            // - LiczOd: 'BRT' dla nievatowców (nie 'NET'!)
            // - RodzajPodpisuOdbiorcy: 'BPO' (nie 'BWO'!)
            // - Kolejność pól jest ważna!
            
            $invoiceData = [
                'Zaplacono' => 0.00, // PIERWSZA - dokładnie 0.00
                'ZaplaconoNaDokumencie' => 0.00, // DRUGA - dokładnie 0.00
                'LiczOd' => 'BRT', // TRZECIA - BRT dla nievatowców!
                'NumerKontaBankowego' => null,
                'DataWystawienia' => now()->format('Y-m-d'),
                'MiejsceWystawienia' => 'Bieżuń',
                'DataSprzedazy' => now()->format('Y-m-d'),
                'FormatDatySprzedazy' => 'DZN',
                'SposobZaplaty' => 'PRZ',
                'RodzajPodpisuOdbiorcy' => 'BPO', // WAŻNE: BPO, nie BWO!
                'WidocznyNumerBdo' => false,
                'Numer' => null,
                'Pozycje' => [$pozycja],
                'Kontrahent' => $kontrahent,
            ];
            
            // Termin płatności - ODROCZONA PŁATNOŚĆ zgodnie z invoice_payment_delay
            $paymentDelay = !empty($zamowienie->invoice_payment_delay) ? (int)$zamowienie->invoice_payment_delay : 14;
            $invoiceData['TerminPlatnosci'] = now()->addDays($paymentDelay)->format('Y-m-d');

            // Uwagi
            if (!empty(trim($uwagi))) {
                $invoiceData['Uwagi'] = trim($uwagi);
            }

            // Numer konta bankowego
            $bankAccount = config('services.ifirma.bank_account', '');
            if (!empty(trim($bankAccount))) {
                $invoiceData['NumerKontaBankowego'] = trim($bankAccount);
            }

            Log::info('iFirma Invoice Request Data', [
                'order_id' => $zamowienie->id,
                'invoice_data' => $invoiceData,
                'payment_delay_days' => $paymentDelay
            ]);

            // Wystawienie faktury przez API iFirma
            $ifirmaService = new IfirmaApiService();
            $result = $ifirmaService->createInvoice($invoiceData);

            Log::info('iFirma Invoice Response', [
                'order_id' => $zamowienie->id,
                'status' => $result['status'] ?? 'unknown',
                'status_code' => $result['status_code'] ?? null,
                'message' => $result['message'] ?? null,
                'full_response' => $result
            ]);

            if ($result['status'] === 'success') {
                // Pobierz Identyfikator z odpowiedzi
                $invoiceId = null;
                if (isset($result['data']['response']['Identyfikator'])) {
                    $invoiceId = $result['data']['response']['Identyfikator'];
                } elseif (isset($result['data']['Identyfikator'])) {
                    $invoiceId = $result['data']['Identyfikator'];
                }

                // Pobierz pełny numer faktury
                $invoiceNumber = null;
                $fullInvoiceData = null;
                
                if (!empty($invoiceId)) {
                    try {
                        $invoiceDetails = $ifirmaService->getInvoice($invoiceId);
                        
                        if ($invoiceDetails['status'] === 'success' && isset($invoiceDetails['data'])) {
                            $fullInvoiceData = $invoiceDetails['data'];
                            
                            if (isset($fullInvoiceData['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['PelnyNumer'];
                            } elseif (isset($fullInvoiceData['response']['PelnyNumer'])) {
                                $invoiceNumber = $fullInvoiceData['response']['PelnyNumer'];
                            }
                        }
                        
                        Log::info('iFirma Invoice - szczegóły pobrane', [
                            'invoice_id' => $invoiceId,
                            'invoice_number' => $invoiceNumber,
                            'details' => $fullInvoiceData
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Nie udało się pobrać pełnego numeru faktury', [
                            'invoice_id' => $invoiceId,
                            'error' => $e->getMessage()
                        ]);
                        $invoiceNumber = $invoiceId;
                    }
                }

                // Aktualizacja numeru faktury w zamówieniu
                if (!empty($invoiceNumber) && empty($zamowienie->invoice_number)) {
                    $zamowienie->invoice_number = $invoiceNumber;
                    $zamowienie->save();
                }

                // Wysyłka e-mailem (jeśli zaznaczono checkbox)
                $sendEmail = $request->input('send_email', false);
                $emailsSent = [];
                $emailErrors = [];
                
                if ($sendEmail && !empty($invoiceId)) {
                    $emails = [];
                    
                    if (!empty($zamowienie->orderer_email)) {
                        $emails[] = strtolower(trim($zamowienie->orderer_email));
                    }
                    
                    if (!empty($zamowienie->participant_email)) {
                        $participantEmail = strtolower(trim($zamowienie->participant_email));
                        if (!in_array($participantEmail, $emails)) {
                            $emails[] = $participantEmail;
                        }
                    }
                    
                    foreach ($emails as $email) {
                        try {
                            $sendResult = $ifirmaService->sendInvoiceByEmail(
                                $invoiceId, 
                                $email, 
                                $invoiceNumber, 
                                $zamowienie->id,
                                'invoice'
                            );
                            
                            if ($sendResult['status'] === 'success') {
                                $emailsSent[] = $email;
                                Log::info('Faktura wysłana e-mailem', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email
                                ]);
                            } else {
                                $emailErrors[] = [
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd'
                                ];
                                Log::warning('Błąd wysyłki faktury', [
                                    'invoice_id' => $invoiceId,
                                    'email' => $email,
                                    'error' => $sendResult['message'] ?? 'Nieznany błąd'
                                ]);
                            }
                        } catch (Exception $e) {
                            $emailErrors[] = [
                                'email' => $email,
                                'error' => $e->getMessage()
                            ];
                            Log::error('Exception podczas wysyłki faktury', [
                                'invoice_id' => $invoiceId,
                                'email' => $email,
                                'exception' => $e->getMessage()
                            ]);
                        }
                    }
                }

                $message = 'Faktura została pomyślnie wystawiona w iFirma.pl';
                if (!empty($emailsSent)) {
                    $message .= ' i wysłana na: ' . implode(', ', $emailsSent);
                }
                if (!empty($emailErrors)) {
                    $message .= ' (Błędy wysyłki: ' . count($emailErrors) . ')';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['data'] ?? $result['raw_response'] ?? null,
                    'emails_sent' => $emailsSent,
                    'email_errors' => $emailErrors,
                    'created_at' => now()->format('d.m.Y H:i')
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Nie udało się wystawić faktury',
                    'invoice_data' => $invoiceData,
                    'ifirma_response' => $result['raw_response'] ?? null,
                    'status_code' => $result['status_code'] ?? null
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przetwarzania: ' . $e->getMessage()
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

    /**
     * Wyświetla listę duplikatów zamówień
     */
    public function duplicates(Request $request)
    {
        // Liczba rekordów na stronę
        $perPage = $request->get('per_page', 25);
        
        // Pobierz grupy duplikatów
        $duplicateGroups = FormOrder::duplicates()->get();
        
        // Przygotuj dane do wyświetlenia
        $duplicates = collect();
        foreach ($duplicateGroups as $group) {
            $orderIds = explode(',', $group->order_ids);
            $orders = FormOrder::whereIn('id', $orderIds)
                ->with('marketingCampaign.sourceType')
                ->get()
                ->sortByDesc('priority'); // Sortuj według priorytetu (najważniejsze pierwsze)
            
            $duplicates->push([
                'email' => $group->participant_email,
                'product_id' => $group->publigo_product_id,
                'count' => $group->duplicate_count,
                'orders' => $orders,
                'recommended_order' => $orders->first(), // Najwyższy priorytet
                'oldest_order' => $orders->sortBy('id')->first(),
                'newest_order' => $orders->sortBy('id')->last(),
            ]);
        }
        
        // Paginacja dla grup duplikatów
        $currentPage = $request->get('page', 1);
        $perPage = min($perPage, 50); // Maksymalnie 50 grup na stronę
        $offset = ($currentPage - 1) * $perPage;
        $paginatedDuplicates = $duplicates->slice($offset, $perPage);
        
        // Tworzenie obiektu paginacji
        $duplicatesPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedDuplicates,
            $duplicates->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page'
            ]
        );
        
        // Statystyki
        $totalDuplicates = $duplicates->sum('count');
        $totalGroups = $duplicates->count();
        $totalOrders = $duplicates->sum(function($group) {
            return $group['count'];
        });
        
        return view('form-orders.duplicates', compact(
            'duplicatesPaginated', 
            'perPage', 
            'totalDuplicates', 
            'totalGroups', 
            'totalOrders'
        ));
    }

    /**
     * Usuwa duplikat (soft delete)
     */
    public function destroyDuplicate(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);
            
            // Sprawdź czy to rzeczywiście duplikat
            $duplicates = FormOrder::findDuplicatesFor($id)->get();
            if ($duplicates->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'To zamówienie nie ma duplikatów.'
                ], 400);
            }
            
            // Soft delete
            $zamowienie->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Duplikat został usunięty.',
                'remaining_duplicates' => $duplicates->count(),
                'email' => $zamowienie->participant_email,
                'product_id' => $zamowienie->publigo_product_id
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Usuwa wszystkie duplikaty dla konkretnej grupy (oprócz najstarszego)
     */
    public function destroyAllDuplicatesForGroup(Request $request, $email, $productId)
    {
        try {
            // Znajdź wszystkie zamówienia w grupie duplikatów
            $orders = FormOrder::where('participant_email', $email)
                ->where('publigo_product_id', $productId)
                ->orderBy('id')
                ->get();
            
            if ($orders->count() < 2) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono duplikatów dla tej grupy.'
                ], 400);
            }
            
            // Zostaw najstarsze zamówienie, usuń resztę
            $oldestOrder = $orders->first();
            $duplicatesToDelete = $orders->skip(1);
            
            $deletedCount = 0;
            foreach ($duplicatesToDelete as $duplicate) {
                $duplicate->delete();
                $deletedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Usunięto {$deletedCount} duplikatów. Zachowano najstarsze zamówienie #{$oldestOrder->id}.",
                'kept_order_id' => $oldestOrder->id
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatów: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Usuwa wszystkie duplikaty oprócz wybranego zamówienia
     */
    public function destroyDuplicatesKeepSelected(Request $request, $email, $productId, $keepOrderId)
    {
        try {
            // Znajdź wszystkie zamówienia w grupie duplikatów
            $orders = FormOrder::where('participant_email', $email)
                ->where('publigo_product_id', $productId)
                ->get();
            
            if ($orders->count() < 2) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono duplikatów dla tej grupy.'
                ], 400);
            }
            
            // Znajdź zamówienie do zachowania
            $keepOrder = $orders->where('id', $keepOrderId)->first();
            if (!$keepOrder) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie znaleziono zamówienia do zachowania.'
                ], 400);
            }
            
            // Usuń wszystkie oprócz wybranego
            $duplicatesToDelete = $orders->where('id', '!=', $keepOrderId);
            
            $deletedCount = 0;
            foreach ($duplicatesToDelete as $duplicate) {
                $duplicate->delete();
                $deletedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Usunięto {$deletedCount} duplikatów. Zachowano zamówienie #{$keepOrder->id}.",
                'kept_order_id' => $keepOrder->id
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania duplikatów: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Oznacz duplikat jako zakończony
     */
    public function markAsCompleted(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);
            
            // Sprawdź czy to rzeczywiście duplikat
            $duplicates = FormOrder::findDuplicatesFor($id)->get();
            if ($duplicates->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'To zamówienie nie ma duplikatów.'
                ], 400);
            }
            
            // Oznacz jako zakończone
            $zamowienie->status_completed = 1;
            
            // Dodaj notatkę jeśli podana
            $notes = $request->input('notes');
            if ($notes) {
                $zamowienie->notes = $notes;
            }
            
            $zamowienie->save();
            
            return response()->json([
                'success' => true,
                'message' => "Zamówienie #{$id} zostało oznaczone jako zakończone (duplikat)."
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas oznaczania jako zakończone: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aktualizuj notatkę zamówienia
     */
    public function updateNotes(Request $request, $id)
    {
        try {
            $zamowienie = FormOrder::findOrFail($id);
            
            $notes = $request->input('notes');
            $zamowienie->notes = $notes;
            $zamowienie->save();
            
            return response()->json([
                'success' => true,
                'message' => "Notatka dla zamówienia #{$id} została zaktualizowana."
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas zapisywania notatki: ' . $e->getMessage()
            ], 500);
        }
    }
}
