@php
    $lesson = $lesson ?? null;
    $embeds = old('embeds');
    if (! is_array($embeds)) {
        if ($lesson) {
            $embeds = $lesson->embeds->map(fn ($e) => [
                'video_url' => $e->video_url,
                'platform' => $e->platform,
                'title' => $e->title,
            ])->values()->all();
        } else {
            $embeds = [
                ['video_url' => '', 'platform' => 'vimeo', 'title' => ''],
                ['video_url' => '', 'platform' => 'vimeo', 'title' => ''],
            ];
        }
    }
    $resource_links = old('resource_links');
    if (! is_array($resource_links)) {
        if ($lesson) {
            $resource_links = $lesson->resourceLinks->map(fn ($r) => [
                'url' => $r->url,
                'title' => $r->title,
            ])->values()->all();
        } else {
            $resource_links = [['url' => '', 'title' => '']];
        }
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form id="online-course-lesson-form" method="post" action="{{ $lesson ? route('online-courses.lessons.update', [$course, $module, $lesson]) : route('online-courses.lessons.store', [$course, $module]) }}">
    @csrf
    @if($lesson)
        @method('PUT')
    @endif

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

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $lesson ? 'Zapisz lekcję' : 'Dodaj lekcję' }}</button>
        <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-outline-secondary">Wróć do struktury</a>
    </div>
</form>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var STORAGE_KEY = 'pneadm_oc_lesson_body_editor_mode';
    var ta = document.getElementById('lesson-body-html');
    var btnW = document.getElementById('lesson-body-btn-wysiwyg');
    var btnH = document.getElementById('lesson-body-btn-html');
    var form = document.getElementById('online-course-lesson-form');
    if (!ta || !btnW || !btnH) return;

    function setModeButtons(mode) {
        var wysiwygActive = mode === 'wysiwyg';
        btnW.classList.toggle('btn-secondary', wysiwygActive);
        btnW.classList.toggle('btn-outline-secondary', !wysiwygActive);
        btnW.setAttribute('aria-pressed', wysiwygActive ? 'true' : 'false');
        btnH.classList.toggle('btn-secondary', !wysiwygActive);
        btnH.classList.toggle('btn-outline-secondary', wysiwygActive);
        btnH.setAttribute('aria-pressed', (!wysiwygActive) ? 'true' : 'false');
        ta.classList.toggle('font-monospace', !wysiwygActive);
        try {
            sessionStorage.setItem(STORAGE_KEY, mode);
        } catch (e) { /* ignore */ }
    }

    function destroyTiny() {
        if (!window.tinymce) return;
        var ed = window.tinymce.get('lesson-body-html');
        if (ed) {
            window.tinymce.triggerSave();
            ed.remove();
        }
    }

    function initTiny() {
        return new Promise(function (resolve, reject) {
            if (!window.tinymce) {
                reject(new Error('TinyMCE unavailable'));
                return;
            }
            destroyTiny();
            var promise = window.tinymce.init({
                selector: '#lesson-body-html',
                height: 440,
                min_height: 280,
                menubar: false,
                branding: false,
                plugins: 'link lists autoresize code',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | link unlink | removeformat | code',
                relative_urls: false,
                browser_spellcheck: true,
                autoresize_bottom_margin: 24,
                valid_elements: '*[*]',
                valid_children: '+body[style]',
                setup: function (editor) {
                    editor.on('change input undo redo ExecCommand NodeChange KeyUp Paste Cut SetContent', function () {
                        editor.save();
                    });
                },
            });
            if (promise && typeof promise.then === 'function') {
                promise.then(function () {
                    resolve();
                }).catch(reject);
            } else {
                window.setTimeout(resolve, 120);
            }
        });
    }

    function showHtmlSource() {
        destroyTiny();
        setModeButtons('html');
    }

    function showWysiwyg() {
        initTiny().then(function () {
            setModeButtons('wysiwyg');
        }).catch(function () {
            destroyTiny();
            setModeButtons('html');
        });
    }

    btnH.addEventListener('click', function () {
        showHtmlSource();
    });

    btnW.addEventListener('click', function () {
        showWysiwyg();
    });

    if (form) {
        form.addEventListener('submit', function () {
            if (window.tinymce) {
                window.tinymce.triggerSave();
            }
        });
    }

    var initialMode = 'html';
    try {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved === 'wysiwyg' || saved === 'html') initialMode = saved;
    } catch (e) { /* ignore */ }

    if (initialMode === 'wysiwyg') {
        showWysiwyg();
    } else {
        setModeButtons('html');
    }

    let embedIdx = {{ count($embeds) }};
    let linkIdx = {{ count($resource_links) }};

    document.getElementById('btn-add-embed')?.addEventListener('click', function () {
        const wrap = document.getElementById('embed-rows');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 embed-row';
        div.innerHTML =
            '<div class="col-md-5"><input type="url" name="embeds[' + embedIdx + '][video_url]" class="form-control" placeholder="URL wideo"></div>' +
            '<div class="col-md-2"><select name="embeds[' + embedIdx + '][platform]" class="form-select"><option value="vimeo" selected>Vimeo</option><option value="youtube">YouTube</option><option value="other">Inny</option></select></div>' +
            '<div class="col-md-4"><input type="text" name="embeds[' + embedIdx + '][title]" class="form-control" placeholder="Opcjonalny tytuł"></div>' +
            '<div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary btn-remove-embed">&times;</button></div>';
        wrap.appendChild(div);
        bindRemove(div.querySelector('.btn-remove-embed'), div);
        embedIdx++;
    });

    document.getElementById('btn-add-link')?.addEventListener('click', function () {
        const wrap = document.getElementById('link-rows');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 link-row';
        div.innerHTML =
            '<div class="col-md-6"><input type="url" name="resource_links[' + linkIdx + '][url]" class="form-control" placeholder="https://..."></div>' +
            '<div class="col-md-5"><input type="text" name="resource_links[' + linkIdx + '][title]" class="form-control" placeholder="Opis linku"></div>' +
            '<div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary btn-remove-link">&times;</button></div>';
        wrap.appendChild(div);
        bindRemove(div.querySelector('.btn-remove-link'), div);
        linkIdx++;
    });

    function bindRemove(btn, row) {
        btn?.addEventListener('click', function () {
            row.remove();
        });
    }
    document.querySelectorAll('.btn-remove-embed').forEach(btn => bindRemove(btn, btn.closest('.embed-row')));
    document.querySelectorAll('.btn-remove-link').forEach(btn => bindRemove(btn, btn.closest('.link-row')));
});
</script>
@endpush
