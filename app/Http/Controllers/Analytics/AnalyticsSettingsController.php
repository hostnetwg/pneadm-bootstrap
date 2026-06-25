<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\Analytics\AnalyticsMode;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AnalyticsSetting;
use App\Services\Analytics\AnalyticsModeResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

class AnalyticsSettingsController extends Controller
{
    public function index(Request $request, AnalyticsModeResolver $resolver): View
    {
        return view('analytics.settings.index', $this->buildViewData($resolver));
    }

    public function update(Request $request, AnalyticsModeResolver $resolver): RedirectResponse
    {
        $allowedModes = AnalyticsSetting::allowedModes();

        $disabling = $request->input('enabled_override') === 'disabled'
            || $request->input('default_mode_override') === AnalyticsMode::Off->value;

        $validated = $request->validate([
            'enabled_override' => ['required', Rule::in(['use_config', 'enabled', 'disabled'])],
            'default_mode_override' => ['required', Rule::in(array_merge(['use_config'], $allowedModes))],
            'confirm_impact' => [$disabling ? 'accepted' : 'nullable'],
        ], [
            'confirm_impact.accepted' => 'Potwierdź, że rozumiesz, iż ta zmiana może zatrzymać zbieranie danych analitycznych.',
        ]);

        $settings = AnalyticsSetting::query()->firstOrCreate(['id' => AnalyticsSetting::SINGLETON_ID]);

        $oldValues = [
            'enabled_override' => $settings->enabled_override,
            'default_mode_override' => $settings->default_mode_override,
        ];

        $newEnabledOverride = match ($validated['enabled_override']) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };

        $newModeOverride = $validated['default_mode_override'] === 'use_config'
            ? null
            : $validated['default_mode_override'];

        $settings->enabled_override = $newEnabledOverride;
        $settings->default_mode_override = $newModeOverride;
        $settings->updated_by = Auth::id();
        $settings->save();

        AnalyticsSetting::forgetSettingsCache();

        $this->logChangeSafely($oldValues, [
            'enabled_override' => $newEnabledOverride,
            'default_mode_override' => $newModeOverride,
        ]);

        return redirect()
            ->route('analytics.settings.index')
            ->with('status', 'Ustawienia analityki zostały zapisane.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(AnalyticsModeResolver $resolver): array
    {
        $configEnabled = (bool) config('analytics.enabled', true);
        $configMode = (string) config('analytics.default_mode', 'standard');
        $sampleRate = (int) config('analytics.sample_rate', 100);

        $enabledOverride = AnalyticsSetting::enabledOverride();
        $modeOverride = AnalyticsSetting::defaultModeOverride();

        $effectiveMode = $resolver->resolve();
        $effectiveEnabled = $effectiveMode !== AnalyticsMode::Off;

        if (! $configEnabled) {
            $enabledSource = 'hard_kill_switch';
        } elseif ($enabledOverride !== null) {
            $enabledSource = 'runtime_override';
        } else {
            $enabledSource = 'config';
        }

        if (! $configEnabled) {
            $modeSource = 'hard_kill_switch';
        } elseif ($enabledOverride === false) {
            $modeSource = 'runtime_override';
        } elseif ($modeOverride !== null) {
            $modeSource = 'runtime_override';
        } else {
            $modeSource = 'config';
        }

        $settings = AnalyticsSetting::getSettings();

        return [
            'effectiveEnabled' => $effectiveEnabled,
            'effectiveMode' => $effectiveMode->value,
            'enabledSource' => $enabledSource,
            'modeSource' => $modeSource,
            'configEnabled' => $configEnabled,
            'configMode' => $configMode,
            'sampleRate' => $sampleRate,
            'enabledOverride' => $enabledOverride,
            'modeOverride' => $modeOverride,
            'updatedBy' => $settings->updated_by,
            'updatedAt' => $settings->updated_at,
            'allowedModes' => AnalyticsSetting::allowedModes(),
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function logChangeSafely(array $oldValues, array $newValues): void
    {
        try {
            ActivityLog::logCustom(
                'analytics_settings_updated',
                'Zmieniono ustawienia analityki (runtime override).',
                [
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ],
            );
        } catch (Throwable) {
            // Audit log nie może blokować zapisu ustawień.
        }
    }
}
