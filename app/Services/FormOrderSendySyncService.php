<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FormOrderSendySyncService
{
    /**
     * @return array{
     *   attempted:int,
     *   success:int,
     *   failed:int,
     *   errors:array<int,string>
     * }
     */
    public function syncByFormOrderId(int $formOrderId, bool $includeParticipant = true): array
    {
        $order = FormOrder::query()->with('primaryParticipant', 'course')->find($formOrderId);

        if (! $order) {
            return [
                'attempted' => 0,
                'success' => 0,
                'failed' => 1,
                'errors' => ['Nie znaleziono zamówienia form_orders.'],
            ];
        }

        return $this->syncOrder($order, $includeParticipant);
    }

    /**
     * @return array{
     *   attempted:int,
     *   success:int,
     *   failed:int,
     *   errors:array<int,string>
     * }
     */
    public function syncOrder(FormOrder $order, bool $includeParticipant = true): array
    {
        $listId = trim((string) config('sendy.lists.paid_participants'));
        if ($listId === '') {
            return [
                'attempted' => 0,
                'success' => 0,
                'failed' => 1,
                'errors' => ['Brak konfiguracji SENDY_PAID_TRAININGS_LIST_ID.'],
            ];
        }

        $contacts = $this->contactsForOrder($order, $includeParticipant);
        $results = [
            'attempted' => $contacts->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        /** @var SendyService $sendy */
        $sendy = app(SendyService::class);

        foreach ($contacts as $contact) {
            $ok = $sendy->subscribe($contact['email'], $listId, [
                'name' => $contact['name'],
                'Sername' => $contact['sername'],
                'data' => $contact['data'],
                'id_szkolenia' => (string) $contact['id_szkolenia'],
            ]);

            if ($ok) {
                $results['success']++;
                continue;
            }

            $results['failed']++;
            $results['errors'][] = sprintf(
                'Nie udało się dodać kontaktu %s (zamówienie #%d).',
                $contact['email'],
                (int) $order->id
            );
        }

        if ($results['failed'] > 0) {
            Log::warning('FormOrderSendySyncService: częściowy/pełny błąd sync do Sendy', [
                'form_order_id' => $order->id,
                'result' => $results,
            ]);
        }

        return $results;
    }

    /**
     * Sync Sendy: zamawiający + opcjonalnie wskazany uczestnik (nie zawsze primary).
     *
     * @return array{attempted:int, success:int, failed:int, errors:array<int,string>}
     */
    public function syncOrderWithParticipant(
        FormOrder $order,
        FormOrderParticipant $fop,
        bool $includeParticipant = true
    ): array {
        $listId = trim((string) config('sendy.lists.paid_participants'));
        if ($listId === '') {
            return [
                'attempted' => 0,
                'success' => 0,
                'failed' => 1,
                'errors' => ['Brak konfiguracji SENDY_PAID_TRAININGS_LIST_ID.'],
            ];
        }

        $order->loadMissing('course');
        $contacts = $this->contactsForOrderAndParticipant($order, $fop, $includeParticipant);
        $results = [
            'attempted' => $contacts->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        /** @var SendyService $sendy */
        $sendy = app(SendyService::class);

        foreach ($contacts as $contact) {
            $ok = $sendy->subscribe($contact['email'], $listId, [
                'name' => $contact['name'],
                'Sername' => $contact['sername'],
                'data' => $contact['data'],
                'id_szkolenia' => (string) $contact['id_szkolenia'],
            ]);

            if ($ok) {
                $results['success']++;
                continue;
            }

            $results['failed']++;
            $results['errors'][] = sprintf(
                'Nie udało się dodać kontaktu %s (zamówienie #%d).',
                $contact['email'],
                (int) $order->id
            );
        }

        if ($results['failed'] > 0) {
            Log::warning('FormOrderSendySyncService: częściowy/pełny błąd sync do Sendy (per uczestnik)', [
                'form_order_id' => $order->id,
                'form_order_participant_id' => $fop->id,
                'result' => $results,
            ]);
        }

        return $results;
    }

    /**
     * @return Collection<int,array{name:string, email:string, sername:string, data:string, id_szkolenia:int|string}>
     */
    public function contactsForOrderAndParticipant(
        FormOrder $order,
        FormOrderParticipant $fop,
        bool $includeParticipant = true
    ): Collection {
        $courseId = $this->resolveCourseId($order);
        $courseDate = $order->course?->start_date?->format('Y-m-d') ?? '';
        $contacts = collect();

        $participantEmail = strtolower(trim((string) ($fop->participant_email ?? '')));
        if ($includeParticipant && $participantEmail !== '' && str_contains($participantEmail, '@')) {
            $contacts->push([
                'name' => trim((string) ($fop->participant_firstname ?? '')),
                'email' => $participantEmail,
                'sername' => trim((string) ($fop->participant_lastname ?? '')),
                'data' => $courseDate,
                'id_szkolenia' => $courseId,
            ]);
        }

        $ordererEmail = strtolower(trim((string) ($order->orderer_email ?? '')));
        if ($ordererEmail !== '' && str_contains($ordererEmail, '@')) {
            $ordererName = trim((string) ($order->orderer_name ?? ''));
            [$name, $sername] = $this->splitOrdererName($ordererName);

            $contacts->push([
                'name' => $name,
                'email' => $ordererEmail,
                'sername' => $sername,
                'data' => $courseDate,
                'id_szkolenia' => $courseId,
            ]);
        }

        return $contacts
            ->filter(fn (array $row) => $row['email'] !== '')
            ->unique(fn (array $row) => $row['email'].'|'.$row['id_szkolenia'])
            ->values();
    }

    /**
     * @return Collection<int,array{
     *   name:string,
     *   email:string,
     *   sername:string,
     *   data:string,
     *   id_szkolenia:int|string
     * }>
     */
    public function contactsForOrder(FormOrder $order, bool $includeParticipant = true): Collection
    {
        $order->loadMissing('primaryParticipant');
        $primary = $order->primaryParticipant;
        if ($primary) {
            return $this->contactsForOrderAndParticipant($order, $primary, $includeParticipant);
        }

        return $this->contactsForOrderAndParticipant(
            $order,
            new FormOrderParticipant([
                'participant_firstname' => '',
                'participant_lastname' => '',
                'participant_email' => '',
            ]),
            false
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitOrdererName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        // Jeśli wygląda na osobę (co najmniej dwa człony), rozbij na imię + nazwisko.
        if (str_contains($name, ' ')) {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            return [
                trim((string) ($parts[0] ?? '')),
                trim((string) ($parts[1] ?? '')),
            ];
        }

        // Dla nazw instytucji/firm zostawiamy całość w polu Name.
        return [$name, ''];
    }

    /**
     * W Sendy pole id_szkolenia ma mieć courses.id, nigdy legacy id_old.
     */
    private function resolveCourseId(FormOrder $order): int|string
    {
        if (! empty($order->course?->id)) {
            return (int) $order->course->id;
        }

        if (! empty($order->product_id)) {
            return (int) $order->product_id;
        }

        return '';
    }
}
