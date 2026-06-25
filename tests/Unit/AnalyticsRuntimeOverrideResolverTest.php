<?php

namespace Tests\Unit;

use App\Enums\Analytics\AnalyticsMode;
use App\Models\AnalyticsSetting;
use App\Services\Analytics\AnalyticsModeResolver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyticsRuntimeOverrideResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        AnalyticsSetting::forgetSettingsCache();

        parent::tearDown();
    }

    public function test_hard_kill_switch_forces_off_despite_override(): void
    {
        config()->set('analytics.enabled', false);
        $this->primeOverride(true, 'full');

        $this->assertSame(AnalyticsMode::Off, (new AnalyticsModeResolver)->resolve());
    }

    public function test_override_disabled_forces_off(): void
    {
        config()->set('analytics.enabled', true);
        $this->primeOverride(false, 'standard');

        $this->assertSame(AnalyticsMode::Off, (new AnalyticsModeResolver)->resolve());
    }

    public function test_override_enabled_with_light_mode_resolves_light(): void
    {
        config()->set('analytics.enabled', true);
        $this->primeOverride(true, 'light');

        $this->assertSame(AnalyticsMode::Light, (new AnalyticsModeResolver)->resolve());
    }

    public function test_null_override_falls_back_to_config(): void
    {
        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'full');
        $this->primeOverride(null, null);

        $this->assertSame(AnalyticsMode::Full, (new AnalyticsModeResolver)->resolve());
    }

    public function test_unknown_mode_falls_back_safely_to_standard(): void
    {
        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'banana');
        $this->primeOverride(null, null);

        $this->assertSame(AnalyticsMode::Standard, (new AnalyticsModeResolver)->resolve());
    }

    public function test_invalid_override_mode_is_ignored_and_config_used(): void
    {
        config()->set('analytics.enabled', true);
        config()->set('analytics.default_mode', 'light');

        $model = new AnalyticsSetting;
        $model->enabled_override = true;
        $model->default_mode_override = 'banana';
        Cache::put(AnalyticsSetting::SETTINGS_CACHE_KEY, $model, 60);

        $this->assertSame(AnalyticsMode::Light, (new AnalyticsModeResolver)->resolve());
    }

    private function primeOverride(?bool $enabled, ?string $mode): void
    {
        $model = new AnalyticsSetting;
        $model->enabled_override = $enabled;
        $model->default_mode_override = $mode;

        Cache::put(AnalyticsSetting::SETTINGS_CACHE_KEY, $model, 60);
    }
}
