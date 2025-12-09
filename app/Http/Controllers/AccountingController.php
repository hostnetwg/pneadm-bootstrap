<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RevenueRecord;
use App\Http\Requests\StoreRevenueRecordRequest;
use App\Http\Requests\UpdateRevenueRecordRequest;
use Illuminate\Support\Facades\Auth;

class AccountingController extends Controller
{
    /**
     * Wyświetl stronę raportów księgowych
     */
    public function reportsIndex(Request $request)
    {
        // Pobierz parametry filtrowania
        $filterType = $request->get('filter_type', 'year'); // 'year' lub 'range'
        $selectedYear = $request->get('year', date('Y'));
        $selectedYear = (int) $selectedYear;
        
        // Parametry zakresu dat
        $startYear = (int) $request->get('start_year', date('Y'));
        $startMonth = (int) $request->get('start_month', 1);
        $endYear = (int) $request->get('end_year', date('Y'));
        $endMonth = (int) $request->get('end_month', 12);

        // Walidacja zakresu dat
        if ($filterType === 'range') {
            // Sprawdź czy zakres jest poprawny
            if ($startYear > $endYear || ($startYear == $endYear && $startMonth > $endMonth)) {
                return redirect()
                    ->route('accounting.reports.index')
                    ->with('error', 'Nieprawidłowy zakres dat. Data "od" musi być wcześniejsza niż data "do".');
            }
        }

        // Pobierz dane w zależności od typu filtra
        if ($filterType === 'range') {
            $monthlyData = RevenueRecord::getDataForDateRange($startYear, $startMonth, $endYear, $endMonth);
            $totalForPeriod = RevenueRecord::getTotalForDateRange($startYear, $startMonth, $endYear, $endMonth);
            $monthsCount = count($monthlyData);
            $averageMonthly = $monthsCount > 0 ? $totalForPeriod / $monthsCount : 0;
        } else {
            // Tryb roku (zachowana kompatybilność wsteczna)
            $monthlyData = RevenueRecord::getMonthlyData($selectedYear);
            $totalForPeriod = RevenueRecord::getTotalForYear($selectedYear);
            $monthsCount = 12;
            $averageMonthly = $totalForPeriod / 12;
        }

        // Pobierz dostępne lata (lata, w których są rekordy)
        $availableYears = RevenueRecord::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Jeśli brak rekordów, dodaj bieżący rok do listy
        if (empty($availableYears)) {
            $availableYears[] = (int) date('Y');
        }
        
        // Najlepszy i najsłabszy miesiąc
        $bestMonth = null;
        $worstMonth = null;
        $bestAmount = 0;
        $worstAmount = PHP_FLOAT_MAX;

        foreach ($monthlyData as $data) {
            if ($data['amount'] > $bestAmount) {
                $bestAmount = $data['amount'];
                $bestMonth = $data;
            }
            if ($data['amount'] < $worstAmount && $data['amount'] > 0) {
                $worstAmount = $data['amount'];
                $worstMonth = $data;
            }
        }

        // Trend - porównanie z poprzednim okresem o tej samej długości
        $trend = 0;
        $totalPreviousPeriod = 0;
        
        if ($filterType === 'range') {
            // Oblicz poprzedni zakres (o tyle samo miesięcy wstecz)
            $rangeMonths = $monthsCount;
            $prevEndYear = $startYear;
            $prevEndMonth = $startMonth - 1;
            if ($prevEndMonth < 1) {
                $prevEndMonth = 12;
                $prevEndYear--;
            }
            
            $prevStartYear = $prevEndYear;
            $prevStartMonth = $prevEndMonth;
            for ($i = 1; $i < $rangeMonths; $i++) {
                $prevStartMonth--;
                if ($prevStartMonth < 1) {
                    $prevStartMonth = 12;
                    $prevStartYear--;
                }
            }
            
            $totalPreviousPeriod = RevenueRecord::getTotalForDateRange($prevStartYear, $prevStartMonth, $prevEndYear, $prevEndMonth);
        } else {
            // Tryb roku - porównanie z poprzednim rokiem
            $previousYear = $selectedYear - 1;
            $totalPreviousPeriod = RevenueRecord::getTotalForYear($previousYear);
        }
        
        $trend = $totalPreviousPeriod > 0 
            ? (($totalForPeriod - $totalPreviousPeriod) / $totalPreviousPeriod) * 100 
            : 0;

        // Dane do porównania miesiąc do miesiąca dla wszystkich lat
        $monthToMonthComparison = RevenueRecord::getMonthToMonthComparison(2020);

        return view('accounting.reports.index', [
            'monthlyData' => $monthlyData,
            'filterType' => $filterType,
            'selectedYear' => $selectedYear,
            'startYear' => $startYear,
            'startMonth' => $startMonth,
            'endYear' => $endYear,
            'endMonth' => $endMonth,
            'availableYears' => $availableYears,
            'totalForPeriod' => $totalForPeriod,
            'averageMonthly' => $averageMonthly,
            'bestMonth' => $bestMonth,
            'worstMonth' => $worstMonth,
            'trend' => $trend,
            'totalPreviousPeriod' => $totalPreviousPeriod,
            'monthsCount' => $monthsCount,
            'monthToMonthComparison' => $monthToMonthComparison,
        ]);
    }

    /**
     * Wyświetl stronę wprowadzania danych księgowych
     */
    public function dataEntryIndex(Request $request)
    {
        // Filtrowanie po roku (domyślnie wszystkie)
        $selectedYear = $request->get('year');

        $query = RevenueRecord::with('user')->latestFirst();

        if ($selectedYear) {
            $query->forYear((int) $selectedYear);
        }

        $revenueRecords = $query->paginate(20);

        // Pobierz dostępne lata dla filtra
        $availableYears = RevenueRecord::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('accounting.data-entry.index', [
            'revenueRecords' => $revenueRecords,
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Zapisz nowy rekord przychodu
     */
    public function dataEntryStore(StoreRevenueRecordRequest $request)
    {
        try {
            $revenueRecord = RevenueRecord::create([
                'year' => $request->year,
                'month' => $request->month,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'source' => $request->source ?? 'manual',
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został zapisany pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas zapisywania rekordu: ' . $e->getMessage());
        }
    }

    /**
     * Aktualizuj istniejący rekord przychodu
     */
    public function dataEntryUpdate(UpdateRevenueRecordRequest $request, $id)
    {
        try {
            $revenueRecord = RevenueRecord::findOrFail($id);

            $revenueRecord->update([
                'year' => $request->year,
                'month' => $request->month,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'source' => $request->source ?? 'manual',
            ]);

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został zaktualizowany pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas aktualizacji rekordu: ' . $e->getMessage());
        }
    }

    /**
     * Usuń rekord przychodu (soft delete)
     */
    public function dataEntryDestroy($id)
    {
        try {
            $revenueRecord = RevenueRecord::findOrFail($id);
            $revenueRecord->delete();

            return redirect()
                ->route('accounting.data-entry.index')
                ->with('success', 'Rekord przychodu został usunięty pomyślnie.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Wystąpił błąd podczas usuwania rekordu: ' . $e->getMessage());
        }
    }
}

