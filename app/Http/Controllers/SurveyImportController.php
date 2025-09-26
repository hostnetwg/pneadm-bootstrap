<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\Course;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurveyImportController extends Controller
{
    /**
     * Pokaż formularz importu ankiety
     */
    public function showImportForm(Course $course)
    {
        return view('surveys.import', compact('course'));
    }

    /**
     * Importuj ankietę z pliku CSV
     */
    public function import(Request $request, Course $course)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'instructor_id' => 'nullable|exists:instructors,id'
        ]);

        try {
            DB::beginTransaction();

            // Utwórz ankietę
            $survey = Survey::create([
                'course_id' => $course->id,
                'instructor_id' => $request->instructor_id,
                'title' => $request->title,
                'description' => $request->description,
                'source' => 'Google Forms',
                'imported_at' => now(),
                'imported_by' => Auth::id(),
                'total_responses' => 0
            ]);

            // Przetwórz plik CSV
            $csvData = $this->parseCsvFile($request->file('csv_file'));
            
            if (empty($csvData)) {
                throw new \Exception('Plik CSV jest pusty lub nieprawidłowy.');
            }

            // Pobierz nagłówki (pytania)
            $headers = array_keys($csvData[0]);
            $questions = [];

            // Utwórz pytania
            foreach ($headers as $index => $header) {
                if ($header === 'Sygnatura czasowa') {
                    continue; // Pomijamy kolumnę z czasem
                }

                $questionType = $this->detectQuestionType($header, $csvData);
                
                $question = SurveyQuestion::create([
                    'survey_id' => $survey->id,
                    'question_text' => $header,
                    'question_type' => $questionType,
                    'question_order' => $index,
                    'options' => $this->extractOptions($header, $csvData, $questionType)
                ]);

                $questions[] = $question;
            }

            // Przetwórz odpowiedzi
            $responseCount = 0;
            foreach ($csvData as $row) {
                $responseData = [];
                $submittedAt = null;

                foreach ($row as $question => $answer) {
                    if ($question === 'Sygnatura czasowa') {
                        $submittedAt = $this->parseTimestamp($answer);
                        continue;
                    }

                    if (!empty($answer)) {
                        $responseData[$question] = $this->cleanAnswer($answer);
                    }
                }

                if (!empty($responseData) && $submittedAt) {
                    SurveyResponse::create([
                        'survey_id' => $survey->id,
                        'response_data' => $responseData,
                        'submitted_at' => $submittedAt
                    ]);
                    $responseCount++;
                }
            }

            // Zaktualizuj liczbę odpowiedzi
            $survey->update(['total_responses' => $responseCount]);

            DB::commit();

            return redirect()->route('surveys.show', $survey->id)
                ->with('success', "Ankieta została zaimportowana pomyślnie. Przetworzono {$responseCount} odpowiedzi.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Błąd importu ankiety: ' . $e->getMessage());
            
            return back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas importu ankiety: ' . $e->getMessage());
        }
    }

    /**
     * Parsuj plik CSV
     */
    private function parseCsvFile($file): array
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if ($handle === false) {
            throw new \Exception('Nie można otworzyć pliku CSV.');
        }

        $headers = fgetcsv($handle, 0, ',', '"');
        
        if ($headers === false) {
            fclose($handle);
            throw new \Exception('Nie można odczytać nagłówków z pliku CSV.');
        }

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Wykryj typ pytania na podstawie nagłówka i danych
     */
    private function detectQuestionType(string $header, array $data): string
    {
        // Sprawdź czy to pytanie ratingowe (1-5)
        $sampleAnswers = array_slice(array_column($data, $header), 0, 10);
        $numericAnswers = array_filter($sampleAnswers, function($answer) {
            return is_numeric($answer) && $answer >= 1 && $answer <= 5;
        });

        if (count($numericAnswers) >= count($sampleAnswers) * 0.8) {
            return 'rating';
        }

        // Sprawdź czy to pytanie z opcjami (zawiera nawiasy kwadratowe)
        if (strpos($header, '[') !== false && strpos($header, ']') !== false) {
            return 'multiple_choice';
        }

        // Sprawdź czy to pytanie z datą/czasem
        $dateAnswers = array_filter($sampleAnswers, function($answer) {
            return !empty($answer) && (strtotime($answer) !== false || preg_match('/\d{4}\/\d{2}\/\d{2}/', $answer));
        });

        if (count($dateAnswers) >= count($sampleAnswers) * 0.8) {
            return 'date';
        }

        // Domyślnie tekst
        return 'text';
    }

    /**
     * Wyciągnij opcje dla pytań wielokrotnego wyboru
     */
    private function extractOptions(string $header, array $data, string $questionType): ?array
    {
        if ($questionType !== 'multiple_choice') {
            return null;
        }

        $options = [];
        $sampleAnswers = array_slice(array_column($data, $header), 0, 20);
        
        foreach ($sampleAnswers as $answer) {
            if (!empty($answer)) {
                // Podziel odpowiedzi po średniku lub przecinku
                $parts = preg_split('/[;,]/', $answer);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (!empty($part) && !in_array($part, $options)) {
                        $options[] = $part;
                    }
                }
            }
        }

        return !empty($options) ? $options : null;
    }

    /**
     * Parsuj timestamp z Google Forms
     */
    private function parseTimestamp(string $timestamp): ?\DateTime
    {
        try {
            // Google Forms używa formatu: "2025/08/18 1:56:47 PM EEST"
            $timestamp = str_replace(' EEST', '', $timestamp);
            $timestamp = str_replace(' CEST', '', $timestamp);
            
            return new \DateTime($timestamp);
        } catch (\Exception $e) {
            return now();
        }
    }

    /**
     * Oczyść odpowiedź
     */
    private function cleanAnswer(string $answer): string
    {
        return trim($answer);
    }
}
