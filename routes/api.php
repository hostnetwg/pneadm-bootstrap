<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PubligoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Webhooki Publigo - bez CSRF protection
Route::post('/publigo/webhook', [PubligoController::class, 'webhook'])
    ->middleware('publigo.webhook')
    ->name('publigo.webhook');

Route::post('/publigo/webhook-test', [PubligoController::class, 'webhookTest'])
    ->name('publigo.webhook.test');

// Prosty test endpoint
Route::post('/publigo/simple-test', function() {
    return response()->json([
        'message' => 'Simple test endpoint working',
        'timestamp' => now()->toISOString()
    ]);
});

// Test endpoint z przykładowymi danymi Publigo
Route::post('/publigo/test-data', function() {
    $testData = [
        'id' => 12345,
        'user_id' => 67890,
        'status' => 'Zakończone',
        'currency' => 'PLN',
        'date_completed' => '2024-01-15 12:00:00',
        'total' => 299.00,
        'payment_method' => 'automatic',
        'url_params' => [
            [
                'product_id' => 359, // ID kursu z URL
                'details' => 'Kurs testowy',
                'external_id' => 359
            ]
        ],
        'items' => [
            [
                'name' => 'Kurs testowy',
                'id' => 359,
                'price_id' => 1,
                'quantity' => 1,
                'discount' => 0,
                'subtotal' => 299.00,
                'price' => 299.00
            ]
        ],
        'customer' => [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.com'
        ]
    ];
    
    return response()->json([
        'message' => 'Test data generated',
        'data' => $testData,
        'timestamp' => now()->toISOString()
    ]);
});
