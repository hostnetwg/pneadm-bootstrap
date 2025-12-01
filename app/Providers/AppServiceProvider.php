<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\FormOrder;
use App\Observers\FormOrderObserver;

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
        
        // ServiceProvider dla pne-certificate-generator jest automatycznie wykrywany
        // przez Laravel dzięki konfiguracji w composer.json pakietu
    }
}
