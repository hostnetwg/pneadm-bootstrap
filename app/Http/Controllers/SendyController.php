<?php

namespace App\Http\Controllers;

use App\Services\SendyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class SendyController extends Controller
{
    private SendyService $sendyService;

    public function __construct(SendyService $sendyService)
    {
        $this->sendyService = $sendyService;
    }

    /**
     * Wyświetla listę wszystkich list mailingowych
     */
    public function index(): View
    {
        try {
            $lists = $this->sendyService->getAllLists();
            
            // Dodaj liczbę aktywnych subskrybentów dla każdej listy
            foreach ($lists as &$list) {
                $list['active_subscribers'] = $this->sendyService->getActiveSubscriberCount($list['id']);
            }

            return view('sendy.index', compact('lists'));
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('sendy.index', [
                'lists' => [],
                'error' => 'Wystąpił błąd podczas pobierania list mailingowych: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Wyświetla szczegóły konkretnej listy
     */
    public function show(string $listId): View
    {
        try {
            $allLists = $this->sendyService->getAllLists();
            $list = collect($allLists)->firstWhere('id', $listId);

            if (!$list) {
                abort(404, 'Lista nie została znaleziona');
            }

            $list['active_subscribers'] = $this->sendyService->getActiveSubscriberCount($listId);

            return view('sendy.show', compact('list'));
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - show', [
                'message' => $e->getMessage(),
                'list_id' => $listId,
                'trace' => $e->getTraceAsString()
            ]);

            return view('sendy.show', [
                'list' => null,
                'error' => 'Wystąpił błąd podczas pobierania szczegółów listy: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Testuje połączenie z Sendy API
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->sendyService->testConnection();
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - testConnection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas testowania połączenia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sprawdza status subskrypcji użytkownika
     */
    public function checkSubscriptionStatus(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'list_id' => 'required|string'
        ]);

        try {
            $status = $this->sendyService->getSubscriptionStatus(
                $request->email,
                $request->list_id
            );

            return response()->json([
                'success' => true,
                'status' => $status,
                'email' => $request->email,
                'list_id' => $request->list_id
            ]);
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - checkSubscriptionStatus', [
                'message' => $e->getMessage(),
                'email' => $request->email,
                'list_id' => $request->list_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas sprawdzania statusu subskrypcji: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dodaje subskrybenta do listy
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'list_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'ipaddress' => 'nullable|ip',
            'referrer' => 'nullable|url',
            'gdpr' => 'nullable|boolean',
            'silent' => 'nullable|boolean'
        ]);

        try {
            $additionalData = [];
            
            if ($request->filled('name')) {
                $additionalData['name'] = $request->name;
            }
            if ($request->filled('country')) {
                $additionalData['country'] = $request->country;
            }
            if ($request->filled('ipaddress')) {
                $additionalData['ipaddress'] = $request->ipaddress;
            }
            if ($request->filled('referrer')) {
                $additionalData['referrer'] = $request->referrer;
            }
            if ($request->filled('gdpr')) {
                $additionalData['gdpr'] = $request->gdpr ? 'true' : 'false';
            }
            if ($request->filled('silent')) {
                $additionalData['silent'] = $request->silent ? 'true' : 'false';
            }

            $success = $this->sendyService->subscribe(
                $request->email,
                $request->list_id,
                $additionalData
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subskrypcja została pomyślnie dodana'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się dodać subskrypcji. Sprawdź logi dla szczegółów.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - subscribe', [
                'message' => $e->getMessage(),
                'email' => $request->email,
                'list_id' => $request->list_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas dodawania subskrypcji: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Usuwa subskrybenta z listy
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'list_id' => 'required|string'
        ]);

        try {
            $success = $this->sendyService->unsubscribe(
                $request->email,
                $request->list_id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subskrypcja została pomyślnie usunięta'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się usunąć subskrypcji. Sprawdź logi dla szczegółów.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - unsubscribe', [
                'message' => $e->getMessage(),
                'email' => $request->email,
                'list_id' => $request->list_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas usuwania subskrypcji: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Usuwa subskrybenta z listy (pełne usunięcie)
     */
    public function deleteSubscriber(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'list_id' => 'required|string'
        ]);

        try {
            $success = $this->sendyService->deleteSubscriber(
                $request->email,
                $request->list_id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subskrybent został pomyślnie usunięty'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się usunąć subskrybenta. Sprawdź logi dla szczegółów.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - deleteSubscriber', [
                'message' => $e->getMessage(),
                'email' => $request->email,
                'list_id' => $request->list_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas usuwania subskrybenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Odświeża dane list (AJAX)
     */
    public function refresh(): JsonResponse
    {
        try {
            $lists = $this->sendyService->getAllLists();
            
            // Dodaj liczbę aktywnych subskrybentów dla każdej listy
            foreach ($lists as &$list) {
                $list['active_subscribers'] = $this->sendyService->getActiveSubscriberCount($list['id']);
            }

            return response()->json([
                'success' => true,
                'lists' => $lists
            ]);
        } catch (\Exception $e) {
            Log::error('Sendy Controller Error - refresh', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas odświeżania danych: ' . $e->getMessage()
            ], 500);
        }
    }
}
