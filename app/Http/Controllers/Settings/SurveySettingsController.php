<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SurveySetting;
use App\Models\SurveyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveySettingsController extends Controller
{
    public function edit(): View
    {
        $settings = SurveySetting::getSettings();
        $templates = SurveyTemplate::query()->where('is_active', true)->orderBy('name')->get();

        return view('settings.surveys', compact('settings', 'templates'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'open_mode' => 'required|in:manual,auto',
            'auto_open_offset_hours' => 'nullable|integer|min:0|max:720',
            'auto_close_after_days' => 'nullable|integer|min:1|max:365',
            'default_channel' => 'required|in:native,external',
            'default_is_anonymous' => 'nullable|boolean',
            'default_template_id' => 'nullable|exists:survey_templates,id',
        ]);

        $settings = SurveySetting::getSettings();
        $settings->fill([
            'open_mode' => $validated['open_mode'],
            'auto_open_offset_hours' => (int) ($validated['auto_open_offset_hours'] ?? 0),
            'auto_close_after_days' => $validated['auto_close_after_days'] ?? null,
            'default_channel' => $validated['default_channel'],
            'default_is_anonymous' => $request->boolean('default_is_anonymous'),
            'default_template_id' => $validated['default_template_id'] ?? null,
        ]);
        $settings->save();

        SurveySetting::forgetSettingsCache();

        return redirect()
            ->route('settings.surveys.edit')
            ->with('success', 'Ustawienia ankiet zostały zapisane.');
    }
}
