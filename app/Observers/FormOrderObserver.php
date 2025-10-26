<?php

namespace App\Observers;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Support\Facades\Log;

class FormOrderObserver
{
    /**
     * Handle the FormOrder "created" event.
     * 
     * Automatycznie tworzy rekord w form_order_participants
     * na podstawie participant_name i participant_email z form_orders
     */
    public function created(FormOrder $formOrder): void
    {
        // Sprawdź czy są dane uczestnika
        if (empty($formOrder->participant_name) || empty($formOrder->participant_email)) {
            Log::info("FormOrderObserver: Brak danych uczestnika dla zamówienia #{$formOrder->id}");
            return;
        }

        try {
            // Rozbij imię i nazwisko
            $names = $this->parseAndNormalizeName($formOrder->participant_name);

            if (!$names) {
                Log::warning("FormOrderObserver: Nie udało się przetworzyć nazwy '{$formOrder->participant_name}' dla zamówienia #{$formOrder->id}");
                return;
            }

            // Utwórz uczestnika w nowej tabeli
            FormOrderParticipant::create([
                'form_order_id' => $formOrder->id,
                'participant_firstname' => $names['firstname'],
                'participant_lastname' => $names['lastname'],
                'participant_email' => $formOrder->participant_email,
                'is_primary' => 1,
            ]);

            Log::info("FormOrderObserver: Utworzono uczestnika dla zamówienia #{$formOrder->id}: {$names['firstname']} {$names['lastname']}");

        } catch (\Exception $e) {
            Log::error("FormOrderObserver: Błąd podczas tworzenia uczestnika dla zamówienia #{$formOrder->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the FormOrder "updated" event.
     * 
     * Aktualizuje dane uczestnika w form_order_participants
     * jeśli zmieniono participant_name lub participant_email
     */
    public function updated(FormOrder $formOrder): void
    {
        // Sprawdź czy zmieniły się dane uczestnika
        if (!$formOrder->isDirty(['participant_name', 'participant_email'])) {
            return;
        }

        // Znajdź głównego uczestnika
        $participant = FormOrderParticipant::where('form_order_id', $formOrder->id)
                                          ->where('is_primary', 1)
                                          ->first();

        if (!$participant) {
            // Jeśli nie ma uczestnika, utwórz go (tak jak przy created)
            $this->created($formOrder);
            return;
        }

        try {
            // Rozbij imię i nazwisko
            $names = $this->parseAndNormalizeName($formOrder->participant_name);

            if (!$names) {
                Log::warning("FormOrderObserver: Nie udało się przetworzyć nazwy '{$formOrder->participant_name}' dla zamówienia #{$formOrder->id}");
                return;
            }

            // Aktualizuj dane uczestnika
            $participant->update([
                'participant_firstname' => $names['firstname'],
                'participant_lastname' => $names['lastname'],
                'participant_email' => $formOrder->participant_email,
            ]);

            Log::info("FormOrderObserver: Zaktualizowano uczestnika dla zamówienia #{$formOrder->id}");

        } catch (\Exception $e) {
            Log::error("FormOrderObserver: Błąd podczas aktualizacji uczestnika dla zamówienia #{$formOrder->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the FormOrder "deleted" event.
     * 
     * Uczestnicy zostaną automatycznie usunięci przez CASCADE w bazie danych,
     * ale możemy tu dodać dodatkową logikę jeśli potrzeba
     */
    public function deleted(FormOrder $formOrder): void
    {
        Log::info("FormOrderObserver: Usunięto zamówienie #{$formOrder->id} - uczestnicy zostaną usunięci przez CASCADE");
    }

    /**
     * Rozbija i normalizuje imię i nazwisko
     * 
     * Zasady:
     * - Pierwsze słowo/słowa to imię, ostatnie to nazwisko
     * - Usuwa zbędne spacje
     * - Zamienia na format: Pierwsza Litera Wielka
     * - Obsługuje dwuczłonowe nazwiska z myślnikiem
     */
    protected function parseAndNormalizeName(string $fullName): ?array
    {
        // Usuń zbędne spacje
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        if (empty($fullName)) {
            return null;
        }

        // Rozbij na części
        $parts = explode(' ', $fullName);

        if (count($parts) < 1) {
            return null;
        }

        if (count($parts) == 1) {
            // Tylko jedno słowo - traktuj jako nazwisko
            return [
                'firstname' => $this->normalizeNamePart($parts[0]),
                'lastname' => $this->normalizeNamePart($parts[0]),
            ];
        }

        // Ostatnia część to nazwisko, reszta to imię/imiona
        $lastname = array_pop($parts);
        $firstname = implode(' ', $parts);

        return [
            'firstname' => $this->normalizeNamePart($firstname),
            'lastname' => $this->normalizeNamePart($lastname),
        ];
    }

    /**
     * Normalizuje część imienia/nazwiska
     * 
     * Zasady:
     * - ADAM → Adam
     * - KOWALSKI → Kowalski
     * - KOWALSKA-NOWAK → Kowalska-Nowak
     * - jan maria → Jan Maria
     */
    protected function normalizeNamePart(string $part): string
    {
        // Usuń zbędne spacje
        $part = trim($part);

        // Rozbij po myślniku (dla nazwisk dwuczłonowych)
        $segments = explode('-', $part);

        // Normalizuj każdy segment
        $normalized = array_map(function($segment) {
            // Rozbij po spacjach (dla imion złożonych)
            $words = explode(' ', $segment);
            
            $capitalizedWords = array_map(function($word) {
                // Konwertuj na małe litery, potem pierwsza wielka
                return mb_convert_case(mb_strtolower($word), MB_CASE_TITLE, 'UTF-8');
            }, $words);
            
            return implode(' ', $capitalizedWords);
        }, $segments);

        // Połącz z powrotem przez myślnik
        return implode('-', $normalized);
    }
}
