<?php

namespace App\Services;

use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * @param  array{body: string, note_channel?: ?string, note_channel_other?: ?string, note_party?: ?string}  $attributes
     */
    public function addNote(ClosedTrainingCase $case, User $user, array $attributes): ClosedTrainingCaseEvent
    {
        $context = $this->resolveNoteContext($case, $attributes);

        return $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => $attributes['body'],
            'note_channel' => $context['note_channel'],
            'note_channel_other' => $context['note_channel_other'],
            'note_party' => $context['note_party'],
            'note_party_name' => $context['note_party_name'],
            'happened_at' => now(),
        ]);
    }

    /**
     * @param  array{body?: ?string, note_channel?: ?string, note_channel_other?: ?string, note_party?: ?string}  $attributes
     */
    public function updateEvent(ClosedTrainingCaseEvent $event, array $attributes): ClosedTrainingCaseEvent
    {
        $body = trim((string) ($attributes['body'] ?? ''));

        if ($event->event_type === ClosedTrainingCaseEvent::TYPE_EMAIL_SENT) {
            $parts = preg_split("/\n\n/", $body, 2);
            $meta = trim((string) ($parts[0] ?? ''));
            $html = ClosedTrainingCase::sanitizeSchoolEmailHtml((string) ($parts[1] ?? ''));
            $body = $html === '' ? $meta : $meta."\n\n".$html;
        }

        $event->body = $body === '' ? null : $body;

        if ($event->event_type === ClosedTrainingCaseEvent::TYPE_NOTE) {
            $case = $event->relationLoaded('caseRecord')
                ? $event->caseRecord
                : $event->caseRecord()->with(['instructor', 'course.instructor'])->first();

            if ($case) {
                $context = $this->resolveNoteContext($case, $attributes);
                $event->note_channel = $context['note_channel'];
                $event->note_channel_other = $context['note_channel_other'];
                $event->note_party = $context['note_party'];
                $event->note_party_name = $context['note_party_name'];
            }
        }

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

        if ($case->isTerminal()) {
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
     * @param  array{
     *     instructor_id: ?int,
     *     client_price: ?string,
     *     client_price_confirmed: bool,
     *     instructor_fee: ?string,
     *     instructor_fee_basis: string,
     *     instructor_fee_confirmed: bool,
     *     start_date: ?Carbon,
     *     end_date: ?Carbon,
     *     schedule_time_known: bool
     * }  $attributes
     */
    public function updateNegotiation(ClosedTrainingCase $case, array $attributes, User $user): void
    {
        if ($case->isLinkedToCourse()) {
            $forbidden = [];
            $requestedInstructorId = $attributes['instructor_id'];
            $currentInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;
            if ($requestedInstructorId !== $currentInstructorId) {
                $forbidden['instructor_id'] = 'Po powiązaniu ze szkoleniem trenera zmieniasz w edycji szkolenia.';
            }
            $requestedStart = $attributes['start_date']?->format('Y-m-d H:i');
            $requestedEnd = $attributes['end_date']?->format('Y-m-d H:i');
            if ($requestedStart !== $case->start_date?->format('Y-m-d H:i')
                || $requestedEnd !== $case->end_date?->format('Y-m-d H:i')
                || (bool) $attributes['schedule_time_known'] !== (bool) $case->schedule_time_known) {
                $forbidden['start_date'] = 'Po powiązaniu ze szkoleniem termin zmieniasz w edycji szkolenia.';
            }
            if ($forbidden !== []) {
                throw ValidationException::withMessages($forbidden);
            }

            $attributes['instructor_id'] = $currentInstructorId;
            $attributes['start_date'] = $case->start_date;
            $attributes['end_date'] = $case->end_date;
            $attributes['schedule_time_known'] = (bool) $case->schedule_time_known;
        }

        $beforeInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;
        $beforeClientPrice = $this->normalizeMoney($case->client_price);
        $beforeInstructorFee = $this->normalizeMoney($case->instructor_fee);
        $beforeFeeBasis = $case->instructor_fee_basis ?: ClosedTrainingCase::FEE_BASIS_GROSS;
        $beforeClientConfirmed = $case->client_price_confirmed_at !== null;
        $beforeFeeConfirmed = $case->instructor_fee_confirmed_at !== null;
        $beforeStart = $case->start_date?->format('Y-m-d H:i');
        $beforeEnd = $case->end_date?->format('Y-m-d H:i');
        $beforeTimeKnown = (bool) $case->schedule_time_known;
        $beforeInstructorLabel = $this->instructorLabel($case->instructor);

        $newInstructorId = $attributes['instructor_id'];
        $newClientPrice = $this->normalizeMoney($attributes['client_price']);
        $newInstructorFee = $this->normalizeMoney($attributes['instructor_fee']);
        $newFeeBasis = $attributes['instructor_fee_basis'] === ClosedTrainingCase::FEE_BASIS_NET
            ? ClosedTrainingCase::FEE_BASIS_NET
            : ClosedTrainingCase::FEE_BASIS_GROSS;
        $newStart = $attributes['start_date'];
        $newEnd = $attributes['end_date'];
        $newTimeKnown = (bool) $attributes['schedule_time_known'];

        $clientPriceChanged = $beforeClientPrice !== $newClientPrice;
        $instructorFeeChanged = $beforeInstructorFee !== $newInstructorFee;
        $feeBasisChanged = $beforeFeeBasis !== $newFeeBasis;
        $instructorChanged = $beforeInstructorId !== $newInstructorId;

        $wantClientConfirmed = (bool) $attributes['client_price_confirmed'];
        $wantFeeConfirmed = (bool) $attributes['instructor_fee_confirmed'];

        if ($newClientPrice === null) {
            $clientConfirmedAt = null;
        } elseif ($clientPriceChanged) {
            $clientConfirmedAt = $wantClientConfirmed ? now() : null;
        } else {
            $clientConfirmedAt = $wantClientConfirmed
                ? ($case->client_price_confirmed_at ?? now())
                : null;
        }

        if ($newInstructorFee === null) {
            $feeConfirmedAt = null;
        } elseif ($instructorChanged || $instructorFeeChanged || $feeBasisChanged) {
            $feeConfirmedAt = $wantFeeConfirmed ? now() : null;
        } else {
            $feeConfirmedAt = $wantFeeConfirmed
                ? ($case->instructor_fee_confirmed_at ?? now())
                : null;
        }

        $case->instructor_id = $newInstructorId;
        $case->client_price = $newClientPrice;
        $case->client_price_confirmed_at = $clientConfirmedAt;
        $case->instructor_fee = $newInstructorFee;
        $case->instructor_fee_basis = $newFeeBasis;
        $case->instructor_fee_confirmed_at = $feeConfirmedAt;
        $case->start_date = $newStart;
        $case->end_date = $newEnd;
        $case->schedule_time_known = $newTimeKnown;

        $afterClientConfirmed = $case->client_price_confirmed_at !== null;
        $afterFeeConfirmed = $case->instructor_fee_confirmed_at !== null;
        $afterStart = $case->start_date?->format('Y-m-d H:i');
        $afterEnd = $case->end_date?->format('Y-m-d H:i');

        $unchanged = $beforeInstructorId === $newInstructorId
            && $beforeClientPrice === $newClientPrice
            && $beforeInstructorFee === $newInstructorFee
            && $beforeFeeBasis === $newFeeBasis
            && $beforeClientConfirmed === $afterClientConfirmed
            && $beforeFeeConfirmed === $afterFeeConfirmed
            && $beforeStart === $afterStart
            && $beforeEnd === $afterEnd
            && $beforeTimeKnown === $newTimeKnown;

        if ($unchanged) {
            return;
        }

        $case->save();
        $case->unsetRelation('instructor');
        $case->load('instructor');

        $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => $this->negotiationChangeSummary(
                beforeInstructorLabel: $beforeInstructorLabel,
                beforeClientPrice: $beforeClientPrice,
                beforeClientConfirmed: $beforeClientConfirmed,
                beforeInstructorFee: $beforeInstructorFee,
                beforeFeeBasis: $beforeFeeBasis,
                beforeFeeConfirmed: $beforeFeeConfirmed,
                beforeStart: $beforeStart,
                beforeEnd: $beforeEnd,
                beforeTimeKnown: $beforeTimeKnown,
                case: $case,
            ),
            'happened_at' => now(),
        ]);
    }

    /**
     * Po powiązaniu: jeśli trener na szkoleniu różni się od ustaleń / potwierdzonego fee — cofnij potwierdzenie wynagrodzenia.
     */
    public function syncFeeConfirmationWithLinkedCourse(ClosedTrainingCase $case): void
    {
        $course = $case->relationLoaded('course') ? $case->course : $case->course()->first();
        if (! $course || $case->instructor_fee_confirmed_at === null) {
            return;
        }

        $courseInstructorId = $course->instructor_id !== null ? (int) $course->instructor_id : null;
        $caseInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;

        if ($courseInstructorId === $caseInstructorId) {
            return;
        }

        $case->instructor_fee_confirmed_at = null;
        $case->save();
        $case->events()->create([
            'user_id' => null,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => 'Cofnięto potwierdzenie wynagrodzenia instruktora — trener na powiązanym szkoleniu różni się od ustaleń na sprawie.',
            'happened_at' => now(),
        ]);
    }

    /**
     * Skopiuj trenera i termin ze powiązanego szkolenia na pola sprawy (po rozjeździe).
     */
    public function syncNegotiationFromLinkedCourse(ClosedTrainingCase $case, User $user): void
    {
        $course = $case->relationLoaded('course')
            ? $case->course
            : $case->course()->with('instructor')->first();

        if (! $course) {
            throw ValidationException::withMessages([
                'course' => 'Sprawa nie jest powiązana ze szkoleniem.',
            ]);
        }

        $beforeInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;
        $beforeInstructorLabel = $this->instructorLabel($case->instructor);
        $beforeStart = $case->start_date?->format('Y-m-d H:i');
        $beforeEnd = $case->end_date?->format('Y-m-d H:i');
        $beforeTimeKnown = (bool) $case->schedule_time_known;
        $beforeFeeConfirmed = $case->instructor_fee_confirmed_at !== null;

        $newInstructorId = $course->instructor_id !== null ? (int) $course->instructor_id : null;
        $newStart = $course->start_date;
        $newEnd = $course->end_date;

        $instructorChanged = $beforeInstructorId !== $newInstructorId;
        $scheduleChanged = $beforeStart !== $newStart?->format('Y-m-d H:i')
            || $beforeEnd !== $newEnd?->format('Y-m-d H:i')
            || $beforeTimeKnown !== true;

        if (! $instructorChanged && ! $scheduleChanged) {
            return;
        }

        $case->instructor_id = $newInstructorId;
        $case->start_date = $newStart;
        $case->end_date = $newEnd;
        $case->schedule_time_known = true;

        $feeNote = '';
        if ($instructorChanged && $beforeFeeConfirmed) {
            $case->instructor_fee_confirmed_at = null;
            $feeNote = ' Cofnięto potwierdzenie wynagrodzenia (zmiana trenera ze szkolenia).';
        }

        $case->save();
        $case->unsetRelation('instructor');
        $case->load('instructor');

        $parts = ['Zsynchronizowano ustalenia ze szkoleniem #'.$course->id.'.'];
        $afterInstructorLabel = $this->instructorLabel($case->instructor);
        if ($beforeInstructorLabel !== $afterInstructorLabel) {
            $parts[] = 'Trener: '.$beforeInstructorLabel.' → '.$afterInstructorLabel.'.';
        }
        if ($scheduleChanged) {
            $parts[] = 'Termin: '
                .$case->formatScheduleRange(
                    $beforeStart ? Carbon::parse($beforeStart) : null,
                    $beforeEnd ? Carbon::parse($beforeEnd) : null,
                    $beforeTimeKnown
                )
                .' → '
                .$case->formatScheduleRange($case->start_date, $case->end_date, true)
                .'.';
        }

        $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => implode(' ', $parts).$feeNote,
            'happened_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function courseLinkDiffLines(ClosedTrainingCase $case, Course $course): array
    {
        $lines = [];

        $caseInstructor = $case->instructor_id
            ? (Instructor::query()->find($case->instructor_id)?->full_title_name
                ?? Instructor::query()->find($case->instructor_id)?->first_name)
            : null;
        $courseInstructor = $course->instructor
            ? ($course->instructor->full_title_name ?? trim($course->instructor->first_name.' '.$course->instructor->last_name))
            : null;
        $caseInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;
        $courseInstructorId = $course->instructor_id !== null ? (int) $course->instructor_id : null;
        if ($caseInstructorId !== $courseInstructorId) {
            $lines[] = 'Trener: '.($caseInstructor ?: 'brak na sprawie').' → '.($courseInstructor ?: 'brak na szkoleniu').'.';
        }

        $caseStart = $case->start_date?->format('Y-m-d H:i');
        $caseEnd = $case->end_date?->format('Y-m-d H:i');
        $courseStart = $course->start_date?->format('Y-m-d H:i');
        $courseEnd = $course->end_date?->format('Y-m-d H:i');
        if (($caseStart !== null || $caseEnd !== null) && ($caseStart !== $courseStart || $caseEnd !== $courseEnd)) {
            $lines[] = 'Termin sprawy: '.$case->formatScheduleRange($case->start_date, $case->end_date, (bool) $case->schedule_time_known)
                .' → szkolenie: '.$case->formatScheduleRange($course->start_date, $course->end_date, true).'.';
        }

        return $lines;
    }

    /**
     * @param  'create'|'bind'|'replace'  $via
     */
    public function linkCourse(ClosedTrainingCase $case, Course $course, User $user, string $via = 'bind'): void
    {
        if ($course->trashed()) {
            throw ValidationException::withMessages([
                'course_id' => 'Nie można powiązać usuniętego szkolenia.',
            ]);
        }

        $existing = $case->course()->first();
        if ($existing && (int) $existing->id === (int) $course->id) {
            return;
        }

        if ($existing && $via !== 'replace') {
            throw ValidationException::withMessages([
                'course_id' => 'Ta sprawa jest już powiązana ze szkoleniem #'.$existing->id.'. Najpierw odłącz obecne powiązanie.',
            ]);
        }

        if ($course->closed_training_case_id !== null && (int) $course->closed_training_case_id !== (int) $case->id) {
            throw ValidationException::withMessages([
                'course_id' => 'Szkolenie #'.$course->id.' jest już powiązane z inną sprawą (#'.$course->closed_training_case_id.').',
            ]);
        }

        try {
            DB::transaction(function () use ($case, $course, $user, $via, $existing) {
                if ($existing && $via === 'replace') {
                    $existing->closed_training_case_id = null;
                    $existing->save();
                }

                $course->closed_training_case_id = $case->id;
                $course->save();

                $case->unsetRelation('course');
                $case->setRelation('course', $course);

                $feeNote = '';
                if ($case->instructor_fee_confirmed_at !== null) {
                    $courseInstructorId = $course->instructor_id !== null ? (int) $course->instructor_id : null;
                    $caseInstructorId = $case->instructor_id !== null ? (int) $case->instructor_id : null;
                    if ($courseInstructorId !== $caseInstructorId) {
                        $case->instructor_fee_confirmed_at = null;
                        $case->save();
                        $feeNote = ' Cofnięto potwierdzenie wynagrodzenia (inny trener na szkoleniu).';
                    }
                }

                $case->events()->create([
                    'user_id' => $user->id,
                    'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
                    'body' => $this->courseLinkSummary($course, $via, $existing).$feeNote,
                    'happened_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'course_id' => 'Nie udało się zapisać powiązania — sprawa lub szkolenie jest już powiązane. Odśwież stronę i spróbuj ponownie.',
            ]);
        }

        $case->unsetRelation('course');
    }

    public function unlinkCourse(ClosedTrainingCase $case, User $user, string $reason = 'manual'): ?Course
    {
        $course = $case->course()->first();
        if (! $course) {
            return null;
        }

        DB::transaction(function () use ($case, $course, $user, $reason) {
            $course->closed_training_case_id = null;
            $course->save();

            $extra = '';
            if ($case->instructor_fee_confirmed_at !== null) {
                $case->instructor_fee_confirmed_at = null;
                $case->save();
                $extra = ' Cofnięto potwierdzenie wynagrodzenia — sprawdź ponownie trenera, termin i warunki.';
            } else {
                $extra = ' Sprawdź ponownie trenera, termin i wynagrodzenie na sprawie.';
            }

            $case->events()->create([
                'user_id' => $user->id,
                'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
                'body' => $this->courseUnlinkSummary($course, $reason).$extra,
                'happened_at' => now(),
            ]);
        });

        $case->unsetRelation('course');

        return $course;
    }

    /**
     * Soft delete szkolenia: wyczyść FK i dopisz historię, żeby UNIQUE nie blokował ponownego powiązania.
     */
    public function detachCourseBeforeSoftDelete(Course $course, ?User $user): void
    {
        if ($course->closed_training_case_id === null) {
            return;
        }

        $case = ClosedTrainingCase::query()->find($course->closed_training_case_id);
        if (! $case) {
            $course->closed_training_case_id = null;
            $course->save();

            return;
        }

        DB::transaction(function () use ($case, $course, $user) {
            $course->closed_training_case_id = null;
            $course->save();

            $extra = '';
            if ($case->instructor_fee_confirmed_at !== null) {
                $case->instructor_fee_confirmed_at = null;
                $case->save();
                $extra = ' Cofnięto potwierdzenie wynagrodzenia — sprawdź ponownie trenera, termin i warunki.';
            } else {
                $extra = ' Sprawdź ponownie trenera, termin i wynagrodzenie na sprawie.';
            }

            $case->events()->create([
                'user_id' => $user?->id,
                'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
                'body' => $this->courseUnlinkSummary($course, 'soft_delete').$extra,
                'happened_at' => now(),
            ]);
        });
    }

    private function courseLinkSummary(Course $course, string $via, ?Course $previous): string
    {
        $label = $this->courseLabel($course);

        return match ($via) {
            'create' => 'Powiązano szkolenie (utworzone ze sprawy): '.$label.'.',
            'replace' => 'Zmieniono powiązanie szkolenia'
                .($previous ? ' z '.$this->courseLabel($previous) : '')
                .' na '.$label.'.',
            default => 'Powiązano szkolenie: '.$label.'.',
        };
    }

    private function courseUnlinkSummary(Course $course, string $reason): string
    {
        $label = $this->courseLabel($course);

        return match ($reason) {
            'soft_delete' => 'Odłączono szkolenie '.$label.' (szkolenie usunięte).',
            'replace' => 'Odłączono szkolenie '.$label.' (zmiana powiązania).',
            default => 'Odłączono szkolenie '.$label.'.',
        };
    }

    private function courseLabel(Course $course): string
    {
        $title = trim((string) $course->title);
        $when = $course->start_date
            ? $course->start_date->timezone(config('app.timezone'))->format('Y-m-d H:i')
            : null;

        return '#'.$course->id
            .($title !== '' ? ' „'.$title.'”' : '')
            .($when ? ' ('.$when.')' : '');
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

    private function normalizeMoney(null|string|float|int $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], trim($value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function instructorLabel(?Instructor $instructor): string
    {
        if (! $instructor) {
            return 'brak';
        }

        $name = trim((string) ($instructor->full_title_name ?? ($instructor->first_name.' '.$instructor->last_name)));

        return $name !== '' ? $name : 'brak';
    }

    private function negotiationChangeSummary(
        string $beforeInstructorLabel,
        ?string $beforeClientPrice,
        bool $beforeClientConfirmed,
        ?string $beforeInstructorFee,
        string $beforeFeeBasis,
        bool $beforeFeeConfirmed,
        ?string $beforeStart,
        ?string $beforeEnd,
        bool $beforeTimeKnown,
        ClosedTrainingCase $case,
    ): string {
        $parts = ['Ustalenia negocjacyjne.'];

        $afterInstructorLabel = $this->instructorLabel($case->instructor);
        if ($beforeInstructorLabel !== $afterInstructorLabel) {
            $parts[] = 'Trener: '.$beforeInstructorLabel.' → '.$afterInstructorLabel.'.';
        }

        $afterClient = $this->normalizeMoney($case->client_price);
        if ($beforeClientPrice !== $afterClient) {
            $parts[] = 'Cena dla szkoły: '.$case->formatMoney($beforeClientPrice).' → '.$case->formatMoney($afterClient).'.';
        }
        $afterClientConfirmed = $case->client_price_confirmed_at !== null;
        if ($beforeClientConfirmed !== $afterClientConfirmed) {
            $parts[] = $afterClientConfirmed
                ? 'Potwierdzono cenę dla szkoły.'
                : 'Cofnięto potwierdzenie ceny dla szkoły.';
        }

        $afterFee = $this->normalizeMoney($case->instructor_fee);
        $afterFeeBasis = $case->instructor_fee_basis ?: ClosedTrainingCase::FEE_BASIS_GROSS;
        $beforeFeeLabel = ClosedTrainingCase::instructorFeeBasisOptions()[$beforeFeeBasis] ?? $beforeFeeBasis;
        $afterFeeLabel = ClosedTrainingCase::instructorFeeBasisOptions()[$afterFeeBasis] ?? $afterFeeBasis;
        if ($beforeInstructorFee !== $afterFee || $beforeFeeBasis !== $afterFeeBasis) {
            $parts[] = 'Wynagrodzenie instruktora: '
                .$case->formatMoneyList($beforeInstructorFee).' '.$beforeFeeLabel
                .' → '
                .$case->formatMoneyList($afterFee).' '.$afterFeeLabel.'.';
        }
        $afterFeeConfirmed = $case->instructor_fee_confirmed_at !== null;
        if ($beforeFeeConfirmed !== $afterFeeConfirmed) {
            $parts[] = $afterFeeConfirmed
                ? 'Potwierdzono wynagrodzenie instruktora.'
                : 'Cofnięto potwierdzenie wynagrodzenia instruktora.';
        }

        $afterStart = $case->start_date?->format('Y-m-d H:i');
        $afterEnd = $case->end_date?->format('Y-m-d H:i');
        $afterTimeKnown = (bool) $case->schedule_time_known;
        if ($beforeStart !== $afterStart || $beforeEnd !== $afterEnd || $beforeTimeKnown !== $afterTimeKnown) {
            $parts[] = 'Termin: '.$case->formatScheduleRange($case->start_date, $case->end_date, $afterTimeKnown).'.';
        }

        return implode(' ', $parts);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array{note_channel?: ?string, note_channel_other?: ?string, note_party?: ?string}  $attributes
     * @return array{note_channel: ?string, note_channel_other: ?string, note_party: ?string, note_party_name: ?string}
     */
    private function resolveNoteContext(ClosedTrainingCase $case, array $attributes): array
    {
        $channel = $attributes['note_channel'] ?? null;
        $channelOther = null;
        if ($channel === ClosedTrainingCaseEvent::NOTE_CHANNEL_OTHER) {
            $channelOther = $this->blankToNull($attributes['note_channel_other'] ?? null);
        } elseif ($channel !== null && $channel !== '') {
            $channelOther = null;
        } else {
            $channel = null;
        }

        $party = $attributes['note_party'] ?? null;
        $partyName = null;
        if ($party === ClosedTrainingCaseEvent::NOTE_PARTY_INSTRUCTOR) {
            $name = $case->listInstructorName();
            $partyName = $name === '—' ? null : $name;
        } elseif ($party === ClosedTrainingCaseEvent::NOTE_PARTY_CLIENT) {
            $partyName = $this->blankToNull($case->contact_name);
        } else {
            $party = null;
        }

        return [
            'note_channel' => $channel,
            'note_channel_other' => $channelOther,
            'note_party' => $party,
            'note_party_name' => $partyName,
        ];
    }
}
