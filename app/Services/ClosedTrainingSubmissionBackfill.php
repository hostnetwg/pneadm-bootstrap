<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;

class ClosedTrainingSubmissionBackfill
{
    public function backfillUntouched(): int
    {
        $editedIds = ClosedTrainingCaseEvent::query()
            ->where('event_type', ClosedTrainingCaseEvent::TYPE_UPDATED)
            ->where(function ($query) {
                $query->where('body', 'like', 'Dane kontaktu.%')
                    ->orWhere('body', 'like', 'Dane zapytania.%');
            })
            ->pluck('closed_training_case_id');

        $count = 0;

        ClosedTrainingCase::query()
            ->whereNull('submitted_payload')
            ->when($editedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $editedIds))
            ->with('trainingOffer')
            ->orderBy('id')
            ->each(function (ClosedTrainingCase $case) use (&$count) {
                $case->submitted_payload = ClosedTrainingCase::submittedSnapshot([
                    'contact_name' => $case->contact_name,
                    'contact_email' => $case->contact_email,
                    'contact_phone' => $case->contact_phone,
                    'institution' => $case->institution,
                    'institution_type' => $case->institution_type,
                    'institution_type_other' => $case->institution_type_other,
                    'preferred_format' => $case->preferred_format,
                    'training_offer_id' => $case->training_offer_id,
                    'offer_title' => $case->trainingOffer?->title,
                    'topic' => $case->topic,
                    'message' => $case->message,
                ]);
                $case->saveQuietly();
                $count++;
            });

        return $count;
    }
}
