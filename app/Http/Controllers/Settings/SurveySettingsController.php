<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SurveySetting;
use App\Models\SurveyTemplate;
use App\Services\PneduFrontendCacheInvalidationService;
use App\Support\SurveyAvatarPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SurveySettingsController extends Controller
{
    public function edit(): View
    {
        $settings = SurveySetting::getSettings();
        $templates = SurveyTemplate::query()->where('is_active', true)->orderBy('name')->get();
        $avatarPresetsByGroup = SurveyAvatarPresets::optionsByGroup();
        $enabledAvatarPresets = old('enabled_avatar_presets', $settings->enabledAvatarPresets());
        if (! is_array($enabledAvatarPresets)) {
            $enabledAvatarPresets = $settings->enabledAvatarPresets();
        }
        $professionalAvatarKeys = SurveyAvatarPresets::defaultEnabledKeys();
        $avatarCatalogCount = count(SurveyAvatarPresets::keys());

        return view('settings.surveys', compact(
            'settings',
            'templates',
            'avatarPresetsByGroup',
            'enabledAvatarPresets',
            'professionalAvatarKeys',
            'avatarCatalogCount',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'open_mode' => 'required|in:manual,auto',
            'auto_open_offset_hours' => 'nullable|integer|min:-24|max:720',
            'auto_close_after_days' => 'nullable|integer|min:1|max:365',
            'default_channel' => 'required|in:native,external',
            'default_is_anonymous' => 'nullable|boolean',
            'allow_multiple_responses' => 'nullable|boolean',
            'default_template_id' => 'nullable|exists:survey_templates,id',
            'enabled_avatar_presets' => 'nullable|array',
            'enabled_avatar_presets.*' => ['string', Rule::in(SurveyAvatarPresets::keys())],
            'show_testimonial_date_on_homepage' => 'nullable|boolean',
        ]);

        $enabledAvatars = array_values(array_unique(array_intersect(
            $validated['enabled_avatar_presets'] ?? [],
            SurveyAvatarPresets::keys()
        )));

        $settings = SurveySetting::getSettings();
        $settings->fill([
            'open_mode' => $validated['open_mode'],
            'auto_open_offset_hours' => (int) ($validated['auto_open_offset_hours'] ?? -2),
            'auto_close_after_days' => $validated['auto_close_after_days'] ?? null,
            'default_channel' => $validated['default_channel'],
            'default_is_anonymous' => $request->boolean('default_is_anonymous'),
            'allow_multiple_responses' => $request->boolean('allow_multiple_responses'),
            'default_template_id' => $validated['default_template_id'] ?? null,
            'enabled_avatar_presets' => $enabledAvatars,
            'show_testimonial_date_on_homepage' => $request->boolean('show_testimonial_date_on_homepage'),
        ]);
        $settings->save();

        SurveySetting::forgetSettingsCache();
        app(PneduFrontendCacheInvalidationService::class)->invalidateSurveySettings();

        return redirect()
            ->route('settings.surveys.edit')
            ->with('success', 'Ustawienia ankiet zostały zapisane.');
    }
}
