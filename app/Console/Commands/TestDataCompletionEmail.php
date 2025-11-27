<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\DataCompletionRequestMail;
use App\Models\DataCompletionToken;
use App\Models\Course;
use App\Models\Participant;
use Illuminate\Support\Collection;

class TestDataCompletionEmail extends Command
{
    protected $signature = 'data-completion:test-email {email} {--course-id=}';
    protected $description = 'Test wysyłki emaila dla modułu uzupełniania danych';

    public function handle()
    {
        $email = $this->argument('email');
        $courseId = $this->option('course-id');

        $this->info("Testowanie wysyłki emaila dla: {$email}");
        $this->info("Email zostanie wysłany na: waldemar.grabowski@hostnet.pl (tryb testowy)");
        $this->newLine();

        // Znajdź uczestnika
        $participant = Participant::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->whereHas('course', function($q) {
                $q->where('source_id_old', 'certgen_Publigo');
            })
            ->first();

        if (!$participant) {
            $this->error("Nie znaleziono uczestnika z emailem: {$email}");
            return 1;
        }

        $this->info("Znaleziono uczestnika: {$participant->first_name} {$participant->last_name}");

        // Pobierz kursy
        $coursesQuery = Course::query()
            ->join('participants', 'courses.id', '=', 'participants.course_id')
            ->where('courses.source_id_old', 'certgen_Publigo')
            ->whereRaw('LOWER(TRIM(participants.email)) = ?', [strtolower(trim($email))])
            ->with('instructor')
            ->distinct()
            ->select('courses.*');

        if ($courseId) {
            $coursesQuery->where('courses.id', $courseId);
        }

        $courses = $coursesQuery->get();

        if ($courses->isEmpty()) {
            $this->error("Nie znaleziono kursów dla tego uczestnika");
            return 1;
        }

        $this->info("Znaleziono {$courses->count()} kursów");
        $this->newLine();

        // Generuj token
        $token = DataCompletionToken::generateForEmail($email, 30);
        $this->info("Wygenerowano token: {$token->token}");

        // Wyślij email
        try {
            $this->info("Wysyłanie emaila...");
            
            Mail::to('waldemar.grabowski@hostnet.pl')->send(
                new DataCompletionRequestMail(
                    $token,
                    $courses,
                    "{$participant->first_name} {$participant->last_name}",
                    true // test mode
                )
            );

            $this->info("✅ Email wysłany pomyślnie!");
            $this->info("Sprawdź skrzynkę: waldemar.grabowski@hostnet.pl");
            $this->info("Lub Mailpit: http://localhost:8026");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Błąd wysyłki emaila:");
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
            
            return 1;
        }
    }
}

