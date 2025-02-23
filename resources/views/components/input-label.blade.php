@props(['value'])

<label {{ $attributes->merge(['class' => 'form-label fw-medium small text-body']) }}>
    {{ $value ?? $slot }}
</label>
