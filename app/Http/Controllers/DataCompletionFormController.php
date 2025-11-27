<?php

namespace App\Http\Controllers;

use App\Models\DataCompletionToken;
use App\Services\DataCompletionService;
use App\Http\Requests\DataCompletionFormRequest;
use Illuminate\Http\Request;

class DataCompletionFormController extends Controller
{
    protected DataCompletionService $service;

    public function __construct(DataCompletionService $service)
    {
        $this->service = $service;
    }

    /**
     * Wyświetla formularz uzupełniania danych
     */
    public function show(Request $request, string $token)
    {
        $tokenModel = DataCompletionToken::where('token', $token)->first();

        if (!$tokenModel) {
            return view('data-completion.form-error', [
                'message' => 'Nieprawidłowy token. Sprawdź czy link jest kompletny.'
            ]);
        }

        if (!$tokenModel->isValid()) {
            $message = 'Token został już wykorzystany.';
            if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
                $message = 'Token wygasł. Prosimy o kontakt z administratorem.';
            }
            
            return view('data-completion.form-error', [
                'message' => $message
            ]);
        }

        // Pobierz listę kursów dla tego uczestnika
        $courses = $this->service->getCoursesForEmail($tokenModel->email);

        return view('data-completion.form', [
            'token' => $tokenModel,
            'email' => $tokenModel->email,
            'courses' => $courses,
        ]);
    }

    /**
     * Przetwarza formularz uzupełniania danych
     */
    public function store(DataCompletionFormRequest $request, string $token)
    {
        $tokenModel = DataCompletionToken::where('token', $token)->first();

        if (!$tokenModel || !$tokenModel->isValid()) {
            return redirect()->route('data-completion.form', ['token' => $token])
                ->withErrors(['error' => 'Token jest nieprawidłowy lub wygasł.']);
        }

        try {
            // Uzupełnij dane
            $this->service->completeDataForEmail(
                $tokenModel->email,
                $request->input('birth_date'),
                $request->input('birth_place')
            );

            // Oznacz token jako użyty
            $tokenModel->markAsUsed();

            return view('data-completion.form-success', [
                'message' => 'Dziękujemy! Twoje dane zostały pomyślnie zaktualizowane we wszystkich zaświadczeniach dotyczących szkoleń, w których brałeś/aś udział.'
            ]);

        } catch (\Exception $e) {
            return redirect()->route('data-completion.form', ['token' => $token])
                ->withErrors(['error' => 'Wystąpił błąd podczas zapisywania danych. Prosimy spróbować ponownie.']);
        }
    }
}

