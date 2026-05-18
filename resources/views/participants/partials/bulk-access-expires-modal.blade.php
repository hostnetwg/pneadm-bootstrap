{{-- Modal: Masowe ustawienie daty wygaśnięcia dostępu --}}
<div class="modal fade" id="bulkAccessExpiresModal" tabindex="-1" aria-labelledby="bulkAccessExpiresModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('participants.bulk-set-access-expires', $course) }}" method="POST" id="bulkAccessExpiresForm">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="bulkAccessExpiresModalLabel">
                        <i class="fas fa-clock me-2"></i> Data wygaśnięcia dostępu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Ustaw tę samą datę wygaśnięcia dostępu do nagrań i materiałów na pnedu.pl
                        dla <strong>wszystkich {{ $courseParticipantsCount }} uczestników</strong> tego szkolenia
                        (niezależnie od filtra wyszukiwania na liście).
                    </p>
                    <div class="mb-3">
                        <label for="bulk_access_expires_at" class="form-label fw-bold">Data i godzina wygaśnięcia</label>
                        <input type="datetime-local"
                               class="form-control"
                               id="bulk_access_expires_at"
                               name="access_expires_at"
                               value="{{ old('access_expires_at') }}">
                        <div class="form-text">Czas lokalny (Polska, Europe/Warsaw), jak przy edycji uczestnika.</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="clear_access_expires" value="1" id="bulk_clear_access_expires" {{ old('clear_access_expires') ? 'checked' : '' }}>
                        <label class="form-check-label" for="bulk_clear_access_expires">
                            Usuń datę wygaśnięcia – <strong>dostęp bezterminowy</strong> dla wszystkich uczestników
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-check me-1"></i> Zastosuj dla wszystkich
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->has('access_expires_at'))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('bulkAccessExpiresModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
@endpush
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var clearCb = document.getElementById('bulk_clear_access_expires');
    var dateInput = document.getElementById('bulk_access_expires_at');
    if (!clearCb || !dateInput) {
        return;
    }
    function sync() {
        var clear = clearCb.checked;
        dateInput.disabled = clear;
        dateInput.required = !clear;
        if (clear) {
            dateInput.value = '';
        }
    }
    clearCb.addEventListener('change', sync);
    sync();
});
</script>
@endpush
