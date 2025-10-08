<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-light border rounded fw-semibold text-uppercase shadow-sm disabled-opacity-50']) }}>
    {{ $slot }}
</button>
