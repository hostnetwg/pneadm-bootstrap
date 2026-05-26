{{-- Aktywne warianty cenowe pod tytułem na liście szkoleń --}}
@php
    /** @var \App\Models\Course $course */
    $variants = $course->relationLoaded('priceVariants')
        ? $course->priceVariants->sortBy('name')->values()
        : collect();
@endphp

@if ($variants->isNotEmpty())
    <div class="course-index-prices small text-danger mt-1 lh-sm">
        @foreach ($variants as $variant)
            <div @class(['mb-1' => ! $loop->last])>
                @if ($variants->count() > 1)
                    <span class="fw-semibold">{{ $variant->name }}:</span>
                @endif
                @if ($variant->isPromotionActive() && $variant->promotion_price !== null)
                    <del class="opacity-75">{{ number_format((float) $variant->price, 2, ',', ' ') }} PLN</del>
                    <strong>{{ number_format((float) $variant->promotion_price, 2, ',', ' ') }} PLN</strong>
                @else
                    <strong>{{ number_format((float) $variant->getCurrentPrice(), 2, ',', ' ') }} PLN</strong>
                @endif
            </div>
        @endforeach
    </div>
@elseif ($course->is_paid)
    <div class="small text-danger mt-1 opacity-75">brak aktywnych wariantów cen</div>
@endif
