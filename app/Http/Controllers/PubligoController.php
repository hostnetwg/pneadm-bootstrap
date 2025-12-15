<?php

namespace App\Http\Controllers;

/**
 * PubligoController - Kontroler do integracji z Publigo.pl (WP IDEA API)
 * 
 * POPRAWKA AUTORYZACJI (2024-01-XX):
 * Zgodnie z dokumentacją Publigo.pl:
 * - nonce: unikalny string dla każdego żądania
 * - token: MD5 z konkatenacji nonce + klucz WP Idea
 * 
 * Poprzednia implementacja była nieprawidłowa - generowała różne nonce
 * dla nonce i tokenu, co powodowało błąd "REST API wrong token".
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Publigo;
use App\Models\Instructor;
use App\Models\Course;
use App\Models\Participant;
use App\Models\FormOrder;
use App\Models\WebhookLog;

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
            'id_old' => 'nullable|string|max:255',
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
    
    /**
     * Webhook do odbierania danych uczestników z Publigo.pl
     */
    public function webhook(Request $request)
    {
        // Logowanie do bazy danych
        $webhookLog = WebhookLog::create([
            'source' => 'publigo',
            'endpoint' => '/api/publigo/webhook',
            'method' => $request->method(),
            'request_data' => [
                'raw_input' => $request->getContent(),
                'parsed_data' => $request->all(),
                'headers' => $request->headers->all(),
                'content_type' => $request->header('Content-Type'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'success' => true
        ]);

        try {
            // Logowanie otrzymanych danych dla debugowania
            \Log::info('Publigo webhook received', [
                'raw_input' => $request->getContent(), // Surowy input
                'parsed_data' => $request->all(),
                'headers' => $request->headers->all(),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method()
            ]);

            // Sprawdzamy surowy input
            $rawInput = $request->getContent();
            $jsonData = json_decode($rawInput, true);
            
            // Sprawdzamy czy to PHP serialized format
            $phpData = null;
            if (strpos($rawInput, 'a:') === 0 && strpos($rawInput, '{') === 0) {
                $phpData = @unserialize($rawInput);
                \Log::info('PHP serialized data detected', [
                    'php_unserialize_success' => $phpData !== false,
                    'php_data_keys' => $phpData ? array_keys($phpData) : []
                ]);
            }
            
            \Log::info('Webhook data analysis', [
                'raw_input_length' => strlen($rawInput),
                'raw_input_preview' => substr($rawInput, 0, 200),
                'json_decode_success' => $jsonData !== null,
                'json_error' => json_last_error_msg(),
                'php_unserialize_success' => $phpData !== false,
                'parsed_data_keys' => $jsonData ? array_keys($jsonData) : [],
                'php_data_keys' => $phpData ? array_keys($phpData) : [],
                'laravel_parsed_keys' => array_keys($request->all())
            ]);

            // Używamy danych z PHP unserialize, potem JSON, potem Laravel
            $data = $phpData ?: ($jsonData ?: $request->all());
            
            \Log::info('Webhook data structure', [
                'keys' => array_keys($data),
                'has_id' => isset($data['id']),
                'has_status' => isset($data['status']),
                'has_customer' => isset($data['customer']),
                'has_url_params' => isset($data['url_params']),
                'customer_keys' => isset($data['customer']) ? array_keys($data['customer']) : [],
                'url_params_count' => isset($data['url_params']) ? count($data['url_params']) : 0
            ]);

            // Sprawdzamy czy JSON został poprawnie sparsowany
            if ($jsonData === null) {
                \Log::error('Invalid JSON data received', [
                    'raw_input' => $rawInput,
                    'json_error' => json_last_error_msg()
                ]);
                return response()->json(['message' => 'Invalid JSON data'], 400);
            }

            // Sprawdzamy czy mamy podstawowe dane
            if (!isset($data['id']) || !isset($data['status']) || !isset($data['customer'])) {
                \Log::error('Missing required fields in webhook data', ['data' => $data]);
                return response()->json(['message' => 'Missing required fields'], 400);
            }

            $orderId = $data['id'] ?? null;
            $orderStatus = $data['status'] ?? null;
            $customer = $data['customer'] ?? [];
            $urlParams = $data['url_params'] ?? [];
            $items = $data['items'] ?? [];

            // Sprawdzamy czy customer ma wymagane pola
            if (!isset($customer['first_name']) || !isset($customer['last_name']) || !isset($customer['email'])) {
                \Log::error('Missing customer fields', ['customer' => $customer]);
                return response()->json(['message' => 'Missing customer fields'], 400);
            }

            // Sprawdzamy czy mamy items lub url_params
            if ((!isset($urlParams) || !is_array($urlParams) || empty($urlParams)) && 
                (!isset($items) || !is_array($items) || empty($items))) {
                \Log::error('Missing both url_params and items', [
                    'url_params' => $urlParams,
                    'items' => $items
                ]);
                return response()->json(['message' => 'Missing url_params or items'], 400);
            }

            // Obsługa tylko zamówień zakończonych
            if ($orderStatus !== 'Zakończone') {
                \Log::info('Order status not completed, skipping', [
                    'order_id' => $orderId,
                    'status' => $orderStatus
                ]);
                return response()->json(['message' => 'Order status not completed'], 200);
            }

            $registeredParticipants = [];

            // Przetwarzaj każdy produkt w zamówieniu
            // Zgodnie z dokumentacją Publigo:
            // - url_params zawiera product_id, details, external_id
            // - items zawiera id, name, price_id, quantity, itd.
            $itemsToProcess = !empty($items) ? $items : $urlParams;
            
            foreach ($itemsToProcess as $item) {
                // Dla url_params: product_id, external_id
                // Dla items: id (to jest cart_item_id)
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $externalId = $item['external_id'] ?? null;

                \Log::info('Processing webhook item', [
                    'item' => $item,
                    'product_id' => $productId,
                    'external_id' => $externalId,
                    'order_id' => $orderId
                ]);

                // Znajdź kurs na podstawie product_id lub external_id z Publigo
                // Tylko kursy z source_id_old = "certgen_Publigo"
                $course = Course::where('source_id_old', 'certgen_Publigo')
                              ->where(function($query) use ($productId, $externalId) {
                                  $query->where('id_old', $productId)
                                        ->orWhere('id_old', $externalId);
                              })
                              ->first();

                if (!$course) {
                    \Log::warning('Course not found for product', [
                        'product_id' => $productId,
                        'external_id' => $externalId,
                        'order_id' => $orderId
                    ]);
                    continue;
                }

                // Sprawdź czy uczestnik już istnieje dla tego kursu
                $existingParticipant = Participant::where('email', $customer['email'])
                                                 ->where('course_id', $course->id)
                                                 ->first();

                if ($existingParticipant) {
                    \Log::info('Participant already exists for course', [
                        'email' => $customer['email'],
                        'course_id' => $course->id,
                        'order_id' => $orderId
                    ]);
                    $registeredParticipants[] = [
                        'course_id' => $course->id,
                        'course_title' => $course->title,
                        'participant_id' => $existingParticipant->id,
                        'status' => 'already_exists'
                    ];
                    continue;
                }

                // Oblicz datę końcową dostępu dla kursów Publigo
                // Zasada: 2 miesiące dostępu do nagrania po zakończeniu szkolenia
                $accessExpiresAt = null;
                $now = now();
                
                if ($course->start_date) {
                    // Kurs ma datę rozpoczęcia - użyj nowej logiki (2 miesiące)
                    $startDate = $course->start_date;
                    
                    if ($now->lt($startDate)) {
                        // Zapis PRZED datą rozpoczęcia kursu
                        // Dostęp do 2 miesięcy od daty rozpoczęcia kursu
                        $accessExpiresAt = $startDate->copy()->addMonths(2);
                        
                        \Log::info('Access expires: 2 months from course start date (registration before start)', [
                            'course_id' => $course->id,
                            'course_title' => $course->title,
                            'start_date' => $startDate->format('Y-m-d H:i:s'),
                            'registration_date' => $now->format('Y-m-d H:i:s'),
                            'access_expires_at' => $accessExpiresAt->format('Y-m-d H:i:s'),
                            'order_id' => $orderId
                        ]);
                    } else {
                        // Zapis PO dacie rozpoczęcia kursu
                        // Dostęp do 2 miesięcy od daty zapisu (zakupu)
                        $accessExpiresAt = $now->copy()->addMonths(2);
                        
                        \Log::info('Access expires: 2 months from registration date (registration after start)', [
                            'course_id' => $course->id,
                            'course_title' => $course->title,
                            'start_date' => $startDate->format('Y-m-d H:i:s'),
                            'registration_date' => $now->format('Y-m-d H:i:s'),
                            'access_expires_at' => $accessExpiresAt->format('Y-m-d H:i:s'),
                            'order_id' => $orderId
                        ]);
                    }
                } else {
                    // Kurs nie ma daty rozpoczęcia - użyj starej logiki (access_duration_days) jako fallback
                    if ($course->access_duration_days) {
                        $accessExpiresAt = $now->copy()->addDays($course->access_duration_days);
                        
                        \Log::info('Access expires: using access_duration_days (course without start_date)', [
                            'course_id' => $course->id,
                            'course_title' => $course->title,
                            'access_duration_days' => $course->access_duration_days,
                            'access_expires_at' => $accessExpiresAt->format('Y-m-d H:i:s'),
                            'order_id' => $orderId
                        ]);
                    }
                }

                // Sprawdź czy istnieje wcześniejszy uczestnik z tym samym e-mailem,
                // który ma wypełnione datę lub miejsce urodzenia (lub oba)
                $previousParticipant = Participant::where('email', $customer['email'])
                    ->where(function($query) {
                        $query->whereNotNull('birth_date')
                              ->orWhereNotNull('birth_place');
                    })
                    ->orderBy('created_at', 'desc') // Najnowszy uczestnik z tymi danymi
                    ->first();

                $birthDate = null;
                $birthPlace = null;

                if ($previousParticipant) {
                    // Kopiuj datę urodzenia jeśli istnieje
                    if ($previousParticipant->birth_date) {
                        $birthDate = $previousParticipant->birth_date;
                    }
                    // Kopiuj miejsce urodzenia jeśli istnieje
                    if ($previousParticipant->birth_place) {
                        $birthPlace = $previousParticipant->birth_place;
                    }
                    
                    \Log::info('Found previous participant with birth data - copying to new participant', [
                        'email' => $customer['email'],
                        'previous_participant_id' => $previousParticipant->id,
                        'previous_course_id' => $previousParticipant->course_id,
                        'birth_date' => $birthDate?->format('Y-m-d'),
                        'birth_place' => $birthPlace,
                        'has_birth_date' => $previousParticipant->birth_date !== null,
                        'has_birth_place' => $previousParticipant->birth_place !== null,
                        'new_course_id' => $course->id,
                        'order_id' => $orderId
                    ]);
                }
                // Jeśli nie ma poprzedniego uczestnika z danymi urodzenia, birthDate i birthPlace pozostają null
                // Uczestnik zostanie dodany bez tych danych - to jest poprawne zachowanie

                // Utwórz nowego uczestnika
                $participant = Participant::create([
                    'course_id' => $course->id,
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                    'email' => $customer['email'],
                    'birth_date' => $birthDate, // Skopiowane z poprzedniego uczestnika lub null
                    'birth_place' => $birthPlace, // Skopiowane z poprzedniego uczestnika lub null
                    'order' => Participant::where('course_id', $course->id)->count() + 1,
                    'access_expires_at' => $accessExpiresAt
                ]);

                \Log::info('Participant created successfully', [
                    'participant_id' => $participant->id,
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'email' => $customer['email'],
                    'birth_date' => $participant->birth_date?->format('Y-m-d'),
                    'birth_place' => $participant->birth_place,
                    'data_copied_from_previous' => $previousParticipant ? true : false,
                    'access_expires_at' => $participant->access_expires_at?->format('Y-m-d H:i:s'),
                    'order_id' => $orderId
                ]);

                $registeredParticipants[] = [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'participant_id' => $participant->id,
                    'status' => 'created'
                ];
            }

            $response = [
                'message' => 'Webhook processed successfully',
                'order_id' => $orderId,
                'participants' => $registeredParticipants
            ];

            // Aktualizuj log z odpowiedzią
            $webhookLog->update([
                'response_data' => $response,
                'status_code' => 201,
                'success' => true
            ]);

            return response()->json($response, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Webhook validation error', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            
            // Aktualizuj log z błędem walidacji
            if (isset($webhookLog)) {
                $webhookLog->update([
                    'error_message' => 'Validation error: ' . json_encode($e->errors()),
                    'status_code' => 422,
                    'success' => false
                ]);
            }
            
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            // Aktualizuj log z błędem
            if (isset($webhookLog)) {
                $webhookLog->update([
                    'error_message' => $e->getMessage(),
                    'status_code' => 500,
                    'success' => false
                ]);
            }
            
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    /**
     * Strona zarządzania webhookami Publigo
     */
    public function webhooks()
    {
        $webhookUrl = config('services.publigo.webhook_url', 'https://adm.pnedu.pl/api/publigo/webhook');
        $webhookToken = config('services.publigo.webhook_token');
        $courses = Course::where('source_id_old', 'certgen_Publigo')
                        ->orderBy('start_date', 'desc')
                        ->get();
        
        // Pobierz ostatnie logi webhooków
        $recentLogs = collect();
        try {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $logs = file($logFile);
                $webhookLogs = collect($logs)->filter(function($line) {
                    return str_contains($line, 'Publigo webhook');
                })->take(20)->reverse();
                
                $recentLogs = $webhookLogs->map(function($line) {
                    return [
                        'timestamp' => substr($line, 1, 19),
                        'message' => trim($line)
                    ];
                });
            }
        } catch (\Exception $e) {
            // Ignoruj błędy czytania logów
        }

        return view('publigo.webhooks', compact('webhookUrl', 'webhookToken', 'recentLogs', 'courses'));
    }

    /**
     * Strona z logami webhooków
     */
    public function webhookLogs()
    {
        $logs = WebhookLog::orderBy('created_at', 'desc')->paginate(50);
        return view('publigo.webhook-logs', compact('logs'));
    }

    /**
     * Test webhooka z panelu administracyjnego
     */
    public function testWebhook(Request $request)
    {
        $request->validate([
            'course_id' => 'required|string',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
        ]);

        $courseId = $request->input('course_id');
        $email = $request->input('email');
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');

        // Sprawdź czy kurs istnieje - tylko kursy z source_id_old = "certgen_Publigo"
        $course = Course::where('source_id_old', 'certgen_Publigo')
                      ->where(function($query) use ($courseId) {
                          $query->where('id_old', $courseId)
                                ->orWhere('id', $courseId);
                      })
                      ->first();

        if (!$course) {
            return back()->withErrors(['course_id' => 'Kurs o podanym ID nie istnieje']);
        }

        // Sprawdź czy uczestnik już istnieje
        $existingParticipant = Participant::where('email', $email)
                                         ->where('course_id', $course->id)
                                         ->first();

        if ($existingParticipant) {
            return back()->with('warning', 'Uczestnik o podanym emailu już istnieje dla tego kursu');
        }

        // Utwórz testowego uczestnika
        $participant = Participant::create([
            'course_id' => $course->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'birth_date' => null,
            'birth_place' => null,
            'order' => Participant::where('course_id', $course->id)->count() + 1
        ]);

        \Log::info('Test participant created', [
            'participant_id' => $participant->id,
            'course_id' => $course->id,
            'course_title' => $course->title,
            'email' => $email
        ]);

        return back()->with('success', 'Testowy uczestnik został dodany do kursu: ' . $course->title);
    }

    /**
     * Prosty test webhooka - symulacja Twojego działającego kodu PHP
     */
    public function webhookTest(Request $request)
    {
        // Symulacja Twojego działającego kodu PHP
        $rawInput = $request->getContent();
        $json = json_decode($rawInput);
        
        // Sprawdzamy czy to PHP serialized format
        $phpData = null;
        if (strpos($rawInput, 'a:') === 0 && strpos($rawInput, '{') === 0) {
            $phpData = @unserialize($rawInput);
        }
        
        \Log::info('Webhook test endpoint hit', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'raw_input' => $rawInput,
            'raw_input_length' => strlen($rawInput),
            'raw_input_preview' => substr($rawInput, 0, 200),
            'json_decode_success' => $json !== null,
            'json_error' => json_last_error_msg(),
            'php_unserialize_success' => $phpData !== false,
            'php_data_keys' => $phpData ? array_keys($phpData) : [],
            'parsed_data' => $request->all(),
            'parsed_data_keys' => array_keys($request->all())
        ]);

        // Jeśli to PHP serialized data, przetwórz ją jak główny webhook
        if ($phpData !== false) {
            \Log::info('Processing PHP serialized data in test endpoint', [
                'php_data' => $phpData
            ]);
            
            // Symuluj przetwarzanie jak w głównym webhooku
            $orderId = $phpData['id'] ?? null;
            $orderStatus = $phpData['status'] ?? null;
            $customer = $phpData['customer'] ?? [];
            $items = $phpData['items'] ?? [];
            
            \Log::info('Extracted data from PHP serialized', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'customer' => $customer,
                'items_count' => count($items),
                'items' => $items
            ]);
            
            return response()->json([
                'message' => 'Webhook test endpoint working - PHP serialized data processed',
                'php_data' => $phpData,
                'extracted_data' => [
                    'order_id' => $orderId,
                    'order_status' => $orderStatus,
                    'customer' => $customer,
                    'items' => $items
                ],
                'timestamp' => now()->toISOString()
            ]);
        }

        if ($json === null) {
            \Log::error('Invalid JSON in test endpoint', [
                'json_error' => json_last_error_msg(),
                'raw_input' => $rawInput
            ]);
            
            return response()->json([
                'error' => 'Invalid JSON',
                'json_error' => json_last_error_msg(),
                'raw_input' => $rawInput
            ], 400);
        }

        return response()->json([
            'message' => 'Webhook test endpoint working',
            'json_data' => $json,
            'parsed_data' => $request->all(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Test połączenia z API Publigo.pl
     */
    public function testApi()
    {
        $apiKey = config('services.publigo.api_key');
        $baseUrl = config('services.publigo.instance_url');
        
        $results = [];

        try {
            // Test połączenia i pobranie kursów za pomocą autoryzacji nonce + token
            $results['connection'] = $this->testPubligoConnection($apiKey, $baseUrl);

            // Pobranie listy kursów, jeśli połączenie się udało
            if ($results['connection']['status'] === 'success') {
                $results['courses'] = $this->getPubligoCourses($apiKey, $baseUrl);
            } else {
                $results['courses'] = ['status' => 'skipped', 'message' => 'Pominięto, ponieważ test połączenia nie powiódł się.'];
            }

        } catch (\Exception $e) {
            \Log::error('Publigo API test failed', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);
            
            $results['error'] = $e->getMessage();
        }

        return view('publigo.test-api', [
            'results' => $results,
            'apiKey' => $apiKey,
            'baseUrl' => $baseUrl
        ]);
    }

    /**
     * Test połączenia z API Publigo (WP IDEA) używając autoryzacji nonce + token.
     */
    private function testPubligoConnection($apiKey, $baseUrl)
    {
        if (empty($apiKey)) {
            return [
                'status' => 'config_error',
                'message' => 'Brak klucza API (PUBLIGO_API_KEY) w pliku .env',
            ];
        }

        try {
            $nonce = $this->generateNonce();
            $token = $this->generateToken($apiKey, $nonce);

            $response = \Illuminate\Support\Facades\Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($baseUrl . '/wp-json/wp-idea/v1/products', [
                'nonce' => $nonce,
                'token' => $token
            ]);

            if ($response->successful()) {
                $courses = $response->json();
                return [
                    'status' => 'success',
                    'status_code' => $response->status(),
                    'body' => $courses,
                    'count' => is_array($courses) ? count($courses) : 0,
                    'nonce_sent' => $nonce,
                    'token_sent' => $token,
                ];
            }
            
            return [
                'status' => 'failed',
                'status_code' => $response->status(),
                'message' => 'Odpowiedź serwera wskazuje na błąd.',
                'body' => $response->json() ?? $response->body(),
                'nonce_sent' => $nonce,
                'token_sent' => $token,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'status' => 'connection_error',
                'message' => 'Błąd połączenia. Sprawdź URL instancji i konfigurację sieci/DNS. ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Pobieranie kursów z Publigo (WP IDEA) z autoryzacją nonce + token.
     */
    private function getPubligoCourses($apiKey, $baseUrl)
    {
        return $this->testPubligoConnection($apiKey, $baseUrl);
    }

    /**
     * Wyświetla listę produktów pobranych z Publigo API.
     */
    public function productsIndex()
    {
        $apiKey = config('services.publigo.api_key');
        $baseUrl = config('services.publigo.instance_url');
        
        $result = $this->getPubligoCourses($apiKey, $baseUrl);

        return view('publigo.products.index', [
            'result' => $result,
            'apiKey' => $apiKey,
            'baseUrl' => $baseUrl
        ]);
    }
    
    

    /**
     * Generowanie nonce dla WP IDEA API
     */
    private function generateNonce()
    {
        // Zmieniono na dokładne odwzorowanie działającego skryptu PHP,
        // który używa tylko uniqid(). Poprzednia wersja generowała dłuższy
        // ciąg, co mogło być przyczyną błędu autoryzacji.
        return uniqid();
    }

    /**
     * Generowanie tokenu MD5 dla WP IDEA API
     * Zgodnie z dokumentacją: token = md5(nonce + api_key)
     */
    private function generateToken($apiKey, $nonce)
    {
        // WP IDEA wymaga tokenu MD5 z konkatenacji nonce + api_key
        return md5($nonce . $apiKey);
    }

    /**
     * Test podstawowych endpointów WordPress
     */
    private function testBasicWordPressEndpoints($baseUrl)
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'verify' => false
        ]);
        
        $tests = [];
        
        // Test 1: Podstawowy endpoint WordPress
        try {
            $response = $client->get($baseUrl . '/wp-json/');
            $tests['wp_json'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['wp_json'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 2: Endpoint wp/v2 (standardowe WordPress API)
        try {
            $response = $client->get($baseUrl . '/wp-json/wp/v2/');
            $tests['wp_v2'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['wp_v2'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 3: Endpoint wp-idea bez autoryzacji
        try {
            $response = $client->get($baseUrl . '/wp-json/wp-idea/v1/');
            $tests['wp_idea_base'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['wp_idea_base'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 4: Sprawdź czy strona główna jest dostępna
        try {
            $response = $client->get($baseUrl);
            $tests['homepage'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'content_type' => $response->getHeader('Content-Type')[0] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            $tests['homepage'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test uwierzytelnienia WordPress
     */
    private function testWordPressAuthentication($apiKey, $baseUrl)
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'verify' => false
        ]);
        
        $tests = [];
        
        // Test 1: Application Passwords (WordPress 5.6+)
        try {
            $response = $client->get($baseUrl . '/wp-json/wp/v2/users/me', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode('admin:' . $apiKey),
                    'Accept' => 'application/json'
                ]
            ]);
            $tests['application_password'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['application_password'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 2: Cookie Authentication
        try {
            $response = $client->get($baseUrl . '/wp-json/wp/v2/users/me', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Cookie' => 'wordpress_logged_in_' . md5($apiKey) . '=' . $apiKey
                ]
            ]);
            $tests['cookie_auth'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['cookie_auth'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 3: OAuth 1.0a (jeśli jest włączony)
        try {
            $response = $client->get($baseUrl . '/wp-json/wp/v2/users/me', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'OAuth oauth_consumer_key="' . $apiKey . '", oauth_signature_method="HMAC-SHA1"'
                ]
            ]);
            $tests['oauth'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['oauth'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Specjalny test WP IDEA API zgodnie z dokumentacją
     */
    private function testWpIdeaSpecific($apiKey, $baseUrl)
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'verify' => false
        ]);
        
        $tests = [];
        
        // Test 0: Sprawdź uprawnienia użytkownika (jeśli API key jest powiązany z użytkownikiem)
        try {
            $nonce = $this->generateNonce();
            $response = $client->get($baseUrl . '/wp-json/wp/v2/users/me', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'query' => [
                    'nonce' => $nonce,
                    'token' => $this->generateToken($apiKey, $nonce)
                ]
            ]);
            $tests['user_permissions'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['user_permissions'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 0.1: Sprawdź podstawowe uprawnienia WordPress (bez autoryzacji)
        try {
            $response = $client->get($baseUrl . '/wp-json/wp/v2/posts');
            $tests['public_posts_access'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['public_posts_access'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 0.2: Sprawdź czy WP IDEA plugin jest aktywny
        try {
            $response = $client->get($baseUrl . '/wp-json/');
            $body = json_decode($response->getBody(), true);
            $tests['wp_idea_plugin_status'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'wp_idea_routes' => isset($body['routes']['/wp-idea/v1']) ? 'available' : 'not_available',
                'body' => $body
            ];
        } catch (\Exception $e) {
            $tests['wp_idea_plugin_status'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 1: GET products z właściwą autoryzacją WP IDEA
        try {
            $nonce = $this->generateNonce();
            $response = $client->get($baseUrl . '/wp-json/wp-idea/v1/products', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'query' => [
                    'nonce' => $nonce,
                    'token' => $this->generateToken($apiKey, $nonce)
                ]
            ]);
            $tests['products_get'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true)
            ];
        } catch (\Exception $e) {
            $tests['products_get'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Test 2: POST orders z właściwą strukturą danych
        try {
            $testData = [
                'source' => [
                    'platform' => 'Platforma Nowoczesnej Edukacji',
                    'id' => 2024001, // Unikalny numer zamówienia
                    'url' => 'https://pnedu.pl'
                ],
                'options' => [
                    'disable_receipt' => false
                ],
                'products' => [
                    '71144' => [ // ID kursu z Publigo
                        'price_id' => 1 // Wariant ceny
                    ]
                ],
                'customer' => [
                    'email' => 'waldemar.grabowski@hostnet.pl',
                    'first_name' => 'Waldemar',
                    'last_name' => 'Grabowski'
                ],
                'shipping_address' => [
                    'address1' => 'Test Street',
                    'address2' => '',
                    'zip_code' => '00-000',
                    'city' => 'Warszawa',
                    'country_code' => 'PL'
                ]
            ];
            
            $nonce = $this->generateNonce();
            $response = $client->post($baseUrl . '/wp-json/wp-idea/v1/orders', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'query' => [
                    'nonce' => $nonce,
                    'token' => $this->generateToken($apiKey, $nonce)
                ],
                'json' => $testData
            ]);
            
            $tests['orders_post'] = [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(), true),
                'test_data' => $testData
            ];
        } catch (\Exception $e) {
            $tests['orders_post'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        return $tests;
    }

    /**
     * Test tworzenia zamówienia (POST orders)
     */
    private function testCreateOrder($apiKey, $baseUrl, $apiVersion, $timeout)
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => $timeout,
            'verify' => false
        ]);
        
        try {
            // Dane testowe zgodne z dokumentacją WP IDEA
            $testData = [
                'source' => [
                    'platform' => 'Platforma Nowoczesnej Edukacji',
                    'id' => 2024001, // Unikalny numer zamówienia (nie ID produktu)
                    'url' => 'https://pnedu.pl'
                ],
                'options' => [
                    'disable_receipt' => false
                ],
                'products' => [
                    '71144' => [ // ID kursu z Publigo
                        'price_id' => 1 // Wariant ceny
                    ]
                ],
                'customer' => [
                    'email' => 'waldemar.grabowski@hostnet.pl',
                    'first_name' => 'Waldemar',
                    'last_name' => 'Grabowski'
                ],
                'shipping_address' => [
                    'address1' => 'Test Street',
                    'address2' => '',
                    'zip_code' => '00-000',
                    'city' => 'Warszawa',
                    'country_code' => 'PL'
                ]
            ];

            // Znajdź działającą metodę autoryzacji
            $connectionTest = $this->testPubligoConnection($apiKey, $baseUrl, $apiVersion, $timeout);
            
            if ($connectionTest['status'] !== 'success') {
                return [
                    'status' => 'auth_failed',
                    'message' => 'Nie udało się znaleźć działającej metody autoryzacji',
                    'test_data' => $testData
                ];
            }
            
            // Użyj działającej metody autoryzacji
            $authConfig = $connectionTest['auth_config'];
            $requestConfig = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Publigo-API-Test/1.0'
                ],
                'json' => $testData
            ];
            
            // Dodaj konfigurację autoryzacji
            if (isset($authConfig['query'])) {
                $requestConfig['query'] = $authConfig['query'];
            }
            if (isset($authConfig['headers'])) {
                $requestConfig['headers'] = array_merge($requestConfig['headers'], $authConfig['headers']);
            }
            
            // WP IDEA API endpoint dla tworzenia zamówień
            $response = $client->post($baseUrl . '/wp-json/wp-idea/v1/orders', $requestConfig);
            
            $body = json_decode($response->getBody(), true);
            
            return [
                'status' => 'success',
                'status_code' => $response->getStatusCode(),
                'body' => $body,
                'test_data' => $testData
            ];
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return [
                'status' => 'client_error',
                'status_code' => $e->getResponse()->getStatusCode(),
                'body' => json_decode($e->getResponse()->getBody(), true),
                'message' => $e->getMessage(),
                'test_data' => $testData ?? []
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'test_data' => $testData ?? []
            ];
        }
    }
}
