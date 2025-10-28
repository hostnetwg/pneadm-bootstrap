<section class="mb-4">
    <header>
        <h2 class="fs-4 fw-medium text-dark">
            Usuń konto
        </h2>

        <p class="mt-2 text-muted small">
            Po usunięciu konta wszystkie jego zasoby i dane zostaną trwale usunięte. Przed usunięciem konta pobierz wszystkie dane lub informacje, które chcesz zachować.
        </p>
    </header>

    <button
        class="btn btn-danger mt-3"
        data-bs-toggle="modal"
        data-bs-target="#confirmUserDeletionModal"
    >
        Usuń konto
    </button>

    <!-- Modal -->
    <div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')

                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmUserDeletionModalLabel">
                            Czy na pewno chcesz usunąć swoje konto?
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted small">
                            Po usunięciu konta wszystkie jego zasoby i dane zostaną trwale usunięte. Wprowadź swoje hasło, aby potwierdzić, że chcesz trwale usunąć swoje konto.
                        </p>

                        <div class="mt-3">
                            <label for="password" class="form-label">Hasło</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="form-control"
                                placeholder="Hasło"
                            />
                            @if ($errors->userDeletion->has('password'))
                                <div class="invalid-feedback">
                                    {{ $errors->userDeletion->first('password') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Anuluj
                        </button>
                        <button type="submit" class="btn btn-danger">
                            Usuń konto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
