<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Pobierz preferencje zalogowanego użytkownika
     */
    public function get(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
                'preferences' => []
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'preferences' => $user->preferences ?? []
        ]);
    }

    /**
     * Zaktualizuj preferencje zalogowanego użytkownika
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required'
        ]);
        
        // Konwertuj wartość na boolean (obsługa różnych formatów)
        $booleanValue = filter_var($validated['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($booleanValue === null) {
            // Jeśli nie można przekonwertować, użyj wartości jako boolean
            $booleanValue = (bool) $validated['value'];
        }
        
        // Pobierz obecne preferencje lub utwórz pustą tablicę
        $preferences = $user->preferences ?? [];
        
        // Zaktualizuj konkretną preferencję
        $preferences[$validated['key']] = $booleanValue;
        
        // Zapisz z powrotem do bazy
        $user->preferences = $preferences;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Preferencje zostały zaktualizowane',
            'preferences' => $preferences
        ]);
    }
}
