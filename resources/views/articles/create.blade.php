<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">
            {{ __('Dodaj artykuł') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <form action="{{ route('articles.store') }}" method="POST" enctype="multipart/form-data">
                @include('articles.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
