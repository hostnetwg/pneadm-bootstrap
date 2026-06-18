<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentDisplayOption;
use App\Services\FunnelSkipService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PneduPurchasesController extends Controller
{
    /**
     * Ustawienia zakupów na pnedu.pl (formularze zamówień, płatności itp.)
     */
    public function index(Request $request)
    {
        $options = PaymentDisplayOption::getSettings();
        $developersOnlyColumnReady = $this->developersOnlyColumnReady();

        return view('settings.pnedu-purchases', compact(
            'options',
            'developersOnlyColumnReady',
        ));
    }

    public function analytics(Request $request)
    {
        $funnelSkip = app(FunnelSkipService::class);
        $funnelSkipCookie = $funnelSkip->cookieName();
        $funnelSkipUntilCookie = $funnelSkip->untilCookieName();
        $funnelSkipAnalyticsCookie = $funnelSkip->analyticsCookieName();

        $funnelSkipEnableUrl = $funnelSkip->pneduFunnelToggleUrl(true);
        $funnelSkipDisableUrl = $funnelSkip->pneduFunnelToggleUrl(false);
        $analyticsSkipEnableUrl = $funnelSkip->pneduAnalyticsToggleUrl(true);
        $analyticsSkipDisableUrl = $funnelSkip->pneduAnalyticsToggleUrl(false);

        $funnelSkipEnabledForBrowser = $request->cookie($funnelSkipCookie) === '1';
        $funnelSkipAnalyticsEnabledForBrowser = $request->cookie($funnelSkipAnalyticsCookie) === '1';
        $funnelSkipUntilRaw = $request->cookie($funnelSkipUntilCookie);
        $funnelSkipUntil = null;
        if (is_string($funnelSkipUntilRaw) && trim($funnelSkipUntilRaw) !== '') {
            try {
                $funnelSkipUntil = Carbon::parse($funnelSkipUntilRaw);
            } catch (\Throwable) {
                $funnelSkipUntil = null;
            }
        }

        return view('settings.analytics', compact(
            'funnelSkipEnableUrl',
            'funnelSkipDisableUrl',
            'analyticsSkipEnableUrl',
            'analyticsSkipDisableUrl',
            'funnelSkipEnabledForBrowser',
            'funnelSkipAnalyticsEnabledForBrowser',
            'funnelSkipUntil',
            'funnelSkipCookie',
            'funnelSkipUntilCookie',
            'funnelSkipAnalyticsCookie',
        ));
    }

    private function developersOnlyColumnReady(): bool
    {
        try {
            return Schema::hasColumn('payment_display_options', 'order_form_auto_fill_test_data_developers_only');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Przełącza opt-out lejka lub analityki w przeglądarce adm, potem przekierowuje na pnedu i wraca tutaj.
     *
     * @param  'funnel'|'analytics'  $scope
     * @param  'enable'|'disable'  $action  enable = opt-out włączony (zliczanie OFF), disable = opt-out wyłączony (zliczanie ON)
     */
    public function funnelSkipToggle(string $scope, string $action, FunnelSkipService $funnelSkip)
    {
        if (! in_array($scope, ['funnel', 'analytics'], true)) {
            abort(404);
        }

        if (! in_array($action, ['enable', 'disable'], true)) {
            abort(404);
        }

        if (! $funnelSkip->isConfigured()) {
            return redirect()
                ->route('settings.analytics.index')
                ->with('error', 'Brak konfiguracji MARKETING_FUNNEL_SKIP_TOKEN w .env.');
        }

        $enableOptOut = $action === 'enable';
        $returnQuery = $scope === 'funnel'
            ? ['funnel' => $enableOptOut ? 'off' : 'on']
            : ['analytics' => $enableOptOut ? 'off' : 'on'];

        $returnUrl = $request->getSchemeAndHttpHost()
            .route('settings.analytics.index', $returnQuery, false);

        $pneduUrl = $scope === 'funnel'
            ? $funnelSkip->pneduFunnelToggleUrl($enableOptOut, $returnUrl)
            : $funnelSkip->pneduAnalyticsToggleUrl($enableOptOut, $returnUrl);

        if ($pneduUrl === null) {
            return redirect()
                ->route('settings.analytics.index')
                ->with('error', 'Nie udało się zbudować linku opt-out dla pnedu.');
        }

        $redirect = redirect()->away($pneduUrl);

        if ($scope === 'funnel') {
            if ($enableOptOut) {
                $redirect->withCookie($funnelSkip->makeOptOutCookie())
                    ->withCookie($funnelSkip->makeOptOutUntilCookie());
            } else {
                $redirect->withCookie($funnelSkip->forgetOptOutCookie())
                    ->withCookie($funnelSkip->forgetOptOutUntilCookie());
            }
        } elseif ($enableOptOut) {
            $redirect->withCookie($funnelSkip->makeAnalyticsOptOutCookie());
        } else {
            $redirect->withCookie($funnelSkip->forgetAnalyticsOptOutCookie());
        }

        return $redirect;
    }

    /**
     * Zapisz widoczność opcji płatności na stronie kursu pnedu.pl
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'show_pay_publigo' => 'boolean',
            'show_pay_online' => 'boolean',
            'show_deferred_order' => 'boolean',
            'show_order_form' => 'boolean',
            'show_order_form_alt' => 'boolean',
            'order_form_auto_fill_test_data_developers_only' => 'boolean',
            'order_form_auto_fill_test_data' => 'boolean',
            'default_post_end_access_duration_value' => 'required|integer|min:1|max:999',
            'default_post_end_access_duration_unit' => 'required|in:days,weeks,months,years',
        ], [], [
            'show_pay_publigo' => 'Zapłać online (Publigo)',
            'show_pay_online' => 'Zapłać online (PayU / PayNow)',
            'show_deferred_order' => 'Formularz z odroczonym terminem (PNEDU)',
            'show_order_form' => 'Zamawiam szkolenie (uniwersalny formularz)',
            'show_order_form_alt' => 'Formularz z odroczonym terminem (zdalna-lekcja.pl)',
            'order_form_auto_fill_test_data_developers_only' => 'Auto-wypełnianie formularza danymi testowymi (konta deweloperskie)',
            'order_form_auto_fill_test_data' => 'Auto-wypełnianie formularza danymi testowymi',
            'default_post_end_access_duration_value' => 'Domyślny okres dostępu po zakończeniu szkolenia',
            'default_post_end_access_duration_unit' => 'Jednostka domyślnego okresu dostępu po zakończeniu szkolenia',
        ]);

        $options = PaymentDisplayOption::getSettings();
        $autoFillEnabled = $request->boolean('order_form_auto_fill_test_data');
        $developersOnlyEnabled = $request->boolean('order_form_auto_fill_test_data_developers_only');

        if ($developersOnlyEnabled && ! $this->developersOnlyColumnReady()) {
            return redirect()
                ->route('settings.pnedu-purchases.index')
                ->with('error', 'Nie można zapisać opcji dla kont deweloperskich — brak kolumny w bazie. Na serwerze produkcyjnym uruchom: php artisan migrate --force');
        }

        $updateData = [
            'show_pay_publigo' => $request->boolean('show_pay_publigo'),
            'show_pay_online' => $request->boolean('show_pay_online'),
            'show_deferred_order' => $request->boolean('show_deferred_order'),
            'show_order_form' => $request->boolean('show_order_form'),
            'show_order_form_alt' => $request->boolean('show_order_form_alt'),
            'order_form_auto_fill_test_data' => $autoFillEnabled,
            'order_form_auto_fill_test_data_enabled_at' => $autoFillEnabled ? Carbon::now() : null,
            'default_post_end_access_duration_value' => $validated['default_post_end_access_duration_value'],
            'default_post_end_access_duration_unit' => $validated['default_post_end_access_duration_unit'],
        ];

        if ($this->developersOnlyColumnReady()) {
            $updateData['order_form_auto_fill_test_data_developers_only'] = $developersOnlyEnabled;
        }

        $options->update($updateData);

        PaymentDisplayOption::forgetSettingsCache();

        return redirect()
            ->route('settings.pnedu-purchases.index')
            ->with('success', 'Ustawienia widoczności opcji płatności zostały zapisane.');
    }
}
