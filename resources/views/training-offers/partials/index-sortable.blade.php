@if($canReorder)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('training-offers-reorder-root');
    const tbody = document.getElementById('training-offers-sortable');
    if (!root || !tbody || typeof Sortable === 'undefined') {
        return;
    }

    const reorderUrl = root.dataset.reorderUrl;
    const toastEl = document.getElementById('training-offers-reorder-toast');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let saving = false;

    function showToast(message, isError) {
        if (!toastEl) {
            return;
        }
        toastEl.className = 'alert alert-dismissible fade show mb-0 py-2 ' + (isError ? 'alert-danger' : 'alert-success');
        const body = toastEl.querySelector('[data-toast-body]');
        if (body) {
            body.textContent = message;
        }
        toastEl.classList.remove('d-none');
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(function () {
            toastEl.classList.add('d-none');
        }, 3500);
    }

    function currentOrder() {
        return Array.from(tbody.querySelectorAll('tr[data-offer-id]'))
            .map(function (row) {
                return parseInt(row.dataset.offerId, 10);
            })
            .filter(function (id) {
                return !Number.isNaN(id);
            });
    }

    async function saveOrder() {
        if (saving || !reorderUrl) {
            return;
        }

        saving = true;
        tbody.classList.add('opacity-75');

        try {
            const response = await fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order: currentOrder() }),
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                showToast(data.message || 'Nie udało się zapisać kolejności.', true);
                return;
            }

            showToast(data.message || 'Kolejność zapisana.', false);
        } catch (error) {
            showToast('Błąd połączenia — kolejność nie została zapisana.', true);
        } finally {
            saving = false;
            tbody.classList.remove('opacity-75');
        }
    }

    function moveRow(row, direction) {
        if (!row) {
            return;
        }
        if (direction < 0 && row.previousElementSibling) {
            tbody.insertBefore(row, row.previousElementSibling);
            saveOrder();
        } else if (direction > 0 && row.nextElementSibling) {
            tbody.insertBefore(row.nextElementSibling, row);
            saveOrder();
        }
    }

    new Sortable(tbody, {
        animation: 150,
        handle: '.training-offer-drag-handle',
        draggable: '.training-offer-row',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        filter: 'input,textarea,select,button:not(.training-offer-drag-handle):not(.btn-training-offer-move-up):not(.btn-training-offer-move-down),a,form',
        preventOnFilter: false,
        onEnd: function () {
            saveOrder();
        },
    });

    tbody.addEventListener('click', function (event) {
        const upBtn = event.target.closest('.btn-training-offer-move-up');
        const downBtn = event.target.closest('.btn-training-offer-move-down');
        if (!upBtn && !downBtn) {
            return;
        }
        event.preventDefault();
        const row = (upBtn || downBtn).closest('tr[data-offer-id]');
        moveRow(row, upBtn ? -1 : 1);
    });
});
</script>
@endpush
@endif
