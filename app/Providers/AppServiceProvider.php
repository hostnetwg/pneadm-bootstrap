<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\FormOrder;
use App\Observers\FormOrderObserver;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();
        
        // Rejestracja Observer dla automatycznego zapisu uczestników
        FormOrder::observe(FormOrderObserver::class);
        
        // Wymuś ustawienie strefy czasowej MySQL na UTC przy każdym połączeniu
        // To rozwiązuje problem z różnymi strefami czasowymi między środowiskami
        // Używamy DB::afterConnecting() zamiast event listenera, bo działa bardziej niezawodnie
        DB::afterConnecting(function ($connection) {
            if ($connection->getDriverName() === 'mysql') {
                try {
                    // Ustaw strefę czasową sesji MySQL na UTC
                    // To zapewni, że MySQL zwraca daty w UTC, niezależnie od konfiguracji serwera
                    $connection->statement("SET time_zone = '+00:00'");
                } catch (\Exception $e) {
                    // Ignoruj błędy - może być problem z uprawnieniami
                    \Log::warning('Failed to set MySQL timezone to UTC', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });
    }
}
