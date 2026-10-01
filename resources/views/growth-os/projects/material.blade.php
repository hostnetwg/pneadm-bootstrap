<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Materiał
    </x-slot>

    @include('growth-os.partials.readable-styles')

    <div class="container-fluid growth-readable">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        <nav class="mb-3 small" aria-label="Okruszki">
            <a href="{{ route('growth.projects.show', $project['id']) }}">Projekt webinaru</a>
            <span class="text-secondary"> / </span>
            <span>{{ $material['name'] }}</span>
        </nav>

        <section class="rounded border growth-hero p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-secondary">{{ $material['kind'] }}</span>
                        <span class="badge {{ \App\Support\GrowthOS\DemoTikWebinarProject::materialStatusBadgeClasses()[$material['status']] ?? 'bg-light text-secondary border' }}">{{ in_array($material['status'], ['APPROVED', 'PUBLISHED'], true) ? '✓ ' : '' }}{{ $materialStatusLabels[$material['status']] ?? $material['status'] }}</span>
                    </div>
                    <h2 class="h4 mb-2">{{ $material['name'] }}</h2>
                    <p class="text-secondary mb-0">{{ $material['summary'] }}</p>
                </div>
                <a href="{{ route('growth.inbox.index') }}" class="btn btn-outline-primary align-self-start">Inbox</a>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-8">
                <form id="material-save" method="POST" action="{{ route('growth.projects.materials.status', [$project['id'], $material['id']]) }}" class="card border">
                    @csrf
                    <div class="card-header">
                        <h3 class="h6 mb-0" id="material-preview-heading">Szkic</h3>
                    </div>
                    <div class="card-body">
                        <label for="draft" class="visually-hidden">Szkic</label>
                        <textarea id="draft" name="draft" class="form-control growth-draft-editor" rows="14">{{ $material['draft'] }}</textarea>
                    </div>
                    <div class="card-footer d-flex flex-column flex-sm-row align-items-sm-end justify-content-between gap-3">
                        <div>
                            <label for="status" class="form-label">Status materiału</label>
                            <select id="status" name="status" class="form-select">
                                @foreach($materialStatusLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($material['status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Zapisz materiał</button>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                @if($aiDraftSupported)
                    <section class="card border mb-4" id="material-ai" aria-labelledby="material-ai-heading">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h3 class="h6 mb-0" id="material-ai-heading">Szkic z pomocą AI</h3>
                            <span class="badge bg-light text-secondary border">
                                {{ $aiRealEnabled ? 'AI: OpenAI / '.$aiModel : 'AI: symulacja lokalna' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="small text-secondary">
                                AI przygotowuje wyłącznie propozycję na podstawie zatwierdzonego kierunku i koncepcji{{ $aiDraftIsFacebookPost ? ' oraz zatwierdzonego opisu YouTube' : '' }}. Obecny szkic zmienia się dopiero po kliknięciu „Zastosuj”. Nic nie jest publikowane.
                            </p>

                            @if($aiDraftIsFacebookPost)
                                <p class="small mb-3">
                                    @if($aiDraftUsesYoutubeDescription)
                                        <span class="badge text-bg-success">✓ Opis YouTube</span> AI użyje zatwierdzonego opisu YouTube jako źródła.
                                    @else
                                        <span class="badge bg-light text-secondary border">Opis YouTube</span> Opis YouTube nie jest zatwierdzony, więc AI go nie dostanie.
                                    @endif
                                </p>
                                <p class="small text-secondary mb-3">AI nie poda linku. W jego miejscu wstawi {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::LINK_PLACEHOLDER }} do ręcznej podmiany.</p>
                            @endif

                            @if(! $aiDraftAllowed)
                                <div class="alert alert-warning small mb-3" role="status">Najpierw zatwierdź kierunek i koncepcję webinaru.</div>
                            @endif

                            <form method="POST" action="{{ route('growth.projects.materials.ai', [$project['id'], $material['id']]) }}" data-growth-ai-form>
                                @csrf
                                <input type="hidden" name="emojis" value="0">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="emojis" value="1" id="material_ai_emojis" @checked(old('emojis', '1') === '1') @disabled(! $aiDraftAllowed)>
                                    <label class="form-check-label" for="material_ai_emojis">{{ $aiDraftIsFacebookPost ? 'Dodaj emotikony do posta' : 'Dodaj emotikony do opisu' }}</label>
                                </div>
                                @if($aiDraftIsFacebookPost)
                                    <input type="hidden" name="hashtags" value="0">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="hashtags" value="1" id="material_ai_hashtags" @checked(old('hashtags', '1') === '1') @disabled(! $aiDraftAllowed)>
                                        <label class="form-check-label" for="material_ai_hashtags">Dodaj hashtagi (3–5)</label>
                                    </div>
                                @endif
                                <label for="material_ai_instruction" class="form-label">Dodatkowa instrukcja dla AI (opcjonalnie)</label>
                                <textarea
                                    id="material_ai_instruction"
                                    name="instruction"
                                    rows="4"
                                    maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}"
                                    class="form-control @error('instruction') is-invalid @enderror"
                                    placeholder="{{ $aiDraftIsFacebookPost ? 'Np. zacznij od pytania do nauczycieli, pisz bardziej na luzie' : 'Np. zacznij od pytania do nauczycieli, podkreśl, że nie trzeba umieć grafiki' }}"
                                    @disabled(! $aiDraftAllowed)
                                >{{ old('instruction', $aiDraftProposal['instruction'] ?? '') }}</textarea>
                                @error('instruction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text mb-3">Nie wpisuj danych osobowych, danych klientów ani sekretów. Instrukcja nie zmieni terminu ani prowadzącego.</div>
                                <button type="submit" class="btn btn-outline-primary" @disabled(! $aiDraftAllowed) data-growth-ai-submit>
                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                    Poproś AI o szkic
                                </button>
                            </form>
                        </div>
                    </section>
                @endif

                <section class="card border" aria-labelledby="material-safety-heading">
                    <div class="card-header">
                        <h3 class="h6 mb-0" id="material-safety-heading">Co stanie się po zmianie statusu?</h3>
                    </div>
                    <div class="card-body">
                        <p>{{ $material['why'] }}</p>
                        <ul class="small mb-0">
                            <li>Status i szkic wracają po zalogowaniu.</li>
                            <li>Nic nie trafia do Sendy, YouTube ani Meta.</li>
                            <li>„Opublikowane / zaplanowane” jest wyłącznie etykietą statusu.</li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>

        @if($aiDraftSupported && $aiDraftProposal)
            <section class="card border mt-4" id="material-ai-proposal" aria-labelledby="material-ai-proposal-heading">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="h6 mb-0" id="material-ai-proposal-heading">Propozycja AI</h3>
                    <span class="badge bg-light text-secondary border">
                        {{ ($aiDraftProposal['source'] ?? '') === 'real_ai' ? 'AI: OpenAI / '.($aiDraftProposal['model'] ?? '') : 'AI: symulacja lokalna' }}
                    </span>
                </div>
                <div class="card-body">
                    <p class="small text-secondary mb-3">{{ $aiDraftProposal['note'] ?? '' }}</p>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="small fw-semibold mb-1">Obecny szkic</div>
                            <div class="growth-compare">{{ $material['draft'] }}</div>
                        </div>
                        <div class="col-lg-6">
                            <div class="small fw-semibold mb-1">Propozycja AI</div>
                            <div class="growth-compare growth-compare-proposal">{{ $aiDraftProposal['draft'] }}</div>
                        </div>
                    </div>
                    <p class="small mt-3 mb-0"><span class="fw-semibold">Co zmieniono:</span> {{ $aiDraftProposal['change_summary'] }}</p>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('growth.projects.materials.ai.apply', [$project['id'], $material['id']]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Zastosuj</button>
                    </form>
                    <form method="POST" action="{{ route('growth.projects.materials.ai.reject', [$project['id'], $material['id']]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Odrzuć</button>
                    </form>
                </div>
            </section>
        @endif

        @if($materialVersions->isNotEmpty())
            @php
                $versionSourceLabels = [
                    'baseline' => 'Stan sprzed historii',
                    'manual' => 'Zapis ręczny',
                    'ai_apply' => 'Zastosowany szkic AI',
                    'restore' => 'Przywrócenie',
                ];
                $currentVersion = $materialVersions->first();
                $currentIsLatest = (string) ($currentVersion->payload['draft'] ?? '') === (string) $material['draft'];
                $firstVersionByText = [];
                foreach ($materialVersions->sortBy('version') as $version) {
                    $firstVersionByText[hash('sha256', (string) ($version->payload['draft'] ?? ''))] ??= $version->version;
                }
            @endphp
            <section class="card border mt-4" id="material-versions" aria-labelledby="material-versions-heading">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="h6 mb-0" id="material-versions-heading">Historia wersji</h3>
                    <span class="small text-secondary">Ostatnie {{ \App\Models\GrowthOS\GrowthArtifactVersion::KEEP_LATEST }} zmian treści</span>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($materialVersions as $version)
                        @php
                            $isCurrent = $loop->first && $currentIsLatest;
                            $sameAsVersion = $firstVersionByText[hash('sha256', (string) ($version->payload['draft'] ?? ''))] ?? null;
                        @endphp
                        <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="badge text-bg-secondary me-1">v{{ $version->version }}</span>
                                @if($isCurrent)
                                    <span class="badge text-bg-success me-1">Aktualna</span>
                                @endif
                                <span class="fw-semibold">
                                    {{ $versionSourceLabels[$version->source] ?? $version->source }}{{ $version->source === 'restore' && $version->restored_from_version ? ' z v'.$version->restored_from_version : '' }}
                                </span>
                                @if($sameAsVersion !== null && $sameAsVersion !== $version->version)
                                    <span class="badge bg-light text-secondary border ms-1">ten sam tekst co v{{ $sameAsVersion }}</span>
                                @endif
                                <div class="small text-secondary">
                                    {{ $version->created_at?->format('Y-m-d H:i') }}{{ $version->createdBy ? ' · '.$version->createdBy->name : '' }} · {{ mb_strlen((string) ($version->payload['draft'] ?? '')) }} znaków
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#material-version-{{ $version->version }}">
                                {{ $isCurrent ? 'Podgląd' : 'Podgląd i przywróć' }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>

            @foreach($materialVersions as $version)
                @php $isCurrent = $loop->first && $currentIsLatest; @endphp
                <div class="modal fade" id="material-version-{{ $version->version }}" tabindex="-1" aria-labelledby="material-version-{{ $version->version }}-label" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title h6" id="material-version-{{ $version->version }}-label">Wersja {{ $version->version }} · {{ $version->created_at?->format('Y-m-d H:i') }}</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                            </div>
                            <div class="modal-body growth-readable p-3">
                                <div class="growth-compare">{{ $version->payload['draft'] ?? '' }}</div>
                                @unless($isCurrent)
                                    <p class="small text-secondary mt-3 mb-0">Przywrócenie zapisze ten tekst jako nową wersję ze statusem Draft. Obecny szkic zostaje w historii.</p>
                                @endunless
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
                                @unless($isCurrent)
                                    <form method="POST" action="{{ route('growth.projects.materials.versions.restore', [$project['id'], $material['id'], $version->version]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">Przywróć tę wersję</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        @if($aiDraftSupported)
            <script>
                document.querySelectorAll('[data-growth-ai-form]').forEach((form) => {
                    form.addEventListener('submit', () => {
                        const button = form.querySelector('[data-growth-ai-submit]');
                        button.disabled = true;
                        form.querySelector('[data-growth-ai-spinner]').classList.remove('d-none');
                    });
                });
            </script>
        @endif
    </div>
</x-app-layout>
