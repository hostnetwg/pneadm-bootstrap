<div class="modal fade" id="growth-ai-limit-reset" tabindex="-1" aria-labelledby="growth-ai-limit-reset-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6" id="growth-ai-limit-reset-title">Zresetować dzienny limit AI?</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body small">
                Licznik wróci do zera i znów będzie można prosić AI o propozycje.
                Każde wywołanie przy włączonym AI jest płatne. Zapisane szkice i propozycje zostają bez zmian.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                <form method="POST" action="{{ route('growth.ai.limit.reset') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Zresetuj limit</button>
                </form>
            </div>
        </div>
    </div>
</div>
