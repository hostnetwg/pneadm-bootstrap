<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Wielu uczestników na create/edit zamówienia w panelu.
 */
class FormOrderAdminParticipantService
{
    public function maxCount(): int
    {
        $max = (int) config('form_orders.max_participants', 50);

        return $max > 0 ? $max : 50;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        $max = $this->maxCount();

        return [
            'participants' => ['nullable', 'array', 'min:1', 'max:'.$max],
            'participants.*.first_name' => ['required_with:participants', 'string', 'max:100'],
            'participants.*.last_name' => ['required_with:participants', 'string', 'max:100'],
            'participants.*.email' => ['required_with:participants', 'email', 'max:255'],
            'participant_firstname' => ['required_without:participants', 'nullable', 'string', 'max:100'],
            'participant_lastname' => ['required_without:participants', 'nullable', 'string', 'max:100'],
            'participant_email' => ['required_without:participants', 'nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        $max = $this->maxCount();

        return [
            'participants.max' => "Na jednym zamówieniu można zapisać maksymalnie {$max} uczestników.",
            'participants.min' => 'Podaj dane przynajmniej jednego uczestnika szkolenia.',
            'participants.*.first_name.required_with' => 'Imię uczestnika jest wymagane.',
            'participants.*.last_name.required_with' => 'Nazwisko uczestnika jest wymagane.',
            'participants.*.email.required_with' => 'E-mail uczestnika jest wymagany.',
            'participants.*.email.email' => 'Podaj prawidłowy adres e-mail uczestnika.',
            'participant_firstname.required_without' => 'Imię uczestnika jest wymagane.',
            'participant_lastname.required_without' => 'Nazwisko uczestnika jest wymagane.',
            'participant_email.required_without' => 'E-mail uczestnika jest wymagany.',
            'participant_email.email' => 'Podaj prawidłowy adres e-mail uczestnika.',
        ];
    }

    /**
     * @return list<array{first_name: string, last_name: string, email: string}>
     */
    public function parseFromRequest(Request $request): array
    {
        $raw = $request->input('participants');
        $rows = [];

        if (is_array($raw) && $raw !== []) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $first = trim((string) ($row['first_name'] ?? ''));
                $last = trim((string) ($row['last_name'] ?? ''));
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                if ($first === '' && $last === '' && $email === '') {
                    continue;
                }
                $rows[] = [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                ];
            }
        }

        if ($rows === []) {
            $rows[] = [
                'first_name' => trim((string) $request->input('participant_firstname', $request->input('participant_first_name', ''))),
                'last_name' => trim((string) $request->input('participant_lastname', $request->input('participant_last_name', ''))),
                'email' => strtolower(trim((string) $request->input('participant_email', ''))),
            ];
        }

        $max = $this->maxCount();
        if (count($rows) > $max) {
            $rows = array_slice($rows, 0, $max);
        }

        return array_values($rows);
    }

    /**
     * @param  list<array{first_name: string, last_name: string, email: string}>  $rows
     *
     * @throws ValidationException
     */
    public function assertEmailsUniqueOnOrder(array $rows): void
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || ! str_contains($email, '@')) {
                continue;
            }

            $key = 'participants.'.$index.'.email';
            if ($index === 0) {
                $key = 'participant_email';
            }

            if (isset($seen[$email])) {
                $errors[$key] = 'Ten sam adres e-mail nie może powtórzyć się na zamówieniu. Każdy uczestnik musi mieć własny e-mail.';

                continue;
            }
            $seen[$email] = $index;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array{first_name: string, last_name: string, email: string}>  $rows
     */
    public function sync(FormOrder $order, array $rows): void
    {
        FormOrderParticipant::syncManyFromFormOrder($order, $rows);

        $unprovisioned = $order->participants()
            ->whereNull('participant_id')
            ->whereRaw("TRIM(participant_email) != ''")
            ->exists();

        if ($unprovisioned && $order->pnedu_provisioned_at !== null) {
            $order->pnedu_provisioned_at = null;
            $order->save();
        }
    }

    /**
     * @return list<array{first_name: string, last_name: string, email: string}>
     */
    public function rowsFromFormOrder(FormOrder $order): array
    {
        $participants = $order->participants()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($participants as $participant) {
            $rows[] = [
                'first_name' => (string) $participant->participant_firstname,
                'last_name' => (string) $participant->participant_lastname,
                'email' => (string) $participant->participant_email,
            ];
        }

        return $rows;
    }
}
