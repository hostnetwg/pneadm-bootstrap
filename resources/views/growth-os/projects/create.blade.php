<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Zaplanuj webinar
    </x-slot>

    @include('growth-os.partials.readable-styles')
    <div class="container-fluid px-0">
        <section class="rounded border bg-light p-4 mb-4">
            <h2 class="h4 mb-2">Zaplanuj webinar</h2>
            <p class="text-secondary mb-0">
                AI może pomóc określić kierunek, ale nie jest wymagane. Po utworzeniu przejdziesz do workspace projektu.
            </p>
        </section>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @include('growth-os.partials.ai-daily-limit-alert')
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                Sprawdź pola formularza.
            </div>
        @endif

        <form method="POST" action="{{ route('growth.projects.store') }}" data-growth-ai-form>
            @csrf
            <input type="hidden" name="use_direction_ai_proposal" value="0" data-use-direction-proposal>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="type" class="form-label">Co planujemy?</label>
                                <input id="type" name="type" class="form-control @error('type') is-invalid @enderror" value="{{ old('type', 'Webinar TIK') }}" required>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="live_date" class="form-label">Data live</label>
                                    <input id="live_date" name="live_date" type="date" class="form-control @error('live_date') is-invalid @enderror" value="{{ old('live_date', now()->addDays(7)->toDateString()) }}" required>
                                    @error('live_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="live_time" class="form-label">Godzina</label>
                                    <input id="live_time" name="live_time" type="time" class="form-control @error('live_time') is-invalid @enderror" value="{{ old('live_time', '20:00') }}" required>
                                    @error('live_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mt-3">
                                @include('growth-os.projects.partials.people-fields', [
                                    'idPrefix' => 'create',
                                    'hostInstructorId' => null,
                                    'hostName' => '',
                                    'voiceInstructorId' => null,
                                    'defaultHostSource' => $instructorOptions !== [] ? 'instructor' : 'manual',
                                ])
                            </div>

                            <div class="mt-3">
                                <label for="goal" class="form-label">Cel</label>
                                <select id="goal" name="goal" class="form-select @error('goal') is-invalid @enderror" required>
                                    @foreach($goals as $goal)
                                        <option value="{{ $goal['value'] }}" @selected(old('goal', 'education') === $goal['value'])>
                                            {{ $goal['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('goal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mt-3">
                                <label for="topic" class="form-label">Temat</label>
                                <input id="topic" name="topic" class="form-control @error('topic') is-invalid @enderror" value="{{ old('topic') }}" placeholder="Np. NotebookLM w pracy nauczyciela — od przygotowania lekcji do pracy z dokumentami">
                                @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Utwórz projekt webinaru</button>
                        </div>
                    </div>
                </div>

                <aside class="col-lg-5">
                    @php
                        $proposal = $directionProposal;
                    @endphp
                    <section class="card border h-100" aria-labelledby="direction-planning-heading">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h3 class="h6 mb-0" id="direction-planning-heading">Asystent planowania</h3>
                            <span class="badge bg-light text-secondary border">
                                @if($growthAiEnabled)
                                    AI: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal(is_array($proposal ?? null) ? $proposal : null, \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_RESEARCH) }}
                                @else
                                    AI: symulacja lokalna
                                @endif
                            </span>
                        </div>
                        <div class="card-body">
                            @if(! $proposal)
                                <p class="small text-secondary">
                                    Wpisz temat po lewej stronie, a AI sprawdzi aktualne informacje i pomoże określić kierunek webinaru.
                                </p>
                            @else
                                <h4 class="h6">Kierunek proponowany przez AI</h4>
                                <p class="small mb-2">
                                    <span class="badge bg-light text-secondary border">
                                        @if(($proposal['source'] ?? '') === 'real_ai')
                                            Wygenerowano: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal($proposal, \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_RESEARCH) }}
                                        @else
                                            Symulacja lokalna
                                        @endif
                                    </span>
                                </p>
                                <p class="small mb-2"><span class="text-secondary">Temat roboczy:</span> <span class="fw-semibold">{{ $proposal['working_topic'] }}</span></p>
                                <p class="small mb-2 growth-ai-text"><span class="fw-semibold">Dlaczego teraz</span><br>{{ $proposal['direction']['why_now'] }}</p>
                                <p class="small mb-2 growth-ai-text"><span class="fw-semibold">Dla kogo</span><br>{{ $proposal['direction']['audience'] }}</p>
                                <p class="small mb-2 growth-ai-text"><span class="fw-semibold">Problem</span><br>{{ $proposal['direction']['problem'] }}</p>
                                <p class="small mb-2 growth-ai-text"><span class="fw-semibold">Co uczestnik wyniesie</span><br>{{ $proposal['direction']['takeaway'] }}</p>
                                <p class="small mb-3"><span class="fw-semibold">Sprzedać później</span><br>{{ $proposal['direction']['sell_later'] }}</p>
                                @if(($proposal['title_suggestions'] ?? []) !== [])
                                    <p class="small fw-semibold mb-1">Alternatywne tytuły</p>
                                    <ul class="small">
                                        @foreach($proposal['title_suggestions'] as $suggestion)
                                            <li>{{ $suggestion }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <p class="small text-secondary growth-ai-text">{{ $proposal['change_summary'] ?? '' }}</p>
                                @include('growth-os.partials.ai-web-search-feedback', ['proposal' => $proposal])
                            @endif

                            @include('growth-os.partials.ai-execution-controls', [
                                'aiChannel' => \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_RESEARCH,
                                'aiProposal' => $proposal ?? null,
                                'aiControlId' => 'create-direction-ai-exec',
                            ])

                            <button type="submit" class="btn btn-outline-primary w-100" formaction="{{ route('growth.projects.direction-planning') }}" name="planning_mode" value="generate" data-growth-ai-submit>
                                <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                Przeanalizuj temat i zaproponuj kierunek
                            </button>

                            @if($proposal)
                                <label for="planning_instruction" class="form-label small mt-3">Co chcesz zmienić?</label>
                                <textarea id="planning_instruction" name="planning_instruction" rows="3" class="form-control form-control-sm" maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}" placeholder="Np. bardziej skup się na nauczycielach niż dyrektorach.">{{ old('planning_instruction') }}</textarea>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="submit" class="btn btn-outline-primary btn-sm" formaction="{{ route('growth.projects.direction-planning') }}" name="planning_mode" value="iterate" data-growth-ai-submit>
                                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true" data-growth-ai-spinner></span>
                                        Popraw propozycję
                                    </button>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm mt-3" data-mark-direction-proposal>
                                    Użyj tego kierunku
                                </button>
                                <p class="small text-success d-none mb-0 mt-2" data-direction-proposal-marked>Kierunek z AI zostanie użyty jako szkic przy tworzeniu projektu. Nadal musisz kliknąć „Utwórz projekt webinaru” i później zatwierdzić kierunek.</p>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
        </form>
        @include('growth-os.partials.ai-daily-limit-reset-modal')
    </div>

    <script>
        document.querySelectorAll('[data-growth-ai-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                setTimeout(() => {
                    form.querySelectorAll('[data-growth-ai-submit]').forEach((button) => { button.disabled = true; });
                }, 0);
                const spinner = submitter?.querySelector('[data-growth-ai-spinner]') || form.querySelector('[data-growth-ai-spinner]');
                spinner?.classList.remove('d-none');
                submitter?.setAttribute('aria-busy', 'true');
            });
        });
        document.querySelectorAll('[data-mark-direction-proposal]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelector('[data-use-direction-proposal]').value = '1';
                document.querySelector('[data-direction-proposal-marked]').classList.remove('d-none');
            });
        });
    </script>
</x-app-layout>
