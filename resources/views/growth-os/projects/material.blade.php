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

                @if($imageGeneratorEnabled)
                    @php
                        $imageFormats = \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::FORMATS;
                        $imageCanGenerate = $aiDraftAllowed && $imageGeneratorReady;
                    @endphp
                    <section class="card border mt-4" id="material-images" aria-labelledby="material-images-heading">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h3 class="h6 mb-0" id="material-images-heading">Generator obrazu</h3>
                            <span class="badge bg-light text-secondary border">
                                {{ $aiRealEnabled ? 'AI: OpenAI / '.$imageModel.' · '.$imageQuality : 'AI: symulacja lokalna' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="small text-secondary">
                                Obraz powstaje z opisu poniżej. Aplikacja dopisuje stałe zasady: bez napisów (chyba że zaznaczysz nagłówek), bez logotypów i bez rozpoznawalnych osób.
                                Z gotowego obrazu poziomego możesz potem utworzyć wersję kwadratową z tymi samymi elementami.
                                Nic nie jest publikowane.
                            </p>
                            <div class="d-flex flex-wrap align-items-center gap-2 small mb-3">
                                <span class="text-secondary">Dzisiejszy limit: wykorzystano {{ $imageDailyUsed }} z {{ config('growth_ai.images.daily_per_user') }} obrazów.</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#material-image-limit-reset" @disabled($imageDailyUsed === 0)>Zresetuj limit</button>
                            </div>
                            <div class="modal fade" id="material-image-limit-reset" tabindex="-1" aria-labelledby="material-image-limit-reset-title" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title h6" id="material-image-limit-reset-title">Zresetować dzienny limit obrazów?</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                        </div>
                                        <div class="modal-body small">
                                            Licznik ({{ $imageDailyUsed }} z {{ config('growth_ai.images.daily_per_user') }}) wróci do zera i znów będzie można wygenerować {{ config('growth_ai.images.daily_per_user') }} obrazów.
                                            Każdy obraz to płatne wywołanie OpenAI. Galeria i obrazy zostają bez zmian.
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                                            <form method="POST" action="{{ route('growth.projects.materials.images.limit.reset', [$project['id'], $material['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">Zresetuj limit</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(! $aiDraftAllowed)
                                <div class="alert alert-warning small mb-3" role="status">Najpierw zatwierdź kierunek i koncepcję webinaru.</div>
                            @elseif(! $imageGeneratorReady)
                                <div class="alert alert-warning small mb-3" role="status">{{ \App\Http\Controllers\GrowthOS\ProjectController::IMAGE_NOT_PERSISTED_MESSAGE }}</div>
                            @endif

                            <form method="POST" action="{{ route('growth.projects.materials.images.generate', [$project['id'], $material['id']]) }}" data-growth-ai-form>
                                @csrf
                                <fieldset class="mb-3">
                                    <legend class="form-label fs-6">Format</legend>
                                    @foreach($imageFormats as $formatKey => $format)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="format" value="{{ $formatKey }}" id="material_image_format_{{ $formatKey }}" @checked(old('format', 'landscape') === $formatKey) @disabled(! $imageCanGenerate)>
                                            <label class="form-check-label" for="material_image_format_{{ $formatKey }}">{{ $format['label'] }} ({{ $format['width'] }}×{{ $format['height'] }})</label>
                                        </div>
                                    @endforeach
                                </fieldset>

                                <label for="material_image_prompt" class="form-label">Opis obrazu</label>
                                <textarea
                                    id="material_image_prompt"
                                    name="image_prompt"
                                    rows="5"
                                    maxlength="{{ \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::MAX_PROMPT_CHARS }}"
                                    class="form-control @error('image_prompt') is-invalid @enderror"
                                    placeholder="Np. jasne biurko nauczyciela z laptopem i kartami pracy, miękkie światło dzienne, granat i pomarańcz"
                                    @disabled(! $imageCanGenerate)
                                >{{ old('image_prompt', $imageDescription) }}</textarea>
                                @error('image_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text mb-3">
                                    {{ $imageDescription !== '' ? 'Wstępnie wypełnione z zapisanego briefu. Możesz poprawić opis przed generowaniem.' : 'Zapisz brief z sekcją „Opis obrazu dla AI” albo wpisz opis sam.' }}
                                    Nie wpisuj danych osobowych.
                                </div>

                                <input type="hidden" name="include_headline" value="0">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="include_headline" value="1" id="material_image_include_headline" @checked(old('include_headline', '0') === '1') @disabled(! $imageCanGenerate)>
                                    <label class="form-check-label" for="material_image_include_headline">Dodaj nagłówek i termin na obrazie</label>
                                </div>
                                <div class="form-text mb-3">
                                    Nagłówek „{{ $imageHeadline }}”, termin: {{ $aiDraftLiveLabel }}. AI może pomylić polskie znaki lub cyfry — sprawdź napis przed użyciem.
                                </div>

                                <button type="submit" class="btn btn-outline-primary" @disabled(! $imageCanGenerate) data-growth-ai-submit>
                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                    Generuj obraz
                                </button>
                                <span class="small text-secondary ms-2">Zwykle trwa to do 1–2 minut.</span>
                            </form>
                        </div>

                        @if($materialImages->isNotEmpty())
                            <div class="card-footer">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <h4 class="h6 mb-0">Galeria</h4>
                                    <span class="small text-secondary">Ostatnie {{ \App\Models\GrowthOS\GrowthArtifactImage::KEEP_LATEST }} obrazów, wybrany zostaje zawsze</span>
                                </div>
                                <div class="row g-3">
                                    @foreach($materialImages as $image)
                                        @php $imageUrl = route('growth.projects.materials.images.show', [$project['id'], $material['id'], $image->id]); @endphp
                                        <div class="col-md-6">
                                            <div class="card h-100 {{ $image->is_selected ? 'border-success border-2' : 'border' }}">
                                                <a href="{{ $imageUrl }}" target="_blank" rel="noopener">
                                                    <img src="{{ $imageUrl }}" class="card-img-top" loading="lazy" width="{{ $image->width }}" height="{{ $image->height }}" style="height: auto;" alt="Obraz grafiki głównej, {{ $imageFormats[$image->format]['label'] ?? $image->format }}, {{ $image->created_at?->format('Y-m-d H:i') }}">
                                                </a>
                                                <div class="card-body small">
                                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                                        @if($image->is_selected)
                                                            <span class="badge text-bg-success">✓ Grafika główna</span>
                                                        @endif
                                                        @if($image->source === \App\Models\GrowthOS\GrowthArtifactImage::SOURCE_SIMULATION)
                                                            <span class="badge bg-light text-secondary border">Symulacja</span>
                                                        @endif
                                                        @if($image->include_headline)
                                                            <span class="badge bg-light text-secondary border">Z nagłówkiem</span>
                                                        @endif
                                                        @if($image->source_image_id)
                                                            <span class="badge bg-light text-secondary border">Kwadrat z poziomego #{{ $image->source_image_id }}</span>
                                                        @endif
                                                    </div>
                                                    <div>#{{ $image->id }} · {{ $imageFormats[$image->format]['label'] ?? $image->format }} · {{ $image->width }}×{{ $image->height }}</div>
                                                    <div class="text-secondary">{{ $image->created_at?->format('Y-m-d H:i') }}{{ $image->createdBy ? ' · '.$image->createdBy->name : '' }}{{ $image->model ? ' · '.$image->model : '' }}</div>
                                                </div>
                                                <div class="card-footer d-flex flex-wrap gap-2">
                                                    @if($image->format === 'landscape')
                                                        <form method="POST" action="{{ route('growth.projects.materials.images.square', [$project['id'], $material['id'], $image->id]) }}" class="w-100" data-growth-ai-form>
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-primary btn-sm" @disabled(! $imageCanGenerate) data-growth-ai-submit>
                                                                <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                                                Utwórz wersję kwadratową
                                                            </button>
                                                            <span class="d-block form-text">Te same elementy rozmieszczone na nowo w kwadracie, bez przycinania.</span>
                                                        </form>
                                                    @endif
                                                    <a href="{{ $imageUrl }}?download=1" class="btn btn-outline-secondary btn-sm">Pobierz</a>
                                                    @unless($image->is_selected)
                                                        <form method="POST" action="{{ route('growth.projects.materials.images.select', [$project['id'], $material['id'], $image->id]) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-success btn-sm">Wybierz jako grafikę główną</button>
                                                        </form>
                                                    @endunless
                                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#material-image-delete-{{ $image->id }}">Usuń</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </section>

                    @foreach($materialImages as $image)
                        <div class="modal fade" id="material-image-delete-{{ $image->id }}" tabindex="-1" aria-labelledby="material-image-delete-{{ $image->id }}-label" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title h6" id="material-image-delete-{{ $image->id }}-label">Usunąć obraz?</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                    </div>
                                    <div class="modal-body">
                                        Obraz z {{ $image->created_at?->format('Y-m-d H:i') }} zostanie trwale usunięty z galerii i z serwera.{{ $image->is_selected ? ' To jest wybrana grafika główna.' : '' }}
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                                        <form method="POST" action="{{ route('growth.projects.materials.images.delete', [$project['id'], $material['id'], $image->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Usuń obraz</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
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
                                AI przygotowuje wyłącznie propozycję na podstawie zatwierdzonego kierunku i koncepcji{{ $aiDraftUsesYoutubeSource ? ' oraz zatwierdzonego opisu YouTube' : '' }}. Obecny szkic zmienia się dopiero po kliknięciu „Zastosuj”. Nic nie jest publikowane.
                            </p>

                            @if($aiDraftUsesYoutubeSource)
                                <p class="small mb-3">
                                    @if($aiDraftUsesYoutubeDescription)
                                        <span class="badge text-bg-success">✓ Opis YouTube</span> AI użyje zatwierdzonego opisu YouTube jako źródła.
                                    @else
                                        <span class="badge bg-light text-secondary border">Opis YouTube</span> Opis YouTube nie jest zatwierdzony, więc AI go nie dostanie.
                                    @endif
                                </p>
                            @endif

                            @if($aiDraftIsFacebookPost)
                                <p class="small text-secondary mb-3">AI nie poda linku. W jego miejscu wstawi {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::LINK_PLACEHOLDER }} do ręcznej podmiany.</p>
                            @endif

                            @if(! $aiDraftAllowed)
                                <div class="alert alert-warning small mb-3" role="status">Najpierw zatwierdź kierunek i koncepcję webinaru.</div>
                            @endif

                            <form method="POST" action="{{ route('growth.projects.materials.ai', [$project['id'], $material['id']]) }}" data-growth-ai-form>
                                @csrf
                                @if($aiDraftIsGraphic)
                                    <p class="small mb-2">
                                        Brief zawsze ma nagłówek, termin i kierunek wizualny. Formaty: {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::GRAPHIC_FORMATS }}.
                                        Termin wstawia aplikacja: <span class="fw-semibold">{{ $aiDraftLiveLabel }}</span>.
                                    </p>
                                    <fieldset class="mb-3">
                                        <legend class="form-label fs-6">Dodatkowe elementy briefu</legend>
                                        @foreach(\App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::GRAPHIC_OPTIONAL_ELEMENTS as $elementKey => $elementLabel)
                                            <div class="form-check">
                                                <input type="hidden" name="elements[{{ $elementKey }}]" value="0">
                                                <input class="form-check-input" type="checkbox" name="elements[{{ $elementKey }}]" value="1" id="material_ai_element_{{ $elementKey }}" @checked(old('elements.'.$elementKey, '1') === '1') @disabled(! $aiDraftAllowed)>
                                                <label class="form-check-label" for="material_ai_element_{{ $elementKey }}">{{ $elementLabel }}</label>
                                            </div>
                                        @endforeach
                                    </fieldset>
                                @else
                                    <input type="hidden" name="emojis" value="0">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="emojis" value="1" id="material_ai_emojis" @checked(old('emojis', '1') === '1') @disabled(! $aiDraftAllowed)>
                                        <label class="form-check-label" for="material_ai_emojis">{{ $aiDraftIsFacebookPost ? 'Dodaj emotikony do posta' : 'Dodaj emotikony do opisu' }}</label>
                                    </div>
                                @endif
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
                                    placeholder="{{ $aiDraftIsGraphic ? 'Np. kolory granat i pomarańcz, motyw tablicy i laptopa, spokojny styl' : ($aiDraftIsFacebookPost ? 'Np. zacznij od pytania do nauczycieli, pisz bardziej na luzie' : 'Np. zacznij od pytania do nauczycieli, podkreśl, że nie trzeba umieć grafiki') }}"
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
