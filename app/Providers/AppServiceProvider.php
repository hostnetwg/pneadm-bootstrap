<?php

namespace App\Providers;

use App\Support\OutboundMailCapture;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\Course;
use App\Models\FormOrder;
use App\Models\Participant;
use App\Observers\CourseObserver;
use App\Observers\FormOrderObserver;
use App\Observers\ParticipantObserver;

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

        Event::listen(MessageSent::class, [OutboundMailCapture::class, 'record']);
    }
}
