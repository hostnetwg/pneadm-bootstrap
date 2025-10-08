<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-dark btn-sm text-uppercase fw-semibold']) }}>
    {{ $slot }}
</button>
