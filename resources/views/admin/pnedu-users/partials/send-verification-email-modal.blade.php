@if($verificationEmailPreview)
    <form method="post"
          id="pnedu-send-verification-email-form"
          action="{{ route('admin.pnedu-users.send-verification-email', ['pnedu_user' => $user->getKey()]) }}">
        @csrf
    </form>

    <button type="button"
            class="btn btn-outline-primary btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#pneduSendVerificationEmailModal">
        <i class="bi bi-envelope-check me-1"></i> Wyślij link weryfikacyjny
    </button>

    <div class="modal fade" id="pneduSendVerificationEmailModal" tabindex="-1" aria-labelledby="pneduSendVerificationEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="pneduSendVerificationEmailModalLabel">
                        Podgląd wiadomości weryfikacyjnej
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        To wiadomość <strong>ponownego przypomnienia</strong> (inna treść niż automatyczny mail po rejestracji).
                        Zostanie wysłana na <strong>{{ $verificationEmailPreview['to'] }}</strong>.
                        Po potwierdzeniu użytkownik otrzyma dokładnie ten link weryfikacyjny.
                    </p>

                    <div class="border rounded bg-light p-3">
                        <dl class="row mb-3 small">
                            <dt class="col-sm-3 text-muted">Do</dt>
                            <dd class="col-sm-9 mb-2">{{ $verificationEmailPreview['to'] }}</dd>

                            <dt class="col-sm-3 text-muted">Od</dt>
                            <dd class="col-sm-9 mb-2">
                                {{ $verificationEmailPreview['from_name'] }}
                                &lt;{{ $verificationEmailPreview['from_address'] }}&gt;
                            </dd>

                            <dt class="col-sm-3 text-muted">Reply-To</dt>
                            <dd class="col-sm-9 mb-2">
                                {{ $verificationEmailPreview['reply_to_name'] }}
                                &lt;{{ $verificationEmailPreview['reply_to_address'] }}&gt;
                            </dd>

                            <dt class="col-sm-3 text-muted">Temat</dt>
                            <dd class="col-sm-9 mb-0 fw-semibold">{{ $verificationEmailPreview['subject'] }}</dd>
                        </dl>

                        <div class="bg-white border rounded p-3">
                            <p class="mb-3">{{ $verificationEmailPreview['intro'] }}</p>
                            <p class="mb-3">{{ $verificationEmailPreview['context'] }}</p>
                            <p class="mb-3">{{ $verificationEmailPreview['action_prompt'] }}</p>
                            <p class="mb-3">
                                <a href="{{ $verificationEmailPreview['action_url'] }}"
                                   class="btn btn-primary btn-sm"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    {{ $verificationEmailPreview['action_label'] }}
                                </a>
                            </p>
                            <p class="mb-3 text-muted small">Link weryfikacyjny (pełny adres):</p>
                            <p class="mb-3">
                                <code class="user-select-all d-block text-break small">{{ $verificationEmailPreview['action_url'] }}</code>
                            </p>
                            <p class="mb-0 text-muted small">{{ $verificationEmailPreview['outro'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" form="pnedu-send-verification-email-form" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Wyślij e-mail
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
