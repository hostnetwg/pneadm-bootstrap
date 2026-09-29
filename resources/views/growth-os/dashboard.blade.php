<x-app-layout>
    <x-slot name="header">
        PNE Growth OS
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">PNE Growth OS</h2>
                    <p class="text-secondary mb-0">Pomagamy planować treści, kampanie i rozwój PNE.</p>
                </div>
                <span class="badge bg-warning text-dark align-self-start align-self-lg-center">
                    Moduł w wersji przygotowawczej
                </span>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="growth-attention-heading">
            <h2 class="h5 mb-3" id="growth-attention-heading">Wymaga Twojej uwagi</h2>
            <div class="alert alert-success mb-2" role="status">
                <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                Brak zadań wymagających decyzji.
            </div>
            <p class="small text-secondary mb-0">
                Docelowo pojawią się tutaj materiały do zatwierdzenia, propozycje tematów,
                grafiki, kampanie i materiały ekspertów.
            </p>
        </section>

        <section class="mb-4" aria-labelledby="growth-upcoming-heading">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h2 class="h5 mb-0" id="growth-upcoming-heading">Nadchodzące</h2>
                <span class="badge bg-secondary">Dane demonstracyjne</span>
            </div>
            <div class="list-group">
                <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                    <span><i class="fas fa-video text-primary me-2" aria-hidden="true"></i>Webinar TIK</span>
                    <span class="small text-secondary">Przykład</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                    <span><i class="fas fa-chalkboard-teacher text-primary me-2" aria-hidden="true"></i>Szkolenie eksperta</span>
                    <span class="small text-secondary">Przykład</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                    <span><i class="fas fa-envelope text-primary me-2" aria-hidden="true"></i>Newsletter</span>
                    <span class="small text-secondary">Przykład</span>
                </div>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="growth-areas-heading">
            <h2 class="h5 mb-3" id="growth-areas-heading">Obszary systemu</h2>
            <div class="row g-3">
                @foreach ([
                    ['Radar', 'fa-satellite-dish'],
                    ['Kampanie', 'fa-bullhorn'],
                    ['Treści', 'fa-file-alt'],
                    ['Eksperci', 'fa-user-tie'],
                    ['Odbiorcy', 'fa-users'],
                    ['Szkoły / CRM', 'fa-school'],
                    ['Produkty', 'fa-box-open'],
                    ['Analityka', 'fa-chart-line'],
                ] as [$label, $icon])
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <i class="fas {{ $icon }} text-primary mb-3" aria-hidden="true"></i>
                                <h3 class="h6 card-title">{{ $label }}</h3>
                                <span class="badge bg-light text-secondary border">Planowane</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="growth-ai-heading">
            <div class="card border-primary-subtle">
                <div class="card-body">
                    <h2 class="h5 card-title" id="growth-ai-heading">
                        <i class="fas fa-wand-magic-sparkles text-primary me-2" aria-hidden="true"></i>
                        AI Producer
                    </h2>
                    <p class="card-text text-secondary mb-0">
                        W przyszłości AI będzie tutaj pomagało przygotowywać kampanie,
                        treści i rekomendacje.
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
