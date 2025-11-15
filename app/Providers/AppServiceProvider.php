<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\FormOrder;
use App\Observers\FormOrderObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\ConnectionEstablished;

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
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'mysql') {
                // Ustaw strefę czasową sesji MySQL na UTC
                // To zapewni, że MySQL zwraca daty w UTC, niezależnie od konfiguracji serwera
                $event->connection->statement("SET time_zone = '+00:00'");
            }
        });
    }
}
