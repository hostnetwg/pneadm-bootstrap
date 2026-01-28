<?php

namespace App\Services\Certificate;

use Carbon\Carbon;
use Illuminate\Support\Str;

class CertificateNumberGenerator
{
    /**
     * Określa rok kursu na podstawie daty rozpoczęcia
     *
     * @param object|array $course Obiekt kursu z właściwością start_date
     * @return string Rok w formacie YYYY
     */
    public function resolveCourseYear($course): string
    {
        $startDate = is_object($course) ? $course->start_date : ($course['start_date'] ?? null);
        
        return $startDate
            ? Carbon::parse($startDate)->format('Y')
            : date('Y');
    }

    /**
     * Określa następny numer sekwencji dla certyfikatu
     *
     * @param object|array $course Obiekt kursu z relacją certificates()
     * @param string $courseYear Rok kursu
     * @return int Następny numer sekwencji
     */
    public function determineNextSequence($course, string $courseYear): int
    {
        $maxSequence = null;
        $totalCertificates = 0;

        // Pobierz certyfikaty kursu
        $certificates = $this->getCourseCertificates($course);

        foreach ($certificates as $certificate) {
            $totalCertificates++;

            $sequence = $this->extractSequenceFromNumber(
                $certificate->certificate_number ?? $certificate['certificate_number'] ?? '',
                $course,
                $courseYear
            );

            if ($sequence === null) {
                $certNumber = $certificate->certificate_number ?? $certificate['certificate_number'] ?? '';
                $sequence = $this->extractFallbackSequence($certNumber);
            }

            if ($sequence !== null) {
                $maxSequence = $maxSequence === null
                    ? $sequence
                    : max($maxSequence, $sequence);
            }
        }

        if ($maxSequence !== null) {
            return $maxSequence + 1;
        }

        if ($totalCertificates > 0) {
            return $totalCertificates + 1;
        }

        return 1;
    }

    /**
     * Formatuje numer certyfikatu zgodnie z formatem kursu
     *
     * @param object|array $course Obiekt kursu z właściwościami certificate_format i id
     * @param int $sequence Numer sekwencji
     * @param string $courseYear Rok kursu
     * @return string Sformatowany numer certyfikatu
     */
    public function formatCertificateNumber($course, int $sequence, string $courseYear): string
    {
        $format = is_object($course) 
            ? ($course->certificate_format ?? '{nr}/{course_id}/{year}')
            : ($course['certificate_format'] ?? '{nr}/{course_id}/{year}');
        
        $courseId = is_object($course) ? $course->id : ($course['id'] ?? '');

        return str_replace(
            ['{nr}', '{year}', '{course_id}'],
            [$sequence, $courseYear, $courseId],
            $format
        );
    }

    /**
     * Wyodrębnia numer sekwencji z numeru certyfikatu
     *
     * @param string $certificateNumber Numer certyfikatu
     * @param object|array $course Obiekt kursu
     * @param string $courseYear Rok kursu
     * @return int|null Numer sekwencji lub null jeśli nie znaleziono
     */
    public function extractSequenceFromNumber(string $certificateNumber, $course, string $courseYear): ?int
    {
        $pattern = $this->buildFormatRegex($course, $courseYear);

        if ($pattern && preg_match($pattern, $certificateNumber, $matches) && isset($matches['nr'])) {
            return (int) $matches['nr'];
        }

        return null;
    }

    /**
     * Buduje wyrażenie regularne na podstawie formatu certyfikatu
     *
     * @param object|array $course Obiekt kursu
     * @param string $courseYear Rok kursu
     * @return string|null Wyrażenie regularne lub null
     */
    protected function buildFormatRegex($course, string $courseYear): ?string
    {
        $format = is_object($course)
            ? ($course->certificate_format ?? '{nr}/{course_id}/{year}')
            : ($course['certificate_format'] ?? '{nr}/{course_id}/{year}');

        $tokens = preg_split('/(\{(?:nr|year|course_id)\})/', $format, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (!$tokens) {
            return null;
        }

        $pattern = '';

        foreach ($tokens as $token) {
            switch ($token) {
                case '{nr}':
                    $pattern .= '(?P<nr>\d+)';
                    break;
                case '{year}':
                    $pattern .= preg_quote($courseYear, '/');
                    break;
                case '{course_id}':
                    $courseId = is_object($course) ? $course->id : ($course['id'] ?? '');
                    $pattern .= preg_quote((string) $courseId, '/');
                    break;
                default:
                    $pattern .= preg_quote($token, '/');
            }
        }

        return '/^' . $pattern . '$/';
    }

    /**
     * Wyodrębnia numer sekwencji z numeru certyfikatu (fallback - pierwsza liczba)
     *
     * @param string $certificateNumber Numer certyfikatu
     * @return int|null Numer sekwencji lub null
     */
    protected function extractFallbackSequence(string $certificateNumber): ?int
    {
        if (preg_match('/\d+/', $certificateNumber, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    /**
     * Pobiera certyfikaty kursu
     * Obsługuje zarówno obiekty Eloquent jak i tablice
     *
     * @param object|array $course Obiekt kursu
     * @return array|iterable Lista certyfikatów
     */
    protected function getCourseCertificates($course): iterable
    {
        // Jeśli to obiekt Eloquent z metodą certificates()
        if (is_object($course) && method_exists($course, 'certificates')) {
            return $course->certificates()
                ->select('id', 'certificate_number')
                ->orderBy('id')
                ->get();
        }

        // Jeśli to tablica z kluczem certificates
        if (is_array($course) && isset($course['certificates'])) {
            return $course['certificates'];
        }

        // Domyślnie zwróć pustą tablicę
        return [];
    }
}







