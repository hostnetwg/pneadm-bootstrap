@php
    $productItems = $zamowienie->orderItems;
    $recipients = $productItems->flatMap->recipients;
    $activeRecipients = $recipients->reject(fn ($recipient) => $recipient->isCancelled());
    $succeededRecipients = $activeRecipients->filter(
        fn ($recipient) => $recipient->fulfillments->contains('status', \App\Models\OrderFulfillment::STATUS_SUCCEEDED)
    );
    $pendingRecipients = $activeRecipients->reject(
        fn ($recipient) => $recipient->fulfillments->contains('status', \App\Models\OrderFulfillment::STATUS_SUCCEEDED)
    );
    $succeeded = $succeededRecipients->count();
    $pendingCount = $pendingRecipients->count();
    $allSucceeded = $activeRecipients->isNotEmpty() && $succeeded === $activeRecipients->count();
    $canRevoke = auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin');
@endphp

<div id="formOrderProductFulfillmentRoot"
     data-fulfillment-partial-url="{{ route('form-orders.product-fulfillment', $zamowienie->id) }}">
<div class="card mb-3">
    <div class="card-header bg-success text-white py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-key"></i> DOSTĘPY DO PRODUKTU</h6>
        <span class="badge bg-light text-success">{{ $succeeded }}/{{ $activeRecipients->count() }}</span>
    </div>
    <div class="card-body py-2">
        <div class="small mb-3">
            @if($zamowienie->legal_confirmation_sent_at)
                <span class="badge text-bg-success">Potwierdzenie przekazane do poczty</span>
                {{ $zamowienie->legal_confirmation_sent_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                <div class="text-muted">To oznacza przyjęcie przez mailer, nie potwierdzone doręczenie do skrzynki klienta.</div>
            @elseif($zamowienie->legal_confirmation_failed_at)
                <span class="badge text-bg-danger">Błąd wysyłki potwierdzenia</span>
                {{ $zamowienie->legal_confirmation_failed_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                @if(filled($zamowienie->legal_confirmation_error))
                    <div class="text-danger">{{ $zamowienie->legal_confirmation_error }}</div>
                @endif
                <div class="text-muted">Ponów: <code>php artisan legal:retry-product-confirmations</code> na pnedu.</div>
            @else
                <span class="badge text-bg-secondary">Potwierdzenie jeszcze nie przekazane</span>
            @endif
            @if($item = $productItems->first())
                @if($item->is_extension)
                    <div class="mt-1">Przedłużenie
                        @if($item->previous_access_expires_at)
                            od {{ $item->previous_access_expires_at->timezone('Europe/Warsaw')->format('d.m.Y') }}
                        @endif
                    </div>
                @endif
                @if($item->satisfaction_guarantee_days)
                    <div>Gwarancja z tego zakupu: {{ $item->satisfaction_guarantee_days }} dni (snapshot).</div>
                @endif
            @endif
        </div>
        @foreach($productItems as $item)
            <div class="mb-3">
                <strong>{{ $item->product_name }}</strong>
                <div class="small text-muted">
                    {{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2, ',', ' ') }} PLN
                    @if($item->access_starts_at)
                        · start {{ $item->access_starts_at->timezone('Europe/Warsaw')->format('d.m.Y') }}
                    @endif
                    @if($item->access_policy === 'unlimited')
                        · bezterminowo
                    @elseif($item->access_policy === 'duration_from_grant')
                        · {{ $item->access_duration_value }} {{ $item->access_duration_unit }} od startu dostępu
                    @elseif($item->access_policy === 'fixed_until' && $item->access_expires_at)
                        · do {{ $item->access_expires_at->timezone('Europe/Warsaw')->format('d.m.Y') }}
                    @endif
                    @if(filled($item->access_note))
                        · {{ $item->access_note }}
                    @endif
                </div>
            </div>

            @forelse($item->recipients as $recipient)
                @php
                    $fulfillment = $recipient->fulfillments->sortByDesc('id')->first();
                    $isSucceeded = $fulfillment?->status === \App\Models\OrderFulfillment::STATUS_SUCCEEDED;
                    $courseId = (int) ($item->metadata['online_course_id'] ?? 0);
                    $enrollmentId = (int) ($fulfillment?->online_course_enrollment_id ?? 0);
                    $fullName = trim($recipient->first_name.' '.$recipient->last_name) ?: '—';
                @endphp
                <div class="border rounded p-2 mb-2 bg-light bg-opacity-50" data-recipient-card="{{ (int) $recipient->id }}">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div>
                            <strong>{{ $fullName }}</strong>
                            @if($item->recipients->count() > 1)
                                <span class="badge bg-secondary ms-1">{{ $loop->iteration }}</span>
                            @endif
                            @if($recipient->isCancelled())
                                <span class="badge bg-secondary ms-1">Rezygnacja uczestnika</span>
                            @elseif($isSucceeded)
                                <span class="badge bg-success ms-1">Dostęp nadany</span>
                            @elseif($fulfillment?->status === \App\Models\OrderFulfillment::STATUS_FAILED)
                                <span class="badge bg-danger ms-1">Błąd</span>
                            @elseif($fulfillment?->status === \App\Models\OrderFulfillment::STATUS_PROCESSING)
                                <span class="badge bg-info text-dark ms-1">W trakcie</span>
                            @else
                                <span class="badge bg-secondary ms-1">Oczekuje</span>
                            @endif
                        </div>
                    </div>
                    <div class="small mb-2">
                        <i class="bi bi-envelope"></i>
                        <a href="mailto:{{ $recipient->email }}" class="text-decoration-none">{{ $recipient->email }}</a>
                    </div>
                    @if($fulfillment?->granted_at)
                        <div class="small text-muted mb-2">
                            Nadano: {{ $fulfillment->granted_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                            @if($courseId && $enrollmentId)
                                · <a href="{{ route('online-courses.enrollments.edit', [$courseId, $enrollmentId]) }}">karta dostępu</a>
                            @endif
                        </div>
                    @endif
                    @if($fulfillment?->error_message)
                        <div class="small text-danger mb-2">{{ $fulfillment->error_message }}</div>
                    @endif

                    @if(! $recipient->isCancelled() && ! $isSucceeded)
                        <button type="button"
                                class="btn btn-warning btn-sm w-100 js-product-fulfill-btn"
                                onclick="fulfillProductAccess({{ $zamowienie->id }}, { recipientId: {{ (int) $recipient->id }} })">
                            <i class="bi bi-plus-circle"></i> Nadaj dostęp
                        </button>
                        <div id="productFulfillResult_{{ (int) $recipient->id }}" class="js-product-fulfill-result mt-2"></div>
                    @elseif(! $recipient->isCancelled() && $canRevoke)
                        <button type="button"
                                class="btn btn-outline-danger btn-sm w-100 js-revoke-product-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#revokeProductAccessModal"
                                data-recipient-id="{{ (int) $recipient->id }}"
                                data-participant-name="{{ $fullName }}"
                                data-participant-email="{{ $recipient->email }}">
                            <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-muted small mb-0">Brak uczestników w zamówieniu.</p>
            @endforelse
        @endforeach

        @if($pendingCount > 1)
            <div class="mt-2">
                <button type="button"
                        class="btn w-100 js-product-fulfill-btn text-white"
                        style="background-color: #c77700; border-color: #a86300;"
                        onclick="fulfillProductAccess({{ $zamowienie->id }}, {})">
                    <i class="bi bi-people"></i> Nadaj dostęp wszystkim ({{ $pendingCount }})
                </button>
                <div id="productFulfillResultAll" class="js-product-fulfill-result mt-2"></div>
            </div>
        @endif

        @if($allSucceeded)
            <div class="alert alert-success mb-2 mt-2 py-2">
                <i class="bi bi-check-circle"></i>
                <strong>Dostęp został nadany wszystkim uczestnikom.</strong>
                @if($zamowienie->pnedu_provisioned_at)
                    <small class="d-block text-muted mt-1">
                        Data: {{ $zamowienie->pnedu_provisioned_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                    </small>
                @endif
                @if($canRevoke && $succeeded > 1)
                    <div class="mt-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger js-revoke-product-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#revokeProductAccessModal"
                                data-revoke-all="1"
                                data-participant-name="wszyscy uczestnicy ({{ $succeeded }})"
                                data-participant-email="">
                            <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp wszystkim
                        </button>
                    </div>
                @endif
            </div>
        @elseif($canRevoke && $succeeded > 1)
            <div class="mt-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger js-revoke-product-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#revokeProductAccessModal"
                        data-revoke-all="1"
                        data-participant-name="wszyscy z nadanym dostępem ({{ $succeeded }})"
                        data-participant-email="">
                    <i class="bi bi-arrow-clockwise"></i> Wycofaj dostęp wszystkim ({{ $succeeded }})
                </button>
            </div>
        @endif

        <p class="small text-muted mb-0 mt-2">
            Faktura i dostęp są niezależnymi akcjami. Ponowne nadanie po wycofaniu tworzy dostęp na aktualny e-mail uczestnika.
            Konto pnedu.pl nie jest usuwane.
        </p>
        <div id="productFulfillResult" class="mt-2"></div>
    </div>
</div>
</div>
