@php
    $aiDailyLimitMessage = \App\Services\GrowthOS\AI\GrowthAiService::DAILY_LIMIT_MESSAGE;
    $aiDailyLimitReached = auth()->user() !== null
        && app(\App\Services\GrowthOS\AI\GrowthAiService::class)->dailyUsage(auth()->user()) >= (int) config('growth_ai.limits.daily_per_user');
@endphp
@if(session('error') && session('error') !== $aiDailyLimitMessage)
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if(session('error') === $aiDailyLimitMessage || $aiDailyLimitReached)
    <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
        <span>{{ $aiDailyLimitMessage }}</span>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#growth-ai-limit-reset">Zresetuj limit</button>
    </div>
@endif
