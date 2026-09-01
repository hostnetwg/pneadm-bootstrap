<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">Import wyciągu mBank</h2>
            <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Wróć do windykacji
            </a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
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

            @if(!empty($coverageGaps))
                <div class="alert alert-warning" role="alert">
                    <div class="fw-semibold mb-1">Luki w okresach wyciągów</div>
                    <p class="small mb-2">
                        Porównanie pól <code>#Za okres</code> ze wszystkich importów (nakładające się / stykające się okresy łączymy).
                        Brak pokrycia do dziś też jest zgłaszany.
                    </p>
                    <ul class="mb-0 small">
                        @foreach($coverageGaps as $gap)
                            <li>
                                @if($gap['from'] === $gap['to'])
                                    {{ $gap['from'] }}
                                @else
                                    {{ $gap['from'] }} → {{ $gap['to'] }}
                                    <span class="text-muted">({{ $gap['days'] }} dni)</span>
                                @endif
                                @if(!empty($gap['trailing']))
                                    <span class="badge text-bg-light border ms-1">do dziś</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header fw-semibold">Wgraj CSV mBank</div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Eksport z mBank: <code>lista_operacji_*.csv</code> (UTF-8, separator <code>;</code>).
                                Po imporcie dopasowania zatwierdzasz ręcznie — bez rejestracji wpłat w iFirma.
                                Duży plik (kilka tysięcy wierszy) może zająć ok. 1–2 minuty.
                            </p>
                            <form method="POST" action="{{ route('accounting.bank-imports.store') }}" enctype="multipart/form-data" data-loading-submit data-loading-text="Importuję…">
                                @csrf
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Plik CSV</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain" required>
                                </div>
                                <button type="submit" class="btn btn-primary" data-loading-text="Importuję…">Importuj i dopasuj</button>
                                <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary">Windykacja</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">
                    Ostatnie importy
                    <span class="fw-normal text-muted small ms-1">— najedź na <i class="bi bi-info-circle" aria-hidden="true"></i> przy nagłówku, aby zobaczyć wyjaśnienie kolumny</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    @php
                                        $statHeaders = [
                                            ['label' => 'ID', 'tip' => 'Unikalny numer rekordu importu w bazie.'],
                                            ['label' => 'Plik', 'tip' => 'Oryginalna nazwa wgranego pliku CSV z mBank (lista_operacji_*.csv). Kopia pliku jest zapisana na serwerze.'],
                                            ['label' => 'Okres', 'tip' => 'Zakres dat operacji odczytany z wyciągu (#Za okres). Pokazuje, jaki okres obejmuje ten import.'],
                                            ['label' => 'Wiersze', 'tip' => 'Wszystkie operacje w pliku CSV — wpływy i wydatki. Liczone są też wiersze pominięte jako duplikaty (nie trafiły do bazy jako nowe rekordy).'],
                                            ['label' => 'Nowe wpływy', 'tip' => 'Ile wpływów (kwota dodatnia) zapisano przy tym imporcie. Pomija operacje już obecne w bazie z wcześniejszych wyciągów — te trafiają do kolumny Duplikaty i nie są tu liczone.'],
                                            ['label' => 'Sugestie', 'tip' => 'Ile nowych wpływów ma co najmniej jedną automatyczną sugestię dopasowania (FV, NIP, zamówienie itd.). Aktualizowane po „Przelicz sugestie”. To nie jest jeszcze akceptacja — decyzja należy do operatora.'],
                                            ['label' => 'Duplikaty', 'tip' => 'Ile wierszy z pliku pominięto, bo taka sama operacja (data + kwota + znormalizowany opis) była już w bazie z wcześniejszego importu. Nie powstaje drugi przelew.'],
                                            ['label' => 'Przegląd', 'tip' => 'Postęp ręcznej pracy przy wpływach z tego importu. „Do przeglądu: N” — wpływy bez decyzji (sugestia do akceptacji/odrzucenia, brak powiązania lub wolna kwota). „Przejrzany” — każdy wpływ zaakceptowany lub zignorowany. „Brak wpływów” — nie zapisano nowych wpływów (np. same duplikaty).'],
                                            ['label' => 'Kto', 'tip' => 'Użytkownik panelu, który wgrał ten plik CSV.'],
                                            ['label' => 'Kiedy', 'tip' => 'Data i godzina wgrania pliku.'],
                                        ];
                                    @endphp
                                    @foreach($statHeaders as $header)
                                        <th scope="col" class="text-nowrap">
                                            {{ $header['label'] }}
                                            <i class="bi bi-info-circle text-muted ms-1"
                                               role="img"
                                               aria-label="Wyjaśnienie: {{ $header['label'] }}"
                                               tabindex="0"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               data-bs-title="{{ $header['tip'] }}"></i>
                                        </th>
                                    @endforeach
                                    <th scope="col" class="text-nowrap">
                                        <span class="visually-hidden">Akcje</span>
                                        <i class="bi bi-info-circle text-muted"
                                           role="img"
                                           aria-label="Wyjaśnienie: Akcje"
                                           tabindex="0"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           data-bs-title="Podgląd transakcji importu — kolejka dopasowań, akceptacja, ignorowanie, ręczne powiązanie ze sprawą. Usuń — tylko gdy brak zaakceptowanych powiązań."></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($imports as $import)
                                    <tr>
                                        <td>{{ $import->id }}</td>
                                        <td class="small">{{ $import->original_filename }}</td>
                                        <td class="small">
                                            @if($import->period_from || $import->period_to)
                                                {{ $import->period_from?->format('Y-m-d') ?? '—' }}
                                                →
                                                {{ $import->period_to?->format('Y-m-d') ?? '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $import->rows_total }}</td>
                                        <td>
                                            @if((int) $import->rows_incoming > 0)
                                                <span class="fw-semibold">{{ $import->rows_incoming }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td>{{ $import->rows_matched }}</td>
                                        <td>{{ $import->rows_duplicate }}</td>
                                        <td>
                                            @php
                                                $pendingReview = (int) ($import->pending_review_count ?? $import->pendingReviewCount());
                                                $reviewLabel = $import->reviewProgressLabel();
                                            @endphp
                                            @if((int) $import->rows_incoming === 0)
                                                <span class="badge text-bg-light border">{{ $reviewLabel }}</span>
                                            @elseif($pendingReview === 0)
                                                <span class="badge text-bg-success">{{ $reviewLabel }}</span>
                                            @else
                                                <span class="badge text-bg-warning text-dark">{{ $reviewLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="small">{{ $import->uploader?->name ?? '—' }}</td>
                                        <td class="small">{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <a href="{{ route('accounting.bank-imports.show', $import) }}" class="btn btn-sm btn-outline-primary">Podgląd</a>
                                                @if($import->canBeDeleted())
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bankImportDeleteModal"
                                                            data-delete-url="{{ route('accounting.bank-imports.destroy', $import) }}"
                                                            data-delete-summary="Import #{{ $import->id }} · {{ $import->original_filename }} · {{ $import->rows_incoming }} nowych wpływów · {{ $import->rows_total }} wierszy">
                                                        Usuń
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-muted text-center py-4">Brak importów.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($imports->hasPages())
                    <div class="card-footer">{{ $imports->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankImportDeleteModal" tabindex="-1" aria-labelledby="bankImportDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="bankImportDeleteModalLabel">Usunąć import wyciągu?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="bankImportDeleteSummary"></p>
                    <div class="alert alert-warning mb-0 small">
                        Usunięcie kasuje rekord importu oraz powiązane przelewy i sugestie z tego wgrania.
                        Dozwolone tylko gdy <strong>nie ma zaakceptowanych powiązań</strong> ze sprawami.
                        Pusty import (same duplikaty) można usunąć zawsze.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <form method="POST" id="bankImportDeleteForm" data-loading-submit data-loading-text="Usuwam…">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" data-loading-text="Usuwam…">Usuń import</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.bootstrap && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    bootstrap.Tooltip.getOrCreateInstance(el, {
                        trigger: 'hover focus',
                        container: 'body',
                    });
                });
            }

            var deleteModal = document.getElementById('bankImportDeleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    var btn = event.relatedTarget;
                    if (!btn) return;
                    var form = document.getElementById('bankImportDeleteForm');
                    var summary = document.getElementById('bankImportDeleteSummary');
                    if (form) form.setAttribute('action', btn.getAttribute('data-delete-url') || '');
                    if (summary) summary.textContent = btn.getAttribute('data-delete-summary') || '';
                });
            }
        });
    </script>
</x-app-layout>
