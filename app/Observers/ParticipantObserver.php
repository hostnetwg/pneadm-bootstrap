<?php

namespace App\Observers;

use App\Models\Participant;
use App\Models\ParticipantEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ParticipantObserver
{
    /**
     * Normalizuj e-mail (usuń BOM, trim, lowercase)
     */
    private function normalizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }
        
        // Usuń BOM (Byte Order Mark) UTF-8
        $email = ltrim($email, "\xEF\xBB\xBF");
        // Trim i lowercase
        $email = trim(strtolower($email));
        
        return empty($email) ? null : $email;
    }

    /**
     * Aktualizuj lub utwórz rekord w participant_emails
     */
    private function syncParticipantEmail(string $email, ?int $participantId = null): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        
        if (empty($normalizedEmail)) {
            return;
        }

        try {
            // Znajdź istniejący rekord lub utwórz nowy
            $participantEmail = ParticipantEmail::where('email', $normalizedEmail)
                ->whereNull('deleted_at')
                ->first();

            if ($participantEmail) {
                // Aktualizuj liczbę uczestników
                $participantsCount = Participant::where('email', $normalizedEmail)
                    ->whereNull('deleted_at')
                    ->count();
                
                $participantEmail->update([
                    'participants_count' => $participantsCount,
                    'updated_at' => now(),
                ]);
            } else {
                // Utwórz nowy rekord
                $firstParticipantId = $participantId ?? Participant::where('email', $normalizedEmail)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->value('id');
                
                if ($firstParticipantId) {
                    ParticipantEmail::create([
                        'email' => $normalizedEmail,
                        'first_participant_id' => $firstParticipantId,
                        'participants_count' => 1,
                        'is_active' => true,
                        'is_verified' => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ParticipantObserver: Błąd podczas synchronizacji e-maila', [
                'email' => $normalizedEmail,
                'participant_id' => $participantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Usuń lub zaktualizuj rekord w participant_emails po usunięciu uczestnika
     */
    private function handleParticipantDeleted(string $email): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        
        if (empty($normalizedEmail)) {
            return;
        }

        try {
            $participantEmail = ParticipantEmail::where('email', $normalizedEmail)
                ->whereNull('deleted_at')
                ->first();

            if ($participantEmail) {
                // Sprawdź ile pozostało uczestników z tym e-mailem
                $remainingCount = Participant::where('email', $normalizedEmail)
                    ->whereNull('deleted_at')
                    ->count();

                if ($remainingCount === 0) {
                    // Jeśli nie ma już uczestników, usuń rekord (soft delete)
                    $participantEmail->delete();
                } else {
                    // Zaktualizuj liczbę uczestników
                    $participantEmail->update([
                        'participants_count' => $remainingCount,
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ParticipantObserver: Błąd podczas obsługi usunięcia uczestnika', [
                'email' => $normalizedEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Participant "created" event.
     */
    public function created(Participant $participant): void
    {
        if (empty($participant->email)) {
            return;
        }

        $this->syncParticipantEmail($participant->email, $participant->id);
    }

    /**
     * Handle the Participant "updated" event.
     */
    public function updated(Participant $participant): void
    {
        // Sprawdź czy zmienił się e-mail
        if ($participant->isDirty('email')) {
            $oldEmail = $participant->getOriginal('email');
            $newEmail = $participant->email;

            // Obsłuż stary e-mail (zmniejsz liczbę lub usuń)
            if (!empty($oldEmail)) {
                $this->handleParticipantDeleted($oldEmail);
            }

            // Obsłuż nowy e-mail (dodaj lub zaktualizuj)
            if (!empty($newEmail)) {
                $this->syncParticipantEmail($newEmail, $participant->id);
            }
        } else {
            // Jeśli e-mail się nie zmienił, tylko zaktualizuj liczbę
            if (!empty($participant->email)) {
                $this->syncParticipantEmail($participant->email, $participant->id);
            }
        }
    }

    /**
     * Handle the Participant "deleted" event (soft delete).
     */
    public function deleted(Participant $participant): void
    {
        if (empty($participant->email)) {
            return;
        }

        $this->handleParticipantDeleted($participant->email);
    }

    /**
     * Handle the Participant "restored" event (przywrócenie z soft delete).
     */
    public function restored(Participant $participant): void
    {
        if (empty($participant->email)) {
            return;
        }

        $this->syncParticipantEmail($participant->email, $participant->id);
    }
}


