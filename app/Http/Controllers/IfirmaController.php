<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\IfirmaApiService;
use Illuminate\Support\Facades\Log;

class IfirmaController extends Controller
{
    private IfirmaApiService $apiService;

    public function __construct(IfirmaApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Test połączenia z API iFirma.pl
     */
    public function testConnection()
    {
        // Pobierz konfigurację
        $login = config('services.ifirma.login', '');
        $baseUrl = config('services.ifirma.base_url', 'https://www.ifirma.pl');
        $keys = config('services.ifirma.keys', []);
        
        // Przygotuj informacje o konfiguracji (bez wyświetlania pełnych kluczy)
        $configInfo = [
            'has_login' => !empty($login),
            'login' => $login ?: 'Brak',
            'base_url' => $baseUrl,
            'keys_configured' => []
        ];

        // Sprawdź które klucze są skonfigurowane
        foreach ($keys as $keyName => $keyValue) {
            $configInfo['keys_configured'][$keyName] = !empty($keyValue);
        }

        // Maskuj klucze dla wyświetlenia (pokazuj tylko ostatnie 4 znaki)
        $maskedKeys = [];
        foreach ($keys as $keyName => $keyValue) {
            if (!empty($keyValue)) {
                $maskedKeys[$keyName] = '***' . substr($keyValue, -4);
            } else {
                $maskedKeys[$keyName] = 'Brak';
            }
        }

        // Wykonaj test połączenia
        $connectionResult = $this->apiService->testConnection();

        // Przygotuj wynik do wyświetlenia
        $results = [
            'connection' => $connectionResult,
            'config' => $configInfo
        ];

        return view('ifirma.test-connection', [
            'results' => $results,
            'login' => $login ?: 'Brak',
            'baseUrl' => $baseUrl,
            'maskedKeys' => $maskedKeys
        ]);
    }
}
