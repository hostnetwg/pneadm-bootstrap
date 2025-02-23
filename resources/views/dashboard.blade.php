<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
                    {{ __("You're logged in!") }}
        </div>
    </div>
</x-app-layout>
