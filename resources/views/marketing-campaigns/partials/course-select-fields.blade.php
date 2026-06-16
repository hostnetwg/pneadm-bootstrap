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
                #{{ $selectedCourse->id }} · {{ strip_tags($selectedCourse->title) }}
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
</div>

@push('scripts')
@php
    $courseSearchUrl = route('online-courses.linkable-courses.search');
    $coursePreselected = null;
    if ($selectedCourse) {
        $tz = config('app.timezone');
        $coursePreselected = [
            'id' => $selectedCourse->id,
            'id_old' => $selectedCourse->id_old,
            'title_text' => trim(strip_tags((string) $selectedCourse->title)),
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

    const courseTs = window.initCourseSelect && window.initCourseSelect(selectId, {
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
});
</script>
@endpush
