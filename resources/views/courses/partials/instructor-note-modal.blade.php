<div class="modal fade" id="instructorNoteModal" tabindex="-1" aria-labelledby="instructorNoteModalLabel" aria-hidden="true" data-update-url="{{ url('/courses/__COURSE__/instructor-note') }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="instructorNoteModalLabel">Notatka o instruktorze</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="instructorNoteCourseLabel"></p>
                <div class="alert alert-danger d-none" id="instructorNoteError" role="alert"></div>
                <label for="instructorNoteInput" class="form-label">Notatka</label>
                <textarea class="form-control" id="instructorNoteInput" rows="6" maxlength="5000" placeholder="Sprawy związane z instruktorem tego szkolenia"></textarea>
                <div class="form-text">Jedna notatka na szkolenie. To nie jest pole „Notatki techniczne” z edycji szkolenia. Puste pole usuwa notatkę.</div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" id="instructorNoteGenerateBtn">Generuj</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger" id="instructorNoteDeleteBtn">USUŃ</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="button" class="btn btn-primary" id="instructorNoteSaveBtn">Zapisz</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="instructorNoteDeleteModal" tabindex="-1" aria-labelledby="instructorNoteDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="instructorNoteDeleteModalLabel">Usunąć notatkę?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Zapisana notatka o instruktorze zniknie z listy szkoleń.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Wróć</button>
                <button type="button" class="btn btn-danger" id="instructorNoteDeleteConfirmBtn">USUŃ</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('instructorNoteModal');
        if (!modalEl || !window.bootstrap) {
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const labelEl = document.getElementById('instructorNoteCourseLabel');
        const inputEl = document.getElementById('instructorNoteInput');
        const errorEl = document.getElementById('instructorNoteError');
        const saveBtn = document.getElementById('instructorNoteSaveBtn');
        const generateBtn = document.getElementById('instructorNoteGenerateBtn');
        const deleteBtn = document.getElementById('instructorNoteDeleteBtn');
        const deleteModalEl = document.getElementById('instructorNoteDeleteModal');
        const deleteModal = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
        const deleteConfirmBtn = document.getElementById('instructorNoteDeleteConfirmBtn');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let activeCourseId = null;
        let activeNoteHeader = '';

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function hideError() {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }

        function showError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
        }

        function renderSlot(courseId, note, courseLabel) {
            const slot = document.querySelector('[data-instructor-note-slot="' + courseId + '"]');
            if (!slot) {
                return;
            }

            const noteHeader = slot.querySelector('.instructor-note-open')?.dataset.noteHeader || activeNoteHeader;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link btn-sm p-0 instructor-note-open';
            button.dataset.courseId = String(courseId);
            button.dataset.courseLabel = courseLabel;
            button.dataset.noteHeader = noteHeader;
            button.dataset.note = note;

            if (note !== '') {
                button.dataset.bsToggle = 'tooltip';
                button.dataset.bsHtml = 'true';
                button.dataset.bsPlacement = 'top';
                button.dataset.bsTitle = escapeHtml(note).replace(/\n/g, '<br>');
                button.setAttribute('aria-label', 'Notatka o instruktorze');
                button.innerHTML = '<i class="bi bi-sticky-fill text-warning" style="font-size: 1.15em;"></i>';
            } else {
                button.setAttribute('aria-label', 'Dodaj notatkę o instruktorze');
                button.innerHTML = '<i class="bi bi-sticky"></i> Notatka';
            }

            slot.replaceChildren(button);

            if (note !== '' && window.bootstrap.Tooltip) {
                window.bootstrap.Tooltip.getOrCreateInstance(button);
            }
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.instructor-note-open');
            if (!button) {
                return;
            }

            activeCourseId = button.dataset.courseId || null;
            activeNoteHeader = button.dataset.noteHeader || '';
            labelEl.textContent = button.dataset.courseLabel || '';
            inputEl.value = button.dataset.note || '';
            hideError();

            const tip = window.bootstrap.Tooltip.getInstance(button);
            if (tip) {
                tip.hide();
            }

            modal.show();
            window.setTimeout(function () {
                inputEl.focus();
            }, 200);
        });

        generateBtn.addEventListener('click', function () {
            if (activeNoteHeader === '') {
                return;
            }

            const headerLine = activeNoteHeader.replace(/\s+$/, '') + ' ';
            const current = inputEl.value;
            const lines = current.split('\n');
            if (/^SZKOLENIE( RP)?:/.test(lines[0] || '')) {
                lines[0] = headerLine;
                inputEl.value = lines.join('\n');
            } else if (current.trim() === '') {
                inputEl.value = headerLine;
            } else {
                inputEl.value = headerLine + '\n' + current;
            }

            inputEl.focus();
        });

        function persistNote(noteText, busyButton) {
            if (!activeCourseId) {
                return;
            }

            hideError();
            if (busyButton) {
                busyButton.disabled = true;
            }
            const courseLabel = labelEl.textContent || '';
            const courseId = activeCourseId;
            const updateUrl = (modalEl.dataset.updateUrl || '').replace('__COURSE__', courseId);

            fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ note: noteText })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        const message = result.data?.message
                            || (result.data?.errors?.note && result.data.errors.note[0])
                            || 'Nie udało się zapisać notatki.';
                        showError(message);
                        if (deleteModal) {
                            deleteModal.hide();
                        }
                        return;
                    }

                    renderSlot(courseId, result.data.note || '', courseLabel);
                    if (deleteModal) {
                        deleteModal.hide();
                    }
                    modal.hide();
                })
                .catch(function () {
                    showError('Nie udało się zapisać notatki.');
                    if (deleteModal) {
                        deleteModal.hide();
                    }
                })
                .finally(function () {
                    if (busyButton) {
                        busyButton.disabled = false;
                    }
                });
        }

        deleteBtn.addEventListener('click', function () {
            if (deleteModal) {
                deleteModal.show();
            }
        });

        deleteConfirmBtn.addEventListener('click', function () {
            persistNote('', deleteConfirmBtn);
        });

        saveBtn.addEventListener('click', function () {
            persistNote(inputEl.value, saveBtn);
        });
    });
</script>
