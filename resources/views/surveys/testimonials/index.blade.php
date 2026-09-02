<x-app-layout>
    <x-slot name="header">
        Rekomendacje (opinie na pnedu.pl)
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-3">
            Opinie zebrane z bloku „rekomendacja” w ankiecie natywnej. Publikacja na stronie głównej wymaga zgody uczestnika
            oraz Twojego zatwierdzenia. Możesz poprawić treść i dane autora (np. literówki) przed publikacją.
            <strong>Wyróżnione</strong> opinie są zawsze na górze na pnedu.pl (od najnowszych do najstarszych),
            poniżej niewyróżnione — też od najnowszych. Optymalnie 4–8 wyróżnień (limit {{ \App\Models\SurveyTestimonial::FEATURED_SOFT_LIMIT }}).
            Obecnie wyróżnionych: <strong id="js-featured-count">{{ (int) ($featuredCount ?? 0) }}</strong>.
        </p>
        <div id="js-testimonial-flash" class="d-none" role="alert"></div>

        <div class="mb-3 btn-group flex-wrap">
            <a href="{{ route('surveys.testimonials.index') }}" class="btn btn-sm {{ $filter === '' ? 'btn-primary' : 'btn-outline-primary' }}">Wszystkie</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'pending']) }}" class="btn btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Do akceptacji</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'published']) }}" class="btn btn-sm {{ $filter === 'published' ? 'btn-primary' : 'btn-outline-primary' }}">Opublikowane</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'featured']) }}" class="btn btn-sm {{ $filter === 'featured' ? 'btn-primary' : 'btn-outline-primary' }}">
                Wyróżnione
                @if(($featuredCount ?? 0) > 0)
                    ({{ $featuredCount }})
                @endif
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Autor</th>
                            <th>Opinia</th>
                            <th>Ocena</th>
                            <th>Wystawiono</th>
                            <th>Zgoda</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($testimonials as $t)
                            @php
                                $editPayloadJson = json_encode([
                                    'updateUrl' => route('surveys.testimonials.update', $t),
                                    'authorName' => $t->author_name,
                                    'authorRole' => $t->author_role,
                                    'authorCity' => $t->author_city,
                                    'quote' => $t->quote,
                                    'rating' => $t->rating,
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                            @endphp
                            <tr class="js-testimonial-row"
                                data-id="{{ $t->id }}"
                                data-publish-consent="{{ $t->publish_consent ? '1' : '0' }}"
                                data-is-published="{{ $t->is_published ? '1' : '0' }}"
                                data-is-featured="{{ $t->is_featured ? '1' : '0' }}"
                                data-publish-url="{{ route('surveys.testimonials.publish', $t) }}"
                                data-unpublish-url="{{ route('surveys.testimonials.unpublish', $t) }}"
                                data-feature-url="{{ route('surveys.testimonials.feature', $t) }}"
                                data-unfeature-url="{{ route('surveys.testimonials.unfeature', $t) }}"
                                data-author-name="{{ $t->author_name }}"
                                data-delete-url="{{ route('surveys.testimonials.destroy', $t) }}"
                                data-edit-payload="{{ $editPayloadJson }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($t->hasAvatar())
                                            <img src="{{ $t->avatarUrl() }}" alt="" width="44" height="44"
                                                 class="rounded-circle flex-shrink-0" style="object-fit:cover;background:#eef2f6;">
                                        @else
                                            <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:44px;height:44px;background:#6c757d;font-size:.75rem;"
                                                 title="Brak awatara">{{ $t->initials() }}</div>
                                        @endif
                                        <div>
                                            <strong>{{ $t->author_name }}</strong><br>
                                            <small class="text-muted">{{ $t->subtitle() }}</small>
                                            @if($t->course)
                                                <br><small class="text-muted">{{ Str::limit($t->course->title, 40) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="testimonial-quote-cell" style="min-width: 280px; max-width: 520px; white-space: pre-wrap; word-break: break-word;">{{ $t->quote }}</td>
                                <td>{{ $t->rating ? $t->rating.'/5' : '—' }}</td>
                                <td class="text-nowrap">
                                    @if($t->created_at)
                                        <span title="{{ $t->created_at->timezone('Europe/Warsaw')->format('d.m.Y H:i:s') }}">
                                            {{ $t->created_at->timezone('Europe/Warsaw')->format('d.m.Y') }}
                                            <br>
                                            <small class="text-muted">{{ $t->created_at->timezone('Europe/Warsaw')->format('H:i') }}</small>
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($t->publish_consent)
                                        <span class="badge bg-success">Tak</span>
                                    @else
                                        <span class="badge bg-secondary">Nie</span>
                                    @endif
                                </td>
                                <td class="js-testimonial-status"></td>
                                <td class="text-end text-nowrap js-testimonial-actions"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Brak rekomendacji w tym filtrze.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($testimonials->hasPages())
                <div class="card-footer">{{ $testimonials->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Edycja opinii (literówki, treść, autor) --}}
    <div class="modal fade" id="editTestimonialModal" tabindex="-1" aria-labelledby="editTestimonialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editTestimonialForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTestimonialModalLabel">
                            <i class="bi bi-pencil-square me-2"></i>Edycja rekomendacji
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Popraw literówki lub sformułowania przed publikacją na stronie głównej.</p>
                        <div class="mb-3">
                            <label for="edit_quote" class="form-label">Opinia <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="quote" id="edit_quote" rows="5" maxlength="1000" required></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_author_name" class="form-label">Imię i nazwisko <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="author_name" id="edit_author_name" maxlength="120" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_author_role" class="form-label">Stanowisko / rola</label>
                                <input type="text" class="form-control" name="author_role" id="edit_author_role" maxlength="120">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_author_city" class="form-label">Miasto</label>
                                <input type="text" class="form-control" name="author_city" id="edit_author_city" maxlength="80">
                            </div>
                        </div>
                        <div class="mt-3" style="max-width: 10rem;">
                            <label for="edit_rating" class="form-label">Ocena (1–5)</label>
                            <input type="number" class="form-control" name="rating" id="edit_rating" min="1" max="5" step="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Potwierdzenie usunięcia --}}
    <div class="modal fade" id="deleteTestimonialModal" tabindex="-1" aria-labelledby="deleteTestimonialModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteTestimonialForm">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteTestimonialModalLabel">
                            <i class="bi bi-exclamation-triangle me-2"></i>Usuń rekomendację
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            Czy na pewno chcesz usunąć rekomendację autora
                            <strong id="deleteTestimonialAuthor"></strong>?
                        </p>
                        <div class="alert alert-warning mt-3 mb-0 small">
                            Tej operacji nie można cofnąć.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger">Usuń</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $reopenEditTestimonial = isset($errors) && $errors->any()
            && old('_method') === 'PUT'
            && old('quote') !== null;
    @endphp

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const flashEl = document.getElementById('js-testimonial-flash');
            const featuredCountEl = document.getElementById('js-featured-count');

            function showFlash(message, type) {
                if (!flashEl) return;
                flashEl.className = 'alert alert-' + (type === 'danger' ? 'danger' : 'success');
                flashEl.textContent = message || '';
                flashEl.classList.remove('d-none');
            }

            function renderRow(row) {
                const published = row.getAttribute('data-is-published') === '1';
                const featured = row.getAttribute('data-is-featured') === '1';
                const consent = row.getAttribute('data-publish-consent') === '1';
                const statusEl = row.querySelector('.js-testimonial-status');
                const actionsEl = row.querySelector('.js-testimonial-actions');
                if (!statusEl || !actionsEl) return;

                let statusHtml = published
                    ? '<span class="badge bg-primary">Opublikowana</span>'
                    : '<span class="badge bg-warning text-dark">Szkic</span>';
                if (featured) {
                    statusHtml += ' <span class="badge bg-warning text-dark ms-1" title="Na górze listy na pnedu.pl"><i class="bi bi-star-fill"></i> Wyróżniona</span>';
                }
                statusEl.innerHTML = statusHtml;

                const actions = document.createDocumentFragment();

                function addBtn(className, label, attrs) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = className;
                    b.innerHTML = label;
                    Object.keys(attrs || {}).forEach(function (k) {
                        b.setAttribute(k, attrs[k]);
                    });
                    actions.appendChild(b);
                    actions.appendChild(document.createTextNode(' '));
                }

                addBtn('btn btn-sm btn-outline-primary js-edit-testimonial', 'Edytuj', {});

                if (!published && consent) {
                    addBtn('btn btn-sm btn-success js-testimonial-ajax', 'Publikuj', { 'data-action': 'publish' });
                } else if (published) {
                    if (featured) {
                        addBtn('btn btn-sm btn-outline-warning js-testimonial-ajax', '<i class="bi bi-star-fill"></i> Odznacz', {
                            'data-action': 'unfeature',
                            title: 'Usuń z góry listy na pnedu.pl',
                        });
                    } else {
                        addBtn('btn btn-sm btn-warning js-testimonial-ajax', '<i class="bi bi-star"></i> Wyróżnij', {
                            'data-action': 'feature',
                            title: 'Pokaż na górze na pnedu.pl',
                        });
                    }
                    addBtn('btn btn-sm btn-outline-secondary js-testimonial-ajax', 'Ukryj', { 'data-action': 'unpublish' });
                }

                addBtn('btn btn-sm btn-outline-danger js-delete-testimonial', 'Usuń', {});

                actionsEl.replaceChildren(actions);
            }

            function clearOrphanModalBackdrop() {
                document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }

            function resetModalElement(modalEl) {
                if (!modalEl) return;
                modalEl.classList.remove('show');
                modalEl.style.removeProperty('display');
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                modalEl.removeAttribute('role');
            }

            function hideBootstrapModal(modalEl) {
                return new Promise(function (resolve) {
                    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                        clearOrphanModalBackdrop();
                        resetModalElement(modalEl);
                        resolve();
                        return;
                    }
                    if (!modalEl.classList.contains('show')) {
                        clearOrphanModalBackdrop();
                        resetModalElement(modalEl);
                        resolve();
                        return;
                    }
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                        clearOrphanModalBackdrop();
                        resetModalElement(modalEl);
                        resolve();
                    }, { once: true });
                    modal.hide();
                });
            }

            function showBootstrapModal(modalEl) {
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return null;
                }
                clearOrphanModalBackdrop();
                resetModalElement(modalEl);
                const existing = bootstrap.Modal.getInstance(modalEl);
                if (existing) {
                    existing.dispose();
                }
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
                return modal;
            }

            function removeDeletedTestimonialRow(rowId) {
                const row = rowId
                    ? document.querySelector('.js-testimonial-row[data-id="' + rowId + '"]')
                    : null;
                if (!row) return;

                const tbody = row.closest('tbody');
                row.remove();
                if (!tbody || tbody.querySelectorAll('.js-testimonial-row').length > 0) {
                    return;
                }

                const pageUrl = new URL(window.location.href);
                const page = parseInt(pageUrl.searchParams.get('page') || '1', 10);
                if (page > 1) {
                    pageUrl.searchParams.set('page', String(page - 1));
                    window.location.href = pageUrl.toString();
                    return;
                }
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Brak rekomendacji w tym filtrze.</td></tr>';
            }

            document.querySelectorAll('.js-testimonial-row').forEach(renderRow);

            const editModal = document.getElementById('editTestimonialModal');
            const deleteModal = document.getElementById('deleteTestimonialModal');
            [editModal, deleteModal].forEach(function (modalEl) {
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
            });

            let deleteTargetRowId = null;

            function openEditTestimonialModal(row) {
                if (!row || !editModal) return;
                let payload = {};
                try {
                    payload = JSON.parse(row.getAttribute('data-edit-payload') || '{}');
                } catch (e) {
                    payload = {};
                }
                document.getElementById('editTestimonialForm').action = payload.updateUrl || '';
                document.getElementById('edit_quote').value = payload.quote || '';
                document.getElementById('edit_author_name').value = payload.authorName || '';
                document.getElementById('edit_author_role').value = payload.authorRole || '';
                document.getElementById('edit_author_city').value = payload.authorCity || '';
                document.getElementById('edit_rating').value = payload.rating != null ? String(payload.rating) : '';
                showBootstrapModal(editModal);
            }

            function openDeleteTestimonialModal(row) {
                if (!row || !deleteModal) return;
                deleteTargetRowId = row.getAttribute('data-id');
                document.getElementById('deleteTestimonialForm').action = row.getAttribute('data-delete-url') || '';
                document.getElementById('deleteTestimonialAuthor').textContent = row.getAttribute('data-author-name') || '';
                showBootstrapModal(deleteModal);
            }

            document.addEventListener('click', function (event) {
                const editBtn = event.target.closest('.js-edit-testimonial');
                if (editBtn) {
                    event.preventDefault();
                    openEditTestimonialModal(editBtn.closest('.js-testimonial-row'));
                    return;
                }

                const deleteBtn = event.target.closest('.js-delete-testimonial');
                if (deleteBtn) {
                    event.preventDefault();
                    openDeleteTestimonialModal(deleteBtn.closest('.js-testimonial-row'));
                    return;
                }
            });

            if (deleteModal) {
                deleteModal.addEventListener('hidden.bs.modal', function () {
                    deleteTargetRowId = null;
                    clearOrphanModalBackdrop();
                    resetModalElement(deleteModal);
                });
            }

            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function () {
                    clearOrphanModalBackdrop();
                    resetModalElement(editModal);
                });
            }

            document.addEventListener('click', function (event) {
                const btn = event.target.closest('.js-testimonial-ajax');
                if (!btn) return;
                const row = btn.closest('.js-testimonial-row');
                if (!row) return;

                const action = btn.getAttribute('data-action');
                const urlMap = {
                    publish: row.getAttribute('data-publish-url'),
                    unpublish: row.getAttribute('data-unpublish-url'),
                    feature: row.getAttribute('data-feature-url'),
                    unfeature: row.getAttribute('data-unfeature-url'),
                };
                const url = urlMap[action];
                if (!url) return;

                btn.disabled = true;
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        const data = result.data || {};
                        showFlash(data.message || (result.ok ? 'Zapisano.' : 'Nie udało się zapisać.'), result.ok ? 'success' : 'danger');
                        if (!result.ok || !data.success || !data.testimonial) {
                            btn.disabled = false;
                            return;
                        }
                        row.setAttribute('data-is-published', data.testimonial.is_published ? '1' : '0');
                        row.setAttribute('data-is-featured', data.testimonial.is_featured ? '1' : '0');
                        row.setAttribute('data-publish-consent', data.testimonial.publish_consent ? '1' : '0');
                        if (data.urls) {
                            if (data.urls.publish) row.setAttribute('data-publish-url', data.urls.publish);
                            if (data.urls.unpublish) row.setAttribute('data-unpublish-url', data.urls.unpublish);
                            if (data.urls.feature) row.setAttribute('data-feature-url', data.urls.feature);
                            if (data.urls.unfeature) row.setAttribute('data-unfeature-url', data.urls.unfeature);
                        }
                        if (featuredCountEl && typeof data.featured_count === 'number') {
                            featuredCountEl.textContent = String(data.featured_count);
                        }
                        renderRow(row);
                    })
                    .catch(function () {
                        showFlash('Błąd połączenia — spróbuj ponownie.', 'danger');
                        btn.disabled = false;
                    });
            });

            const deleteForm = document.getElementById('deleteTestimonialForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    const url = deleteForm.action;
                    if (!url) return;

                    const submitBtn = deleteForm.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;

                    fetch(url, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                return { ok: res.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            const data = result.data || {};
                            if (!result.ok || !data.success) {
                                showFlash(data.message || 'Nie udało się usunąć rekomendacji.', 'danger');
                                if (submitBtn) submitBtn.disabled = false;
                                return;
                            }

                            const rowId = deleteTargetRowId;
                            hideBootstrapModal(deleteModal).then(function () {
                                showFlash(data.message || 'Rekomendacja usunięta.', 'success');

                                if (featuredCountEl && typeof data.featured_count === 'number') {
                                    featuredCountEl.textContent = String(data.featured_count);
                                }

                                removeDeletedTestimonialRow(rowId);
                                deleteTargetRowId = null;
                                if (submitBtn) submitBtn.disabled = false;
                            });
                        })
                        .catch(function () {
                            showFlash('Błąd połączenia — spróbuj ponownie.', 'danger');
                            if (submitBtn) submitBtn.disabled = false;
                        });
                });
            }

            const shouldReopenEdit = @json($reopenEditTestimonial);
            if (shouldReopenEdit && typeof bootstrap !== 'undefined' && editModal) {
                document.getElementById('edit_quote').value = @json(old('quote'));
                document.getElementById('edit_author_name').value = @json(old('author_name'));
                document.getElementById('edit_author_role').value = @json(old('author_role'));
                document.getElementById('edit_author_city').value = @json(old('author_city'));
                document.getElementById('edit_rating').value = @json(old('rating'));
                showBootstrapModal(editModal);
            }
        });
    </script>
    @endpush
</x-app-layout>
