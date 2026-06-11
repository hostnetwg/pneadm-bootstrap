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
        'linkedCourse' => $linkedCourse ?? null,
    ])
</form>

@push('scripts')
@php
    $linkedCourseSearchUrl = route('online-courses.linkable-courses.search');
    $linkedCoursePreselected = null;
    if (! empty($linkedCourse)) {
        $tz = config('app.timezone');
        $linkedCoursePreselected = [
            'id' => $linkedCourse->id,
            'id_old' => $linkedCourse->id_old,
            'title_text' => trim(strip_tags((string) $linkedCourse->title)),
            'start_date' => $linkedCourse->start_date ? $linkedCourse->start_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'end_date' => $linkedCourse->end_date ? $linkedCourse->end_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'status' => $linkedCourse->getLifecycleStatus(),
            'instructor' => optional($linkedCourse->instructor)->full_title_name ?? '',
            'certificate_registration_open' => (bool) $linkedCourse->certificate_registration_open,
        ];
    }
@endphp
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const linkedCourseSearchUrl = @json($linkedCourseSearchUrl);
    const linkedCoursePreselected = @json($linkedCoursePreselected);
    const linkedCourseInfo = document.getElementById('linked-course-info');
    const linkedCourseDetails = document.getElementById('linked-course-details');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderLinkedCourseInfo(item) {
        if (!linkedCourseInfo || !linkedCourseDetails) {
            return;
        }
        if (!item) {
            linkedCourseInfo.style.display = 'none';
            return;
        }
        const certNote = item.certificate_registration_open
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Rejestracja zaświadczenia wł.</span>'
            : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Rejestracja zaświadczenia wył.</span>';
        const instructor = item.instructor ? escapeHtml(item.instructor) : '<span class="text-muted">—</span>';
        linkedCourseDetails.innerHTML =
            '<div class="d-flex flex-wrap align-items-center gap-2 mb-1">' + certNote + '</div>' +
            '<div><strong>Tytuł:</strong> ' + escapeHtml(item.title_text || '') + '</div>' +
            '<div><strong>Data:</strong> ' + (item.start_date ? escapeHtml(item.start_date) : '—') + '</div>' +
            '<div><strong>Prowadzący:</strong> ' + instructor + '</div>';
        linkedCourseInfo.style.display = 'block';
    }

    const linkedCourseTs = window.initCourseSelect && window.initCourseSelect('linked_course_id', {
        searchUrl: linkedCourseSearchUrl,
        preselected: linkedCoursePreselected,
        includeArchived: true,
        placeholder: 'Wybierz lub wpisz tytuł / ID szkolenia...',
        onCourseChanged: renderLinkedCourseInfo,
    });

    if (linkedCourseTs && linkedCoursePreselected) {
        renderLinkedCourseInfo(linkedCoursePreselected);
    }

    var STORAGE_KEY_EDITOR = 'pneadm_oc_lesson_body_editor_mode';
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
            sessionStorage.setItem(STORAGE_KEY_EDITOR, mode);
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
        var saved = sessionStorage.getItem(STORAGE_KEY_EDITOR);
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
