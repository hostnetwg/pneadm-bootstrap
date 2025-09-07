<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Zamowienia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // Dzisiejsze daty
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // Statystyki dzisiejsze - zamowienia (Publigo) - używamy data_wplaty
            $todayOrders = Zamowienia::whereDate('data_wplaty', $today)->get();
            $todayOrdersCount = $todayOrders->count();
            $todayOrdersValue = $todayOrders->sum('produkt_cena');

            // Statystyki dzisiejsze - formularze zamówień - używamy data_zamowienia
            $todayForms = DB::connection('mysql_certgen')
                ->table('zamowienia_FORM')
                ->whereDate('data_zamowienia', $today)
                ->get();
            $todayFormsCount = $todayForms->count();

            // Statystyki miesięczne - zamowienia (Publigo) - używamy data_wplaty
            $monthlyOrders = Zamowienia::whereBetween('data_wplaty', [$startOfMonth, $endOfMonth])->get();
            $monthlyOrdersCount = $monthlyOrders->count();
            $monthlyOrdersValue = $monthlyOrders->sum('produkt_cena');

            // Statystyki miesięczne - formularze zamówień - używamy data_zamowienia
            $monthlyForms = DB::connection('mysql_certgen')
                ->table('zamowienia_FORM')
                ->whereBetween('data_zamowienia', [$startOfMonth, $endOfMonth])
                ->get();
            $monthlyFormsCount = $monthlyForms->count();

            // Statystyki z poprzedniego miesiąca dla porównania - używamy data_wplaty
            $lastMonthOrders = Zamowienia::whereBetween('data_wplaty', [$startOfLastMonth, $endOfLastMonth])->get();
            $lastMonthOrdersCount = $lastMonthOrders->count();
            $lastMonthOrdersValue = $lastMonthOrders->sum('produkt_cena');

            $lastMonthForms = DB::connection('mysql_certgen')
                ->table('zamowienia_FORM')
                ->whereBetween('data_zamowienia', [$startOfLastMonth, $endOfLastMonth])
                ->get();
            $lastMonthFormsCount = $lastMonthForms->count();

            // Zamówienia oczekujące na przetworzenie (bez nr_faktury)
            $pendingForms = DB::connection('mysql_certgen')
                ->table('zamowienia_FORM')
                ->where(function($q) {
                    $q->whereNull('nr_fakury')
                      ->orWhere('nr_fakury', '')
                      ->orWhere('nr_fakury', '0');
                })
                ->where(function($q) {
                    $q->whereNull('status_zakonczone')
                      ->orWhere('status_zakonczone', '!=', 1);
                })
                ->count();

            // Podział na płatności online vs faktury (z miesięcznych zamówień)
            $onlinePayments = $monthlyOrders->whereNotNull('kod')->whereNotNull('poczta')->whereNotNull('adres');
            $invoicePayments = $monthlyOrders->where(function($order) {
                return is_null($order->kod) || is_null($order->poczta) || is_null($order->adres);
            });

            // Najpopularniejsze produkty z bieżącego miesiąca
            $popularProducts = $monthlyOrders
                ->groupBy('produkt_nazwa')
                ->map(function($orders) {
                    return [
                        'name' => $orders->first()->produkt_nazwa,
                        'count' => $orders->count(),
                        'value' => $orders->sum('produkt_cena')
                    ];
                })
                ->sortByDesc('count')
                ->take(5);

            // Obliczenie trendów (procentowa zmiana)
            $ordersTrend = $this->calculateTrend($monthlyOrdersCount, $lastMonthOrdersCount);
            $formsTrend = $this->calculateTrend($monthlyFormsCount, $lastMonthFormsCount);
            $valueTrend = $this->calculateTrend($monthlyOrdersValue, $lastMonthOrdersValue);

            return view('dashboard', compact(
                'todayOrdersCount',
                'todayOrdersValue',
                'todayFormsCount',
                'monthlyOrdersCount',
                'monthlyOrdersValue',
                'monthlyFormsCount',
                'pendingForms',
                'onlinePayments',
                'invoicePayments',
                'popularProducts',
                'ordersTrend',
                'formsTrend',
                'valueTrend'
            ));

        } catch (\Exception $e) {
            // W przypadku błędu połączenia z bazą, zwracamy puste statystyki
            return view('dashboard', [
                'todayOrdersCount' => 0,
                'todayOrdersValue' => 0,
                'todayFormsCount' => 0,
                'monthlyOrdersCount' => 0,
                'monthlyOrdersValue' => 0,
                'monthlyFormsCount' => 0,
                'pendingForms' => 0,
                'onlinePayments' => collect(),
                'invoicePayments' => collect(),
                'popularProducts' => collect(),
                'ordersTrend' => 0,
                'formsTrend' => 0,
                'valueTrend' => 0,
                'error' => 'Nie można połączyć się z bazą danych: ' . $e->getMessage()
            ]);
        }
    }

    private function calculateTrend($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
