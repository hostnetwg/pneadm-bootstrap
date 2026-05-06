<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SurveyImportController extends Controller
{
    /**
     * Pokaż formularz importu ankiety
     */
    public function showImportForm(Course $course)
    {
        $course->loadMissing('instructor');
        $defaultImportSurveyTitle = $this->defaultImportSurveyTitle($course);

        return view('surveys.import', compact('course', 'defaultImportSurveyTitle'));
    }

    /**
     * Domyślny tytuł ankiety przy imporcie z kartą szkolenia.
     */
    private function defaultImportSurveyTitle(Course $course): string
    {
        $plainTitle = strip_tags(html_entity_decode((string) ($course->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainTitle = trim(preg_replace('/\s+/u', ' ', $plainTitle));
        if ($plainTitle === '') {
            $plainTitle = 'Szkolenie';
        }

        $datePart = $course->start_date
            ? $course->start_date->format('d.m.Y H:i')
            : 'brak daty';

        $assembled = 'ANKIETA: '.$plainTitle.' ('.$datePart.')';

        return Str::limit($assembled, 255, '');
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
            'instructor_id' => 'nullable|exists:instructors,id',
        ]);

        try {
            DB::beginTransaction();

            // Zapisz plik CSV na serwerze z prefiksem ID szkolenia
            $originalFileName = $request->file('csv_file')->getClientOriginalName();
            $fileName = $course->id.'_'.$originalFileName;
            $filePath = $request->file('csv_file')->storeAs('surveys/imports', $fileName, 'private');

            // Utwórz ankietę
            $survey = Survey::create([
                'course_id' => $course->id,
                'instructor_id' => $request->instructor_id,
                'title' => $request->title,
                'description' => $request->description,
                'source' => 'Google Forms',
                'imported_at' => now(),
                'imported_by' => Auth::id(),
                'total_responses' => 0,
                'original_file_path' => $filePath,
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
                    'options' => $this->extractOptions($header, $csvData, $questionType),
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

                    if (! empty($answer)) {
                        $responseData[$question] = $this->cleanAnswer($answer);
                    }
                }

                if (! empty($responseData) && $submittedAt) {
                    SurveyResponse::create([
                        'survey_id' => $survey->id,
                        'response_data' => $responseData,
                        'submitted_at' => $submittedAt,
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
            Log::error('Błąd importu ankiety: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas importu ankiety: '.$e->getMessage());
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
        $numericAnswers = array_filter($sampleAnswers, function ($answer) {
            return is_numeric($answer) && $answer >= 1 && $answer <= 5;
        });

        if (count($numericAnswers) >= count($sampleAnswers) * 0.8) {
            return 'rating';
        }

        // Sprawdź czy to pytanie z opcjami (zawiera nawiasy kwadratowe)
        if (strpos($header, '[') !== false && strpos($header, ']') !== false) {
            return 'multiple_choice';
        }

        // Sprawdź czy to pytanie jednokrotnego wyboru
        // Analizuj unikalne odpowiedzi - jeśli jest mało unikalnych wartości, to prawdopodobnie single_choice
        $uniqueAnswers = array_unique(array_filter($sampleAnswers, function ($answer) {
            return ! empty(trim($answer));
        }));

        if (count($uniqueAnswers) <= 15 && count($uniqueAnswers) >= 2) {
            // Sprawdź czy odpowiedzi wyglądają jak opcje wyboru
            $choicePatterns = [
                '/^(tak|nie)$/i',
                '/^(bardzo dobry|dobry|średni|słaby|bardzo słaby)$/i',
                '/^(zdecydowanie tak|raczej tak|nie mam zdania|raczej nie|zdecydowanie nie)$/i',
                '/^(zawsze|często|czasami|rzadko|nigdy)$/i',
                '/^(bardzo zadowolony|zadowolony|neutralny|niezadowolony|bardzo niezadowolony)$/i',
                '/^(portal|facebook|instagram|linkedin|twitter|youtube|tiktok)$/i',
                '/^(dyrekcja|szkoła|nauczyciel|kolega|koleżanka|przełożony|szef)$/i',
                '/^(e-mail|mail|wiadomość|newsletter|strona internetowa|www)$/i',
                '/^(radio|telewizja|gazeta|czasopismo|ulotka|plakat)$/i',
                '/^(inne|inny|inna|inne źródło|inne miejsce)$/i',
            ];

            $matchesChoicePattern = false;
            foreach ($choicePatterns as $pattern) {
                $matches = 0;
                foreach ($uniqueAnswers as $answer) {
                    if (preg_match($pattern, trim($answer))) {
                        $matches++;
                    }
                }
                if ($matches >= count($uniqueAnswers) * 0.6) {
                    $matchesChoicePattern = true;
                    break;
                }
            }

            // Sprawdź czy to może być single_choice na podstawie charakterystyk odpowiedzi
            if (! $matchesChoicePattern) {
                // Sprawdź czy odpowiedzi są krótkie i mają podobną strukturę
                $shortAnswers = array_filter($uniqueAnswers, function ($answer) {
                    return strlen(trim($answer)) <= 50;
                });

                // Sprawdź czy większość odpowiedzi to pojedyncze słowa lub krótkie frazy
                $singleWordAnswers = array_filter($uniqueAnswers, function ($answer) {
                    return str_word_count(trim($answer)) <= 3;
                });

                // Sprawdź czy odpowiedzi zawierają typowe słowa kluczowe dla opcji wyboru
                $choiceKeywords = ['portal', 'facebook', 'instagram', 'dyrekcja', 'szkoła', 'e-mail', 'mail', 'wiadomość', 'radio', 'telewizja', 'gazeta', 'ulotka', 'inne', 'inny', 'inna'];
                $hasChoiceKeywords = false;
                foreach ($uniqueAnswers as $answer) {
                    foreach ($choiceKeywords as $keyword) {
                        if (stripos($answer, $keyword) !== false) {
                            $hasChoiceKeywords = true;
                            break 2;
                        }
                    }
                }

                // Jeśli spełnia kryteria single_choice
                if (count($shortAnswers) >= count($uniqueAnswers) * 0.7 &&
                    count($singleWordAnswers) >= count($uniqueAnswers) * 0.5 &&
                    (count($uniqueAnswers) <= 10 || $hasChoiceKeywords)) {
                    return 'single_choice';
                }
            } elseif ($matchesChoicePattern) {
                return 'single_choice';
            }
        }

        // Sprawdź czy to pytanie z datą/czasem
        $dateAnswers = array_filter($sampleAnswers, function ($answer) {
            return ! empty($answer) && (strtotime($answer) !== false || preg_match('/\d{4}\/\d{2}\/\d{2}/', $answer));
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
            if (! empty($answer)) {
                // Podziel odpowiedzi po średniku lub przecinku
                $parts = preg_split('/[;,]/', $answer);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (! empty($part) && ! in_array($part, $options)) {
                        $options[] = $part;
                    }
                }
            }
        }

        return ! empty($options) ? $options : null;
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
