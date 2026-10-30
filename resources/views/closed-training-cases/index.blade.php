<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Sprawy szkoleń zamkniętych</h2>
            <a href="{{ route('closed-training-cases.create') }}" class="btn btn-primary">Nowa sprawa</a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('closed-training-cases.index') }}" class="row g-3 align-items-end">
                        @if($sort)
                            <input type="hidden" name="sort" value="{{ $sort }}">
                            <input type="hidden" name="dir" value="{{ $dir }}">
                        @endif
                        <div class="col-md-5">
                            <label for="q" class="form-label">Szukaj</label>
                            <input type="text" name="q" id="q" value="{{ $search }}" class="form-control" placeholder="SZ-3, temat, placówka, osoba, e-mail">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="open" @selected($status === 'open')>Otwarte</option>
                                <option value="all" @selected($status === 'all')>Wszystkie</option>
                                @foreach(\App\Models\ClosedTrainingCase::statusOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">Filtruj</button>
                            <a href="{{ route('closed-training-cases.index', array_filter(['sort' => $sort, 'dir' => $sort ? $dir : null])) }}" class="btn btn-outline-secondary">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $sortBase = array_filter([
                    'q' => $search !== '' ? $search : null,
                    'status' => $status !== 'open' ? $status : null,
                ]);
                $symbolDir = ($sort === 'id' && $dir === 'asc') ? 'desc' : 'asc';
                $inquiryDir = ($sort === 'inquired_at' && $dir === 'desc') ? 'asc' : 'desc';
                $activityDir = ($sort === 'last_activity' && $dir === 'desc') ? 'asc' : 'desc';
                $symbolSortUrl = route('closed-training-cases.index', array_merge($sortBase, ['sort' => 'id', 'dir' => $symbolDir]));
                $inquirySortUrl = route('closed-training-cases.index', array_merge($sortBase, ['sort' => 'inquired_at', 'dir' => $inquiryDir]));
                $activitySortUrl = route('closed-training-cases.index', array_merge($sortBase, ['sort' => 'last_activity', 'dir' => $activityDir]));
            @endphp

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th style="width: 5.5rem;" title="Sortowanie po numerze sprawy (id)">
                                    <a href="{{ $symbolSortUrl }}" class="text-decoration-none text-dark">
                                        Symbol
                                        @if($sort === 'id')
                                            <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                        @else
                                            <i class="bi bi-caret-down text-muted" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 7.5rem;" title="Data pierwszego zapytania (formularz albo wpis ręczny)">
                                    <a href="{{ $inquirySortUrl }}" class="text-decoration-none text-dark">
                                        Zapytanie
                                        @if($sort === 'inquired_at')
                                            <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                        @else
                                            <i class="bi bi-caret-down text-muted" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="min-width: 15rem;">Temat / placówka</th>
                                <th style="width: 14rem; max-width: 15.5rem;" title="Osoba do kontaktu, na dole cena dla szkoły; (i) = szczegóły placówki i oferty">Kontakt</th>
                                <th style="width: 14.5rem; max-width: 16rem;" title="Termin, instruktor i wynagrodzenie pod nazwiskiem. Zielone / ✓ = potwierdzone">Ustalenia</th>
                                <th style="min-width: 8rem;" title="Status z liczbą wpisów w historii, data ostatniego wpisu i opiekun; sortowanie po dacie">
                                    <a href="{{ $activitySortUrl }}" class="text-decoration-none text-dark">
                                        Ostatni wpis
                                        @if($sort === 'last_activity')
                                            <i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                        @else
                                            <i class="bi bi-caret-down text-muted" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="min-width: 14rem;" title="Czyj ruch + co trzeba zrobić">Ruch</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                @php
                                    $schedule = $case->listScheduleLabelCompact();
                                    $instructor = $case->listInstructorName();
                                    $price = $case->client_price;
                                    $offerTitle = $case->trainingOffer?->title;
                                    $offerUrl = $case->trainingOffer
                                        ? route('training-offers.show', $case->trainingOffer)
                                        : '';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('closed-training-cases.show', $case) }}"
                                           class="font-monospace text-decoration-none fw-semibold"
                                           title="Ten sam symbol jest w temacie e-maila do szkoły">[{{ $case->referenceCode() }}]</a>
                                    </td>
                                    <td class="text-nowrap {{ $case->inquired_at ? '' : 'text-muted' }}" title="Data pierwszego zapytania">
                                        @if($case->inquired_at)
                                            {{ $case->formatLocal($case->inquired_at, 'd.m.Y') }}
                                            <div class="text-muted">{{ $case->formatLocal($case->inquired_at, 'H:i') }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="min-width: 15rem; max-width: 22rem;">
                                        <a href="{{ route('closed-training-cases.show', $case) }}" class="fw-semibold text-decoration-none">{{ $case->trainingTopicLabel() }}</a>
                                        <div class="text-muted">{{ $case->institution ?: '—' }}</div>
                                        @php($messagePreview = trim(preg_replace('/\s+/u', ' ', (string) $case->message)))
                                        @if($messagePreview !== '')
                                            <div class="text-muted text-truncate" style="max-width: 22rem;" title="{{ $messagePreview }}">{{ \Illuminate\Support\Str::limit($messagePreview, 100) }}</div>
                                        @endif
                                    </td>
                                    <td style="width: 14rem; max-width: 15.5rem;">
                                        <div class="border-start border-3 border-primary ps-2 py-1">
                                            <div class="d-flex align-items-start justify-content-between gap-1">
                                                <div class="fw-semibold text-dark text-break">{{ $case->contact_name ?: '—' }}</div>
                                                <button type="button"
                                                        class="btn btn-link p-0 lh-1 flex-shrink-0 text-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#caseInquiryDetailsModal"
                                                        data-symbol="{{ $case->referenceCode() }}"
                                                        data-institution="{{ $case->institution ?: '—' }}"
                                                        data-institution-type="{{ $case->institutionTypeLabel() }}"
                                                        data-council-size="{{ $case->pedagogical_council_size ?: '—' }}"
                                                        data-www="{{ $case->institution_www ?: '' }}"
                                                        data-format="{{ $case->preferredFormatLabel() }}"
                                                        data-offer="{{ $offerTitle ?: '—' }}"
                                                        data-offer-url="{{ $offerUrl }}"
                                                        title="Szczegóły placówki i oferty"
                                                        aria-label="Szczegóły placówki i oferty">
                                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            @if(filled($case->contact_phone))
                                                <div class="mt-1 text-break">
                                                    <a href="tel:{{ preg_replace('/\s+/', '', $case->contact_phone) }}"
                                                       class="text-decoration-none fw-semibold text-dark"
                                                       title="Zadzwoń">
                                                        <i class="bi bi-telephone-fill text-primary me-1" aria-hidden="true"></i>{{ $case->contact_phone }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if(filled($case->contact_email))
                                                <div class="{{ filled($case->contact_phone) ? '' : 'mt-1' }} text-break">
                                                    <a href="mailto:{{ $case->contact_email }}"
                                                       class="text-decoration-none text-primary"
                                                       title="Napisz e-mail">
                                                        <i class="bi bi-envelope-fill me-1" aria-hidden="true"></i>{{ $case->contact_email }}
                                                    </a>
                                                </div>
                                            @elseif(! filled($case->contact_phone))
                                                <div class="text-muted small mt-1">Brak telefonu i e-maila</div>
                                            @endif
                                            <div class="mt-2 text-break {{ $price === null ? 'text-muted' : '' }} {{ $case->client_price_confirmed_at ? 'text-success fw-semibold' : '' }}"
                                                 title="Cena dla szkoły{{ $case->client_price_confirmed_at ? ' — potwierdzona' : '' }}">
                                                <span class="{{ $case->client_price_confirmed_at ? 'text-success' : 'text-muted' }}">CENA:</span>
                                                {{ $case->formatMoneyList($price) }}@if($case->client_price_confirmed_at) ✓@endif
                                            </div>
                                        </div>
                                    </td>
                                    <td style="width: 14.5rem; max-width: 16rem; line-height: 1.35;">
                                        <div class="{{ $schedule === '—' ? 'text-muted' : '' }} text-break" title="{{ $case->listScheduleLabel() }}">
                                            <span class="text-muted">TERMIN:</span> {{ $schedule }}
                                        </div>
                                        <div class="text-break" title="{{ $case->course ? 'Instruktor ze powiązanego szkolenia' : 'Instruktor z ustaleń na sprawie' }}">
                                            @if($instructor === '—')
                                                <span class="text-muted">—</span>
                                            @else
                                                <div>{{ $instructor }}</div>
                                                @if($case->instructor_fee !== null)
                                                    <div class="{{ $case->instructor_fee_confirmed_at ? 'text-success fw-semibold' : 'text-muted' }}"
                                                         title="Wynagrodzenie instruktora ({{ $case->instructorFeeBasisLabel() }}){{ $case->instructor_fee_confirmed_at ? ' — potwierdzone' : '' }}">
                                                        {{ $case->formatInstructorFeeList() }}@if($case->instructor_fee_confirmed_at) ✓@endif
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="mb-1">
                                            <span class="badge {{ $case->statusBadgeClass() }}">{{ $case->statusLabel() }}</span>
                                            <span class="text-muted" title="Liczba działań w historii sprawy">({{ (int) $case->events_count }})</span>
                                        </div>
                                        <div class="text-muted">
                                            @if($case->lastActivityAt())
                                                {{ $case->formatLocal($case->lastActivityAt()) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                        <div class="{{ $case->assignee ? 'text-dark' : 'text-muted' }}" title="Opiekun sprawy">
                                            {{ $case->assignee?->name ?: '—' }}
                                        </div>
                                    </td>
                                    <td class="{{ $case->nextActionIsOverdue() ? 'text-danger' : '' }}" style="min-width: 14rem; max-width: 16rem;">
                                        <div class="mb-1">
                                            <span class="badge {{ $case->waitingOnBadgeClass() }}">{{ $case->waitingOnLabel() }}</span>
                                        </div>
                                        <div style="white-space: normal;" title="{{ $case->next_action }}">{{ $case->next_action ?: '—' }}</div>
                                        @if($case->next_action_at)
                                            <div class="text-muted">{{ $case->formatLocal($case->next_action_at) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted p-4">Brak spraw dla tego filtra.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">{{ $cases->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="caseInquiryDetailsModal" tabindex="-1" aria-labelledby="caseInquiryDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="caseInquiryDetailsModalLabel">Szczegóły zapytania</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Nazwa szkoły</dt>
                        <dd class="col-sm-8" id="caseInquiryInstitution">—</dd>
                        <dt class="col-sm-4 text-muted">Rodzaj szkoły</dt>
                        <dd class="col-sm-8" id="caseInquiryInstitutionType">—</dd>
                        <dt class="col-sm-4 text-muted">Rada pedagogiczna</dt>
                        <dd class="col-sm-8" id="caseInquiryCouncilSize">—</dd>
                        <dt class="col-sm-4 text-muted">WWW</dt>
                        <dd class="col-sm-8" id="caseInquiryWww">—</dd>
                        <dt class="col-sm-4 text-muted">Forma</dt>
                        <dd class="col-sm-8" id="caseInquiryFormat">—</dd>
                        <dt class="col-sm-4 text-muted">Oferta</dt>
                        <dd class="col-sm-8" id="caseInquiryOffer">—</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('caseInquiryDetailsModal')?.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!(button instanceof HTMLElement)) {
                return;
            }

            const symbol = button.getAttribute('data-symbol') || '';
            const title = document.getElementById('caseInquiryDetailsModalLabel');
            if (title) {
                title.textContent = symbol ? `Szczegóły zapytania [${symbol}]` : 'Szczegóły zapytania';
            }

            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = value && value.trim() !== '' ? value : '—';
                }
            };

            setText('caseInquiryInstitution', button.getAttribute('data-institution'));
            setText('caseInquiryInstitutionType', button.getAttribute('data-institution-type'));
            const councilSize = (button.getAttribute('data-council-size') || '').trim();
            setText(
                'caseInquiryCouncilSize',
                councilSize === '' || councilSize === '—' ? '—' : councilSize+' osób'
            );
            setText('caseInquiryFormat', button.getAttribute('data-format'));

            const wwwEl = document.getElementById('caseInquiryWww');
            const www = (button.getAttribute('data-www') || '').trim();
            if (wwwEl) {
                if (www === '') {
                    wwwEl.textContent = '—';
                } else {
                    wwwEl.replaceChildren();
                    const link = document.createElement('a');
                    link.href = www;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = www;
                    wwwEl.appendChild(link);
                }
            }

            const offerEl = document.getElementById('caseInquiryOffer');
            const offer = (button.getAttribute('data-offer') || '').trim();
            const offerUrl = (button.getAttribute('data-offer-url') || '').trim();
            if (offerEl) {
                if (offer === '' || offer === '—') {
                    offerEl.textContent = '—';
                } else if (offerUrl !== '') {
                    offerEl.replaceChildren();
                    const link = document.createElement('a');
                    link.href = offerUrl;
                    link.textContent = offer;
                    offerEl.appendChild(link);
                } else {
                    offerEl.textContent = offer;
                }
            }
        });
    </script>
</x-app-layout>
