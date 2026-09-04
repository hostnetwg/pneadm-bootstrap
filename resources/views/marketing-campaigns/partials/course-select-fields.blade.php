@php
    $selectedCourse = $selectedCourse ?? null;
    $fieldId = $fieldId ?? 'course_id';
    $fieldLabel = $fieldLabel ?? 'Powiązane szkolenie';
    $showEarlyPickHint = $showEarlyPickHint ?? false;
@endphp

<div class="mb-0">
    <label class="form-label fw-semibold" for="{{ $fieldId }}">{{ $fieldLabel }}</label>
    <select id="{{ $fieldId }}" name="{{ $fieldId }}" class="form-control @error($fieldId) is-invalid @enderror">
        @if($selectedCourse)
            <option value="{{ $selectedCourse->id }}" selected>
                #{{ $selectedCourse->id }} · {{ $selectedCourse->plainTitle() }}
                @if($selectedCourse->start_date)
                    [{{ $selectedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}]
                @endif
            </option>
        @endif
    </select>
    @error($fieldId)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($showEarlyPickHint)
        <x-campaign-field-hint summary="Wybierz szkolenie, aby zobaczyć podgląd linku i zaproponować kod kampanii.">
            Wyszukiwanie: tytuł, Publigo ID lub <code>#50</code> (wewnętrzne ID). Lista obejmuje archiwalne terminy.
        </x-campaign-field-hint>
    @else
        <small class="form-text text-muted d-block mt-1">
            Wpisz tytuł lub Publigo ID. Aby wyszukać po wewnętrznym ID kursu, użyj <code>#50</code>.
            Lista obejmuje także szkolenia archiwalne.
        </small>
    @endif
    <div id="marketing-campaign-course-info" class="alert alert-light mt-2 mb-0 py-2 small border" style="display: none;">
        <div id="marketing-campaign-course-details"></div>
    </div>
    <div id="marketing-campaign-course-select-error" class="alert alert-warning mt-2 mb-0 py-2 small border" style="display: none;" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        Nie udało się uruchomić wyszukiwarki szkoleń. Odśwież stronę lub skontaktuj się z administratorem
        (wymagany build frontendu: <code>npm run build</code>).
    </div>
</div>

@push('scripts')
@php
    $courseSearchUrl = route('marketing-campaigns.courses.search');
    $coursePreselected = null;
    if ($selectedCourse) {
        $tz = config('app.timezone');
        $coursePreselected = [
            'id' => $selectedCourse->id,
            'id_old' => $selectedCourse->id_old,
            'title_text' => $selectedCourse->plainTitle(''),
            'start_date' => $selectedCourse->start_date ? $selectedCourse->start_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'end_date' => $selectedCourse->end_date ? $selectedCourse->end_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
            'status' => $selectedCourse->getLifecycleStatus(),
            'instructor' => optional($selectedCourse->instructor)->full_title_name ?? '',
            'certificate_registration_open' => (bool) $selectedCourse->certificate_registration_open,
        ];
    }
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const courseSearchUrl = @json($courseSearchUrl);
    const coursePreselected = @json($coursePreselected);
    const selectId = @json($fieldId);
    const courseInfo = document.getElementById('marketing-campaign-course-info');
    const courseDetails = document.getElementById('marketing-campaign-course-details');
    const courseSelectError = document.getElementById('marketing-campaign-course-select-error');
    const fallbackScriptUrl = @json(asset('js/course-select-fallback.js'));

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderCourseInfo(item) {
        if (!courseInfo || !courseDetails) {
            return;
        }
        if (!item) {
            courseInfo.style.display = 'none';
            return;
        }
        const instructor = item.instructor ? escapeHtml(item.instructor) : '<span class="text-muted">—</span>';
        courseDetails.innerHTML =
            '<div><strong>Tytuł:</strong> ' + escapeHtml(item.title_text || '') + '</div>' +
            '<div><strong>Data:</strong> ' + (item.start_date ? escapeHtml(item.start_date) : '—') + '</div>' +
            '<div><strong>Prowadzący:</strong> ' + instructor + '</div>';
        courseInfo.style.display = 'block';
    }

    function showCourseSelectError() {
        if (courseSelectError) {
            courseSelectError.style.display = 'block';
        }
    }

    function bootCourseSelect(initFn) {
        const courseTs = initFn(selectId, {
            searchUrl: courseSearchUrl,
            preselected: coursePreselected,
            includeArchived: true,
            placeholder: 'Tytuł, Publigo ID lub #ID kursu (np. #50)...',
            onCourseChanged: function (item) {
                renderCourseInfo(item);
                document.dispatchEvent(new CustomEvent('pne:campaign-course-changed', {
                    detail: { item: item },
                }));
                document.dispatchEvent(new CustomEvent('pne:campaign-form-change'));
            },
        });

        if (courseTs && coursePreselected) {
            renderCourseInfo(coursePreselected);
            document.dispatchEvent(new CustomEvent('pne:campaign-course-changed', {
                detail: { item: coursePreselected },
            }));
        }

        return courseTs;
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (existing.dataset.loaded === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); }, { once: true });
                existing.addEventListener('error', function () { reject(new Error('Script load failed')); }, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.onload = function () {
                script.dataset.loaded = '1';
                resolve();
            };
            script.onerror = function () {
                reject(new Error('Script load failed'));
            };
            document.head.appendChild(script);
        });
    }

    function waitForInitFn(maxWaitMs) {
        return new Promise(function (resolve) {
            if (typeof window.initCourseSelect === 'function') {
                resolve(window.initCourseSelect);
                return;
            }

            const started = Date.now();
            const timer = window.setInterval(function () {
                if (typeof window.initCourseSelect === 'function') {
                    window.clearInterval(timer);
                    resolve(window.initCourseSelect);
                    return;
                }

                if (Date.now() - started >= maxWaitMs) {
                    window.clearInterval(timer);
                    resolve(null);
                }
            }, 50);
        });
    }

    function ensureCourseSelectInit() {
        return waitForInitFn(4000).then(function (initFn) {
            if (initFn) {
                return initFn;
            }

            return loadScript(fallbackScriptUrl).then(function () {
                if (typeof window.ensureCourseSelectFallback !== 'function') {
                    return null;
                }

                return window.ensureCourseSelectFallback();
            });
        });
    }

    ensureCourseSelectInit()
        .then(function (initFn) {
            if (!initFn) {
                showCourseSelectError();
                return;
            }

            bootCourseSelect(initFn);
        })
        .catch(function () {
            showCourseSelectError();
        });
});
</script>
@endpush
