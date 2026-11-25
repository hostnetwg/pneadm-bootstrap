<?php

namespace App\Services;

use App\Mail\TrainerPortalCaseSharedMail;
use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\Instructor;
use App\Models\TrainerPortalNotification;
use App\Models\User;
use App\Services\Sms\SmsapiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrainerPortalService
{
    public function __construct(
        private readonly SmsapiClient $smsapi,
    ) {}

    /**
     * @return array{warning: ?string}
     */
    public function applySharing(
        ClosedTrainingCase $case,
        bool $wanted,
        bool $wasShared,
        ?int $previousInstructorId,
        User $actor,
    ): array {
        $instructor = $case->instructor_id
            ? Instructor::query()->find($case->instructor_id)
            : null;
        $canShare = $wanted
            && $instructor instanceof Instructor
            && $instructor->portal_access_enabled
            && $instructor->pnedu_user_id;

        $case->show_in_trainer_panel = (bool) $canShare;
        if ($canShare && ! $wasShared) {
            $case->trainer_panel_shared_at = now();
        }
        $case->save();

        if (! $canShare) {
            if ($wasShared) {
                $case->events()->create([
                    'user_id' => $actor->id,
                    'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
                    'body' => 'Sprawa ukryta w panelu trenera. Wpisany termin zostaje na sprawie.',
                    'happened_at' => now(),
                ]);
            }

            return ['warning' => null];
        }

        $instructorChanged = (int) $previousInstructorId !== (int) $case->instructor_id;
        if ($wasShared && ! $instructorChanged) {
            return ['warning' => null];
        }

        $warning = $this->notifyTrainer($case, $instructor, true, $actor);
        $case->events()->create([
            'user_id' => $actor->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => 'Sprawa pokazana w panelu trenera: '.$instructor->full_title_name.'.',
            'happened_at' => now(),
        ]);

        return ['warning' => $warning];
    }

    /**
     * @return array{warning: ?string}
     */
    public function shareWithMessage(ClosedTrainingCase $case, User $actor, ?string $message): array
    {
        $instructor = $case->instructor_id
            ? Instructor::query()->find($case->instructor_id)
            : null;
        if (! $instructor instanceof Instructor || ! $instructor->portal_access_enabled || ! $instructor->pnedu_user_id) {
            throw ValidationException::withMessages([
                'show_in_trainer_panel' => 'Najpierw przypisz instruktora i włącz panel trenera na jego karcie.',
            ]);
        }

        $message = trim((string) $message);
        $message = $message === '' ? null : $message;
        $previous = trim((string) $case->trainer_panel_message);
        $previous = $previous === '' ? null : $previous;
        $wasShared = (bool) $case->show_in_trainer_panel;

        $case->show_in_trainer_panel = true;
        $case->trainer_panel_message = $message;
        if (! $wasShared) {
            $case->trainer_panel_shared_at = now();
        }
        $case->save();

        if ($wasShared && $previous === $message) {
            return ['warning' => null];
        }

        $warning = $this->notifyTrainer($case, $instructor, ! $wasShared, $actor);
        $body = 'Sprawa pokazana w panelu trenera: '.$instructor->full_title_name.'.';
        if ($message !== null) {
            $body .= "\nWiadomość do trenera:\n".$message;
        }
        $case->events()->create([
            'user_id' => $actor->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => $body,
            'happened_at' => now(),
        ]);

        return ['warning' => $warning];
    }

    public function hideFromTrainer(ClosedTrainingCase $case, User $actor): void
    {
        if (! $case->show_in_trainer_panel) {
            return;
        }

        $case->show_in_trainer_panel = false;
        $case->save();
        $case->events()->create([
            'user_id' => $actor->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => 'Sprawa ukryta w panelu trenera. Wpisany termin zostaje na sprawie.',
            'happened_at' => now(),
        ]);
    }

    private function notifyTrainer(ClosedTrainingCase $case, Instructor $instructor, bool $sendSms, User $actor): ?string
    {
        $panelUrl = rtrim((string) config('marketing.pnedu_public_url'), '/').'/trener/zapytania/'.$case->id;
        $topic = $case->trainingTopicLabel();

        $notification = TrainerPortalNotification::query()->create([
            'audience' => TrainerPortalNotification::AUDIENCE_TRAINER,
            'pnedu_user_id' => $instructor->pnedu_user_id,
            'closed_training_case_id' => $case->id,
            'kind' => TrainerPortalNotification::KIND_CASE_SHARED,
            'title' => 'Nowe zapytanie',
            'body' => 'Zapytanie o szkolenie: '.$topic.'. Kontakt jest w panelu.',
        ]);

        $warning = null;

        try {
            $senderName = trim((string) $actor->name);
            Mail::to($instructor->email)->send(new TrainerPortalCaseSharedMail(
                $case,
                $instructor,
                $panelUrl,
                $senderName !== '' ? $senderName : (string) $actor->email,
            ));
            $notification->email_sent_at = now();
            $notification->save();
        } catch (Throwable $exception) {
            Log::warning('Trainer portal share mail failed', [
                'case_id' => $case->id,
                'exception' => $exception::class,
            ]);
            $warning = 'E-mail do trenera nie wyszedł.';
        }

        if (! $sendSms || ! $instructor->sms_notifications_enabled) {
            return $warning;
        }

        $phone = $this->normalizePhone($instructor->phone);
        if ($phone === null) {
            return trim(($warning ? $warning.' ' : '').'SMS pominięty: brak poprawnego numeru telefonu trenera.');
        }

        $sms = 'PNE: nowe zapytanie '.ClosedTrainingCase::emailReference($case->id).'. Szczegóły w panelu trenera na pnedu.pl.';
        $result = $this->smsapi->sendSms($phone, $sms);
        if ($result['ok']) {
            $notification->sms_sent_at = now();
            $notification->save();

            return $warning;
        }

        Log::warning('Trainer portal share SMS failed', [
            'case_id' => $case->id,
            'message' => $result['message'],
        ]);

        return trim(($warning ? $warning.' ' : '').'SMS do trenera nie wyszedł.');
    }

    private function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $raw)) ?: '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0048')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9) {
            $digits = '48'.$digits;
        }
        if (! preg_match('/^48\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
