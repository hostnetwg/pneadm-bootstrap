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
}
