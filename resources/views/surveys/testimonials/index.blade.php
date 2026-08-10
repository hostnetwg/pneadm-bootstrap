<x-app-layout>
    <x-slot name="header">
        Rekomendacje (opinie na pnedu.pl)
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-3">
            Opinie zebrane z bloku „rekomendacja” w ankiecie natywnej. Publikacja na stronie głównej wymaga zgody uczestnika
            oraz Twojego zatwierdzenia. Możesz poprawić treść i dane autora (np. literówki) przed publikacją.
        </p>

        <div class="mb-3 btn-group">
            <a href="{{ route('surveys.testimonials.index') }}" class="btn btn-sm {{ $filter === '' ? 'btn-primary' : 'btn-outline-primary' }}">Wszystkie</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'pending']) }}" class="btn btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Do akceptacji</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'published']) }}" class="btn btn-sm {{ $filter === 'published' ? 'btn-primary' : 'btn-outline-primary' }}">Opublikowane</a>
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
                            <tr>
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
                                <td style="max-width: 360px;">{{ Str::limit($t->quote, 180) }}</td>
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
                                <td>
                                    @if($t->is_published)
                                        <span class="badge bg-primary">Opublikowana</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Szkic</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
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
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary js-edit-testimonial"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTestimonialModal"
                                            data-payload="{{ $editPayloadJson }}">
                                        Edytuj
                                    </button>
                                    @if(!$t->is_published && $t->publish_consent)
                                        <form method="POST" action="{{ route('surveys.testimonials.publish', $t) }}" class="d-inline">
                                            @csrf
                                            @if($filter !== '')
                                                <input type="hidden" name="filter" value="{{ $filter }}">
                                            @endif
                                            <button type="submit" class="btn btn-sm btn-success">Publikuj</button>
                                        </form>
                                    @elseif($t->is_published)
                                        <form method="POST" action="{{ route('surveys.testimonials.unpublish', $t) }}" class="d-inline">
                                            @csrf
                                            @if($filter !== '')
                                                <input type="hidden" name="filter" value="{{ $filter }}">
                                            @endif
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Ukryj</button>
                                        </form>
                                    @endif
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteTestimonialModal"
                                            data-delete-url="{{ route('surveys.testimonials.destroy', $t) }}"
                                            data-author-name="{{ $t->author_name }}">
                                        Usuń
                                    </button>
                                </td>
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

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editModal = document.getElementById('editTestimonialModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    const btn = event.relatedTarget;
                    if (!btn || !btn.classList.contains('js-edit-testimonial')) return;
                    let payload = {};
                    try {
                        payload = JSON.parse(btn.getAttribute('data-payload') || '{}');
                    } catch (e) {
                        payload = {};
                    }
                    document.getElementById('editTestimonialForm').action = payload.updateUrl || '';
                    document.getElementById('edit_quote').value = payload.quote || '';
                    document.getElementById('edit_author_name').value = payload.authorName || '';
                    document.getElementById('edit_author_role').value = payload.authorRole || '';
                    document.getElementById('edit_author_city').value = payload.authorCity || '';
                    document.getElementById('edit_rating').value = payload.rating != null ? String(payload.rating) : '';
                });
            }

            const deleteModal = document.getElementById('deleteTestimonialModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    const btn = event.relatedTarget;
                    if (!btn) return;
                    document.getElementById('deleteTestimonialForm').action = btn.getAttribute('data-delete-url') || '';
                    document.getElementById('deleteTestimonialAuthor').textContent = btn.getAttribute('data-author-name') || '';
                });
            }

            @if($errors->any() && old('_method') === 'PUT' && old('quote') !== null)
            const reopen = document.getElementById('editTestimonialModal');
            if (reopen && typeof bootstrap !== 'undefined') {
                document.getElementById('edit_quote').value = @json(old('quote'));
                document.getElementById('edit_author_name').value = @json(old('author_name'));
                document.getElementById('edit_author_role').value = @json(old('author_role'));
                document.getElementById('edit_author_city').value = @json(old('author_city'));
                document.getElementById('edit_rating').value = @json(old('rating'));
                bootstrap.Modal.getOrCreateInstance(reopen).show();
            }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
