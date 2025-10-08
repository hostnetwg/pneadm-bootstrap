@props(['active'])

@php
$classes = ($active ?? false)
            ? 'd-block w-100 ps-3 pe-4 py-2 border-start border-4 border-primary text-start text-body bg-light fw-medium'
            : 'd-block w-100 ps-3 pe-4 py-2 border-start border-4 border-transparent text-start text-body-secondary fw-medium hover-bg-light';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
