<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Model RevenueRecord
 * 
 * Przechowuje dane przychodów księgowych na dany miesiąc
 */
class RevenueRecord extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Pola możliwe do masowego przypisania
     */
    protected $fillable = [
        'year',
        'month',
        'amount',
        'notes',
        'source',
        'user_id',
    ];

    /**
     * Casty typów
     */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Domyślne wartości
     */
    protected $attributes = [
        'source' => 'manual',
    ];

    /**
     * Relacja z użytkownikiem
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor: Formatowana kwota (np. "12 345,67 zł")
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' zł';
    }

    /**
     * Accessor: Nazwa miesiąca po polsku
     */
    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Styczeń',
            2 => 'Luty',
            3 => 'Marzec',
            4 => 'Kwiecień',
            5 => 'Maj',
            6 => 'Czerwiec',
            7 => 'Lipiec',
            8 => 'Sierpień',
            9 => 'Wrzesień',
            10 => 'Październik',
            11 => 'Listopad',
            12 => 'Grudzień',
        ];

        return $months[$this->month] ?? '';
    }

    /**
     * Accessor: Etykieta okresu (np. "Styczeń 2024")
     */
    public function getPeriodLabelAttribute(): string
    {
        return $this->month_name . ' ' . $this->year;
    }

    /**
     * Scope: Filtrowanie po roku
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope: Filtrowanie po konkretnym miesiącu
     */
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * Scope: Ostatnie N rekordów
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit($limit);
    }

    /**
     * Scope: Sortowanie chronologiczne (od najstarszego)
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('year', 'asc')->orderBy('month', 'asc');
    }

    /**
     * Scope: Sortowanie odwrotne (od najnowszego)
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('year', 'desc')->orderBy('month', 'desc');
    }

    /**
     * Metoda statyczna: Suma przychodów za rok
     */
    public static function getTotalForYear(int $year): float
    {
        return static::forYear($year)->sum('amount') ?? 0.00;
    }

    /**
     * Metoda statyczna: Suma przychodów za miesiąc
     */
    public static function getTotalForMonth(int $year, int $month): float
    {
        return static::forMonth($year, $month)->sum('amount') ?? 0.00;
    }

    /**
     * Metoda statyczna: Dane miesięczne dla roku (dla wykresu)
     * Zwraca tablicę z danymi dla wszystkich 12 miesięcy
     */
    public static function getMonthlyData(int $year): array
    {
        $records = static::forYear($year)
            ->chronological()
            ->get()
            ->keyBy('month');

        $data = [];
        $months = [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ];

        for ($month = 1; $month <= 12; $month++) {
            $record = $records->get($month);
            $data[] = [
                'month' => $month,
                'month_name' => $months[$month],
                'amount' => $record ? (float) $record->amount : 0.00,
                'period_label' => $months[$month] . ' ' . $year,
            ];
        }

        return $data;
    }

    /**
     * Metoda statyczna: Dane dla zakresu dat (od roku+miesiąca do roku+miesiąca)
     */
    public static function getDataForDateRange(int $startYear, int $startMonth, int $endYear, int $endMonth): array
    {
        // Pobierz wszystkie rekordy w zakresie
        $records = static::where(function ($query) use ($startYear, $startMonth, $endYear, $endMonth) {
            $query->where(function ($q) use ($startYear, $startMonth, $endYear, $endMonth) {
                // Rekordy między start a end
                $q->where(function ($q2) use ($startYear, $startMonth, $endYear, $endMonth) {
                    // Rok większy niż start lub równy start z miesiącem >= startMonth
                    $q2->where('year', '>', $startYear)
                        ->orWhere(function ($q3) use ($startYear, $startMonth) {
                            $q3->where('year', '=', $startYear)
                                ->where('month', '>=', $startMonth);
                        });
                })
                ->where(function ($q2) use ($endYear, $endMonth) {
                    // Rok mniejszy niż end lub równy end z miesiącem <= endMonth
                    $q2->where('year', '<', $endYear)
                        ->orWhere(function ($q3) use ($endYear, $endMonth) {
                            $q3->where('year', '=', $endYear)
                                ->where('month', '<=', $endMonth);
                        });
                });
            });
        })
        ->chronological()
        ->get();

        $months = [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ];

        $data = [];
        $currentYear = $startYear;
        $currentMonth = $startMonth;

        // Generuj wszystkie miesiące w zakresie
        while ($currentYear < $endYear || ($currentYear == $endYear && $currentMonth <= $endMonth)) {
            $record = $records->first(function ($r) use ($currentYear, $currentMonth) {
                return $r->year == $currentYear && $r->month == $currentMonth;
            });

            $data[] = [
                'year' => $currentYear,
                'month' => $currentMonth,
                'month_name' => $months[$currentMonth],
                'amount' => $record ? (float) $record->amount : 0.00,
                'period_label' => $months[$currentMonth] . ' ' . $currentYear,
            ];

            // Przejdź do następnego miesiąca
            $currentMonth++;
            if ($currentMonth > 12) {
                $currentMonth = 1;
                $currentYear++;
            }
        }

        return $data;
    }

    /**
     * Metoda statyczna: Suma dla zakresu dat
     */
    public static function getTotalForDateRange(int $startYear, int $startMonth, int $endYear, int $endMonth): float
    {
        return static::where(function ($query) use ($startYear, $startMonth, $endYear, $endMonth) {
            $query->where(function ($q) use ($startYear, $startMonth, $endYear, $endMonth) {
                $q->where(function ($q2) use ($startYear, $startMonth, $endYear, $endMonth) {
                    $q2->where('year', '>', $startYear)
                        ->orWhere(function ($q3) use ($startYear, $startMonth) {
                            $q3->where('year', '=', $startYear)
                                ->where('month', '>=', $startMonth);
                        });
                })
                ->where(function ($q2) use ($endYear, $endMonth) {
                    $q2->where('year', '<', $endYear)
                        ->orWhere(function ($q3) use ($endYear, $endMonth) {
                            $q3->where('year', '=', $endYear)
                                ->where('month', '<=', $endMonth);
                        });
                });
            });
        })->sum('amount') ?? 0.00;
    }

    /**
     * Metoda statyczna: Dane do porównania miesiąc do miesiąca dla wszystkich lat (od 2020)
     * Zwraca dane w formacie: [miesiąc => [rok => kwota]]
     */
    public static function getMonthToMonthComparison(int $startYear = 2020): array
    {
        $currentYear = (int) date('Y');
        $endYear = $currentYear;

        // Pobierz wszystkie rekordy od startYear do obecnego roku
        $records = static::where('year', '>=', $startYear)
            ->where('year', '<=', $endYear)
            ->chronological()
            ->get();

        $months = [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ];

        // Struktura: [miesiąc => [rok => kwota]]
        $comparisonData = [];
        
        // Inicjalizuj strukturę dla wszystkich miesięcy i lat
        for ($month = 1; $month <= 12; $month++) {
            $comparisonData[$month] = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                $comparisonData[$month][$year] = 0.00;
            }
        }

        // Wypełnij danymi z bazy
        foreach ($records as $record) {
            if (isset($comparisonData[$record->month][$record->year])) {
                $comparisonData[$record->month][$record->year] = (float) $record->amount;
            }
        }

        // Przygotuj dane dla wykresu Chart.js
        $chartData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthData = [
                'month' => $month,
                'month_name' => $months[$month],
                'years' => []
            ];
            
            for ($year = $startYear; $year <= $endYear; $year++) {
                $monthData['years'][] = [
                    'year' => $year,
                    'amount' => $comparisonData[$month][$year]
                ];
            }
            
            $chartData[] = $monthData;
        }

        return [
            'data' => $chartData,
            'years' => range($startYear, $endYear),
            'months' => $months,
        ];
    }

    /**
     * Metoda statyczna: Dane dla ostatnich N miesięcy
     */
    public static function getRecentMonthsData(int $monthsCount = 12): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonths($monthsCount - 1)->startOfMonth();

        $records = static::where(function ($query) use ($startDate, $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where('year', '>', $startDate->year)
                    ->orWhere(function ($q2) use ($startDate) {
                        $q2->where('year', '=', $startDate->year)
                            ->where('month', '>=', $startDate->month);
                    });
            })
            ->where(function ($q) use ($endDate) {
                $q->where('year', '<', $endDate->year)
                    ->orWhere(function ($q2) use ($endDate) {
                        $q2->where('year', '=', $endDate->year)
                            ->where('month', '<=', $endDate->month);
                    });
            });
        })
        ->chronological()
        ->get();

        $months = [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ];

        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $record = $records->first(function ($r) use ($currentDate) {
                return $r->year == $currentDate->year && $r->month == $currentDate->month;
            });

            $data[] = [
                'year' => $currentDate->year,
                'month' => $currentDate->month,
                'month_name' => $months[$currentDate->month],
                'amount' => $record ? (float) $record->amount : 0.00,
                'period_label' => $months[$currentDate->month] . ' ' . $currentDate->year,
            ];

            $currentDate->addMonth();
        }

        return $data;
    }
}
