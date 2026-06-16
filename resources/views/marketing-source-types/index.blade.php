<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Typy źródeł marketingowych') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0">Typy źródeł marketingowych</h3>
                <a href="{{ route('marketing-source-types.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Dodaj typ źródła
                </a>
            </div>

            <div class="alert alert-light border mb-4 small">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle text-primary"></i> Jak ustawiać typy źródeł (UTM)</h6>
                <p class="mb-2">Typ źródła określa domyślne parametry w <strong>generowanym linku kampanii</strong> na pnedu.pl:</p>
                <ul class="mb-2">
                    <li><code>utm_source</code> — <strong>platforma</strong> (np. <code>newsletter</code>, <code>facebook</code>, <code>pnedu</code>). Nie wpisuj adresu e-mail — adres nadawcy opisz w <em>Nazwie</em> typu.</li>
                    <li><code>utm_medium</code> — <strong>rodzaj kanału</strong> (np. <code>email</code>, <code>paid</code>, <code>social</code>). Domyślna wartość z kolumny poniżej; kampania może ją nadpisać.</li>
                    <li><code>utm_content</code> — <strong>taktyka / wariant</strong> (np. <code>prospecting</code>, <code>remarketing</code>) — domyślnie z typu źródła; kampania może nadpisać.</li>
                    <li><code>utm_campaign</code> — ustawiany w <a href="{{ route('marketing-campaigns.index') }}">kampanii</a> jako <em>Kod kampanii</em>, nie w typie źródła.</li>
                </ul>
                <p class="mb-2">Nowe kampanie: kopiuj link <strong>UTM</strong> lub <strong>krótki</strong> (social media). Nieużywane typy <strong>dezaktywuj</strong> (nie usuwaj — historia kampanii).</p>
                <p class="mb-2"><i class="bi bi-arrows-move text-primary"></i> <strong>Kolejność na liście</strong> ustawiasz przeciągając wiersze lub strzałkami — ta sama kolejność pojawia się w polu <em>Typ źródła</em> przy tworzeniu i edycji kampanii.</p>
                <p class="mb-0">
                    <a href="{{ route('marketing.help.links') }}" class="text-decoration-none">Pomoc: jak działają linki i UTM</a>
                    <span class="text-muted"> · dokumentacja techniczna: <code>docs/MARKETING.md</code></span>
                </p>
            </div>

            @if($sourceTypes->count() > 0)
                <div id="source-types-reorder-root"
                     data-reorder-url="{{ route('marketing-source-types.reorder') }}">
                    <div id="source-types-reorder-toast"
                         class="alert alert-success alert-dismissible fade show d-none mb-2 py-2"
                         role="alert">
                        <span data-toast-body></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                    </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width: 5rem;" title="Kolejność w polu Typ źródła w kampaniach">Kolej.</th>
                                <th>Nazwa</th>
                                <th>Slug</th>
                                <th><code>utm_source</code></th>
                                <th><code>utm_medium</code></th>
                                <th><code>utm_content</code></th>
                                <th>Status</th>
                                <th class="text-center" title="Kampanie marketingowe (cała historia)">Kamp.</th>
                                <th class="text-center" title="Zamówienia przypisane do kampanii tego typu — cała historia, nie zależy od okresu w lejku">Zam.</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody id="source-types-sortable">
                            @foreach($sourceTypes as $sourceType)
                                @php
                                    $mediumLabel = $utmMediumOptions[$sourceType->default_utm_medium] ?? $sourceType->default_utm_medium;
                                    $missingUtm = blank($sourceType->utm_source);
                                @endphp
                                <tr class="source-type-row {{ !$sourceType->is_active ? 'table-secondary' : '' }}{{ $missingUtm ? ' table-warning' : '' }}"
                                    data-source-type-id="{{ $sourceType->id }}">
                                    <td class="text-center text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button type="button"
                                                    class="btn btn-sm btn-light source-type-drag-handle border-0 px-1"
                                                    title="Przeciągnij, aby zmienić kolejność"
                                                    aria-label="Przeciągnij typ źródła {{ $sourceType->name }}">
                                                <i class="bi bi-grip-vertical text-secondary"></i>
                                            </button>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Przesuń w górę lub w dół">
                                                <button type="button"
                                                        class="btn btn-outline-secondary btn-source-type-move-up py-0 px-1"
                                                        title="Wyżej"
                                                        aria-label="Przesuń wyżej">
                                                    <i class="bi bi-chevron-up"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-secondary btn-source-type-move-down py-0 px-1"
                                                        title="Niżej"
                                                        aria-label="Przesuń niżej">
                                                    <i class="bi bi-chevron-down"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $sourceType->name }}</strong>
                                        @if($sourceType->description)
                                            <div class="text-muted small text-truncate" style="max-width: 220px;" title="{{ $sourceType->description }}">
                                                {{ Str::limit($sourceType->description, 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td><code class="small">{{ $sourceType->slug }}</code></td>
                                    <td>
                                        @if(filled($sourceType->utm_source))
                                            <code>{{ $sourceType->utm_source }}</code>
                                        @else
                                            <span class="badge bg-warning text-dark" title="Uzupełnij w edycji — generator użyje slug lub mapowania domyślnego">brak</span>
                                        @endif
                                    </td>
                                    <td>
                                        <code>{{ $sourceType->default_utm_medium }}</code>
                                        <span class="text-muted small d-block">{{ $mediumLabel }}</span>
                                    </td>
                                    <td>
                                        @if(filled($sourceType->default_utm_content))
                                            <code>{{ $sourceType->default_utm_content }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sourceType->is_active)
                                            <span class="badge bg-success">Aktywny</span>
                                        @else
                                            <span class="badge bg-secondary">Wyłączony</span>
                                        @endif
                                        <span class="badge ms-1" style="background-color: {{ $sourceType->color }}; color: white;" title="Kolor w UI">■</span>
                                    </td>
                                    <td class="text-center">
                                        @if($sourceType->marketing_campaigns_count > 0)
                                            <a href="{{ route('marketing-campaigns.index', ['source_type_id' => $sourceType->id]) }}"
                                               class="badge bg-primary text-decoration-none"
                                               title="Pokaż kampanie tego typu">
                                                {{ $sourceType->marketing_campaigns_count }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary">0</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">{{ $sourceType->form_orders_count ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('marketing-source-types.show', $sourceType) }}" class="btn btn-sm btn-outline-primary" title="Podgląd">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('marketing-source-types.edit', $sourceType) }}" class="btn btn-sm btn-outline-warning" title="Edytuj UTM">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal{{ $sourceType->id }}"
                                                    title="Usuń">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div>

                <p class="small text-muted mt-2 mb-0">
                    Wiersze na żółto: brak <code>utm_source</code>. Wiersze szare: typ wyłączony (nie pojawia się przy tworzeniu nowej kampanii).
                    Kolumny <strong>Kamp.</strong> i <strong>Zam.</strong> — liczniki za <strong>całą historię</strong> (inaczej niż w <a href="{{ route('marketing-funnel.index') }}">lejku konwersji</a>, gdzie okres wybierasz w filtrze dat).
                </p>

                <style>
                    #source-types-sortable .source-type-row.sortable-ghost {
                        opacity: 0.45;
                        background-color: var(--bs-warning-bg-subtle, #fff3cd);
                    }
                    #source-types-sortable .source-type-row.sortable-chosen {
                        box-shadow: inset 0 0 0 2px var(--bs-primary);
                    }
                    .source-type-drag-handle { cursor: grab; line-height: 1; }
                    .source-type-drag-handle:active { cursor: grabbing; }
                </style>

                @include('marketing-source-types.partials.index-sortable')
            @else
                <div class="text-center py-5">
                    <i class="bi bi-tags fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Brak typów źródeł</h4>
                    <p class="text-muted">Dodaj pierwszy typ źródła, aby rozpocząć kategoryzację kampanii.</p>
                    <a href="{{ route('marketing-source-types.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Dodaj pierwszy typ źródła
                    </a>
                </div>
            @endif

            {{-- Modale potwierdzenia usunięcia typów źródeł --}}
            @foreach ($sourceTypes as $sourceType)
            <div class="modal fade" id="deleteModal{{ $sourceType->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $sourceType->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel{{ $sourceType->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć typ źródła <strong>#{{ $sourceType->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły typu źródła:</h6>
                                <ul class="mb-0">
                                    <li><strong>Nazwa:</strong> {{ $sourceType->name }}</li>
                                    <li><strong>utm_source:</strong> {{ $sourceType->utm_source ?: '—' }}</li>
                                    <li><strong>utm_medium:</strong> {{ $sourceType->default_utm_medium }}</li>
                                    <li><strong>Opis:</strong> {{ $sourceType->description ? Str::limit($sourceType->description, 100) : 'Brak' }}</li>
                                    <li><strong>Status:</strong> {{ $sourceType->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Typ źródła zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('marketing-source-types.destroy', $sourceType) }}"
                                  method="POST"
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń typ źródła
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
