<div class="modal fade" id="formConfirmModal" tabindex="-1" aria-labelledby="formConfirmModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="formConfirmModalTitle">Potwierdzenie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body" id="formConfirmModalBody" style="white-space: pre-line;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Anuluj
                </button>
                <button type="button" class="btn btn-primary" id="formConfirmModalSubmit">Potwierdź</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        (function wireFormConfirmModal() {
            var modalEl = document.getElementById('formConfirmModal');
            if (!modalEl || typeof bootstrap === 'undefined') {
                return;
            }

            var headerEl = modalEl.querySelector('.modal-header');
            var titleEl = document.getElementById('formConfirmModalTitle');
            var bodyEl = document.getElementById('formConfirmModalBody');
            var submitBtn = document.getElementById('formConfirmModalSubmit');
            var closeBtn = modalEl.querySelector('.btn-close');
            var pendingForm = null;

            modalEl.addEventListener('show.bs.modal', function(event) {
                var trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                var title = trigger.getAttribute('data-confirm-title') || 'Potwierdzenie';
                var message = trigger.getAttribute('data-confirm-message') || 'Czy na pewno chcesz kontynuować?';
                try {
                    var parsedMessage = JSON.parse(message);
                    if (typeof parsedMessage === 'string') {
                        message = parsedMessage;
                    }
                } catch (parseError) {
                    message = message.replace(/\\n/g, '\n');
                }
                var formSelector = trigger.getAttribute('data-confirm-form');
                var btnClass = trigger.getAttribute('data-confirm-btn-class') || 'btn-primary';
                var btnText = trigger.getAttribute('data-confirm-btn-text') || 'Potwierdź';
                var headerClass = trigger.getAttribute('data-confirm-header-class') || 'bg-primary text-white';

                titleEl.textContent = title;
                bodyEl.textContent = message;

                pendingForm = formSelector ? document.querySelector(formSelector) : trigger.closest('form');
                if (!pendingForm) {
                    pendingForm = null;
                }

                headerEl.className = 'modal-header ' + headerClass;
                if (closeBtn) {
                    closeBtn.classList.toggle('btn-close-white', headerClass.indexOf('text-white') !== -1);
                }

                submitBtn.className = 'btn ' + btnClass;
                submitBtn.textContent = btnText;
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                pendingForm = null;
            });

            submitBtn.addEventListener('click', function() {
                if (pendingForm) {
                    pendingForm.submit();
                }
                var instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) {
                    instance.hide();
                }
            });
        })();
    </script>
    @endpush
@endonce
