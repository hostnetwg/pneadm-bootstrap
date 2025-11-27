<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Course;
use App\Models\DataCompletionToken;
use App\Models\DataCompletionRequest;
use App\Mail\DataCompletionRequestMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DataCompletionService
{
    /**
     * Znajduje uczestników z brakującymi danymi dla kursów certgen_Publigo
     * Grupuje po emailu i zwraca listę z kursami
     */
    public function findParticipantsWithMissingData(?int $courseId = null): Collection
    {
        $query = Participant::query()
            ->select([
                'participants.email',
                'participants.first_name',
                'participants.last_name',
            ])
            ->join('courses', 'participants.course_id', '=', 'courses.id')
            ->where('courses.source_id_old', 'certgen_Publigo')
            ->whereNotNull('participants.email')
            ->where('participants.email', '!=', '')
            ->where(function($q) {
                $q->whereNull('participants.birth_date')
                  ->orWhereNull('participants.birth_place')
                  ->orWhere('participants.birth_place', '');
            })
            ->groupBy('participants.email', 'participants.first_name', 'participants.last_name');

        if ($courseId) {
            $query->where('courses.id', $courseId);
        }

        $groupedParticipants = $query->get();

        // Dla każdego emaila pobierz wszystkie kursy z brakami
        return $groupedParticipants->map(function($participant) use ($courseId) {
            $email = strtolower(trim($participant->email));
            
            $coursesQuery = Course::query()
                ->select([
                    'courses.id',
                    'courses.title',
                    'courses.start_date',
                    'courses.end_date',
                    'courses.instructor_id',
                ])
                ->join('participants', 'courses.id', '=', 'participants.course_id')
                ->where('courses.source_id_old', 'certgen_Publigo')
                ->where(function($q) {
                    $q->whereNull('participants.birth_date')
                      ->orWhereNull('participants.birth_place')
                      ->orWhere('participants.birth_place', '');
                })
                ->whereRaw('LOWER(TRIM(participants.email)) = ?', [strtolower(trim($email))])
                ->with('instructor')
                ->orderBy('courses.start_date', 'desc')
                ->distinct();

            if ($courseId) {
                $coursesQuery->where('courses.id', $courseId);
            }

            $courses = $coursesQuery->get();

            return [
                'email' => $email,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'full_name' => "{$participant->first_name} {$participant->last_name}",
                'courses' => $courses,
                'courses_count' => $courses->count(),
            ];
        })->filter(function($item) {
            return $item['courses_count'] > 0;
        });
    }

    /**
     * Wysyła prośby o uzupełnienie danych dla uczestników danego kursu
     */
    public function sendRequestsForCourse(int $courseId, bool $testMode = false, ?string $testEmail = null, bool $forceResend = false): array
    {
        $participants = $this->findParticipantsWithMissingData($courseId);
        $sent = 0;
        $skipped = 0;
        $resent = 0;
        $errors = [];

        foreach ($participants as $participant) {
            $email = $participant['email'];

            // W trybie testowym - tylko dla testowego emaila
            if ($testMode && $testEmail && $email !== strtolower(trim($testEmail))) {
                continue;
            }

            // Sprawdź czy już wysłano prośbę (która nie została uzupełniona)
            $hasPendingRequest = DataCompletionRequest::hasPendingRequest($email, $courseId);
            
            if ($hasPendingRequest && !$forceResend) {
                // Sprawdź czy można wysłać ponownie (token wygasł lub minęło 30 dni)
                if (!DataCompletionRequest::canResend($email, $courseId, 30)) {
                    $skipped++;
                    continue;
                }
                // Jeśli można wysłać ponownie, kontynuuj (nie pomijaj)
            }

            try {
                // Pobierz lub wygeneruj token
                $token = DataCompletionToken::generateForEmail($email, 30); // 30 dni ważności

                // Pobierz pełną listę wszystkich kursów certgen_Publigo dla tego uczestnika
                $allCourses = $this->getCoursesForEmail($email);

                // Wysyłka maila (tylko jeśli nie jest to tryb testowy lub jest testowy email)
                if (!$testMode || ($testEmail && $email === strtolower(trim($testEmail)))) {
                    // W trybie testowym zawsze wysyłaj na waldemar.grabowski@hostnet.pl
                    $recipientEmail = $testMode ? 'waldemar.grabowski@hostnet.pl' : $email;
                    
                    try {
                        $mail = new \App\Mail\DataCompletionRequestMail(
                            $token,
                            $allCourses, // Pełna lista wszystkich kursów
                            $participant['full_name'],
                            $testMode
                        );
                        
                        Log::info('Próba wysyłki emaila', [
                            'to' => $recipientEmail,
                            'from' => config('mail.from.address'),
                            'reply_to' => config('mail.data_completion.reply_to_address', 'biuro@nowoczesna-edukacja.pl'),
                            'original_email' => $email,
                            'test_mode' => $testMode,
                            'smtp_host' => config('mail.mailers.smtp.host'),
                            'smtp_port' => config('mail.mailers.smtp.port'),
                        ]);
                        
                        Mail::to($recipientEmail)->send($mail);
                        
                        Log::info('Email wysłany pomyślnie przez SMTP', [
                            'to' => $recipientEmail,
                            'from' => config('mail.from.address'),
                            'original_email' => $email,
                            'test_mode' => $testMode,
                        ]);
                    } catch (\Swift_TransportException $e) {
                        Log::error('Błąd transportu SMTP', [
                            'error' => $e->getMessage(),
                            'to' => $recipientEmail,
                            'smtp_host' => config('mail.mailers.smtp.host'),
                            'smtp_port' => config('mail.mailers.smtp.port'),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    } catch (\Exception $e) {
                        Log::error('Błąd wysyłki emaila', [
                            'error' => $e->getMessage(),
                            'to' => $recipientEmail,
                            'type' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                }

                // Loguj wysłanie prośby
                if ($hasPendingRequest && $forceResend) {
                    // Aktualizuj istniejący rekord
                    $lastRequest = DataCompletionRequest::getLastRequest($email, $courseId);
                    if ($lastRequest) {
                        $lastRequest->update(['sent_at' => now()]);
                        $resent++;
                    } else {
                        DataCompletionRequest::create([
                            'email' => $email,
                            'course_id' => $courseId,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    }
                } else {
                    // Twórz nowy rekord
                    DataCompletionRequest::create([
                        'email' => $email,
                        'course_id' => $courseId,
                        'sent_at' => now(),
                    ]);
                    $sent++;
                }

                Log::info('Wysłano prośbę o uzupełnienie danych', [
                    'email' => $email,
                    'course_id' => $courseId,
                    'test_mode' => $testMode,
                    'resent' => $hasPendingRequest && $forceResend,
                ]);

            } catch (\Exception $e) {
                $errors[] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];

                Log::error('Błąd wysyłki prośby o uzupełnienie danych', [
                    'email' => $email,
                    'course_id' => $courseId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'resent' => $resent,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => $participants->count(),
        ];
    }

    /**
     * Uzupełnia dane dla wszystkich uczestników z danym emailem
     */
    public function completeDataForEmail(string $email, string $birthDate, string $birthPlace): bool
    {
        try {
            DB::beginTransaction();

            // Konwersja daty z formatu DD-MM-RRRR na YYYY-MM-DD
            $dateParts = explode('-', $birthDate);
            if (count($dateParts) === 3) {
                $formattedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                throw new \InvalidArgumentException('Nieprawidłowy format daty');
            }

            // Aktualizuj wszystkie rekordy uczestnika z tym emailem
            $updated = Participant::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
                ->update([
                    'birth_date' => $formattedDate,
                    'birth_place' => trim($birthPlace),
                ]);

            // Oznacz wszystkie prośby dla tego emaila jako uzupełnione
            DataCompletionRequest::where('email', strtolower(trim($email)))
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);

            DB::commit();

            Log::info('Uzupełniono dane uczestnika', [
                'email' => $email,
                'updated_records' => $updated,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Błąd uzupełniania danych uczestnika', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Pobiera statystyki dla kursu
     */
    public function getCourseStatistics(int $courseId): array
    {
        $course = Course::findOrFail($courseId);

        // Uczestnicy z brakami
        $participantsWithMissingData = $this->findParticipantsWithMissingData($courseId);

        // Uczestnicy, którym wysłano prośby
        $emailsWithRequests = DataCompletionRequest::where('course_id', $courseId)
            ->whereNull('completed_at')
            ->distinct('email')
            ->pluck('email')
            ->toArray();

        // Uczestnicy, którzy uzupełnili dane - liczymy na podstawie rzeczywistych danych z participants
        // (mają birth_date i birth_place w tabeli participants dla tego kursu)
        $emailsCompleted = Participant::where('course_id', $courseId)
            ->whereNotNull('birth_date')
            ->whereNotNull('birth_place')
            ->where('birth_place', '!=', '')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->distinct('email')
            ->pluck('email')
            ->toArray();

        return [
            'course' => $course,
            'total_participants' => $course->participants()->count(),
            'participants_with_missing_data' => $participantsWithMissingData->count(),
            'requests_sent' => count($emailsWithRequests),
            'completed' => count($emailsCompleted),
        ];
    }

    /**
     * Pobiera wszystkie kursy dla uczestnika na podstawie emaila
     * (tylko kursy certgen_Publigo)
     */
    public function getCoursesForEmail(string $email): Collection
    {
        $email = strtolower(trim($email));
        
        $courses = Course::query()
            ->select([
                'courses.id',
                'courses.title',
                'courses.start_date',
                'courses.end_date',
                'courses.instructor_id',
            ])
            ->join('participants', 'courses.id', '=', 'participants.course_id')
            ->where('courses.source_id_old', 'certgen_Publigo')
            ->whereRaw('LOWER(TRIM(participants.email)) = ?', [$email])
            ->with('instructor')
            ->orderBy('courses.start_date', 'desc')
            ->distinct()
            ->get();

        return $courses;
    }

    /**
     * Odświeża statystyki dla kursów certgen_Publigo
     * Sprawdza tabelę participants i aktualizuje statystyki uzupełnionych danych
     */
    public function refreshCertgenPubligoStatistics(): array
    {
        // Pobierz wszystkie kursy z certgen_Publigo
        $courses = Course::where('source_id_old', 'certgen_Publigo')
            ->with('instructor')
            ->get();

        $totalStats = [
            'total_courses' => $courses->count(),
            'total_participants' => 0,
            'participants_with_missing_data' => 0,
            'participants_with_completed_data' => 0,
        ];

        $courseStats = [];

        foreach ($courses as $course) {
            // Licz uczestników z brakami (brak birth_date lub birth_place)
            $participantsWithMissingData = Participant::where('course_id', $course->id)
                ->where(function($q) {
                    $q->whereNull('birth_date')
                      ->orWhereNull('birth_place')
                      ->orWhere('birth_place', '');
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->distinct('email')
                ->count('email');

            // Licz uczestników z uzupełnionymi danymi (mają birth_date i birth_place)
            $participantsWithCompletedData = Participant::where('course_id', $course->id)
                ->whereNotNull('birth_date')
                ->whereNotNull('birth_place')
                ->where('birth_place', '!=', '')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->distinct('email')
                ->count('email');

            // Licz wszystkich uczestników z emailem
            $totalParticipants = Participant::where('course_id', $course->id)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->distinct('email')
                ->count('email');

            $courseStats[] = [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'start_date' => $course->start_date,
                'instructor' => $course->instructor,
                'total_participants' => $totalParticipants,
                'participants_with_missing_data' => $participantsWithMissingData,
                'participants_with_completed_data' => $participantsWithCompletedData,
            ];

            $totalStats['total_participants'] += $totalParticipants;
            $totalStats['participants_with_missing_data'] += $participantsWithMissingData;
            $totalStats['participants_with_completed_data'] += $participantsWithCompletedData;
        }

        return [
            'total_stats' => $totalStats,
            'course_stats' => $courseStats,
        ];
    }

    /**
     * Odświeża statystyki dla kursów BD:Certgen-education
     * Sprawdza tabelę participants i liczy braki oraz uzupełnione dane
     */
    public function refreshBDCertgenEducationStatistics(): array
    {
        // Pobierz wszystkie kursy z BD:Certgen-education
        $courses = Course::where('source_id_old', 'BD:Certgen-education')
            ->with('instructor')
            ->get();

        $totalStats = [
            'total_courses' => $courses->count(),
            'total_participants' => 0,
            'participants_with_missing_data' => 0,
            'participants_with_completed_data' => 0,
        ];

        $courseStats = [];

        foreach ($courses as $course) {
            // Licz uczestników z brakami (brak birth_date lub birth_place)
            $participantsWithMissingData = Participant::where('course_id', $course->id)
                ->where(function($q) {
                    $q->whereNull('birth_date')
                      ->orWhereNull('birth_place')
                      ->orWhere('birth_place', '');
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->distinct('email')
                ->count('email');

            // Licz uczestników z uzupełnionymi danymi (mają birth_date i birth_place)
            $participantsWithCompletedData = Participant::where('course_id', $course->id)
                ->whereNotNull('birth_date')
                ->whereNotNull('birth_place')
                ->where('birth_place', '!=', '')
                ->distinct('email')
                ->count('email');

            // Licz wszystkich uczestników z emailem
            $totalParticipants = Participant::where('course_id', $course->id)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->distinct('email')
                ->count('email');

            $courseStats[] = [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'start_date' => $course->start_date,
                'instructor' => $course->instructor,
                'total_participants' => $totalParticipants,
                'participants_with_missing_data' => $participantsWithMissingData,
                'participants_with_completed_data' => $participantsWithCompletedData,
            ];

            $totalStats['total_participants'] += $totalParticipants;
            $totalStats['participants_with_missing_data'] += $participantsWithMissingData;
            $totalStats['participants_with_completed_data'] += $participantsWithCompletedData;
        }

        return [
            'total_stats' => $totalStats,
            'course_stats' => $courseStats,
        ];
    }
}

