<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Projekt webinaru
    </x-slot>

    <div class="container-fluid px-0">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
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
                    <span class="badge text-bg-success">Gotowe</span>
                @else
                    <span class="badge text-bg-danger">Czeka na decyzję</span>
                @endif
            </div>
            <div class="card-body">
                <p class="mb-3"><span class="h6 text-secondary">Temat</span><br>{{ $project['topic'] }}</p>
                <form method="POST" action="{{ route('growth.projects.direction.update', $project['id']) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="direction_why_now" class="form-label">Dlaczego teraz</label>
                            <textarea id="direction_why_now" name="why_now" rows="3" class="form-control @error('why_now') is-invalid @enderror" required>{{ old('why_now', $project['direction']['why_now'] ?? '') }}</textarea>
                            @error('why_now')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="direction_audience" class="form-label">Dla kogo</label>
                            <textarea id="direction_audience" name="audience" rows="3" class="form-control @error('audience') is-invalid @enderror" required>{{ old('audience', $project['direction']['audience'] ?? '') }}</textarea>
                            @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="direction_problem" class="form-label">Jaki problem rozwiązujemy</label>
                            <textarea id="direction_problem" name="problem" rows="3" class="form-control @error('problem') is-invalid @enderror" required>{{ old('problem', $project['direction']['problem'] ?? '') }}</textarea>
                            @error('problem')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="direction_takeaway" class="form-label">Co uczestnik ma wynieść</label>
                            <textarea id="direction_takeaway" name="takeaway" rows="3" class="form-control @error('takeaway') is-invalid @enderror" required>{{ old('takeaway', $project['direction']['takeaway'] ?? '') }}</textarea>
                            @error('takeaway')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="direction_sell_later" class="form-label">Czy chcemy coś później sprzedawać</label>
                            <select id="direction_sell_later" name="sell_later" class="form-select @error('sell_later') is-invalid @enderror" required>
                                @foreach(['nie', 'być może', 'tak'] as $option)
                                    <option value="{{ $option }}" @selected(old('sell_later', $project['direction']['sell_later'] ?? 'być może') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('sell_later')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary mt-3">Zapisz kierunek</button>
                </form>
                <div class="alert alert-info small mt-3 mb-3">
                    Sugestia AI: temat jest aktualny, bo łączy AI z konkretną pracą nauczyciela. Komunikację prowadź przez oszczędność czasu, nie przez technologiczną modę. Ta podpowiedź zostaje tylko w tej przeglądarce.
                </div>
                @unless(isset($project['completed_steps']['direction']))
                    <form method="POST" action="{{ route('growth.projects.steps.complete', [$project['id'], 'direction']) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Zatwierdź kierunek</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('growth.projects.steps.reopen', [$project['id'], 'direction']) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Cofnij zatwierdzenie</button>
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
                            <p class="small mb-2">
                                <span class="badge bg-light text-secondary border">
                                    @if($growthAiEnabled)
                                        AI: {{ ucfirst($growthAiProvider) }} / {{ $growthAiModel }}
                                    @else
                                        AI: symulacja lokalna
                                    @endif
                                </span>
                            </p>
                            <form
                                id="growth-concept-ai-form"
                                method="POST"
                                action="{{ route('growth.projects.concept.ai', $project['id']) }}"
                            >
                                @csrf
                                <label for="concept_ai_intent" class="form-label">Co zmienić?</label>
                                <select id="concept_ai_intent" name="intent" class="form-select mb-2 @error('intent') is-invalid @enderror" required>
                                    @foreach($conceptAiIntents as $intent)
                                        <option value="{{ $intent['value'] }}" @selected(old('intent') === $intent['value'])>{{ $intent['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('intent')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror

                                <label for="concept_ai_instruction" class="form-label small">Dodatkowa instrukcja (opcjonalnie)</label>
                                <textarea
                                    id="concept_ai_instruction"
                                    name="instruction"
                                    rows="3"
                                    maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}"
                                    class="form-control form-control-sm mb-2 @error('instruction') is-invalid @enderror"
                                    placeholder="Np. uprość język i dodaj dwa przykłady z lekcji"
                                >{{ old('instruction') }}</textarea>
                                @error('instruction')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
                                @if($growthAiEnabled)
                                    <p class="small text-secondary mb-2">
                                        Wysyłana jest tylko koncepcja i ta instrukcja. Nie wpisuj danych osobowych, danych klientów ani sekretów.
                                    </p>
                                @endif

                                <button
                                    id="growth-concept-ai-submit"
                                    type="submit"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    Wygeneruj propozycję
                                </button>
                                <p id="growth-concept-ai-wait-hint" class="small text-secondary mt-2 mb-0 d-none" aria-live="polite">
                                    To trwa dłużej niż zwykle — propozycja zwykle wraca w 10–60 sekund.
                                </p>
                            </form>
                            <div id="growth-concept-ai-status" class="alert mt-3 mb-0 d-none" role="status"></div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const form = document.getElementById('growth-concept-ai-form');
                                    const button = document.getElementById('growth-concept-ai-submit');
                                    const hint = document.getElementById('growth-concept-ai-wait-hint');
                                    const status = document.getElementById('growth-concept-ai-status');
                                    const proposalContainer = document.getElementById('growth-concept-ai-proposal');
                                    if (!form || !button || !hint || !status || !proposalContainer) {
                                        return;
                                    }

                                    const idleLabel = 'Wygeneruj propozycję';
                                    let waitTimer = null;
                                    let audioCtx = null;

                                    const clearTimers = function () {
                                        window.clearTimeout(waitTimer);
                                        waitTimer = null;
                                    };

                                    const setIdle = function () {
                                        clearTimers();
                                        hint.classList.add('d-none');
                                        button.disabled = false;
                                        button.removeAttribute('aria-busy');
                                        button.innerHTML = idleLabel;
                                    };

                                    const showStatus = function (message, type) {
                                        status.className = 'alert mt-3 mb-0 alert-' + type;
                                        status.textContent = message;
                                    };

                                    const setLoading = function () {
                                        button.disabled = true;
                                        button.setAttribute('aria-busy', 'true');
                                        button.innerHTML =
                                            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                                            'AI przygotowuje propozycję…';
                                        clearTimers();
                                        waitTimer = window.setTimeout(function () {
                                            hint.classList.remove('d-none');
                                        }, 20000);
                                    };

                                    const unlockAudio = function () {
                                        try {
                                            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                                            if (!AudioContextClass) {
                                                return null;
                                            }
                                            if (!audioCtx) {
                                                audioCtx = new AudioContextClass();
                                                const oscillator = audioCtx.createOscillator();
                                                const gain = audioCtx.createGain();
                                                gain.gain.value = 0.0001;
                                                oscillator.connect(gain);
                                                gain.connect(audioCtx.destination);
                                                oscillator.start();
                                                oscillator.stop(audioCtx.currentTime + 0.01);
                                            }
                                            if (audioCtx.state === 'suspended') {
                                                audioCtx.resume();
                                            }
                                            return audioCtx;
                                        } catch (error) {
                                            return null;
                                        }
                                    };

                                    const playSuccessChime = function () {
                                        try {
                                            const ctx = unlockAudio();
                                            if (!ctx) {
                                                return;
                                            }

                                            const play = function () {
                                                const now = ctx.currentTime;
                                                [
                                                    { frequency: 523.25, start: 0, duration: 0.16 },
                                                    { frequency: 659.25, start: 0.12, duration: 0.18 },
                                                    { frequency: 783.99, start: 0.26, duration: 0.28 },
                                                ].forEach(function (tone) {
                                                    const oscillator = ctx.createOscillator();
                                                    const gain = ctx.createGain();
                                                    oscillator.type = 'sine';
                                                    oscillator.frequency.value = tone.frequency;
                                                    gain.gain.setValueAtTime(0.0001, now + tone.start);
                                                    gain.gain.exponentialRampToValueAtTime(0.12, now + tone.start + 0.02);
                                                    gain.gain.exponentialRampToValueAtTime(0.0001, now + tone.start + tone.duration);
                                                    oscillator.connect(gain);
                                                    gain.connect(ctx.destination);
                                                    oscillator.start(now + tone.start);
                                                    oscillator.stop(now + tone.start + tone.duration + 0.05);
                                                });
                                            };

                                            if (ctx.state === 'suspended') {
                                                ctx.resume().then(play).catch(function () {});
                                            } else {
                                                play();
                                            }
                                        } catch (error) {
                                            // Dźwięk jest opcjonalny.
                                        }
                                    };

                                    form.addEventListener('submit', function (event) {
                                        event.preventDefault();
                                        if (button.getAttribute('aria-busy') === 'true') {
                                            return;
                                        }

                                        unlockAudio();
                                        setLoading();

                                        fetch(form.action, {
                                            method: 'POST',
                                            body: new FormData(form),
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-Requested-With': 'XMLHttpRequest',
                                            },
                                            credentials: 'same-origin',
                                        }).then(async function (response) {
                                            let payload = null;
                                            try {
                                                payload = await response.json();
                                            } catch (error) {
                                                payload = null;
                                            }

                                            if (response.ok && payload && payload.ok && typeof payload.proposal_html === 'string') {
                                                proposalContainer.innerHTML = payload.proposal_html;
                                                setIdle();
                                                showStatus(payload.message || 'AI przygotowało propozycję.', 'success');
                                                playSuccessChime();
                                                window.setTimeout(function () {
                                                    const proposal = proposalContainer.querySelector('section');
                                                    if (proposal) {
                                                        proposal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                                    }
                                                }, 100);
                                                return;
                                            }

                                            setIdle();
                                            showStatus(
                                                (payload && payload.message) || 'Nie udało się przygotować propozycji AI. Możesz kontynuować ręcznie.',
                                                'danger'
                                            );
                                        }).catch(function () {
                                            setIdle();
                                            showStatus('AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.', 'danger');
                                        });
                                    });

                                    window.addEventListener('pageshow', setIdle);
                                });
                            </script>
                        </section>

                        <div id="growth-concept-ai-proposal" aria-live="polite">
                            @include('growth-os.projects.partials.concept-ai-proposal', [
                                'proposal' => $proposal,
                                'projectId' => $project['id'],
                            ])
                        </div>

                        @if(count($conceptDecisions) > 0)
                            <section class="border rounded p-3 mb-3" aria-labelledby="concept-decisions-heading">
                                <h3 class="h6" id="concept-decisions-heading">Decyzje</h3>
                                <ul class="small mb-0 list-unstyled">
                                    @foreach($conceptDecisions as $conceptDecision)
                                        <li class="mb-2">
                                            <strong>{{ $conceptDecision['decided_at'] }}</strong>
                                            · {{ $conceptDecision['decision'] }}
                                            @if($conceptDecision['actor'] !== '')
                                                <div class="text-secondary">{{ $conceptDecision['actor'] }}</div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
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
                                <ul class="small mb-0 list-unstyled">
                                    @foreach($group['items'] as $item)
                                        @php
                                            $task = ($item['task_key'] ?? null) ? ($operationalTasks[$item['task_key']] ?? null) : null;
                                            $isDone = $task && $task->status === \App\Models\GrowthOS\GrowthTask::STATUS_DONE;
                                        @endphp
                                        <li class="mb-2">
                                            @if($task)
                                                <form method="POST" action="{{ route('growth.projects.tasks.update', [$project['id'], $task->key]) }}">
                                                    @csrf
                                                    <input type="hidden" name="done" value="{{ $isDone ? '0' : '1' }}">
                                                    <label class="form-check mb-0">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            @checked($isDone)
                                                            onchange="this.form.submit()"
                                                            aria-label="{{ $item['label'] }}"
                                                        >
                                                        <span class="form-check-label">{{ $item['label'] }}</span>
                                                    </label>
                                                </form>
                                            @else
                                                <span>{{ $item['label'] }}</span>
                                            @endif
                                        </li>
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
