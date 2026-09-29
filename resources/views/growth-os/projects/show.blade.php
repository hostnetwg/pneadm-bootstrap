<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Projekt webinaru
    </x-slot>

    <div class="container-fluid px-0">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <nav class="mb-3 small" aria-label="Okruszki">
            <a href="{{ route('growth.projects.index') }}">Projekty</a>
            <span class="text-secondary"> / </span>
            <span>{{ $project['topic'] }}</span>
        </nav>

        <section class="rounded border bg-light p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-primary">{{ $projectStatusLabels[$project['status']] ?? $project['status'] }}</span>
                        <span class="badge bg-warning text-dark">Prototyp sesyjny</span>
                    </div>
                    <h2 class="h4 mb-2">{{ $project['topic'] }}</h2>
                    <p class="text-secondary mb-0">
                        {{ $project['type'] }} · {{ $project['live_date'] }} {{ $project['live_time'] }} · {{ $project['host'] }}
                    </p>
                </div>
                <div class="text-lg-end">
                    <div class="fw-semibold">{{ $health['days_label'] }}</div>
                    <div class="{{ $health['critical_count'] > 0 ? 'text-danger' : 'text-success' }}">{{ $health['critical_label'] }}</div>
                </div>
            </div>
        </section>

        <section class="card border-primary mb-4" aria-labelledby="next-action-heading">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1" id="next-action-heading">Najważniejszy następny krok</h2>
                    <p class="text-secondary mb-0">{{ $nextAction['meta'] }}</p>
                </div>
                <a href="{{ $nextAction['href'] }}" class="btn btn-primary align-self-start">
                    {{ $nextAction['label'] }}
                </a>
            </div>
        </section>

        <section class="mb-4" aria-labelledby="stages-heading">
            <h2 class="h5 mb-3" id="stages-heading">Etapy przygotowania</h2>
            <div class="row g-2">
                @foreach($stages as $stage)
                    @php $done = isset($project['completed_steps'][$stage['id']]); @endphp
                    <div class="col-md-4 col-xl-2">
                        <a href="#{{ $stage['anchor'] }}" class="card border text-decoration-none text-reset h-100">
                            <div class="card-body p-3">
                                <span class="badge {{ $done ? 'text-bg-success' : 'bg-light text-secondary border' }}">
                                    {{ $done ? 'Gotowe' : 'Przed nami' }}
                                </span>
                                <div class="fw-semibold mt-2">{{ $stage['label'] }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="direction" class="card border mb-4" aria-labelledby="direction-heading">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h5 mb-0" id="direction-heading">Pomysł i kierunek</h2>
                @if(isset($project['completed_steps']['direction']))
                    <span class="badge text-bg-success">Zatwierdzone w sesji</span>
                @else
                    <span class="badge text-bg-danger">Czeka na decyzję</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Temat</h3>
                        <p>{{ $project['topic'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Dlaczego teraz</h3>
                        <p>{{ $project['direction']['why_now'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Dla kogo</h3>
                        <p>{{ $project['direction']['audience'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Jaki problem rozwiązujemy</h3>
                        <p>{{ $project['direction']['problem'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Co uczestnik ma wynieść</h3>
                        <p>{{ $project['direction']['takeaway'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-secondary">Czy chcemy coś później sprzedawać</h3>
                        <p>{{ $project['direction']['sell_later'] }}</p>
                    </div>
                </div>
                <div class="alert alert-info small mb-3">
                    Sugestia AI: temat jest aktualny, bo łączy AI z konkretną pracą nauczyciela. Komunikację prowadź przez oszczędność czasu, nie przez technologiczną modę.
                </div>
                @unless(isset($project['completed_steps']['direction']))
                    <form method="POST" action="{{ route('growth.projects.steps.complete', [$project['id'], 'direction']) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Zatwierdź kierunek</button>
                    </form>
                @endunless
            </div>
        </section>

        <section id="concept" class="card border mb-4" aria-labelledby="concept-heading">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h5 mb-0" id="concept-heading">Koncepcja webinaru</h2>
                @if(isset($project['completed_steps']['concept']))
                    <span class="badge text-bg-success">Gotowe</span>
                @else
                    <span class="badge text-bg-danger">Do dopracowania</span>
                @endif
            </div>
            <div class="card-body">
                @php
                    $concept = $project['concept'] ?? [];
                    $proposal = $project['concept_ai_proposal'] ?? null;
                    $versions = $project['concept_versions'] ?? [];
                    $pointsText = implode("\n", is_array($concept['points'] ?? null) ? $concept['points'] : []);
                @endphp

                <div class="row g-4">
                    <div class="col-lg-7">
                        <form method="POST" action="{{ route('growth.projects.concept.update', $project['id']) }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="concept_title" class="form-label">Tytuł webinaru</label>
                                <input id="concept_title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $concept['title'] ?? '') }}" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_subtitle" class="form-label">Podtytuł</label>
                                <input id="concept_subtitle" name="subtitle" type="text" class="form-control @error('subtitle') is-invalid @enderror" value="{{ old('subtitle', $concept['subtitle'] ?? '') }}" required>
                                @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_promise" class="form-label">Główna obietnica</label>
                                <textarea id="concept_promise" name="promise" rows="2" class="form-control @error('promise') is-invalid @enderror" required>{{ old('promise', $concept['promise'] ?? '') }}</textarea>
                                @error('promise')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_points" class="form-label">Główne punkty (jeden w linijce)</label>
                                <textarea id="concept_points" name="points" rows="5" class="form-control @error('points') is-invalid @enderror" required>{{ old('points', $pointsText) }}</textarea>
                                @error('points')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_plan" class="form-label">Plan webinaru</label>
                                <textarea id="concept_plan" name="plan" rows="2" class="form-control @error('plan') is-invalid @enderror" required>{{ old('plan', $concept['plan'] ?? '') }}</textarea>
                                @error('plan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_cta" class="form-label">Główne CTA</label>
                                <input id="concept_cta" name="cta" type="text" class="form-control @error('cta') is-invalid @enderror" value="{{ old('cta', $concept['cta'] ?? '') }}" required>
                                @error('cta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_lead_magnet" class="form-label">Materiał dodatkowy / lead magnet</label>
                                <input id="concept_lead_magnet" name="lead_magnet" type="text" class="form-control @error('lead_magnet') is-invalid @enderror" value="{{ old('lead_magnet', $concept['lead_magnet'] ?? '') }}" required>
                                @error('lead_magnet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="concept_next_product" class="form-label">Czy prowadzi do produktu</label>
                                <select id="concept_next_product" name="next_product" class="form-select @error('next_product') is-invalid @enderror" required>
                                    @foreach(['nie', 'być może', 'tak'] as $option)
                                        <option value="{{ $option }}" @selected(old('next_product', $concept['next_product'] ?? 'być może') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('next_product')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-outline-primary">Zapisz koncepcję</button>
                            </div>
                        </form>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @unless(isset($project['completed_steps']['concept']))
                                <form method="POST" action="{{ route('growth.projects.steps.complete', [$project['id'], 'concept']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Koncepcja gotowa</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('growth.projects.steps.reopen', [$project['id'], 'concept']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">Cofnij zatwierdzenie</button>
                                </form>
                            @endunless
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <section class="border rounded p-3 mb-3" aria-labelledby="concept-ai-heading">
                            <h3 class="h6" id="concept-ai-heading">Poproś AI o zmianę</h3>
                            <p class="small text-secondary">AI przygotuje wariant. Obecna koncepcja nie zostanie nadpisana, dopóki nie klikniesz „Zastosuj”.</p>
                            <form method="POST" action="{{ route('growth.projects.concept.ai', $project['id']) }}">
                                @csrf
                                <label for="concept_ai_intent" class="form-label">Co zmienić?</label>
                                <select id="concept_ai_intent" name="intent" class="form-select mb-2" required>
                                    @foreach($conceptAiIntents as $intent)
                                        <option value="{{ $intent['value'] }}">{{ $intent['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Wygeneruj propozycję</button>
                            </form>
                        </section>

                        @if(is_array($proposal))
                            <section class="border border-warning rounded p-3 mb-3" aria-labelledby="concept-proposal-heading">
                                <h3 class="h6" id="concept-proposal-heading">Propozycja AI</h3>
                                <p class="small mb-2">
                                    <span class="badge text-bg-warning text-dark">{{ $proposal['intent_label'] ?? 'Wariant' }}</span>
                                    {{ $proposal['note'] ?? '' }}
                                </p>
                                <p class="fw-semibold mb-1">{{ $proposal['concept']['title'] ?? '' }}</p>
                                <p class="small text-secondary mb-2">{{ $proposal['concept']['subtitle'] ?? '' }}</p>
                                <p class="small mb-2">{{ $proposal['concept']['promise'] ?? '' }}</p>
                                <ul class="small">
                                    @foreach(($proposal['concept']['points'] ?? []) as $point)
                                        <li>{{ $point }}</li>
                                    @endforeach
                                </ul>
                                <p class="small mb-3"><strong>CTA:</strong> {{ $proposal['concept']['cta'] ?? '' }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('growth.projects.concept.ai.apply', $project['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Zastosuj</button>
                                    </form>
                                    <form method="POST" action="{{ route('growth.projects.concept.ai.reject', $project['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Odrzuć</button>
                                    </form>
                                </div>
                            </section>
                        @endif

                        @if(is_array($versions) && count($versions) > 0)
                            <section class="border rounded p-3" aria-labelledby="concept-versions-heading">
                                <h3 class="h6" id="concept-versions-heading">Historia (sesja)</h3>
                                <ul class="small mb-0 list-unstyled">
                                    @foreach(array_slice($versions, 0, 5) as $index => $version)
                                        <li class="mb-2">
                                            <strong>v{{ count($versions) - $index }}</strong>
                                            · {{ $version['label'] ?? 'Wersja' }}
                                            <div class="text-secondary">{{ $version['concept']['title'] ?? '' }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section id="materials" class="mb-4" aria-labelledby="materials-heading">
            <h2 class="h5 mb-3" id="materials-heading">Materiały</h2>
            <div class="list-group">
                @foreach($project['materials'] as $material)
                    <a href="{{ route('growth.projects.materials.show', [$project['id'], $material['id']]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3">
                        <span>
                            <span class="badge text-bg-secondary me-2">{{ $material['kind'] }}</span>
                            <span class="fw-semibold">{{ $material['name'] }}</span>
                            <span class="d-block small text-secondary mt-1">{{ $material['summary'] }}</span>
                        </span>
                        <span class="badge bg-light text-secondary border text-nowrap">
                            {{ $materialStatusLabels[$material['status']] ?? $material['status'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section id="timeline" class="mb-4" aria-labelledby="timeline-heading">
            <h2 class="h5 mb-3" id="timeline-heading">Checklista czasowa</h2>
            <div class="row g-3">
                @foreach($timeline as $group)
                    <div class="col-md-6 col-xl-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h3 class="h6">{{ $group['period'] }}</h3>
                                <ul class="small mb-0">
                                    @foreach($group['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
