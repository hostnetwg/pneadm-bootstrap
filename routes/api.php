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
