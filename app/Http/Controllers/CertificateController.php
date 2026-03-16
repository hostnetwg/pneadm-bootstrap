<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Course;
use App\Jobs\GenerateCertificatePdfJob;
use App\Services\Certificate\CertificateGeneratorService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // Pobieranie uczestnika i kursu z załadowaną relacją szablonu
        $participant = Participant::findOrFail($participantId);
        $course = Course::with('certificateTemplate')->findOrFail($participant->course_id);
    
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

        // Użyj nowego CertificateGeneratorService zamiast plików Blade
        $certificateGenerator = app(CertificateGeneratorService::class);
        
        // Generuj PDF używając nowego systemu (renderowanie z JSON)
        $pdf = $certificateGenerator->generatePdf($participant->id, [
            'save_to_storage' => true,
            'cache' => false
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
    
        // Pobieranie pliku PDF (z folderu public/storage) – nazwa przy zapisie na dysk użytkownika z przedrostkiem
        $downloadFileName = 'zaswiadczenie_' . str_replace('/', '-', $certificateNumber) . '.pdf';
        return response()->download(storage_path('app/public/' . $filePath), $downloadFileName);
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
        // Pobranie wszystkich uczestników kursu z kompletnymi danymi
        // Kompletne dane: Nazwisko, Imię, Data urodzenia, Miejsce urodzenia
        $participantsWithCompleteData = $course->participants()
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '')
            ->whereNotNull('birth_date')
            ->whereNotNull('birth_place')
            ->where('birth_place', '!=', '')
            ->orderBy('order')
            ->get();

        if ($participantsWithCompleteData->isEmpty()) {
            return redirect()->route('participants.index', $course)->with('info', 'Brak uczestników z kompletnymi danymi (Nazwisko, Imię, Data urodzenia, Miejsce urodzenia).');
        }

        $courseYear = $this->resolveCourseYear($course);
        $nextCertificateNumber = $this->determineNextSequence($course, $courseYear);

        $generatedCount = 0;

        foreach ($participantsWithCompleteData as $participant) {
            $certificateNumber = $this->formatCertificateNumber($course, $nextCertificateNumber, $courseYear);

            // Zapis numeru certyfikatu w bazie danych
            // Uwaga: generujemy zaświadczenia również dla uczestników, którzy już mają zaświadczenia
            Certificate::create([
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
                'generated_at' => now()
            ]);

            $nextCertificateNumber++;
            $generatedCount++;
        }

        return redirect()->route('participants.index', $course)->with('success', "Wygenerowano {$generatedCount} zaświadczeń dla uczestników z kompletnymi danymi.");
    }

    /**
     * Generowanie zaświadczeń dla WSZYSTKICH uczestników (bez filtrowania po komplecie danych)
     */
    public function bulkGenerateAll(Course $course)
    {
        // Pobranie wszystkich uczestników kursu (bez filtrowania)
        $allParticipants = $course->participants()
            ->orderBy('order')
            ->get();

        if ($allParticipants->isEmpty()) {
            return redirect()->route('participants.index', $course)->with('info', 'Brak uczestników w tym szkoleniu.');
        }

        $courseYear = $this->resolveCourseYear($course);
        $nextCertificateNumber = $this->determineNextSequence($course, $courseYear);

        $generatedCount = 0;

        foreach ($allParticipants as $participant) {
            $certificateNumber = $this->formatCertificateNumber($course, $nextCertificateNumber, $courseYear);

            // Zapis numeru certyfikatu w bazie danych
            // Uwaga: generujemy zaświadczenia również dla uczestników, którzy już mają zaświadczenia
            Certificate::create([
                'participant_id' => $participant->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
                'generated_at' => now()
            ]);

            $nextCertificateNumber++;
            $generatedCount++;
        }

        return redirect()->route('participants.index', $course)->with('success', "Wygenerowano {$generatedCount} zaświadczeń dla wszystkich uczestników.");
    }

    /**
     * Zleca generowanie plików PDF dla wszystkich zaświadczeń kursu.
     * Dla małej liczby zaświadczeń (≤25) generuje od razu w żądaniu (bez workera).
     * Dla większej liczby używa batcha w tle – wymaga działającego workera (sail artisan queue:work).
     */
    public function generateAllPdfs(Course $course)
    {
        $certificates = Certificate::where('course_id', $course->id)->get();
        if ($certificates->isEmpty()) {
            return redirect()->route('participants.index', $course)->with('info', 'Brak zaświadczeń dla tego szkolenia. Najpierw wygeneruj zaświadczenia (rekordy w bazie).');
        }

        $count = $certificates->count();
        $syncThreshold = 25;

        if ($count <= $syncThreshold) {
            // Generowanie synchroniczne – działa bez queue workera
            set_time_limit(600); // do 10 min (dompdf + obrazy mogą trwać kilka sekund na PDF)
            $generator = app(CertificateGeneratorService::class);
            $generated = 0;
            foreach ($certificates as $certificate) {
                $cert = Certificate::where('participant_id', $certificate->participant_id)->first();
                if ($cert && !empty($cert->file_path)) {
                    $relativePath = Str::replaceFirst('storage/', '', $cert->file_path);
                    if (Storage::disk('public')->exists($relativePath)) {
                        continue; // idempotent – plik już jest
                    }
                }
                try {
                    $generator->generatePdf($certificate->participant_id, [
                        'save_to_storage' => true,
                        'cache' => false,
                    ]);
                    $generated++;
                } catch (\Throwable $e) {
                    Log::warning('GenerateCertificatePdf (sync): błąd', [
                        'participant_id' => $certificate->participant_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            Cache::put("certificate_pdf_generation_finished_{$course->id}", now()->toDateTimeString(), 86400);
            return redirect()->route('participants.index', $course)->with('success', "Wygenerowano pliki PDF dla {$generated} zaświadczeń.");
        }

        // Duża liczba – batch w tle (wymaga queue workera)
        $jobs = $certificates->map(fn ($certificate) => new GenerateCertificatePdfJob($certificate->participant_id))->all();

        Bus::batch($jobs)
            ->name("certificate-pdfs-course-{$course->id}")
            ->then(function (Batch $batch) {
                $name = $batch->name ?? '';
                if (preg_match('/^certificate-pdfs-course-(\d+)$/', $name, $m)) {
                    $courseId = (int) $m[1];
                    Cache::put("certificate_pdf_generation_finished_{$courseId}", now()->toDateTimeString(), 86400);
                }
            })
            ->dispatch();

        return redirect()->route('participants.index', $course)->with('success', "Zlecono generowanie {$count} plików PDF. Pliki są generowane w tle. Po zakończeniu zobaczysz komunikat na tej stronie. Upewnij się, że worker kolejki działa (sail artisan queue:work).");
    }

    /**
     * Postęp generowania plików PDF dla kursu (do odpytywania co 2 s z frontu).
     * Liczba „X z Y” z tabeli certificates (faktyczne pliki), nie z job_batches.
     */
    public function pdfGenerationProgress(Course $course)
    {
        $total = Certificate::where('course_id', $course->id)->count();
        $withFile = Certificate::where('course_id', $course->id)->whereNotNull('file_path')->where('file_path', '!=', '')->count();

        return response()->json([
            'total' => $total,
            'with_file' => $withFile,
        ]);
    }

    /**
     * Czy dla kursu trwa generowanie PDF w tle (batch w kolejce). Do wykrywania na starcie strony i przycisku „Przerwij”.
     */
    public function pdfGenerationStatus(Course $course)
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');
        $name = "certificate-pdfs-course-{$course->id}";
        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $name)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->first();

        if (!$row) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'batch_id' => $row->id,
        ]);
    }

    /**
     * Przerywa generowanie plików PDF w tle (anuluje batch). Użycie: przycisk „Przerwij generowanie”.
     */
    public function cancelPdfGeneration(Course $course)
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');
        $name = "certificate-pdfs-course-{$course->id}";
        $row = DB::connection($connection)->table($batchTable)
            ->where('name', $name)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Brak aktywnego generowania.'], 404);
        }

        $batch = Bus::findBatch($row->id);
        if ($batch && !$batch->cancelled()) {
            $batch->cancel();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Czy trwa generowanie plików PDF w tle dla dowolnego szkolenia (do globalnego komunikatu na listach uczestników).
     * Zwraca pierwszy znaleziony aktywny batch z nazwą kursu.
     */
    public function pdfGenerationStatusAny()
    {
        $connection = config('queue.batching.database');
        $batchTable = config('queue.batching.table', 'job_batches');
        $row = DB::connection($connection)->table($batchTable)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->where('name', 'like', 'certificate-pdfs-course-%')
            ->orderByDesc('created_at')
            ->first();

        if (!$row || !preg_match('/^certificate-pdfs-course-(\d+)$/', $row->name, $m)) {
            return response()->json(['active' => false]);
        }

        $courseId = (int) $m[1];
        $course = Course::find($courseId);

        return response()->json([
            'active' => true,
            'course_id' => $courseId,
            'course_title' => $course ? $course->title : null,
            'participants_url' => $course ? route('participants.index', $course) : null,
        ]);
    }

    /**
     * Usuwa tylko pliki PDF zaświadczeń z dysku i zeruje file_path (zachowuje rekordy i numery).
     * Po edycji tytułu/zagadnień kursu pozwala wygenerować pliki od nowa.
     */
    public function deleteCertificatePdfFiles(Course $course)
    {
        $certificates = Certificate::where('course_id', $course->id)->whereNotNull('file_path')->get();
        $deleted = 0;

        foreach ($certificates as $certificate) {
            $relativePath = Str::replaceFirst('storage/', '', $certificate->file_path ?? '');
            if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
            $certificate->update(['file_path' => null]);
            $deleted++;
        }

        return redirect()->route('participants.index', $course)->with('success', "Usunięto pliki PDF dla {$deleted} zaświadczeń. Numery zaświadczeń zostały zachowane. Możesz teraz wygenerować pliki ponownie (np. po edycji danych szkolenia).");
    }

    /**
     * Pobiera istniejący plik PDF z serwera (bez generowania). Używane przy kliknięciu w ikonę PDF.
     */
    public function downloadCertificatePdf(Certificate $certificate)
    {
        if (empty($certificate->file_path)) {
            return redirect()->back()->with('error', 'Brak pliku PDF na serwerze. Użyj linku z numerem zaświadczenia, aby wygenerować plik.');
        }

        $relativePath = Str::replaceFirst('storage/', '', $certificate->file_path);
        if (!Storage::disk('public')->exists($relativePath)) {
            return redirect()->back()->with('error', 'Plik PDF nie istnieje na serwerze. Użyj linku z numerem zaświadczenia, aby wygenerować plik.');
        }

        $certificateNumber = $certificate->certificate_number;
        $downloadFileName = 'zaswiadczenie_' . str_replace('/', '-', $certificateNumber) . '.pdf';
        return response()->download(storage_path('app/public/' . $relativePath), $downloadFileName);
    }

    /**
     * Usuwa tylko plik PDF jednego zaświadczenia (zachowuje rekord i numer). Pozwala wygenerować PDF ponownie np. po poprawce danych uczestnika.
     */
    public function deleteCertificatePdf(Certificate $certificate)
    {
        if (!empty($certificate->file_path)) {
            $relativePath = Str::replaceFirst('storage/', '', $certificate->file_path);
            if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
            $certificate->update(['file_path' => null]);
        }

        return redirect()->back()->with('success', 'Plik PDF zaświadczenia został usunięty. Możesz wygenerować go ponownie (link z numerem zaświadczenia).');
    }

    public function bulkDelete(Course $course)
    {
        // Pobranie wszystkich certyfikatów dla danego kursu
        $certificates = Certificate::where('course_id', $course->id)->get();

        if ($certificates->isEmpty()) {
            return redirect()->route('participants.index', $course)->with('info', 'Brak zaświadczeń do usunięcia dla tego szkolenia.');
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

        return redirect()->route('participants.index', $course)->with('success', "Usunięto {$deletedCount} zaświadczeń dla szkolenia '{$course->title}'.");
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