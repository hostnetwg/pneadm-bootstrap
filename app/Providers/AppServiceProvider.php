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
use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Providers\OpenAiImageProvider;
use App\Services\GrowthOS\AI\Providers\OpenAiProvider;
use App\Services\ReleaseChangelogService;
use App\Support\DestructiveDatabaseGuard;
use App\Support\OutboundMailCapture;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GrowthAiProvider::class, function (): GrowthAiProvider {
            return match ((string) config('growth_ai.provider')) {
                'openai' => app(OpenAiProvider::class),
                default => throw new LogicException('Unsupported Growth AI provider.'),
            };
        });

        $this->app->bind(GrowthAiImageProvider::class, function (): GrowthAiImageProvider {
            return match ((string) config('growth_ai.provider')) {
                'openai' => app(OpenAiImageProvider::class),
                default => throw new LogicException('Unsupported Growth AI provider.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DestructiveDatabaseGuard::register();

        RateLimiter::for('growth-ai', function (Request $request): Limit {
            if (config('growth_ai.enabled') !== true) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('growth_ai.limits.per_minute'))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->response(fn () => redirect()->back()->with(
                    'error',
                    'Limit krótkich wywołań AI został osiągnięty. Spróbuj ponownie za chwilę lub kontynuuj ręcznie.',
                ));
        });

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
