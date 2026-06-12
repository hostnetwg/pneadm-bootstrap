<div class="mb-3">
    <label class="form-label" for="title">Tytuł lekcji</label>
    <input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $lesson?->title) }}">
</div>

<div class="form-check mb-3">
    <input type="hidden" name="is_published" value="0">
    <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $lesson?->is_published ?? true))>
    <label class="form-check-label" for="is_published">Opublikowana (widoczna na pnedu dla użytkowników z dostępem)</label>
</div>

<div class="mb-3" id="lesson-body-editor-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <label class="form-label mb-0" for="lesson-body-html">Treść lekcji</label>
        <div class="btn-group btn-group-sm" role="group" aria-label="Tryb edycji treści">
            <button type="button" class="btn btn-outline-secondary" id="lesson-body-btn-wysiwyg" aria-pressed="false">Wizualny</button>
            <button type="button" class="btn btn-secondary" id="lesson-body-btn-html" aria-pressed="true">HTML</button>
        </div>
    </div>
    <p class="small text-muted mb-2">
        Tryb HTML: pełna kontrola (np. atrybuty <code class="small">data-time</code> w spisie treści wideo). Tryb wizualny: wygodniejszy — do surowego kodu przełącz na „HTML”; w edytorze jest też przycisk „źródło” (<strong>&lt;/&gt;</strong>).
    </p>
    <div class="alert alert-info small py-2 mb-2">
        <strong>Spis treści wideo (znaczniki czasu):</strong> URL nagrania wpisz w sekcji <strong>„Wideo osadzone”</strong> poniżej — nie wklejaj tutaj <code>&lt;iframe&gt;</code> ani <code>&lt;script&gt;</code> ze starej strony NE.pl.
        W treści zostaw opis i listę linków, np.:
        <code class="d-block mt-1 user-select-all">&lt;ul class="no-bullets"&gt;&lt;li&gt;&lt;a href="#" data-time="00:03:59"&gt;00:03:59 - Tytuł fragmentu&lt;/a&gt;&lt;/li&gt;&lt;/ul&gt;</code>
        Przy kilku nagraniach w lekcji dodaj <code>data-embed-index="1"</code> (0 = pierwsze wideo, 1 = drugie itd.).
    </div>
    <textarea id="lesson-body-html" name="body_html" class="form-control font-monospace" rows="14" spellcheck="true">{{ old('body_html', $lesson?->body_html) }}</textarea>
</div>

<h5>Wideo osadzone (YouTube / Vimeo / „inny” jako link)</h5>
<p class="small text-muted mb-2">
    <strong>Wymagane dla spisu treści ze znacznikami czasu.</strong> Wklej URL YouTube/Vimeo (np. <code>https://www.youtube.com/watch?v=…</code>) i wybierz platformę.
    Odtwarzacz na pnedu.pl obsługuje przewijanie po kliknięciu linków <code>data-time</code> z treści lekcji — bez dodatkowego kodu JavaScript w HTML.
    Puste wiersze są pomijane.
</p>
<div id="embed-rows">
    @foreach($embeds as $i => $row)
        <div class="row g-2 mb-2 embed-row">
            <div class="col-md-5">
                <input type="url" name="embeds[{{ $i }}][video_url]" class="form-control" placeholder="URL wideo" value="{{ $row['video_url'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <select name="embeds[{{ $i }}][platform]" class="form-select">
                    @foreach(['vimeo' => 'Vimeo','youtube' => 'YouTube','other' => 'Inny'] as $pv => $pl)
                        <option value="{{ $pv }}" @selected(($row['platform'] ?? '') === $pv || (($row['platform'] ?? '') === '' && $pv === 'vimeo'))>{{ $pl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="embeds[{{ $i }}][title]" class="form-control" placeholder="Opcjonalny tytuł" value="{{ $row['title'] ?? '' }}">
            </div>
            <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-secondary btn-remove-embed">&times;</button>
            </div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-primary mb-2" id="btn-add-embed">+ kolejne wideo</button>

@include('online-courses.lessons.partials.certificate-link-fields', ['linkedCourse' => $linkedCourse ?? null])

<h5 class="mt-2">Linki do materiałów</h5>
<div id="link-rows">
    @foreach($resource_links as $i => $row)
        <div class="row g-2 mb-2 link-row">
            <div class="col-md-6">
                <input type="url" name="resource_links[{{ $i }}][url]" class="form-control" placeholder="https://..." value="{{ $row['url'] ?? '' }}">
            </div>
            <div class="col-md-5">
                <input type="text" name="resource_links[{{ $i }}][title]" class="form-control" placeholder="Opis linku" value="{{ $row['title'] ?? '' }}">
            </div>
            <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-secondary btn-remove-link">&times;</button>
            </div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-primary mb-4" id="btn-add-link">+ kolejny link</button>

<div class="form-check mb-3">
    <input type="checkbox" name="redirect_to_create" value="1" class="form-check-input" id="redirect_to_create">
    <label class="form-check-label" for="redirect_to_create">Po utworzeniu lekcji otwórz formularz tworzenia nowej lekcji</label>
</div>

<div class="d-flex gap-2 flex-wrap">
    <button type="submit" class="btn btn-primary">{{ $lesson ? 'Zapisz lekcję' : 'Dodaj lekcję' }}</button>
    <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-outline-secondary">Wróć do struktury</a>
</div>

@if($lesson && !empty($lessonNav) && ($lessonNav['prev'] || $lessonNav['next']))
    <div class="d-flex gap-2 flex-wrap mt-2" id="lesson-nav-buttons">
        @if($lessonNav['prev'])
            <a href="{{ $lessonNav['prev']['url'] }}"
               class="btn btn-outline-primary lesson-nav-link"
               data-lesson-nav="prev"
               title="{{ $lessonNav['prev']['title'] }}">
                ← Poprzednia lekcja
            </a>
        @else
            <span class="btn btn-outline-secondary disabled" aria-disabled="true">← Poprzednia lekcja</span>
        @endif
        @if($lessonNav['next'])
            <a href="{{ $lessonNav['next']['url'] }}"
               class="btn btn-outline-primary lesson-nav-link"
               data-lesson-nav="next"
               title="{{ $lessonNav['next']['title'] }}">
                Następna lekcja →
            </a>
        @else
            <span class="btn btn-outline-secondary disabled" aria-disabled="true">Następna lekcja →</span>
        @endif
    </div>
    <p class="small text-muted mb-0 mt-1" id="lesson-nav-dirty-hint" style="display: none;">
        Zapisz lekcję, aby przejść do poprzedniej lub następnej.
    </p>
@endif
