<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Pomysły
    </x-slot>

    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">Pomysły</h2>
                    <p class="text-secondary mb-0">
                        Minimalny ekran inspiracji. Nie zaczynamy od abstrakcyjnego Topic — główne CTA to nadal zaplanowanie webinaru TIK.
                    </p>
                </div>
                <a href="{{ route('growth.projects.create') }}" class="btn btn-primary align-self-start">Zaplanuj webinar TIK</a>
            </div>
        </section>

        <div class="row g-3">
            @foreach($ideas as $idea)
                <div class="col-md-6 col-xl-4">
                    <div class="card border h-100">
                        <div class="card-body">
                            <span class="badge bg-light text-secondary border mb-2">Sugestia AI (symulacja)</span>
                            <h3 class="h5">{{ $idea['title'] }}</h3>
                            <p class="text-secondary mb-0">{{ $idea['why'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($project)
            <div class="alert alert-info mt-4 mb-0">
                Masz już projekt w tej sesji:
                <a href="{{ route('growth.projects.show', $project['id']) }}" class="alert-link">{{ $project['topic'] }}</a>.
            </div>
        @endif
    </div>
</x-app-layout>
