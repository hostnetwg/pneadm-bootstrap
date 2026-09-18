<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\Participant;
use App\Models\ProductPrice;
use App\Observers\CourseObserver;
use App\Observers\CoursePriceVariantObserver;
use App\Observers\DebtCaseObserver;
use App\Observers\FormOrderObserver;
use App\Observers\ParticipantObserver;
use App\Observers\ProductPriceObserver;
use App\Services\ReleaseChangelogService;
use App\Support\OutboundMailCapture;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        // Rejestracja Observer dla automatycznej aktualizacji participant_emails
        Participant::observe(ParticipantObserver::class);

        Course::observe(CourseObserver::class);
        CoursePriceVariant::observe(CoursePriceVariantObserver::class);
        ProductPrice::observe(ProductPriceObserver::class);
        DebtCase::observe(DebtCaseObserver::class);

        Event::listen(MessageSent::class, [OutboundMailCapture::class, 'record']);

        View::composer('layouts.navigation', function ($view) {
            $view->with('releaseMenu', app(ReleaseChangelogService::class)->menuItems(auth()->user()));
        });
    }
}
