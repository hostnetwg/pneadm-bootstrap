@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active text-body fw-medium border-bottom border-primary'
            : 'nav-link text-body-secondary fw-medium border-bottom border-transparent hover-border-secondary';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
