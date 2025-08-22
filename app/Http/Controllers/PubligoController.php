<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Publigo;
use App\Models\Instructor;
use App\Models\Course;
use App\Models\Participant;

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
            
            \Log::info('Webhook data analysis', [
                'raw_input_length' => strlen($rawInput),
                'raw_input_preview' => substr($rawInput, 0, 200),
                'json_decode_success' => $jsonData !== null,
                'json_error' => json_last_error_msg(),
                'parsed_data_keys' => $jsonData ? array_keys($jsonData) : [],
                'laravel_parsed_keys' => array_keys($request->all())
            ]);

            // Używamy danych z JSON decode jeśli Laravel nie sparsował poprawnie
            $data = $jsonData ?: $request->all();
            
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

            // Sprawdzamy czy customer ma wymagane pola
            if (!isset($customer['first_name']) || !isset($customer['last_name']) || !isset($customer['email'])) {
                \Log::error('Missing customer fields', ['customer' => $customer]);
                return response()->json(['message' => 'Missing customer fields'], 400);
            }

            // Sprawdzamy czy url_params istnieje
            if (!isset($urlParams) || !is_array($urlParams) || empty($urlParams)) {
                \Log::error('Missing or invalid url_params', ['url_params' => $urlParams]);
                return response()->json(['message' => 'Missing url_params'], 400);
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
            foreach ($urlParams as $urlParam) {
                $productId = $urlParam['product_id'];
                $externalId = $urlParam['external_id'] ?? null;

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

                // Utwórz nowego uczestnika
                $participant = Participant::create([
                    'course_id' => $course->id,
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                    'email' => $customer['email'],
                    'birth_date' => null, // Publigo nie wysyła daty urodzenia
                    'birth_place' => null, // Publigo nie wysyła miejsca urodzenia
                    'order' => Participant::where('course_id', $course->id)->count() + 1
                ]);

                \Log::info('Participant created successfully', [
                    'participant_id' => $participant->id,
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'email' => $customer['email'],
                    'order_id' => $orderId
                ]);

                $registeredParticipants[] = [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'participant_id' => $participant->id,
                    'status' => 'created'
                ];
            }

            return response()->json([
                'message' => 'Webhook processed successfully',
                'order_id' => $orderId,
                'participants' => $registeredParticipants
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Webhook validation error', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
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
        $logs = collect();
        try {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $logLines = file($logFile);
                $webhookLogs = collect($logLines)->filter(function($line) {
                    return str_contains($line, 'Publigo webhook');
                })->reverse();
                
                $logs = $webhookLogs->map(function($line) {
                    return [
                        'timestamp' => substr($line, 1, 19),
                        'message' => trim($line)
                    ];
                })->paginate(50);
            }
        } catch (\Exception $e) {
            // Ignoruj błędy czytania logów
        }

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
        
        \Log::info('Webhook test endpoint hit', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'raw_input' => $rawInput,
            'json_decode_success' => $json !== null,
            'json_error' => json_last_error_msg(),
            'parsed_data' => $request->all()
        ]);

        if ($json === null) {
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
}
