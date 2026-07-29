<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            {{ __('Dodaj ofertę szkolenia') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <form action="{{ route('training-offers.store') }}" method="POST" enctype="multipart/form-data">
                @include('training-offers.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
