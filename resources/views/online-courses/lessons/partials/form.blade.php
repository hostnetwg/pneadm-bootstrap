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

    @include('online-courses.lessons.partials.form-fields', [
        'course' => $course,
        'module' => $module,
        'lesson' => $lesson,
        'embeds' => $embeds,
        'resource_links' => $resource_links,
    ])
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
