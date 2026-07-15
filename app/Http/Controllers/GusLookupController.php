<?php

namespace App\Http\Controllers;

use App\Services\GusBirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GusLookupController extends Controller
{
    public function __invoke(Request $request, GusBirService $gusBir): JsonResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string'],
            'target' => ['nullable', 'in:buyer,recipient'],
        ]);

        $target = $validated['target'] ?? 'buyer';

        $nip = $gusBir->normalizeNip((string) $validated['nip']);
        if ($nip === null) {
            throw ValidationException::withMessages([
                'nip' => 'Wpisz poprawny NIP składający się z 10 cyfr.',
            ]);
        }

        try {
            $data = $gusBir->lookupByNip($nip);
        } catch (RuntimeException $e) {
            Log::warning('GUS BIR lookup failed (pneadm form-orders)', [
                'target' => $target,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się teraz pobrać danych z GUS. Spróbuj ponownie lub wpisz dane ręcznie.',
            ], 503);
        } catch (\Throwable $e) {
            Log::error('GUS BIR lookup exception (pneadm form-orders)', [
                'target' => $target,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się teraz pobrać danych z GUS. Spróbuj ponownie lub wpisz dane ręcznie.',
            ], 503);
        }

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nie znaleziono podmiotu o podanym NIP.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
