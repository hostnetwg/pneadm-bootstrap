<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zamowienia;
use App\Models\FormOrder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function index()
    {
        try {
            // Dzisiejsze daty
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // Form-orders: `order_date` jest zapisywane w UTC, ale karty "Wczoraj/Dzisiaj"
            // mają liczyć dzień wg strefy aplikacji.
            $tz = config('app.timezone', 'Europe/Warsaw');
            $todayLocal = Carbon::today($tz);
            $yesterdayLocal = Carbon::yesterday($tz);

            $todayStartUtc = $todayLocal->copy()->startOfDay()->utc();
            $tomorrowStartUtc = $todayLocal->copy()->addDay()->startOfDay()->utc(); // koniec wyłączny
            $yesterdayStartUtc = $yesterdayLocal->copy()->startOfDay()->utc();

            // Statystyki dzisiejsze - zamowienia (Publigo) - używamy data_wplaty
            $todayOrders = Zamowienia::whereDate('data_wplaty', $today)->get();
            $todayOrdersCount = $todayOrders->count();
            $todayOrdersValue = $todayOrders->sum('produkt_cena');

            // Statystyki dzisiejsze - formularze zamówień - używamy order_date z pneadm:form_orders (tylko niezakończone)
            $todayForms = FormOrder::where('order_date', '>=', $todayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $tomorrowStartUtc->format('Y-m-d H:i:s'))
                ->where(function($q) {
                    $q->whereNull('status_completed')
                      ->orWhere('status_completed', '!=', 1);
                })
                ->get();
            $todayFormsCount = $todayForms->count();
            $todayFormsValue = $todayForms->sum('product_price');

            // Statystyki wczoraj - zamowienia (Publigo) - używamy data_wplaty
            $yesterdayOrders = Zamowienia::whereDate('data_wplaty', $yesterday)->get();
            $yesterdayOrdersCount = $yesterdayOrders->count();
            $yesterdayOrdersValue = $yesterdayOrders->sum('produkt_cena');

            // Statystyki wczoraj - formularze zamówień - używamy order_date z pneadm:form_orders (tylko niezakończone)
            $yesterdayForms = FormOrder::where('order_date', '>=', $yesterdayStartUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $todayStartUtc->format('Y-m-d H:i:s'))
                ->where(function($q) {
                    $q->whereNull('status_completed')
                      ->orWhere('status_completed', '!=', 1);
                })
                ->get();
            $yesterdayFormsCount = $yesterdayForms->count();
            $yesterdayFormsValue = $yesterdayForms->sum('product_price');

            // Statystyki miesięczne - zamowienia (Publigo) - używamy data_wplaty
            $monthlyOrders = Zamowienia::whereBetween('data_wplaty', [$startOfMonth, $endOfMonth])->get();
            $monthlyOrdersCount = $monthlyOrders->count();
            $monthlyOrdersValue = $monthlyOrders->sum('produkt_cena');

            // Statystyki miesięczne - formularze zamówień - używamy order_date z pneadm:form_orders (tylko niezakończone)
            $startOfMonthUtc = $startOfMonth->copy()->startOfDay()->utc();
            $startOfNextMonthUtc = $startOfMonth->copy()->addMonth()->startOfDay()->utc(); // koniec wyłączny
            $monthlyForms = FormOrder::where('order_date', '>=', $startOfMonthUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $startOfNextMonthUtc->format('Y-m-d H:i:s'))
                ->where(function($q) {
                    $q->whereNull('status_completed')
                      ->orWhere('status_completed', '!=', 1);
                })
                ->get();
            $monthlyFormsCount = $monthlyForms->count();
            $monthlyFormsValue = $monthlyForms->sum('product_price');

            // Statystyki z poprzedniego miesiąca dla porównania - używamy data_wplaty
            $lastMonthOrders = Zamowienia::whereBetween('data_wplaty', [$startOfLastMonth, $endOfLastMonth])->get();
            $lastMonthOrdersCount = $lastMonthOrders->count();
            $lastMonthOrdersValue = $lastMonthOrders->sum('produkt_cena');

            $startOfLastMonthUtc = $startOfLastMonth->copy()->startOfDay()->utc();
            $startOfThisMonthUtc = $startOfMonth->copy()->startOfDay()->utc();
            $lastMonthForms = FormOrder::where('order_date', '>=', $startOfLastMonthUtc->format('Y-m-d H:i:s'))
                ->where('order_date', '<', $startOfThisMonthUtc->format('Y-m-d H:i:s'))
                ->where(function($q) {
                    $q->whereNull('status_completed')
                      ->orWhere('status_completed', '!=', 1);
                })
                ->get();
            $lastMonthFormsCount = $lastMonthForms->count();
            $lastMonthFormsValue = $lastMonthForms->sum('product_price');

            // Zamówienia oczekujące na przetworzenie (bez nr_faktury) - używamy nowej tabeli form_orders
            $pendingForms = FormOrder::where(function($q) {
                    $q->whereNull('invoice_number')
                      ->orWhere('invoice_number', '')
                      ->orWhere('invoice_number', '0');
                })
                ->where(function($q) {
                    $q->whereNull('status_completed')
                      ->orWhere('status_completed', '!=', 1);
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
            $valueTrend = $this->calculateTrend($monthlyOrdersValue + $monthlyFormsValue, $lastMonthOrdersValue + $lastMonthFormsValue);

            return view('admin.statistics', compact(
                'todayOrdersCount',
                'todayOrdersValue',
                'todayFormsCount',
                'todayFormsValue',
                'yesterdayOrdersCount',
                'yesterdayOrdersValue',
                'yesterdayFormsCount',
                'yesterdayFormsValue',
                'monthlyOrdersCount',
                'monthlyOrdersValue',
                'monthlyFormsCount',
                'monthlyFormsValue',
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
            return view('admin.statistics', [
                'todayOrdersCount' => 0,
                'todayOrdersValue' => 0,
                'todayFormsCount' => 0,
                'todayFormsValue' => 0,
                'yesterdayOrdersCount' => 0,
                'yesterdayOrdersValue' => 0,
                'yesterdayFormsCount' => 0,
                'yesterdayFormsValue' => 0,
                'monthlyOrdersCount' => 0,
                'monthlyOrdersValue' => 0,
                'monthlyFormsCount' => 0,
                'monthlyFormsValue' => 0,
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

