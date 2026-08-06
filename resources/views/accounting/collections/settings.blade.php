<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Windykacja — ustawienia
            </h2>
            <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Wróć do windykacji
            </a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4" style="max-width: 720px;">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Nie udało się zapisać:</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header fw-semibold">Ustawienia ogólne</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('accounting.collections.settings.update') }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="contact_phone">Telefon kontaktowy (stopka e-maili)</label>
                            <input type="text"
                                   class="form-control @error('contact_phone') is-invalid @enderror"
                                   id="contact_phone"
                                   name="contact_phone"
                                   value="{{ old('contact_phone', $settings->contact_phone) }}"
                                   maxlength="64"
                                   placeholder="np. +48 123 456 789"
                                   autocomplete="tel">
                            @error('contact_phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Numer pojawia się w stopce przypomnień / ponagleń wysyłanych ze sprawy.
                                Zostaw puste, jeśli nie chcesz go pokazywać.
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Zapisz ustawienia
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
