<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Models\GrowthAiSetting;
use App\Services\GrowthOS\AI\Support\GrowthAiExecutionOptions;
use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AiSettingsController extends Controller
{
    public function edit(): View
    {
        $defaults = GrowthAiSetting::resolvedDefaults();

        return view('growth-os.ai-settings', [
            'defaults' => $defaults,
            'models' => GrowthAiModelCatalog::models(),
            'effortLabels' => GrowthAiModelCatalog::effortLabels(),
            'recommended' => GrowthAiSetting::recommendedDefaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $modelIds = array_keys(GrowthAiModelCatalog::models());
        $data = $request->validate([
            'general_model' => ['required', 'string', Rule::in($modelIds)],
            'general_reasoning_effort' => ['required', 'string', Rule::in(GrowthAiModelCatalog::EFFORTS)],
            'research_model' => ['required', 'string', Rule::in($modelIds)],
            'research_reasoning_effort' => ['required', 'string', Rule::in(GrowthAiModelCatalog::EFFORTS)],
        ]);

        GrowthAiExecutionOptions::assertAllowed(
            $data['general_model'],
            $data['general_reasoning_effort'],
            requiresWebSearch: false,
        );
        GrowthAiExecutionOptions::assertAllowed(
            $data['research_model'],
            $data['research_reasoning_effort'],
            requiresWebSearch: true,
        );

        GrowthAiSetting::updateDefaults($data, $request->user()?->id);

        return redirect()
            ->route('growth.ai-settings.edit')
            ->with('success', 'Zapisano domyślne ustawienia AI.');
    }

    public function restoreRecommended(Request $request): RedirectResponse
    {
        GrowthAiSetting::updateDefaults(GrowthAiSetting::recommendedDefaults(), $request->user()?->id);

        return redirect()
            ->route('growth.ai-settings.edit')
            ->with('success', 'Przywrócono zalecane ustawienia AI (Sol).');
    }
}
