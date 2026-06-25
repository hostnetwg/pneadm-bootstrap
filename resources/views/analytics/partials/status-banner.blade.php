@php
    $analyticsStatus = $analyticsStatus ?? app(\App\Services\Analytics\AnalyticsRuntimeStatusService::class)->status();
    $showSettingsLink = $showSettingsLink ?? true;
    $bannerClass = $analyticsStatus['warning_level'] === 'danger' ? 'alert-danger' : 'alert-warning';
@endphp

@if($analyticsStatus['show_banner'])
    <div class="alert {{ $bannerClass }} d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
        <div class="mb-0">
            <strong>Uwaga — stan analityki.</strong>
            {{ $analyticsStatus['message'] }}
        </div>
        @if($showSettingsLink)
            <a href="{{ route('analytics.settings.index') }}" class="btn btn-sm btn-outline-dark">
                Przejdź do ustawień analityki
            </a>
        @endif
    </div>
@endif
