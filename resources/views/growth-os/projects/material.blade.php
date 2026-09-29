<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Materiał
    </x-slot>

    <div class="container-fluid px-0">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <nav class="mb-3 small" aria-label="Okruszki">
            <a href="{{ route('growth.projects.show', $project['id']) }}">Projekt webinaru</a>
            <span class="text-secondary"> / </span>
            <span>{{ $material['name'] }}</span>
        </nav>

        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-secondary">{{ $material['kind'] }}</span>
                        <span class="badge bg-light text-secondary border">{{ $materialStatusLabels[$material['status']] ?? $material['status'] }}</span>
                    </div>
                    <h2 class="h4 mb-2">{{ $material['name'] }}</h2>
                    <p class="text-secondary mb-0">{{ $material['summary'] }}</p>
                </div>
                <a href="{{ route('growth.inbox.index') }}" class="btn btn-outline-primary align-self-start">Inbox</a>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card border h-100" aria-labelledby="material-preview-heading">
                    <div class="card-header bg-white">
                        <h3 class="h6 mb-0" id="material-preview-heading">Draft / sugestia AI</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-line;">{{ $material['draft'] }}</p>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="card border border-warning-subtle mb-3" aria-labelledby="material-safety-heading">
                    <div class="card-header bg-warning-subtle">
                        <h3 class="h6 mb-0" id="material-safety-heading">Co stanie się po zmianie statusu?</h3>
                    </div>
                    <div class="card-body">
                        <p>{{ $material['why'] }}</p>
                        <ul class="small mb-0">
                            <li>Zmiana dotyczy tylko tej sesji przeglądarki.</li>
                            <li>Nic nie trafia do Sendy, YouTube, Meta ani bazy.</li>
                            <li>„Opublikowane / zaplanowane” jest wyłącznie symulacją statusu.</li>
                        </ul>
                    </div>
                </section>

                <form method="POST" action="{{ route('growth.projects.materials.status', [$project['id'], $material['id']]) }}" class="card border">
                    @csrf
                    <div class="card-body">
                        <label for="status" class="form-label">Status materiału</label>
                        <select id="status" name="status" class="form-select">
                            @foreach($materialStatusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($material['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Zapisz status w sesji</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
