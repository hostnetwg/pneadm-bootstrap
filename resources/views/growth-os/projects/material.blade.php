<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Materiał
    </x-slot>

    @include('growth-os.partials.readable-styles')

    <div class="container-fluid growth-readable">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @include('growth-os.partials.ai-daily-limit-alert')

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
                        @include('growth-os.partials.ai-origin-badge', [
                            'origin' => \App\Support\GrowthOS\DemoTikWebinarProject::materialAiOrigin($material),
                            'channel' => \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL,
                            'title' => 'Ostatnie AI, które ukształtowało ten materiał',
                        ])
                    </div>
                    <h2 class="h4 mb-2">{{ $material['name'] }}</h2>
                    <p class="text-secondary mb-0">{{ $material['summary'] }}</p>
                </div>
                <a href="{{ route('growth.inbox.index') }}" class="btn btn-outline-primary align-self-start">Inbox</a>
            </div>
        </section>

        @if($materialSkipped)
            <div class="alert alert-secondary" role="status">
                Ten materiał jest wyłączony w tym projekcie (status „Nie dotyczy”). Nie liczy się do następnego kroku ani elementów krytycznych, a szkic, AI i historia wersji są zablokowane.
                Żeby go włączyć, wybierz inny status i kliknij „Zapisz materiał”. Szkic wróci taki, jaki był.
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                <form id="material-save" method="POST" action="{{ route('growth.projects.materials.status', [$project['id'], $material['id']]) }}" class="card border">
                    @csrf
                    <div class="card-header">
                        <h3 class="h6 mb-0" id="material-preview-heading">Szkic</h3>
                    </div>
                    <div class="card-body">
                        @if($mailFields !== null)
                            @if(! empty($mailFieldsFromAiProposal) && ! $materialSkipped)
                                <div class="alert alert-info small" role="status">
                                    W polach poniżej jest <strong>propozycja AI</strong> (jeszcze nie zapisana jako szkic materiału).
                                    Wprowadź poprawki i kliknij <strong>Zapisz materiał</strong>.
                                    „Zastosuj” w panelu AI przywraca oryginalną propozycję bez Twoich zmian z pól.
                                </div>
                            @endif
                            <div class="mb-3">
                                <label for="mail_subject" class="form-label">Temat</label>
                                <input type="text" id="mail_subject" name="mail_subject" maxlength="200" class="form-control @error('mail_subject') is-invalid @enderror" value="{{ old('mail_subject', $mailFields['subject']) }}" @readonly($materialSkipped) data-mail-counter="60">
                                @error('mail_subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text"><span data-mail-counter-for="mail_subject">0</span> znaków, zalecane do 60.</div>
                                @if($mailFields['alternatives'] !== [] && ! $materialSkipped)
                                    <div class="small mt-2">
                                        <div class="fw-semibold mb-1">Propozycje tematu od AI</div>
                                        <ul class="list-unstyled mb-1">
                                            @foreach(array_values(array_unique(array_filter([$mailFields['subject'], ...$mailFields['alternatives']]))) as $option)
                                                <li class="d-flex align-items-center gap-2 mb-1">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-mail-use-subject="{{ $option }}">Użyj</button>
                                                    <span>{{ $option }}</span>
                                                    <span class="badge text-bg-light border d-none" data-mail-subject-current>w polu</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="text-secondary">Po zapisie zostaje tylko temat z pola powyżej.</div>
                                    </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="mail_preheader" class="form-label">Preheader</label>
                                <input type="text" id="mail_preheader" name="mail_preheader" maxlength="200" class="form-control @error('mail_preheader') is-invalid @enderror" value="{{ old('mail_preheader', $mailFields['preheader']) }}" @readonly($materialSkipped) data-mail-counter="100">
                                @error('mail_preheader')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    <span data-mail-counter-for="mail_preheader">0</span> znaków, zalecane 40–100. Skrzynka pokazuje go na liście maili obok tematu. Sendy nie ma na niego pola: skopiuj kod i wklej na samym początku treści w trybie HTML.
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-mail-copy-preheader>Kopiuj kod HTML preheadera</button>
                                    <span class="small text-success d-none" role="status" data-mail-copy-status>Skopiowano. Wklej kod na początku treści maila w trybie HTML.</span>
                                </div>
                                <details class="small mt-2">
                                    <summary>Pokaż kod HTML preheadera</summary>
                                    <label for="mail_preheader_html" class="visually-hidden">Kod HTML preheadera</label>
                                    <textarea id="mail_preheader_html" class="form-control form-control-sm font-monospace mt-2" rows="3" readonly data-mail-preheader-html></textarea>
                                </details>
                            </div>
                            @if($aiDraftIsReminder)
                                <input type="hidden" name="reminder_timing" value="{{ old('reminder_timing', $material['reminder_timing'] ?? \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::REMINDER_DEFAULT_TIMING) }}">
                            @endif
                            @include('growth-os.projects.partials.main-mail-editor', ['isReminderMail' => $aiDraftIsReminder])
                            @error('mail_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @else
                            <label for="draft" class="visually-hidden">Szkic</label>
                            <textarea id="draft" name="draft" class="form-control growth-draft-editor" rows="14" @readonly($materialSkipped)>{{ old('draft', $materialEditorDraft ?? session('material_restored_draft', $material['draft'])) }}</textarea>
                            @if($aiDraftIsFacebookPost)
                                @if(filled($project['registration_url'] ?? null))
                                    <p class="form-text mb-0">Link do zapisów z karty projektu jest wstawiany zamiast {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::LINK_PLACEHOLDER }}.</p>
                                @else
                                    <p class="form-text mb-0 text-warning-emphasis">Brak linku do zapisów na <a href="{{ route('growth.projects.show', $project['id']) }}#project-links">karcie projektu</a> — w szkicu zostaje znacznik {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::LINK_PLACEHOLDER }}.</p>
                                @endif
                            @endif
                        @endif
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
                                Obraz powstaje z opisu poniżej. Model nie rysuje napisów (chyba że zaznaczysz nagłówek) ani logotypów.
                                Logo Platformy i logo sponsora aplikacja dokłada potem z plików, także na wersji kwadratowej.
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

                            @if($materialSkipped)
                                <div class="alert alert-secondary small mb-3" role="status">{{ \App\Http\Controllers\GrowthOS\ProjectController::MATERIAL_SKIPPED_MESSAGE }}</div>
                            @elseif(! $aiDraftAllowed)
                                <div class="alert alert-warning small mb-3" role="status">Najpierw zatwierdź kierunek i koncepcję webinaru.</div>
                            @elseif(! $imageGeneratorReady)
                                <div class="alert alert-warning small mb-3" role="status">{{ \App\Http\Controllers\GrowthOS\ProjectController::IMAGE_NOT_PERSISTED_MESSAGE }}</div>
                            @endif

                            <div class="border rounded p-3 mb-3">
                                <h4 class="h6">Logo na grafice</h4>
                                <p class="small text-secondary">Prawdziwe pliki są kładzione w dolnych rogach gotowego obrazu. Model ich nie przerysowuje.</p>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6">
                                        <div class="small fw-semibold mb-1">Logo Platformy</div>
                                        @if($pneLogoAvailable)
                                            <img src="{{ asset(\App\Support\GrowthOS\GraphicLogoStore::PNE_PUBLIC) }}" alt="Logo Platformy Nowoczesnej Edukacji" class="border rounded mb-2" style="background:#1e293b;max-width:180px;height:auto;padding:.5rem" width="180">
                                        @else
                                            <p class="small text-secondary mb-0">Brak pliku logo Platformy.</p>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small fw-semibold mb-1">Logo sponsora tego webinaru</div>
                                        @if($sponsorLogoReady)
                                            <img src="{{ route('growth.projects.materials.sponsor-logo.show', [$project['id'], $material['id']]) }}" alt="Logo sponsora" class="border rounded mb-2" style="background:#f8f9fa;max-width:180px;height:auto;padding:.5rem" width="180">
                                            <div>
                                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#sponsor-logo-delete">Usuń logo sponsora</button>
                                            </div>
                                        @elseif($imageGeneratorReady)
                                            <form method="POST" action="{{ route('growth.projects.materials.sponsor-logo.store', [$project['id'], $material['id']]) }}" enctype="multipart/form-data">
                                                @csrf
                                                <label for="sponsor_logo" class="form-label small">PNG, JPG albo WebP, do 2 MB</label>
                                                <input id="sponsor_logo" name="sponsor_logo" type="file" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error('sponsor_logo') is-invalid @enderror" required>
                                                @error('sponsor_logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                <button type="submit" class="btn btn-outline-primary btn-sm mt-2">Wgraj logo sponsora</button>
                                            </form>
                                        @else
                                            <p class="small text-secondary mb-0">Logo sponsora można wgrać, gdy projekt jest zapisany.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($sponsorLogoReady)
                                <div class="modal fade" id="sponsor-logo-delete" tabindex="-1" aria-labelledby="sponsor-logo-delete-title" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h4 class="modal-title h6" id="sponsor-logo-delete-title">Usunąć logo sponsora?</h4>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                            </div>
                                            <div class="modal-body small">
                                                Kolejne obrazy nie dostaną tego logo. Obrazy już wygenerowane zostają bez zmian.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                                                <form method="POST" action="{{ route('growth.projects.materials.sponsor-logo.delete', [$project['id'], $material['id']]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Usuń logo</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                    rows="8"
                                    maxlength="{{ \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::MAX_PROMPT_CHARS }}"
                                    class="form-control @error('image_prompt') is-invalid @enderror"
                                    placeholder="Np. jasne biurko nauczyciela z laptopem i kartami pracy, miękkie światło dzienne, granat i pomarańcz"
                                    @disabled(! $imageCanGenerate)
                                >{{ old('image_prompt', $imageDescription) }}</textarea>
                                @error('image_prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text mb-3">
                                    Do {{ number_format((int) \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::MAX_PROMPT_CHARS, 0, ',', ' ') }} znaków.
                                    {{ $imageDescription !== '' ? 'Wstępnie wypełnione z zapisanego briefu. Możesz poprawić opis przed generowaniem.' : 'Zapisz brief z sekcją „Opis obrazu dla AI” albo wpisz opis sam.' }}
                                    Nie wpisuj danych osobowych.
                                </div>

                                <input type="hidden" name="include_headline" value="0">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="include_headline" value="1" id="material_image_include_headline" @checked(old('include_headline', '0') === '1') @disabled(! $imageCanGenerate)>
                                    <label class="form-check-label" for="material_image_include_headline">Dodaj nagłówek i termin na obrazie</label>
                                </div>
                                <div class="form-text mb-3">
                                    Temat webinaru: <span class="fw-semibold">{{ $project['topic'] }}</span>.
                                    Nagłówek z briefu: „{{ $imageHeadline }}”, termin: {{ $aiDraftLiveLabel }}.
                                    AI może pomylić polskie znaki lub cyfry — sprawdź napis przed użyciem.
                                </div>

                                <input type="hidden" name="include_pne_logo" value="0">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="include_pne_logo" value="1" id="material_image_include_pne_logo" @checked(old('include_pne_logo', $pneLogoAvailable ? '1' : '0') === '1') @disabled(! $imageCanGenerate || ! $pneLogoAvailable)>
                                    <label class="form-check-label" for="material_image_include_pne_logo">Nałóż logo Platformy</label>
                                </div>
                                <input type="hidden" name="include_sponsor_logo" value="0">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="include_sponsor_logo" value="1" id="material_image_include_sponsor_logo" @checked(old('include_sponsor_logo', $sponsorLogoReady ? '1' : '0') === '1') @disabled(! $imageCanGenerate || ! $sponsorLogoReady)>
                                    <label class="form-check-label" for="material_image_include_sponsor_logo">Nałóż logo sponsora</label>
                                </div>

                                <button type="submit" class="btn btn-outline-primary" @disabled(! $imageCanGenerate) data-growth-ai-submit>
                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                    Generuj obraz
                                </button>
                                <span class="small text-secondary ms-2">Zwykle trwa to do 1–2 minut.</span>
                            </form>

                            <div class="border rounded p-3 mt-4" id="image-description-ai">
                                <h4 class="h6">Popraw opis obrazu</h4>
                                <p class="small text-secondary">
                                    Osobno od briefu. AI widzi temat, kierunek i koncepcję.
                                    „Nowy opis” pisze od zera. „Popraw ten opis” redaguje tekst z pola powyżej, także niezapisany.
                                    Opis w polu i w briefie zmienia się dopiero po „Zastosuj”. Nagłówek briefu zostaje.
                                </p>
                                <form method="POST" action="{{ route('growth.projects.materials.images.description.ai', [$project['id'], $material['id']]) }}" data-growth-ai-form>
                                    @csrf
                                    @include('growth-os.partials.ai-execution-controls', [
                                        'aiChannel' => \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL,
                                        'aiProposal' => $imageDescriptionProposal,
                                        'aiControlId' => 'image-description-ai-exec',
                                        'aiDisabled' => ! $imageCanGenerate,
                                    ])
                                    <input type="hidden" name="mode" value="generate" data-growth-ai-mode-input>
                                    <input type="hidden" name="author_draft" value="" data-growth-ai-author-draft data-growth-ai-author-source="material_image_prompt">
                                    <label for="image_description_instruction" class="form-label small">Dodatkowa instrukcja (opcjonalnie)</label>
                                    <textarea id="image_description_instruction" name="description_instruction" rows="2" maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}" class="form-control form-control-sm mb-2" placeholder="Np. nauczyciel siedzi przodem do uczniów, za nim tablica, uczniowie nie siedzą za jego plecami" @disabled(! $imageCanGenerate)>{{ old('description_instruction') }}</textarea>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" @disabled(! $imageCanGenerate) data-growth-ai-submit data-growth-ai-mode="generate">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                            Nowy opis
                                        </button>
                                        <button type="submit" class="btn btn-outline-primary btn-sm" @disabled(! $imageCanGenerate) data-growth-ai-submit data-growth-ai-mode="refine">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                            Popraw ten opis
                                        </button>
                                    </div>
                                </form>
                                @if($imageDescriptionProposal)
                                    @php
                                        $descriptionMode = $imageDescriptionProposal['mode'] ?? null;
                                        $descriptionCompare = match ($descriptionMode) {
                                            'refine' => 'Twój opis (wysłany do AI)',
                                            'iterate' => 'Poprzednia propozycja',
                                            default => 'Opis z briefu',
                                        };
                                    @endphp
                                    <div class="border rounded p-3 mt-3 bg-light">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                            <div class="small fw-semibold">Propozycja opisu</div>
                                            <span class="badge bg-white text-secondary border">
                                                @if(($imageDescriptionProposal['source'] ?? '') === 'real_ai')
                                                    Wygenerowano: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal($imageDescriptionProposal, \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL) }}
                                                @else
                                                    AI: symulacja lokalna
                                                @endif
                                            </span>
                                        </div>
                                        <p class="small text-secondary mb-2">
                                            {{ $imageDescriptionProposal['note'] ?? '' }}
                                            @if(($imageDescriptionProposal['iteration_count'] ?? 0) > 0)
                                                <span class="badge bg-white text-secondary border">Poprawka nr {{ $imageDescriptionProposal['iteration_count'] }}</span>
                                            @endif
                                        </p>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="small fw-semibold mb-1">{{ $descriptionCompare }}</div>
                                                <div class="growth-compare">{{ $imageDescriptionProposal['compare_description'] ?? '' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="small fw-semibold mb-1">Propozycja AI</div>
                                                <div class="growth-compare growth-compare-proposal">{{ $imageDescriptionProposal['description'] ?? '' }}</div>
                                            </div>
                                        </div>
                                        <p class="small mt-2 mb-0 growth-ai-text"><span class="fw-semibold">Co zmieniono:</span> {{ $imageDescriptionProposal['change_summary'] ?? '' }}</p>
                                        @unless($materialSkipped)
                                            <form method="POST" action="{{ route('growth.projects.materials.images.description.ai', [$project['id'], $material['id']]) }}" class="mt-3" data-growth-ai-form>
                                                @csrf
                                                <input type="hidden" name="mode" value="iterate">
                                                <label for="image_description_iterate" class="form-label small fw-semibold">Co jeszcze poprawić w opisie?</label>
                                                <textarea id="image_description_iterate" name="description_instruction" rows="2" maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}" class="form-control form-control-sm" placeholder="Np. zostaw biurko, zmień tylko układ klasy" @disabled(! $imageCanGenerate)></textarea>
                                                <button type="submit" class="btn btn-outline-primary btn-sm mt-2" @disabled(! $imageCanGenerate) data-growth-ai-submit>
                                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                                    Popraw ponownie
                                                </button>
                                            </form>
                                        @endunless
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            @unless($materialSkipped)
                                                <form method="POST" action="{{ route('growth.projects.materials.images.description.ai.apply', [$project['id'], $material['id']]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm">Zastosuj opis</button>
                                                </form>
                                            @endunless
                                            <form method="POST" action="{{ route('growth.projects.materials.images.description.ai.reject', [$project['id'], $material['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Odrzuć</button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </div>
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
                                                        @if($image->include_pne_logo || $image->include_sponsor_logo)
                                                            <span class="badge bg-light text-secondary border">Z logo</span>
                                                        @endif
                                                        @if($image->prompt_version === \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::REVISE_PROMPT_VERSION)
                                                            <span class="badge bg-light text-secondary border">Poprawka obrazu #{{ $image->source_image_id }}</span>
                                                        @elseif($image->source_image_id)
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
                                                            <span class="d-block form-text">Te same elementy rozmieszczone na nowo w kwadracie. Logo jest dokładane dopiero potem, z plików.</span>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('growth.projects.materials.images.revise', [$project['id'], $material['id'], $image->id]) }}" class="w-100" data-growth-ai-form>
                                                        @csrf
                                                        <label for="image_revise_{{ $image->id }}" class="form-label small fw-semibold mb-1">Co poprawić na tym obrazie?</label>
                                                        <textarea id="image_revise_{{ $image->id }}" name="instruction" rows="2" maxlength="{{ \App\Services\GrowthOS\AI\Tasks\GraphicImageTask::MAX_REVISE_CHARS }}" class="form-control form-control-sm" placeholder="Np. nauczyciel siedzi przodem do uczniów, za nim tablica, uczniowie nie siedzą za jego plecami" @disabled(! $imageCanGenerate)></textarea>
                                                        <button type="submit" class="btn btn-outline-primary btn-sm mt-2" @disabled(! $imageCanGenerate) data-growth-ai-submit>
                                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                                            Popraw ten obraz
                                                        </button>
                                                        <span class="d-block form-text">Jedna zmiana. Reszta zdjęcia zostaje. Logo jest dokładane dopiero potem. Poprzedni obraz zostaje w galerii.</span>
                                                    </form>
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
                                {{ $aiRealEnabled ? 'AI: '.$aiGeneralLabel : 'AI: symulacja lokalna' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="small text-secondary">
                                AI przygotowuje wyłącznie propozycję na podstawie zatwierdzonego kierunku i koncepcji{{ $aiDraftUsesMainMailSource ? ' oraz zatwierdzonego opisu YouTube i mailingu głównego' : ($aiDraftUsesYoutubeSource ? ' oraz zatwierdzonego opisu YouTube' : '') }}. Obecny szkic zmienia się dopiero po kliknięciu „Zastosuj”. Nic nie jest publikowane.
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

                            @if($aiDraftUsesMainMailSource)
                                <p class="small mb-3">
                                    @if($aiDraftUsesMainMail)
                                        <span class="badge text-bg-success">✓ Mailing główny</span> AI użyje zatwierdzonego mailingu głównego, żeby przypomnienie go nie powtarzało.
                                    @else
                                        <span class="badge bg-light text-secondary border">Mailing główny</span> Mailing główny nie jest zatwierdzony, więc AI go nie dostanie.
                                    @endif
                                </p>
                            @endif

                            @if($aiDraftIsMail)
                                <p class="small text-secondary mb-3">
                                    AI zwraca 3 propozycje tematu (pierwsza jest główna), preheader i treść redakcyjną. Forma zwrotu z ustawienia projektu ({{ \App\Services\GrowthOS\AI\Support\AddressFormPolicy::label($project['address_form'] ?? null) }}), podpis prowadzącego i „Zespół PNE”.
                                    Możesz ją wyjątkowo nadpisać w dodatkowej instrukcji (np. „napisz na Państwo”).
                                    @if($aiDraftIsReminder)
                                        Greeting Sendy i przyciski zapisu / YouTube dodaje aplikacja — nie zaczynaj body od „Dzień dobry,” ani nie wstawiaj linków.
                                        Tematy w stylu „Widzimy się o 20!” z krótkim opisem webinaru. Termin: <span class="fw-semibold">{{ $aiDraftLiveLabel }}</span>.
                                        „Poproś AI o nowy szkic” pisze od zera. „Popraw mój szkic” redaguje temat, preheader i treść z pól powyżej, także niezapisane.
                                    @else
                                        Zwrot „Dzień dobry,” nie wstawiaj do body — greeting dodaje aplikacja. Termin: <span class="fw-semibold">{{ $aiDraftLiveLabel }}</span>.
                                        „Poproś AI o nowy szkic” pisze od zera. „Popraw mój szkic” redaguje temat, preheader i treść z pól powyżej, także niezapisane.
                                    @endif
                                    Stopkę prawną i wypisanie dodaje system mailingowy.
                                </p>
                            @endif

                            @if($aiDraftIsFacebookPost)
                                <p class="small text-secondary mb-3">
                                    AI nie poda adresu URL — wstawi {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::LINK_PLACEHOLDER }}.
                                    Aplikacja podmieni go na link do zapisów z karty projektu (gdy jest uzupełniony).
                                </p>
                            @endif
                            @if($aiDraftIsFacebookPost)
                                <p class="small text-secondary mb-3">„Poproś AI o nowy szkic” pisze od zera. „Popraw mój szkic” redaguje tekst z pola powyżej, także niezapisany.</p>
                            @endif

                            @if($aiDraftUsesVoice && $aiDraftVoice !== null)
                                <p class="small mb-1" data-ai-voice>
                                    <span class="text-secondary">Prowadzący:</span> <span class="fw-semibold">{{ $project['host'] }}</span>
                                    · <span class="text-secondary">Głos komunikacji:</span>
                                    <span class="fw-semibold">{{ $aiDraftVoice['name'] !== '' ? $aiDraftVoice['name'] : 'PNE — neutralnie' }}</span>
                                    · <a href="{{ route('growth.projects.show', $project['id']) }}#project-host">zmień</a>
                                </p>
                                @if($aiDraftVoice['status'] === \App\Support\GrowthOS\GrowthPeople::VOICE_NO_PROFILE)
                                    <p class="small text-warning-emphasis mb-3">Ten instruktor nie ma jeszcze indywidualnego profilu komunikacji. AI użyje głosu PNE.</p>
                                @elseif($aiDraftVoice['status'] === \App\Support\GrowthOS\GrowthPeople::VOICE_UNAVAILABLE)
                                    <p class="small text-warning-emphasis mb-3">Instruktor wybrany jako głos jest nieaktywny albo usunięty. AI użyje głosu PNE.</p>
                                @else
                                    <p class="small text-secondary mb-3">„Poproś AI o nowy szkic” pisze od zera. „Popraw mój szkic” redaguje tekst z pola powyżej (także niezapisany), zachowując Twój styl.</p>
                                @endif
                            @endif

                            @if($materialSkipped)
                                <div class="alert alert-secondary small mb-3" role="status">{{ \App\Http\Controllers\GrowthOS\ProjectController::MATERIAL_SKIPPED_MESSAGE }}</div>
                            @elseif(! $aiDraftAllowed)
                                <div class="alert alert-warning small mb-3" role="status">Najpierw zatwierdź kierunek i koncepcję webinaru.</div>
                            @endif

                            <form method="POST" action="{{ route('growth.projects.materials.ai', [$project['id'], $material['id']]) }}" data-growth-ai-form>
                                @csrf
                                @include('growth-os.partials.ai-execution-controls', [
                                    'aiChannel' => \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL,
                                    'aiProposal' => $aiDraftProposal,
                                    'aiControlId' => 'material-ai-exec',
                                    'aiDisabled' => $materialSkipped || ! $aiDraftAllowed,
                                ])
                                @if($aiDraftIsGraphic)
                                    <p class="small mb-2">
                                        Brief zawsze ma nagłówek, termin i kierunek wizualny. Formaty: {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::GRAPHIC_FORMATS }}.
                                        Termin wstawia aplikacja: <span class="fw-semibold">{{ $aiDraftLiveLabel }}</span>.
                                        Nagłówek bierze się z tematu webinaru. „Nowy brief” pisze od zera. „Popraw mój brief” redaguje tekst szkicu, także niezapisany.
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
                                @elseif($aiDraftIsHostScript)
                                    @php
                                        $durationChoice = (string) old('duration', \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_DEFAULT_DURATION);
                                    @endphp
                                    <p class="small mb-2">
                                        Scenariusz ma checklistę przed startem, bloki z godzinami od <span class="fw-semibold">{{ $project['live_time'] ?? '' }}</span>, pytania na czat, pytania i odpowiedzi oraz zakończenie z CTA.
                                        „Poproś AI o nowy szkic” pisze od zera (bez obecnego scenariusza). „Popraw mój szkic” redaguje tekst z pola powyżej, także niezapisany.
                                    </p>
                                    <fieldset class="mb-3">
                                        <legend class="form-label fs-6">Czas trwania webinaru</legend>
                                        @foreach(\App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_DURATIONS as $minutes)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="duration" value="{{ $minutes }}" id="material_ai_duration_{{ $minutes }}" @checked($durationChoice === (string) $minutes) @disabled(! $aiDraftAllowed)>
                                                <label class="form-check-label" for="material_ai_duration_{{ $minutes }}">{{ $minutes }} minut</label>
                                            </div>
                                        @endforeach
                                        <div class="form-check d-flex flex-wrap align-items-center gap-2">
                                            <input class="form-check-input" type="radio" name="duration" value="custom" id="material_ai_duration_custom" @checked($durationChoice === 'custom') @disabled(! $aiDraftAllowed)>
                                            <label class="form-check-label" for="material_ai_duration_custom">Inny:</label>
                                            <input type="number" name="duration_custom" id="material_ai_duration_custom_minutes" class="form-control form-control-sm w-auto @error('duration_custom') is-invalid @enderror" min="{{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_MIN_DURATION }}" max="{{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_MAX_DURATION }}" step="1" value="{{ old('duration_custom') }}" aria-label="Własny czas trwania w minutach" data-duration-custom @disabled(! $aiDraftAllowed)>
                                            <span class="small">minut</span>
                                            @error('duration_custom')<div class="invalid-feedback d-block w-100">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-text">Własny czas: od {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_MIN_DURATION }} do {{ \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::HOST_SCRIPT_MAX_DURATION }} minut.</div>
                                    </fieldset>
                                @else
                                    @if($aiDraftIsReminder)
                                        <fieldset class="mb-3">
                                            <legend class="form-label fs-6">Kiedy wysyłasz przypomnienie</legend>
                                            @foreach(\App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::REMINDER_TIMINGS as $timingKey => $timingLabel)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="timing" value="{{ $timingKey }}" id="material_ai_timing_{{ $timingKey }}" @checked(old('timing', \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::REMINDER_DEFAULT_TIMING) === $timingKey) @disabled(! $aiDraftAllowed)>
                                                    <label class="form-check-label" for="material_ai_timing_{{ $timingKey }}">{{ $timingLabel }}</label>
                                                </div>
                                            @endforeach
                                        </fieldset>
                                    @endif
                                    @if($aiDraftIsMail && ! $aiDraftIsReminder)
                                        <input type="hidden" name="html" value="0">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="html" value="1" id="material_ai_html" @checked(old('html', '1') === '1') @disabled(! $aiDraftAllowed)>
                                            <label class="form-check-label" for="material_ai_html">Profesjonalny HTML maila</label>
                                            <div class="form-text">Treść powstanie jako HTML do wklejenia w Sendy: akapity, lista i przycisk zapisu. Temat i preheader zostają zwykłym tekstem.</div>
                                        </div>
                                    @endif
                                    @if($aiDraftIsMail)
                                        <fieldset class="mb-3">
                                            <legend class="form-label fs-6">Długość maila</legend>
                                            @foreach($aiDraftMailLengths as $lengthKey => $lengthLabel)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="length" value="{{ $lengthKey }}" id="material_ai_length_{{ $lengthKey }}" @checked(old('length', \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::MAIL_DEFAULT_LENGTH) === $lengthKey) @disabled(! $aiDraftAllowed)>
                                                    <label class="form-check-label" for="material_ai_length_{{ $lengthKey }}">{{ $lengthLabel }}</label>
                                                </div>
                                            @endforeach
                                        </fieldset>
                                    @endif
                                    <div class="form-check mb-3">
                                        <input type="hidden" name="emojis" value="0">
                                        <input class="form-check-input" type="checkbox" name="emojis" value="1" id="material_ai_emojis" @checked(old('emojis', $aiDraftIsMail || $aiDraftUsesVoice ? '0' : '1') === '1') @disabled(! $aiDraftAllowed)>
                                        <label class="form-check-label" for="material_ai_emojis">{{ $aiDraftIsMail ? 'Dodaj emotikony do treści maila' : ($aiDraftIsFacebookPost ? 'Dodaj emotikony do posta' : 'Dodaj emotikony do opisu') }}</label>
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
                                    rows="8"
                                    maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}"
                                    class="form-control @error('instruction') is-invalid @enderror"
                                    placeholder="{{ $aiDraftIsHostScript ? 'Np. dodaj krótki pokaz na żywo w drugim bloku, mniej teorii' : ($aiDraftIsReminder ? 'Np. dodaj, że warto przygotować konto Canva przed spotkaniem' : ($aiDraftIsMail ? 'Np. podkreśl, że webinar jest dla początkujących, dodaj zdanie o nagraniu' : ($aiDraftIsGraphic ? 'Np. kolory granat i pomarańcz, motyw tablicy i laptopa, spokojny styl' : ($aiDraftIsFacebookPost ? 'Np. zacznij od pytania do nauczycieli, pisz bardziej na luzie' : 'Np. zacznij od pytania do nauczycieli, podkreśl, że nie trzeba umieć grafiki')))) }}"
                                    @disabled(! $aiDraftAllowed)
                                >{{ old('instruction', $aiDraftProposal['instruction'] ?? '') }}</textarea>
                                @error('instruction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text mb-3">Do {{ number_format((int) config('growth_ai.limits.max_instruction_chars'), 0, ',', ' ') }} znaków. Nie wpisuj danych osobowych, danych klientów ani sekretów. Instrukcja nie zmieni terminu ani prowadzącego.</div>
                                @if($aiDraftUsesWorkModes)
                                    <input type="hidden" name="mode" value="generate" data-growth-ai-mode-input>
                                    <input type="hidden" name="author_draft" value="" data-growth-ai-author-draft @if($aiDraftIsMail) data-growth-ai-author-source="mail" @endif>
                                    <div class="d-flex flex-wrap align-items-center gap-2" @if($aiDraftIsHostScript) data-host-script-ai-actions @endif>
                                        <button type="submit" class="btn btn-outline-primary" @disabled(! $aiDraftAllowed) data-growth-ai-submit data-growth-ai-mode="generate">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                            {{ $aiDraftIsGraphic ? 'Poproś AI o nowy brief' : 'Poproś AI o nowy szkic' }}
                                        </button>
                                        <button type="submit" class="btn btn-outline-primary" @disabled(! $aiDraftAllowed) data-growth-ai-submit data-growth-ai-mode="refine">
                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                            {{ $aiDraftIsGraphic ? 'Popraw mój brief' : 'Popraw mój szkic' }}
                                        </button>
                                        @if($aiDraftIsHostScript)
                                            <a
                                                href="{{ route('growth.projects.materials.ai.chatgpt-pdf', [$project['id'], $material['id'], 'mode' => 'bundle']) }}"
                                                class="btn btn-outline-secondary"
                                                title="PDF: prompt aplikacji + dane projektu — wgraj na ChatGPT.com"
                                                aria-label="Pobierz PDF z promptem i danymi projektu do ChatGPT"
                                                data-chatgpt-pdf="bundle"
                                                @if(! $aiDraftAllowed) tabindex="-1" aria-disabled="true" @endif
                                            >
                                                <i class="bi bi-file-earmark-richtext" aria-hidden="true"></i>
                                            </a>
                                            <a
                                                href="{{ route('growth.projects.materials.ai.chatgpt-pdf', [$project['id'], $material['id'], 'mode' => 'data']) }}"
                                                class="btn btn-outline-secondary"
                                                title="PDF: tylko dane projektu — własny prompt na ChatGPT.com"
                                                aria-label="Pobierz PDF z danymi projektu do ChatGPT"
                                                data-chatgpt-pdf="data"
                                                @if(! $aiDraftAllowed) tabindex="-1" aria-disabled="true" @endif
                                            >
                                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if($aiDraftIsHostScript)
                                        <p class="form-text mb-0 mt-2">
                                            Ikony PDF: pełny pakiet (prompt + dane) albo same dane projektu — do pracy na ChatGPT.com w ramach abonamentu, bez API.
                                        </p>
                                    @endif
                                @else
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <button type="submit" class="btn btn-outline-primary" @disabled(! $aiDraftAllowed) data-growth-ai-submit>
                                            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                            Poproś AI o szkic
                                        </button>
                                    </div>
                                @endif
                            </form>
                            @if($aiDraftIsHostScript)
                                <script>
                                    (() => {
                                        const form = document.querySelector('[data-growth-ai-form]');
                                        if (!form || form.dataset.chatgptPdfBound === '1') return;
                                        form.dataset.chatgptPdfBound = '1';
                                        const syncPdfLinks = () => {
                                            const duration = form.querySelector('input[name="duration"]:checked')?.value || '60';
                                            const custom = form.querySelector('[data-duration-custom]')?.value || '';
                                            const instruction = form.querySelector('#material_ai_instruction')?.value || '';
                                            form.querySelectorAll('[data-chatgpt-pdf]').forEach((link) => {
                                                const url = new URL(link.getAttribute('data-chatgpt-pdf-base') || link.href, window.location.origin);
                                                url.searchParams.set('mode', link.getAttribute('data-chatgpt-pdf') || 'data');
                                                url.searchParams.set('duration', duration);
                                                if (duration === 'custom' && custom !== '') {
                                                    url.searchParams.set('duration_custom', custom);
                                                } else {
                                                    url.searchParams.delete('duration_custom');
                                                }
                                                if (instruction.trim() !== '') {
                                                    url.searchParams.set('instruction', instruction);
                                                } else {
                                                    url.searchParams.delete('instruction');
                                                }
                                                link.href = url.pathname + '?' + url.searchParams.toString();
                                            });
                                        };
                                        form.querySelectorAll('[data-chatgpt-pdf]').forEach((link) => {
                                            link.setAttribute('data-chatgpt-pdf-base', link.href.split('?')[0]);
                                            if (link.getAttribute('aria-disabled') === 'true') {
                                                link.classList.add('disabled');
                                                link.addEventListener('click', (e) => e.preventDefault());
                                            }
                                        });
                                        form.addEventListener('change', syncPdfLinks);
                                        form.addEventListener('input', syncPdfLinks);
                                        syncPdfLinks();
                                    })();
                                </script>
                            @endif
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
                        @if(($aiDraftProposal['source'] ?? '') === 'real_ai')
                            Wygenerowano: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::badgeForProposal($aiDraftProposal, \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL) }}
                        @else
                            AI: symulacja lokalna
                            @if(! empty($aiDraftProposal['model']) && ! empty($aiDraftProposal['reasoning_effort']))
                                (wybór: {{ \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::compactLabel((string) $aiDraftProposal['model'], (string) $aiDraftProposal['reasoning_effort']) }})
                            @endif
                        @endif
                    </span>
                </div>
                <div class="card-body">
                    @php
                        $proposalMode = $aiDraftProposal['mode'] ?? null;
                        $compareLabel = match ($proposalMode) {
                            'refine' => 'Twój szkic (wysłany do AI)',
                            'iterate' => 'Poprzednia propozycja',
                            default => 'Obecny szkic',
                        };
                    @endphp
                    <p class="small text-secondary mb-3">
                        {{ $aiDraftProposal['note'] ?? '' }}
                        @if(($aiDraftProposal['iteration_count'] ?? 0) > 0)
                            <span class="badge bg-light text-secondary border">Poprawka nr {{ $aiDraftProposal['iteration_count'] }}</span>
                        @endif
                    </p>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="small fw-semibold mb-1">{{ $compareLabel }}</div>
                            <div class="growth-compare">{{ $proposalMode !== null ? ($aiDraftProposal['compare_draft'] ?? '') : $material['draft'] }}</div>
                        </div>
                        <div class="col-lg-6">
                            <div class="small fw-semibold mb-1">Propozycja AI</div>
                            @php
                                $proposalMail = $aiDraftIsMail ? \App\Services\GrowthOS\AI\Tasks\MaterialDraftTask::parseMainMail((string) $aiDraftProposal['draft']) : null;
                                $proposalHtml = null;
                                if (is_array($proposalMail) && ! $aiDraftIsReminder && (($aiDraftProposal['mail_html'] ?? false) || str_contains($proposalMail['body'], \App\Support\GrowthOS\MailHtmlFormatter::MARKER))) {
                                    $proposalHtml = str_contains($proposalMail['body'], \App\Support\GrowthOS\MailHtmlFormatter::MARKER)
                                        ? $proposalMail['body']
                                        : \App\Support\GrowthOS\MailHtmlFormatter::format(
                                            $proposalMail['body'],
                                            \App\Support\GrowthOS\MailTemplates::CANONICAL,
                                            \App\Support\GrowthOS\MailRenderContext::fromProject($project, $material, false),
                                        );
                                }
                            @endphp
                            @if(is_string($proposalHtml))
                                <iframe class="w-100 border rounded bg-light" style="height:28rem;" sandbox title="Podgląd propozycji HTML" srcdoc="{{ $proposalHtml }}"></iframe>
                            @else
                                <div class="growth-compare growth-compare-proposal">{{ $aiDraftProposal['draft'] }}</div>
                            @endif
                        </div>
                    </div>
                    <p class="small mt-3 mb-0 growth-ai-text"><span class="fw-semibold">Co zmieniono:</span> {{ $aiDraftProposal['change_summary'] }}</p>
                    @include('growth-os.partials.ai-web-search-feedback', ['proposal' => $aiDraftProposal])
                    @if($aiDraftUsesWorkModes && ! $materialSkipped)
                        <form method="POST" action="{{ route('growth.projects.materials.ai', [$project['id'], $material['id']]) }}" class="mt-3" data-growth-ai-form>
                            @csrf
                            <input type="hidden" name="mode" value="iterate">
                            @include('growth-os.partials.ai-execution-controls', [
                                'aiChannel' => \App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog::CHANNEL_GENERAL,
                                'aiProposal' => $aiDraftProposal,
                                'aiControlId' => 'material-ai-iterate-exec',
                                'aiDisabled' => ! $aiDraftAllowed,
                            ])
                            <label for="material_ai_iterate_instruction" class="form-label small fw-semibold">Co jeszcze poprawić?</label>
                            <textarea id="material_ai_iterate_instruction" name="instruction" rows="2" maxlength="{{ config('growth_ai.limits.max_instruction_chars') }}" class="form-control form-control-sm" placeholder="{{ $aiDraftIsGraphic ? 'Np. zmień tylko nagłówek na pełny temat; zostaw opis obrazu' : 'Np. popraw tylko CTA; zostaw pierwszy akapit bez zmian; popraw tylko literówki' }}" @disabled(! $aiDraftAllowed)></textarea>
                            <button type="submit" class="btn btn-outline-primary btn-sm mt-2" @disabled(! $aiDraftAllowed) data-growth-ai-submit>
                                <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" data-growth-ai-spinner></span>
                                Popraw ponownie
                            </button>
                        </form>
                    @endif
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    @unless($materialSkipped)
                        <form method="POST" action="{{ route('growth.projects.materials.ai.apply', [$project['id'], $material['id']]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ $aiDraftIsMail ? 'Zastosuj oryginalną propozycję' : 'Zastosuj' }}</button>
                        </form>
                    @endunless
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
                                @unless($isCurrent || $materialSkipped)
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
                const mailAuthorDraft = () => {
                    window.growthSyncMailBody?.();
                    const subject = document.getElementById('mail_subject')?.value.trim() || '';
                    const preheader = document.getElementById('mail_preheader')?.value.trim() || '';
                    const body = document.getElementById('mail_body')?.value.trim() || '';
                    const header = [
                        subject ? 'Temat: ' + subject : '',
                        preheader ? 'Preheader: ' + preheader : '',
                    ].filter(Boolean).join('\n');
                    if (header === '') {
                        return body;
                    }

                    return body === '' ? header : header + '\n\n' + body;
                };
                document.querySelectorAll('[data-duration-custom]').forEach((input) => {
                    input.addEventListener('input', () => {
                        document.getElementById('material_ai_duration_custom').checked = true;
                    });
                });
                document.querySelectorAll('[data-growth-ai-form]').forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        const modeInput = form.querySelector('[data-growth-ai-mode-input]');
                        if (modeInput) {
                            modeInput.value = event.submitter?.dataset.growthAiMode || 'generate';
                            const authorDraft = form.querySelector('[data-growth-ai-author-draft]');
                            const sourceId = authorDraft?.dataset.growthAiAuthorSource || 'draft';
                            authorDraft.value = modeInput.value === 'refine'
                                ? (sourceId === 'mail' ? mailAuthorDraft() : (document.getElementById(sourceId)?.value || ''))
                                : '';
                        }
                        form.querySelectorAll('[data-growth-ai-submit]').forEach((button) => {
                            button.disabled = true;
                        });
                        (event.submitter?.querySelector('[data-growth-ai-spinner]') || form.querySelector('[data-growth-ai-spinner]')).classList.remove('d-none');
                    });
                });
            </script>
        @endif

        @if($mailFields !== null)
            <script>
                (() => {
                    const subject = document.getElementById('mail_subject');
                    const preheader = document.getElementById('mail_preheader');
                    const htmlField = document.querySelector('[data-mail-preheader-html]');
                    const copyStatus = document.querySelector('[data-mail-copy-status]');
                    const escapeHtml = (value) => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

                    const preheaderHtml = () => '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">'
                        + escapeHtml(preheader.value.trim())
                        + '&nbsp;&zwnj;'.repeat(40)
                        + '</div>';

                    const refresh = () => {
                        document.querySelectorAll('[data-mail-counter]').forEach((input) => {
                            const counter = document.querySelector('[data-mail-counter-for="' + input.id + '"]');
                            const length = [...input.value.trim()].length;
                            counter.textContent = length;
                            counter.classList.toggle('text-danger', length > Number(input.dataset.mailCounter));
                        });
                        htmlField.value = preheaderHtml();
                        document.querySelectorAll('[data-mail-use-subject]').forEach((button) => {
                            const isCurrent = button.dataset.mailUseSubject === subject.value.trim();
                            button.disabled = isCurrent;
                            button.parentElement.querySelector('[data-mail-subject-current]').classList.toggle('d-none', ! isCurrent);
                        });
                    };

                    document.querySelectorAll('[data-mail-use-subject]').forEach((button) => {
                        button.addEventListener('click', () => {
                            subject.value = button.dataset.mailUseSubject;
                            refresh();
                            subject.focus();
                        });
                    });

                    document.querySelector('[data-mail-copy-preheader]').addEventListener('click', async () => {
                        refresh();
                        try {
                            await navigator.clipboard.writeText(htmlField.value);
                        } catch {
                            htmlField.closest('details').open = true;
                            htmlField.select();
                            document.execCommand('copy');
                        }
                        copyStatus.classList.remove('d-none');
                    });

                    subject.addEventListener('input', refresh);
                    preheader.addEventListener('input', () => {
                        copyStatus.classList.add('d-none');
                        refresh();
                    });
                    refresh();
                })();
            </script>
        @endif
        @if($mailFields !== null)
            <div class="modal fade" id="mail-editor-link" tabindex="-1" aria-labelledby="mail-editor-link-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title h6" id="mail-editor-link-title">Wstaw link</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                        </div>
                        <div class="modal-body">
                            <label for="mail-editor-link-url" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="mail-editor-link-url" placeholder="https:// lub [LINK DO ZAPISU]">
                            <div id="mail-editor-link-error" class="invalid-feedback d-block d-none">Wpisz adres http, https, mailto albo znacznik [LINK DO ZAPISU].</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="button" class="btn btn-primary btn-sm" id="mail-editor-link-save">Wstaw link</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @include('growth-os.partials.ai-daily-limit-reset-modal')
    </div>
        @if($mailFields !== null)
        @push('scripts')
            @php
                try {
                    echo app(\Illuminate\Foundation\Vite::class)(['resources/js/growth-mail-editor.js']);
                } catch (\Throwable $viteException) {
                    report($viteException);
                    echo '<div class="alert alert-warning m-3" role="alert">Brak zbudowanego JS edytora maila (<code>npm run build</code>). '
                        .e($viteException->getMessage()).'</div>';
                }
            @endphp
        @endpush
    @endif
</x-app-layout>
