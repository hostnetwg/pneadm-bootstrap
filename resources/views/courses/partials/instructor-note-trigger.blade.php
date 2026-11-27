@php
    $instructorNoteBody = trim((string) ($course->instructorNote->body ?? ''));
    $instructorNoteLabel = $course->plainTitle();
    if ($course->instructor) {
        $instructorNoteLabel .= ' — '.$course->instructor->getFullTitleNameAttribute();
    }
@endphp
<div class="mt-1" data-instructor-note-slot="{{ $course->id }}">
    @if($instructorNoteBody !== '')
        <button type="button"
                class="btn btn-link btn-sm p-0 instructor-note-open"
                data-course-id="{{ $course->id }}"
                data-course-label="{{ $instructorNoteLabel }}"
                data-note-header="{{ $course->instructorNoteHeader() }}"
                data-note="{{ $instructorNoteBody }}"
                data-bs-toggle="tooltip"
                data-bs-html="true"
                data-bs-placement="top"
                data-bs-title="{!! nl2br(e($instructorNoteBody)) !!}"
                aria-label="Notatka o instruktorze">
            <i class="bi bi-sticky-fill text-warning" style="font-size: 1.15em;"></i>
        </button>
    @else
        <button type="button"
                class="btn btn-link btn-sm p-0 instructor-note-open"
                data-course-id="{{ $course->id }}"
                data-course-label="{{ $instructorNoteLabel }}"
                data-note-header="{{ $course->instructorNoteHeader() }}"
                data-note=""
                aria-label="Dodaj notatkę o instruktorze">
            <i class="bi bi-sticky"></i> Notatka
        </button>
    @endif
</div>
