<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class ClosedTrainingCaseService
{
    public function recordCreated(ClosedTrainingCase $case, ?User $user, string $body): ClosedTrainingCaseEvent
    {
        return $case->events()->create([
            'user_id' => $user?->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_CREATED,
            'body' => $body,
            'happened_at' => now(),
        ]);
    }

    public function addNote(ClosedTrainingCase $case, User $user, string $body): ClosedTrainingCaseEvent
    {
        return $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => $body,
            'happened_at' => now(),
        ]);
    }

    public function updateEvent(ClosedTrainingCaseEvent $event, string $body): ClosedTrainingCaseEvent
    {
        if ($event->event_type === ClosedTrainingCaseEvent::TYPE_EMAIL_SENT) {
            $parts = preg_split("/\n\n/", $body, 2);
            $meta = trim((string) ($parts[0] ?? ''));
            $html = ClosedTrainingCase::sanitizeSchoolEmailHtml((string) ($parts[1] ?? ''));
            $body = $html === '' ? $meta : $meta."\n\n".$html;
        }

        $event->body = $body === '' ? null : $body;
        $event->save();

        return $event;
    }

    public function deleteEvent(ClosedTrainingCaseEvent $event): void
    {
        $event->delete();
    }

    public function recordSchoolEmailSent(
        ClosedTrainingCase $case,
        User $user,
        string $toEmail,
        string $subject,
        string $body,
    ): ClosedTrainingCaseEvent {
        return $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_EMAIL_SENT,
            'body' => "E-mail wysłany do szkoły\nDo: {$toEmail}\nTemat: {$subject}\n\n{$body}",
            'happened_at' => now(),
        ]);
    }

    /**
     * @param  array{status: string, assignee_user_id: ?int, next_action: ?string, next_action_at: ?string}  $attributes
     */
    public function updateHandling(ClosedTrainingCase $case, array $attributes, User $user): void
    {
        $before = [
            'status' => $case->status,
            'assignee_user_id' => $case->assignee_user_id,
            'next_action' => $case->next_action,
            'next_action_at' => $case->next_action_at?->format('Y-m-d H:i'),
        ];

        $case->status = $attributes['status'];
        $case->assignee_user_id = $attributes['assignee_user_id'];
        $case->next_action = $attributes['next_action'];
        $case->next_action_at = $attributes['next_action_at'];

        if ($case->status === ClosedTrainingCase::STATUS_CLOSED) {
            $case->closed_at ??= now();
        } else {
            $case->closed_at = null;
        }

        $after = [
            'status' => $case->status,
            'assignee_user_id' => $case->assignee_user_id,
            'next_action' => $case->next_action,
            'next_action_at' => $case->next_action_at?->format('Y-m-d H:i'),
        ];

        if ($before === $after) {
            return;
        }

        $case->save();
        $case->unsetRelation('assignee');
        $case->load('assignee');

        $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => $this->changeSummary($before, $case),
            'happened_at' => now(),
        ]);
    }

    /**
     * @param  array{contact_name: string, contact_email: ?string, contact_phone: ?string, institution: ?string, institution_type: ?string, institution_type_other: ?string, preferred_format: ?string, training_offer_id: ?int, offer_url: ?string}  $attributes
     */
    public function updateContact(ClosedTrainingCase $case, array $attributes, User $user): void
    {
        $before = [
            'contact_name' => $case->contact_name,
            'contact_email' => $case->contact_email,
            'contact_phone' => $case->contact_phone,
            'institution' => $case->institution,
            'institution_www' => $case->institution_www,
            'institution_type' => $case->institution_type,
            'institution_type_other' => $case->institution_type_other,
            'preferred_format' => $case->preferred_format,
            'training_offer_id' => $case->training_offer_id,
            'offer_url' => $case->offer_url,
            'institution_type_label' => $case->institutionTypeLabel(),
            'format_label' => $case->preferredFormatLabel(),
            'offer_label' => $case->trainingOffer?->title ?: 'brak',
        ];

        $case->contact_name = $attributes['contact_name'];
        $case->contact_email = $attributes['contact_email'];
        $case->contact_phone = $attributes['contact_phone'];
        $case->institution = $attributes['institution'];
        $case->institution_www = $attributes['institution_www'];
        $case->institution_type = $attributes['institution_type'];
        $case->institution_type_other = $attributes['institution_type_other'];
        $case->preferred_format = $attributes['preferred_format'];
        $case->training_offer_id = $attributes['training_offer_id'];
        $case->offer_url = $attributes['offer_url'];

        $after = [
            'contact_name' => $case->contact_name,
            'contact_email' => $case->contact_email,
            'contact_phone' => $case->contact_phone,
            'institution' => $case->institution,
            'institution_www' => $case->institution_www,
            'institution_type' => $case->institution_type,
            'institution_type_other' => $case->institution_type_other,
            'preferred_format' => $case->preferred_format,
            'training_offer_id' => $case->training_offer_id,
            'offer_url' => $case->offer_url,
        ];

        $unchanged = true;
        foreach ([
            'contact_name',
            'contact_email',
            'contact_phone',
            'institution',
            'institution_www',
            'institution_type',
            'institution_type_other',
            'preferred_format',
            'training_offer_id',
            'offer_url',
        ] as $field) {
            if ((string) $before[$field] !== (string) $after[$field]) {
                $unchanged = false;
                break;
            }
        }

        if ($unchanged) {
            return;
        }

        $case->save();
        $case->unsetRelation('trainingOffer');
        $case->load('trainingOffer');

        $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => $this->contactChangeSummary($before, $case),
            'happened_at' => now(),
        ]);
    }

    public function recordSchoolEmail(ClosedTrainingCase $case, User $user, string $recipient, string $subject, string $body): ClosedTrainingCaseEvent
    {
        return $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_EMAIL_SENT,
            'body' => "E-mail wysłany do szkoły.\nDo: {$recipient}\nTemat: {$subject}\n\n{$body}",
            'happened_at' => now(),
        ]);
    }

    /**
     * @param  array{status: string, assignee_user_id: ?int, next_action: ?string, next_action_at: ?string}  $before
     */
    private function changeSummary(array $before, ClosedTrainingCase $case): string
    {
        $parts = [];

        if ($before['status'] !== $case->status) {
            $parts[] = 'Status: '.ClosedTrainingCase::statusOptions()[$before['status']].' → '.$case->statusLabel().'.';
        }

        if ((int) $before['assignee_user_id'] !== (int) $case->assignee_user_id) {
            $parts[] = 'Opiekun: '.($case->assignee?->name ?: 'nieprzypisany').'.';
        }

        if ((string) $before['next_action'] !== (string) $case->next_action) {
            $parts[] = 'Następne działanie: '.($case->next_action !== null && $case->next_action !== '' ? $case->next_action : 'wyczyszczone').'.';
        }

        $afterAt = $case->next_action_at?->format('Y-m-d H:i');
        if ($before['next_action_at'] !== $afterAt) {
            $parts[] = 'Termin następnego działania: '.($case->next_action_at instanceof Carbon
                ? $case->formatLocal($case->next_action_at)
                : 'wyczyszczony').'.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array{contact_name: string, contact_email: ?string, contact_phone: ?string, institution: ?string, institution_www: ?string, institution_type_label: string, format_label: string, offer_label: string}  $before
     */
    private function contactChangeSummary(array $before, ClosedTrainingCase $case): string
    {
        $parts = [];

        if ($before['contact_name'] !== $case->contact_name) {
            $parts[] = 'Osoba: '.$before['contact_name'].' → '.$case->contact_name.'.';
        }

        if ($before['contact_email'] !== $case->contact_email) {
            $parts[] = 'E-mail: '.$this->contactValue($before['contact_email']).' → '.$this->contactValue($case->contact_email).'.';
        }

        if ($before['contact_phone'] !== $case->contact_phone) {
            $parts[] = 'Telefon: '.$this->contactValue($before['contact_phone']).' → '.$this->contactValue($case->contact_phone).'.';
        }

        if ($before['institution'] !== $case->institution) {
            $parts[] = 'Nazwa szkoły / placówki: '.$this->contactValue($before['institution']).' → '.$this->contactValue($case->institution).'.';
        }

        if ($before['institution_www'] !== $case->institution_www) {
            $parts[] = 'WWW: '.$this->contactValue($before['institution_www']).' → '.$this->contactValue($case->institution_www).'.';
        }

        if ($before['institution_type_label'] !== $case->institutionTypeLabel()) {
            $parts[] = 'Rodzaj placówki: '.$before['institution_type_label'].' → '.$case->institutionTypeLabel().'.';
        }

        if ($before['format_label'] !== $case->preferredFormatLabel()) {
            $parts[] = 'Forma: '.$before['format_label'].' → '.$case->preferredFormatLabel().'.';
        }

        $offerLabel = $case->trainingOffer?->title ?: 'brak';
        if ($before['offer_label'] !== $offerLabel) {
            $parts[] = 'Oferta: '.$before['offer_label'].' → '.$offerLabel.'.';
        }

        return 'Dane zapytania. '.implode(' ', $parts);
    }

    private function contactValue(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'brak' : $value;
    }
}
