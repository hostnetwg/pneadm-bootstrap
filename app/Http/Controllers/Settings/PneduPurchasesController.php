<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentDisplayOption;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class PneduPurchasesController extends Controller
{
    /**
     * Ustawienia zakupów na pnedu.pl (formularze zamówień, płatności itp.)
     */
    public function index()
    {
        $options = PaymentDisplayOption::getSettings();

        return view('settings.pnedu-purchases', compact('options'));
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
            'order_form_auto_fill_test_data' => 'boolean',
            'default_post_end_access_duration_value' => 'required|integer|min:1|max:999',
            'default_post_end_access_duration_unit' => 'required|in:days,weeks,months,years',
        ], [], [
            'show_pay_publigo' => 'Zapłać online (Publigo)',
            'show_pay_online' => 'Zapłać online (PayU / PayNow)',
            'show_deferred_order' => 'Formularz z odroczonym terminem (PNEDU)',
            'show_order_form' => 'Zamawiam szkolenie (uniwersalny formularz)',
            'show_order_form_alt' => 'Formularz z odroczonym terminem (zdalna-lekcja.pl)',
            'order_form_auto_fill_test_data' => 'Auto-wypełnianie formularza danymi testowymi',
            'default_post_end_access_duration_value' => 'Domyślny okres dostępu po zakończeniu szkolenia',
            'default_post_end_access_duration_unit' => 'Jednostka domyślnego okresu dostępu po zakończeniu szkolenia',
        ]);

        $options = PaymentDisplayOption::getSettings();
        $autoFillEnabled = $request->boolean('order_form_auto_fill_test_data');
        $options->update([
            'show_pay_publigo' => $request->boolean('show_pay_publigo'),
            'show_pay_online' => $request->boolean('show_pay_online'),
            'show_deferred_order' => $request->boolean('show_deferred_order'),
            'show_order_form' => $request->boolean('show_order_form'),
            'show_order_form_alt' => $request->boolean('show_order_form_alt'),
            'order_form_auto_fill_test_data' => $autoFillEnabled,
            'order_form_auto_fill_test_data_enabled_at' => $autoFillEnabled ? Carbon::now() : null,
            'default_post_end_access_duration_value' => $validated['default_post_end_access_duration_value'],
            'default_post_end_access_duration_unit' => $validated['default_post_end_access_duration_unit'],
        ]);

        PaymentDisplayOption::forgetSettingsCache();

        return redirect()
            ->route('settings.pnedu-purchases.index')
            ->with('success', 'Ustawienia widoczności opcji płatności zostały zapisane.');
    }
}
