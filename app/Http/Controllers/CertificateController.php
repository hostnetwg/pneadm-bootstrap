<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Course;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CertificateController extends Controller
{


    public function store($participantId)
    {
        // Pobieranie uczestnika i kursu
        $participant = Participant::findOrFail($participantId);
        $course = Course::findOrFail($participant->course_id);
    
        $courseYear = $this->resolveCourseYear($course);
        $nextCertificateNumber = $this->determineNextSequence($course, $courseYear);
        $certificateNumber = $this->formatCertificateNumber($course, $nextCertificateNumber, $courseYear);
    
        // Zapis numeru certyfikatu w bazie danych (bez generowania PDF)
        Certificate::updateOrCreate(
            ['participant_id' => $participant->id, 'course_id' => $course->id],
            ['certificate_number' => $certificateNumber, 'generated_at' => now()]
        );
    
        return redirect()->back()->with('success', "Certyfikat nr {$certificateNumber} został zapisany.");
    }
    
    

    public function generate($participantId)
    {
        // Pobieranie uczestnika i kursu
        $participant = Participant::findOrFail($participantId);
        $course = Course::findOrFail($participant->course_id);
    
        // Pobieranie instruktora, jeśli kurs ma relację do instruktora
        $instructor = $course->instructor ?? null;
    
        // Pobranie istniejącego certyfikatu z bazy danych
        $certificate = Certificate::where('participant_id', $participant->id)
            ->where('course_id', $course->id)
            ->first();
    
        // Jeśli certyfikat nie istnieje, zwracamy błąd
        if (!$certificate) {
            return redirect()->back()->with('error', 'Certyfikat dla tego uczestnika nie został znaleziony.');
        }
    
        // Używamy istniejącego numeru certyfikatu
        $certificateNumber = $certificate->certificate_number;
    
        // Tworzenie nazwy pliku na podstawie numeru certyfikatu
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = 'certificates/' . $fileName; // Ścieżka w public/storage
    
        // Tworzenie ścieżki do folderu kursu
        $courseFolder = 'certificates/' . $course->id;
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = $courseFolder . '/' . $fileName; // Ścieżka w public/storage

        // Sprawdzenie i utworzenie katalogu jeśli nie istnieje
        if (!Storage::disk('public')->exists($courseFolder)) {
            Storage::disk('public')->makeDirectory($courseFolder, 0777, true);
        }
    
        // Usuwamy ewentualne spacje i konwertujemy format
        $startDateTime = Carbon::parse(trim($course->start_date));
        $endDateTime = Carbon::parse(trim($course->end_date));

        // Obliczanie czasu trwania w minutach
        $durationMinutes = $startDateTime->diffInMinutes($endDateTime);

        // Określenie szablonu do użycia
        $templateView = 'certificates.default'; // Domyślny szablon
        
        // Jeśli kurs ma przypisany szablon, użyj go
        if ($course->certificateTemplate && $course->certificateTemplate->bladeFileExists()) {
            $templateView = $course->certificateTemplate->blade_path;
        }
    
        // Tworzenie widoku PDF z przekazaniem wszystkich danych
        $isPdfMode = true; // Generujemy PDF, nie podgląd HTML
        
        $pdf = Pdf::loadView($templateView, [
            'participant' => $participant,
            'certificateNumber' => $certificateNumber,
            'course' => $course,
            'instructor' => $instructor,
            'durationMinutes' => $durationMinutes,
            'isPdfMode' => $isPdfMode,
        ])->setPaper('A4', 'portrait')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans', // Obsługa polskich znaków
              'isHtml5ParserEnabled' => true, 
              'isRemoteEnabled' => true
          ]);
    
        // Zapisywanie pliku PDF w storage/public/certificates
        Storage::disk('public')->put($filePath, $pdf->output());
    
        // Aktualizacja ścieżki pliku w bazie (jeśli brak)
        if (empty($certificate->file_path)) {
            $certificate->update([
                'file_path' => 'storage/' . $filePath,
                'generated_at' => now(),
            ]);
        }
    
        // Pobieranie pliku PDF (z folderu public/storage)
        return response()->download(storage_path('app/public/' . $filePath));
    }
    

    public function destroy(Certificate $certificate)
    {
        //dd($certificate->file_path);

        // Sprawdzenie, czy certyfikat ma powiązany plik PDF
        if ($certificate->file_path) {
            $filePath = storage_path("app/public/" . str_replace("storage/", "", $certificate->file_path));
    
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    
        // Usunięcie certyfikatu z bazy danych
        $certificate->delete();
    
        return redirect()->back()->with('success', 'Certyfikat został usunięty.');
    }

    public function bulkGenerate(Course $course)
    {
        // Pobranie wszystkich uczestników kursu, którzy nie mają jeszcze certyfikatu
        $participantsWithoutCertificates = $course->participants()
            ->whereDoesntHave('certificate')
            ->orderBy('order')
            ->get();

        if ($participantsWithoutCertificates->isEmpty()) {
            return redirect()->back()->with('info', 'Wszyscy uczestnicy mają już wygenerowane zaświadczenia.');
        }

        $courseYear = $this->resolveCourseYear($course);
        $nextCertificateNumber = $this->determineNextSequence($course, $courseYear);

        $generatedCount = 0;

        foreach ($participantsWithoutCertificates as $participant) {
            $certificateNumber = $this->formatCertificateNumber($course, $nextCertificateNumber, $courseYear);

            // Zapis numeru certyfikatu w bazie danych
            Certificate::create([
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
                'generated_at' => now()
            ]);

            $nextCertificateNumber++;
            $generatedCount++;
        }

        return redirect()->back()->with('success', "Wygenerowano {$generatedCount} zaświadczeń dla uczestników bez certyfikatów.");
    }



    public function bulkDelete(Course $course)
    {
        // Pobranie wszystkich certyfikatów dla danego kursu
        $certificates = Certificate::where('course_id', $course->id)->get();

        if ($certificates->isEmpty()) {
            return redirect()->back()->with('info', 'Brak zaświadczeń do usunięcia dla tego szkolenia.');
        }

        $deletedCount = 0;

        foreach ($certificates as $certificate) {
            // Sprawdzenie, czy certyfikat ma powiązany plik PDF
            if ($certificate->file_path) {
                $filePath = storage_path("app/public/" . str_replace("storage/", "", $certificate->file_path));
                
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Usunięcie certyfikatu z bazy danych
            $certificate->delete();
            $deletedCount++;
        }

        return redirect()->back()->with('success', "Usunięto {$deletedCount} zaświadczeń dla szkolenia '{$course->title}'.");
    }

    public function importFromPubligo(Request $request, Course $course)
    {
        $request->validate([
            'certificates_csv' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $file = $request->file('certificates_csv');
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            return redirect()->route('participants.index', $course)
                ->with('error', 'Nie udało się odczytać przesłanego pliku.');
        }

        $rawHeader = fgetcsv($handle);

        if (!$rawHeader) {
            fclose($handle);
            return redirect()->route('participants.index', $course)
                ->with('error', 'Plik CSV nie zawiera nagłówka.');
        }

        $headerMap = $this->prepareHeaderMap($rawHeader);
        $requiredColumns = ['email', 'numer certyfikatu'];

        foreach ($requiredColumns as $column) {
            if (!array_key_exists($column, $headerMap)) {
                fclose($handle);
                return redirect()->route('participants.index', $course)
                    ->with('error', "Brakuje wymaganej kolumny: {$column}.");
            }
        }

        $stats = [
            'createdCertificates' => 0,
            'updatedCertificates' => 0,
            'skippedRows' => 0,
            'createdParticipants' => 0,
            'errors' => [],
        ];

        $currentOrder = $course->participants()->max('order') ?? 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rowData = $this->mapRowByHeader($headerMap, $row);

            $email = Str::lower(trim($rowData['email'] ?? ''));
            $certificateNumber = trim($rowData['numer certyfikatu'] ?? '');

            if ($certificateNumber === '') {
                $stats['skippedRows']++;
                continue;
            }

            $participant = $this->findParticipantByEmail($course, $email);

            if (!$participant) {
                $fullName = $rowData['imię i nazwisko'] ?? '';
                [$firstName, $lastName] = $this->splitFullName($fullName);
                $currentOrder++;

                $participant = Participant::create([
                    'course_id' => $course->id,
                    'first_name' => $firstName ?: 'Uczestnik',
                    'last_name' => $lastName ?: '',
                    'email' => $email ?: null,
                    'order' => $currentOrder,
                ]);

                $stats['createdParticipants']++;
            }

            $generatedAt = $this->parseCertificateDate($rowData['data utworzenia'] ?? null);

            $existingCertificateByNumber = Certificate::where('certificate_number', $certificateNumber)->first();

            if ($existingCertificateByNumber && $existingCertificateByNumber->course_id !== $course->id) {
                $stats['errors'][] = "Numer {$certificateNumber} jest już przypisany do innego szkolenia.";
                $stats['skippedRows']++;
                continue;
            }

            if ($existingCertificateByNumber) {
                $existingCertificateByNumber->update([
                    'participant_id' => $participant->id,
                    'course_id' => $course->id,
                    'generated_at' => $generatedAt ?? $existingCertificateByNumber->generated_at ?? now(),
                ]);

                $stats['updatedCertificates']++;
                continue;
            }

            $participantCertificate = Certificate::where('participant_id', $participant->id)
                ->where('course_id', $course->id)
                ->first();

            if ($participantCertificate) {
                $participantCertificate->update([
                    'certificate_number' => $certificateNumber,
                    'generated_at' => $generatedAt ?? $participantCertificate->generated_at ?? now(),
                ]);

                $stats['updatedCertificates']++;
            } else {
                Certificate::create([
                    'participant_id' => $participant->id,
                    'course_id' => $course->id,
                    'certificate_number' => $certificateNumber,
                    'generated_at' => $generatedAt ?? now(),
                ]);

                $stats['createdCertificates']++;
            }
        }

        fclose($handle);

        $messageParts = [];
        $messageParts[] = "Dodano {$stats['createdCertificates']} nowych zaświadczeń";
        $messageParts[] = "zaktualizowano {$stats['updatedCertificates']}";

        if ($stats['createdParticipants'] > 0) {
            $messageParts[] = "dodano {$stats['createdParticipants']} nowych uczestników";
        }

        if ($stats['skippedRows'] > 0) {
            $messageParts[] = "pominięto {$stats['skippedRows']} wierszy";
        }

        $message = implode(', ', $messageParts) . '.';

        if (!empty($stats['errors'])) {
            $message .= ' Błędy: ' . implode(' | ', array_slice($stats['errors'], 0, 3));
        }

        return redirect()->route('participants.index', $course)->with('success', $message);
    }
    
    private function resolveCourseYear(Course $course): string
    {
        return $course->start_date
            ? Carbon::parse($course->start_date)->format('Y')
            : date('Y');
    }

    private function determineNextSequence(Course $course, string $courseYear): int
    {
        $maxSequence = null;
        $totalCertificates = 0;

        $course->certificates()
            ->select('id', 'certificate_number')
            ->orderBy('id')
            ->chunkById(200, function ($certificates) use (&$maxSequence, &$totalCertificates, $course, $courseYear) {
                foreach ($certificates as $certificate) {
                    $totalCertificates++;

                    $sequence = $this->extractSequenceFromNumber(
                        $certificate->certificate_number,
                        $course,
                        $courseYear
                    );

                    if ($sequence === null) {
                        $sequence = $this->extractFallbackSequence($certificate->certificate_number);
                    }

                    if ($sequence !== null) {
                        $maxSequence = $maxSequence === null
                            ? $sequence
                            : max($maxSequence, $sequence);
                    }
                }
            });

        if ($maxSequence !== null) {
            return $maxSequence + 1;
        }

        if ($totalCertificates > 0) {
            return $totalCertificates + 1;
        }

        return 1;
    }

    private function formatCertificateNumber(Course $course, int $sequence, string $courseYear): string
    {
        $format = $course->certificate_format ?? '{nr}/{course_id}/{year}';

        return str_replace(
            ['{nr}', '{year}', '{course_id}'],
            [$sequence, $courseYear, $course->id],
            $format
        );
    }

    private function extractSequenceFromNumber(string $certificateNumber, Course $course, string $courseYear): ?int
    {
        $pattern = $this->buildFormatRegex($course, $courseYear);

        if ($pattern && preg_match($pattern, $certificateNumber, $matches) && isset($matches['nr'])) {
            return (int) $matches['nr'];
        }

        return null;
    }

    private function buildFormatRegex(Course $course, string $courseYear): ?string
    {
        $format = $course->certificate_format ?? '{nr}/{course_id}/{year}';

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
                    $pattern .= preg_quote((string) $course->id, '/');
                    break;
                default:
                    $pattern .= preg_quote($token, '/');
            }
        }

        return '/^' . $pattern . '$/';
    }

    private function prepareHeaderMap(array $rawHeader): array
    {
        $map = [];

        foreach ($rawHeader as $index => $column) {
            $normalized = $this->normalizeHeaderValue($column);

            if ($normalized === '') {
                continue;
            }

            $map[$normalized] = $index;
        }

        return $map;
    }

    private function normalizeHeaderValue(?string $value): string
    {
        $clean = trim($value ?? '', "\xEF\xBB\xBF\" \t\n\r\0\x0B");

        return Str::lower($clean);
    }

    private function mapRowByHeader(array $headerMap, array $row): array
    {
        $assoc = [];

        foreach ($headerMap as $key => $index) {
            $assoc[$key] = $row[$index] ?? null;
        }

        return $assoc;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findParticipantByEmail(Course $course, ?string $email): ?Participant
    {
        if (!$email) {
            return null;
        }

        return Participant::where('course_id', $course->id)
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }

    private function splitFullName(?string $fullName): array
    {
        $fullName = trim(str_replace('"', '', $fullName ?? ''));

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName);
        $firstName = array_shift($parts) ?: '';
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private function extractFallbackSequence(string $certificateNumber): ?int
    {
        if (preg_match('/\d+/', $certificateNumber, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function parseCertificateDate(?string $value): ?Carbon
    {
        $value = trim(str_replace('"', '', $value ?? ''));

        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd.m.Y H:i',
            'd.m.Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}