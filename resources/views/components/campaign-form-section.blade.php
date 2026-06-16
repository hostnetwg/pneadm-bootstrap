@props([
    'title',
    'subtitle' => null,
    'icon' => 'circle',
    'accent' => 'primary',
    'step' => null,
])

<div {{ $attributes->merge(['class' => 'campaign-form-section card border-'.$accent.'-subtle mb-4 shadow-sm']) }}>
    <div class="card-header bg-{{ $accent }}-subtle py-2 px-3">
        <div class="d-flex align-items-start gap-2">
            @if($step)
                <span class="campaign-form-section-number bg-{{ $accent }} text-white">{{ $step }}</span>
            @else
                <i class="bi bi-{{ $icon }} text-{{ $accent }} mt-1"></i>
            @endif
            <div class="min-w-0">
                <h6 class="mb-0 fw-semibold">{{ $title }}</h6>
                @if(filled($subtitle))
                    <p class="small text-muted mb-0 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body px-3 py-3">
        {{ $slot }}
    </div>
</div>
