<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold text-dark">
            Profil
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>

                <!-- Informacja o usuwaniu konta -->
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-muted">
                                <i class="bi bi-info-circle me-2"></i>
                                Usuwanie konta
                            </h5>
                            <p class="card-text text-muted mb-0">
                                Jeśli chcesz usunąć swoje konto, skontaktuj się z administratorem systemu. 
                                Usuwanie konta wymaga uprawnień administratora ze względów bezpieczeństwa.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
