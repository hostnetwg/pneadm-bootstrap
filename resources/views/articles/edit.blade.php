<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Edytuj artykuł') }}
            </h2>
            <a href="{{ route('articles.show', $article) }}" class="btn btn-outline-secondary">
                Podgląd
            </a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('articles.update', $article) }}" method="POST" enctype="multipart/form-data">
                @include('articles.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
