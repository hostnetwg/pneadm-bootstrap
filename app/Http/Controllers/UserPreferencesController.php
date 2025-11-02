<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Pobierz preferencje zalogowanego użytkownika
     */
    public function get()
    {
        $user = Auth::user();
        
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
        
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required|boolean'
        ]);
        
        // Pobierz obecne preferencje lub utwórz pustą tablicę
        $preferences = $user->preferences ?? [];
        
        // Zaktualizuj konkretną preferencję
        $preferences[$validated['key']] = $validated['value'];
        
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
