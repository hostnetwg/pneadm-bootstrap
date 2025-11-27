<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Survey;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    const CACHE_KEY = 'dashboard_statistics';
    const CACHE_TTL = 86400; // 24 godziny (w sekundach)

    public function index()
    {
        try {
            // Pobierz statystyki z cache lub wygeneruj nowe
            $statistics = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return $this->generateStatistics();
            });

            // Pobierz informację o czasie ostatniej aktualizacji
            $lastUpdated = Cache::get(self::CACHE_KEY . '_timestamp', now());

            return view('dashboard', array_merge($statistics, [
                'lastUpdated' => $lastUpdated,
                'isCached' => Cache::has(self::CACHE_KEY)
            ]));

        } catch (\Exception $e) {
            // W przypadku błędu, zwracamy puste statystyki
            return view('dashboard', [
                'averageRating' => 0,
                'npsData' => [
                    'nps' => 0,
                    'promoters' => 0,
                    'detractors' => 0,
                    'passives' => 0,
                    'total_responses' => 0
                ],
                'ratingDistribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'topSurveys' => collect(),
                'bottomSurveys' => collect(),
                'timeTrend' => collect(),
                'recentSurveys' => collect(),
                'lastUpdated' => now(),
                'isCached' => false,
                'error' => 'Nie można załadować danych ankiet: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Odśwież statystyki (wyczyść cache i wygeneruj nowe)
     */
    public function refresh()
    {
        try {
            // Wyczyść cache
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::CACHE_KEY . '_timestamp');

            // Wygeneruj nowe statystyki
            $statistics = $this->generateStatistics();

            // Zapisz timestamp aktualizacji
            Cache::put(self::CACHE_KEY . '_timestamp', now(), self::CACHE_TTL);

            return redirect()->route('dashboard')->with('success', 'Statystyki zostały odświeżone.');
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Nie udało się odświeżyć statystyk: ' . $e->getMessage());
        }
    }

    /**
     * Generuje wszystkie statystyki dashboardu
     */
    private function generateStatistics()
    {
        // Pobierz wszystkie ankiety z relacjami
        // Uwaga: responses są ładowane lazy, co jest OK dla cache'owania
        $surveys = Survey::with(['course', 'instructor', 'questions', 'responses'])
            ->whereHas('course')
            ->get();

        // Obliczanie średniej oceny ze wszystkich ankiet
        $totalRating = 0;
        $surveysWithRatings = 0;
        
        foreach ($surveys as $survey) {
            $surveyRating = $survey->getAverageRating();
            if ($surveyRating > 0) {
                $totalRating += $surveyRating;
                $surveysWithRatings++;
            }
        }
        
        $averageRating = $surveysWithRatings > 0 ? round($totalRating / $surveysWithRatings, 2) : 0;

        // Obliczanie NPS ze wszystkich ankiet
        $npsData = $this->calculateNPS($surveys);

        // Obliczanie rozkładu ocen (1-5)
        $ratingDistribution = $this->calculateRatingDistribution($surveys);

        // Top 5 najlepiej ocenianych szkoleń
        $topSurveys = $this->getTopSurveys($surveys, 'desc', 5);

        // Top 5 najgorzej ocenianych szkoleń
        $bottomSurveys = $this->getTopSurveys($surveys, 'asc', 5);

        // Trend czasowy - ostatnie 6 miesięcy
        $timeTrend = $this->calculateTimeTrend($surveys, 6);

        // Ostatnie 10 szkoleń (według daty szkolenia)
        $recentSurveys = $this->getRecentSurveys(10);

        return [
            'averageRating' => $averageRating,
            'npsData' => $npsData,
            'ratingDistribution' => $ratingDistribution,
            'topSurveys' => $topSurveys,
            'bottomSurveys' => $bottomSurveys,
            'timeTrend' => $timeTrend,
            'recentSurveys' => $recentSurveys
        ];
    }

    /**
     * Obliczanie NPS dla kolekcji ankiet
     */
    private function calculateNPS($surveys): array
    {
        $npsResponses = [];
        
        // Wzorce pytania NPS
        $npsQuestionPatterns = [
            '/czy.*poleci.*szkolenie.*innym/i',
            '/poleci.*szkolenie.*innym/i',
            '/poleci.*innym.*osobom/i',
            '/czy.*poleci.*innym/i',
            '/poleci.*innym/i'
        ];
        
        foreach ($surveys as $survey) {
            foreach ($survey->responses as $response) {
                foreach ($response->response_data as $questionText => $answer) {
                    // Sprawdź czy to pytanie NPS
                    $isNpsQuestion = false;
                    foreach ($npsQuestionPatterns as $pattern) {
                        if (preg_match($pattern, $questionText)) {
                            $isNpsQuestion = true;
                            break;
                        }
                    }
                    
                    if ($isNpsQuestion && is_numeric($answer) && $answer >= 1 && $answer <= 5) {
                        $npsResponses[] = (int) $answer;
                    }
                }
            }
        }
        
        if (empty($npsResponses)) {
            return [
                'nps' => 0,
                'promoters' => 0,
                'detractors' => 0,
                'passives' => 0,
                'total_responses' => 0
            ];
        }
        
        $totalResponses = count($npsResponses);
        $promoters = 0; // 4-5
        $detractors = 0; // 1-2
        $passives = 0; // 3
        
        foreach ($npsResponses as $rating) {
            if ($rating >= 4) {
                $promoters++;
            } elseif ($rating <= 2) {
                $detractors++;
            } else {
                $passives++;
            }
        }
        
        $promotersPercent = ($promoters / $totalResponses) * 100;
        $detractorsPercent = ($detractors / $totalResponses) * 100;
        $nps = round($promotersPercent - $detractorsPercent, 1);
        
        return [
            'nps' => $nps,
            'promoters' => $promoters,
            'detractors' => $detractors,
            'passives' => $passives,
            'total_responses' => $totalResponses
        ];
    }

    /**
     * Obliczanie rozkładu ocen (1-5)
     */
    private function calculateRatingDistribution($surveys): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        
        foreach ($surveys as $survey) {
            $ratingQuestions = $survey->questions()->where('question_type', 'rating')->get();
            
            foreach ($ratingQuestions as $question) {
                $responses = $survey->responses()
                    ->get()
                    ->pluck('response_data')
                    ->map(function ($data) use ($question) {
                        return $data[$question->question_text] ?? null;
                    })
                    ->filter()
                    ->map(function ($value) {
                        return is_numeric($value) ? (int) $value : null;
                    })
                    ->filter();
                
                foreach ($responses as $rating) {
                    if ($rating >= 1 && $rating <= 5) {
                        $distribution[$rating]++;
                    }
                }
            }
        }
        
        return $distribution;
    }

    /**
     * Pobiera top N najlepiej/najgorzej ocenianych szkoleń
     */
    private function getTopSurveys($surveys, $order = 'desc', $limit = 5)
    {
        $surveysWithRatings = collect();
        
        foreach ($surveys as $survey) {
            $rating = $survey->getAverageRating();
            $nps = $survey->getNPS();
            
            if ($rating > 0) {
                $surveysWithRatings->push([
                    'survey' => $survey,
                    'survey_id' => $survey->id,
                    'rating' => $rating,
                    'nps' => $nps['nps'],
                    'responses_count' => $survey->total_responses,
                    'participants_count' => $survey->course ? $survey->course->participants()->count() : 0,
                    'course_title' => $survey->course->title ?? 'Brak tytułu',
                    'course_date' => $survey->course->start_date ?? null,
                    'instructor' => $survey->instructor ? $survey->instructor->getFullTitleNameAttribute() : null
                ]);
            }
        }
        
        if ($order === 'desc') {
            return $surveysWithRatings->sortByDesc('rating')->take($limit)->values();
        } else {
            return $surveysWithRatings->sortBy('rating')->take($limit)->values();
        }
    }

    /**
     * Obliczanie trendu czasowego (ostatnie N miesięcy)
     */
    private function calculateTimeTrend($surveys, $months = 6)
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonths($months);
        
        $trend = collect();
        
        // Grupuj ankiety według miesiąca szkolenia
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            
            $monthSurveys = $surveys->filter(function ($survey) use ($monthStart, $monthEnd) {
                if (!$survey->course || !$survey->course->start_date) {
                    return false;
                }
                return $survey->course->start_date->between($monthStart, $monthEnd);
            });
            
            $totalRating = 0;
            $surveysWithRatings = 0;
            
            foreach ($monthSurveys as $survey) {
                $rating = $survey->getAverageRating();
                if ($rating > 0) {
                    $totalRating += $rating;
                    $surveysWithRatings++;
                }
            }
            
            $averageRating = $surveysWithRatings > 0 ? round($totalRating / $surveysWithRatings, 2) : 0;
            
            $trend->push([
                'month' => $monthStart->format('Y-m'),
                'month_name' => $monthStart->locale('pl')->translatedFormat('F Y'),
                'average_rating' => $averageRating,
                'surveys_count' => $monthSurveys->count()
            ]);
        }
        
        return $trend;
    }

    /**
     * Pobiera ostatnie N szkoleń (według daty szkolenia)
     */
    private function getRecentSurveys($limit = 5)
    {
        $surveys = Survey::with(['course', 'instructor'])
            ->whereHas('course')
            ->join('courses', 'surveys.course_id', '=', 'courses.id')
            ->orderBy('courses.start_date', 'desc')
            ->orderBy('surveys.imported_at', 'desc')
            ->select('surveys.*')
            ->limit($limit)
            ->get();

        $recentSurveys = collect();
        
        foreach ($surveys as $survey) {
            $rating = $survey->getAverageRating();
            $nps = $survey->getNPS();
            
            $recentSurveys->push([
                'survey' => $survey,
                'survey_id' => $survey->id,
                'rating' => $rating,
                'nps' => $nps['nps'],
                'responses_count' => $survey->total_responses,
                'participants_count' => $survey->course ? $survey->course->participants()->count() : 0,
                'course_title' => $survey->course->title ?? 'Brak tytułu',
                'course_date' => $survey->course->start_date ?? null,
                'instructor' => $survey->instructor ? $survey->instructor->getFullTitleNameAttribute() : null
            ]);
        }
        
        return $recentSurveys;
    }
}
