@push('styles')
<style>
    .online-course-module.sortable-ghost {
        opacity: 0.45;
    }
    .online-course-module.sortable-chosen .card {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
    }
    .online-course-lesson.sortable-ghost {
        opacity: 0.45;
        background-color: var(--bs-light);
    }
    .lessons-sortable {
        min-height: 2.5rem;
    }
    .lessons-sortable:empty::before {
        content: 'Przeciągnij lekcję tutaj lub dodaj nową';
        display: block;
        padding: 0.5rem 0;
        font-size: 0.875rem;
        color: var(--bs-secondary-color);
        font-style: italic;
    }
    .drag-handle {
        cursor: grab;
        color: var(--bs-secondary-color);
        padding: 0.15rem 0.35rem;
        border: 0;
        background: transparent;
        line-height: 1;
    }
    .drag-handle:active {
        cursor: grabbing;
    }
    #structure-reorder-toast {
        position: sticky;
        top: 0.5rem;
        z-index: 20;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('online-course-structure');
    if (!root || typeof Sortable === 'undefined') {
        return;
    }

    const courseId = root.dataset.courseId;
    const modulesUrl = root.dataset.modulesReorderUrl;
    const lessonsUrl = root.dataset.lessonsReorderUrl;
    const toastEl = document.getElementById('structure-reorder-toast');

    let modulesSaving = false;
    let lessonsSaving = false;

    function lessonEditUrl(moduleId, lessonId) {
        return '/online-courses/' + courseId + '/modules/' + moduleId + '/lessons/' + lessonId + '/edit';
    }

    function lessonDestroyUrl(moduleId, lessonId) {
        return '/online-courses/' + courseId + '/modules/' + moduleId + '/lessons/' + lessonId;
    }

    function updateLessonRowUrls() {
        root.querySelectorAll('.lessons-sortable').forEach(function (ul) {
            const moduleId = ul.dataset.moduleId;
            ul.querySelectorAll('.online-course-lesson').forEach(function (li) {
                const lessonId = li.dataset.lessonId;
                const editA = li.querySelector('.lesson-edit-link');
                if (editA) {
                    editA.href = lessonEditUrl(moduleId, lessonId);
                }
                const form = li.querySelector('.lesson-destroy-form');
                if (form) {
                    form.action = lessonDestroyUrl(moduleId, lessonId);
                }
            });
        });
    }

    function showToast(message, isError) {
        if (!toastEl) {
            return;
        }
        toastEl.className = 'alert alert-dismissible fade show ' + (isError ? 'alert-danger' : 'alert-success');
        toastEl.querySelector('[data-toast-body]').textContent = message;
        toastEl.classList.remove('d-none');
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(function () {
            toastEl.classList.add('d-none');
        }, 4000);
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            throw new Error(data.message || 'Nie udało się zapisać kolejności.');
        }
        return data;
    }

    function collectModuleOrder() {
        return Array.from(root.querySelectorAll('.online-course-module')).map(function (el) {
            return parseInt(el.dataset.moduleId, 10);
        });
    }

    function collectLessonsStructure() {
        return Array.from(root.querySelectorAll('.online-course-module')).map(function (moduleEl) {
            const moduleId = parseInt(moduleEl.dataset.moduleId, 10);
            const list = moduleEl.querySelector('.lessons-sortable');
            const lessonIds = list
                ? Array.from(list.querySelectorAll('.online-course-lesson')).map(function (li) {
                    return parseInt(li.dataset.lessonId, 10);
                })
                : [];
            return { id: moduleId, lesson_ids: lessonIds };
        });
    }

    async function saveModuleOrder() {
        if (modulesSaving) {
            return;
        }
        modulesSaving = true;
        try {
            const data = await postJson(modulesUrl, { order: collectModuleOrder() });
            showToast(data.message || 'Kolejność modułów zapisana.', false);
        } catch (e) {
            showToast(e.message, true);
        } finally {
            modulesSaving = false;
        }
    }

    async function saveLessonsStructure() {
        if (lessonsSaving) {
            return;
        }
        lessonsSaving = true;
        try {
            const data = await postJson(lessonsUrl, { modules: collectLessonsStructure() });
            updateLessonRowUrls();
            showToast(data.message || 'Kolejność lekcji zapisana.', false);
        } catch (e) {
            showToast(e.message, true);
        } finally {
            lessonsSaving = false;
        }
    }

    const modulesContainer = document.getElementById('modules-sortable');
    if (modulesContainer) {
        new Sortable(modulesContainer, {
            animation: 150,
            handle: '.module-drag-handle',
            draggable: '.online-course-module',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            filter: 'input,textarea,select,button:not(.module-drag-handle),a,form',
            preventOnFilter: false,
            onEnd: function () {
                saveModuleOrder();
            },
        });
    }

    root.querySelectorAll('.lessons-sortable').forEach(function (list) {
        new Sortable(list, {
            group: 'online-course-lessons',
            animation: 150,
            handle: '.lesson-drag-handle',
            draggable: '.online-course-lesson',
            ghostClass: 'sortable-ghost',
            filter: 'input,textarea,select,button:not(.lesson-drag-handle),a,form',
            preventOnFilter: false,
            onEnd: function () {
                saveLessonsStructure();
            },
        });
    });
});
</script>
@endpush
