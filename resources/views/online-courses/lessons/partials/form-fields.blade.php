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
    <textarea id="lesson-body-html" name="body_html" class="form-control font-monospace" rows="14" spellcheck="true">{{ old('body_html', $lesson?->body_html) }}</textarea>
</div>

<h5>Wideo osadzone (YouTube / Vimeo / „inny” jako link)</h5>
<p class="small text-muted">Puste wiersze są pomijane.</p>
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
<button type="button" class="btn btn-sm btn-outline-primary mb-4" id="btn-add-embed">+ kolejne wideo</button>

<h5>Linki do materiałów</h5>
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

<div class="d-flex gap-2 flex-wrap">
    <button type="submit" class="btn btn-primary">{{ $lesson ? 'Zapisz lekcję' : 'Dodaj lekcję' }}</button>
    <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-outline-secondary">Wróć do struktury</a>
</div>
