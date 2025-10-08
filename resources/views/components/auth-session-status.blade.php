@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'fw-bold text-success small']) }}>
        {{ $status }}
    </div>
@endif
