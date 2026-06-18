@php
    $currentSortBy = request('sort_by', 'created_at');
    $currentSortOrder = request('sort_order', 'desc');

    $sortLink = function (string $column) use ($currentSortBy, $currentSortOrder) {
        return request()->fullUrlWithQuery([
            'sort_by' => $column,
            'sort_order' => $currentSortBy === $column && $currentSortOrder === 'asc' ? 'desc' : 'asc',
        ]);
    };

    $sortIcon = function (string $column) use ($currentSortBy, $currentSortOrder) {
        if ($currentSortBy !== $column) {
            return '';
        }

        return '<i class="bi bi-arrow-'.($currentSortOrder === 'asc' ? 'up' : 'down').'-short ms-1"></i>';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Kampanie marketingowe') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid col-xl-11 px-0 px-xl-2">
            @include('marketing-campaigns.partials.campaign-index-styles')

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            @if($filteredCourse ?? null)
                <div class="alert alert-info py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <span>
                        Kampanie dla szkolenia <strong>#{{ $filteredCourse->id }}</strong>:
                        {!! \Illuminate\Support\Str::limit(strip_tags($filteredCourse->title), 80) !!}
                    </span>
                    <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-sm btn-outline-secondary">Pokaż wszystkie kampanie</a>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                <div>
                    <h3 class="h4 mb-1 fw-semibold">Kampanie marketingowe</h3>
                    <p class="small text-muted mb-0">
                        <strong>Wejście</strong> — kliknięcia w link kampanii (UTM / skrócony <code>/l/</code>, max 1× gość/kampania/dzień).
                        <strong>Zam.</strong> — cała historia zamówień z tym kodem.
                        <a href="{{ route('marketing-funnel.index') }}">Lejek konwersji</a> ·
                        <a href="{{ route('marketing-source-types.index') }}">Typy źródeł</a> ·
                        <a href="{{ route('marketing.help.links') }}">Pomoc: linki UTM</a>
                    </p>
                </div>
                <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Dodaj kampanię
                </a>
            </div>

            <div class="card shadow-sm mb-4 campaigns-index-filters">
                <div class="card-header bg-white py-2 px-3 border-bottom-0">
                    <span class="small fw-semibold text-muted"><i class="bi bi-funnel"></i> Filtry</span>
                </div>
                <div class="card-body pt-2 pb-3">
                    <form method="GET" action="{{ route('marketing-campaigns.index') }}" class="row g-2 g-lg-3 align-items-end">
                        @if(request()->filled('course_id'))
                            <input type="hidden" name="course_id" value="{{ request('course_id') }}">
                        @endif
                        <div class="col-lg-4 col-md-6">
                            <label for="search" class="form-label">Szukaj</label>
                            <input type="text" class="form-control form-control-sm" id="search" name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Kod, nazwa lub opis…">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="source_type_id" class="form-label">Typ źródła</label>
                            <select class="form-select form-select-sm" id="source_type_id" name="source_type_id">
                                <option value="">Wszystkie typy</option>
                                @foreach($sourceTypes as $sourceType)
                                    <option value="{{ $sourceType->id }}"
                                            {{ (string) request('source_type_id') === (string) $sourceType->id ? 'selected' : '' }}>
                                        {{ $sourceType->name }}@if(!$sourceType->is_active) (wył.)@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select form-select-sm" id="is_active" name="is_active">
                                <option value="">Wszystkie</option>
                                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktywne</option>
                                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nieaktywne</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-3">
                            <label for="per_page" class="form-label">Na str.</label>
                            <select class="form-select form-select-sm" id="per_page" name="per_page">
                                @foreach([10, 20, 50, 100] as $size)
                                    <option value="{{ $size }}" {{ (int) request('per_page', 20) === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-search"></i> Szukaj
                            </button>
                            <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-outline-secondary btn-sm" title="Wyczyść filtry">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if($campaigns->count() > 0)
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0 campaigns-index-table">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-3">
                                        <a href="{{ $sortLink('campaign_code') }}" class="text-dark text-decoration-none">
                                            Kod {!! $sortIcon('campaign_code') !!}
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ $sortLink('name') }}" class="text-dark text-decoration-none">
                                            Nazwa {!! $sortIcon('name') !!}
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ $sortLink('source_type') }}" class="text-dark text-decoration-none">
                                            Źródło {!! $sortIcon('source_type') !!}
                                        </a>
                                    </th>
                                    <th class="text-center">
                                        <a href="{{ $sortLink('is_active') }}" class="text-dark text-decoration-none">
                                            Status {!! $sortIcon('is_active') !!}
                                        </a>
                                    </th>
                                    <th class="text-center" title="Wejścia przez link kampanii — cała historia">
                                        <a href="{{ $sortLink('link_entries_count') }}" class="text-dark text-decoration-none">
                                            Wejś. {!! $sortIcon('link_entries_count') !!}
                                        </a>
                                    </th>
                                    <th class="text-center" title="Zamówienia — cała historia">
                                        <a href="{{ $sortLink('orders_count') }}" class="text-dark text-decoration-none">
                                            Zam. {!! $sortIcon('orders_count') !!}
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ $sortLink('created_at') }}" class="text-dark text-decoration-none">
                                            Utworzono {!! $sortIcon('created_at') !!}
                                        </a>
                                    </th>
                                    <th class="text-end pe-3" style="min-width: 9rem;">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($campaigns as $campaign)
                                    @php
                                        $urls = $campaignUrlsById[$campaign->id] ?? ['utm' => '', 'legacy' => '', 'short' => ''];
                                        $landingLabel = ($campaign->landing_target ?? 'order_form') === 'order_form'
                                            ? 'formularz'
                                            : 'opis';
                                    @endphp
                                    <tr class="{{ !$campaign->is_active ? 'table-secondary' : '' }}">
                                        <td class="ps-3">
                                            <a href="{{ route('marketing-campaigns.show', $campaign) }}"
                                               class="campaign-code-link font-monospace fw-semibold">
                                                {{ $campaign->campaign_code }}
                                            </a>
                                        </td>
                                        <td class="campaign-name-cell">
                                            <div class="text-truncate fw-medium" title="{{ $campaign->name }}">
                                                {{ $campaign->name }}
                                            </div>
                                            @if($campaign->course_id)
                                                <div class="campaign-meta-line text-muted">
                                                    <i class="bi bi-mortarboard"></i>
                                                    #{{ $campaign->course_id }}
                                                    @if($campaign->course)
                                                        · {{ Str::limit(strip_tags($campaign->course->title), 40) }}
                                                    @endif
                                                    · {{ $landingLabel }}
                                                </div>
                                            @else
                                                <div class="campaign-meta-line text-warning-emphasis">
                                                    <i class="bi bi-exclamation-circle"></i> Brak szkolenia — brak linków
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($campaign->sourceType)
                                                <span class="badge rounded-pill text-truncate d-inline-block"
                                                      style="background-color: {{ $campaign->sourceType->color }}; color: #fff; max-width: 160px;"
                                                      title="{{ $campaign->sourceType->name }}"
                                                      data-bs-toggle="tooltip">
                                                    {{ $campaign->sourceType->name }}
                                                </span>
                                                @if(filled($urls['utm_content'] ?? null))
                                                    <span class="badge bg-light text-dark border font-monospace ms-1"
                                                          title="utm_content w linku (GA4)"
                                                          data-bs-toggle="tooltip">
                                                        {{ $urls['utm_content'] }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary rounded-pill">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($campaign->is_active)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktywna</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border">Wył.</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @php $linkEntries = (int) ($campaign->link_entries_total ?? 0); @endphp
                                            @if($linkEntries > 0)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"
                                                      title="Wejścia przez link kampanii">
                                                    {{ $linkEntries }}
                                                </span>
                                            @else
                                                <span class="text-muted small">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(($campaign->form_orders_count ?? 0) > 0)
                                                <a href="{{ route('marketing-campaigns.show', $campaign) }}"
                                                   class="badge bg-primary text-decoration-none"
                                                   title="Podgląd kampanii i zamówień">
                                                    {{ $campaign->form_orders_count }}
                                                </a>
                                            @else
                                                <span class="text-muted small">0</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small text-nowrap">
                                            {{ $campaign->created_at?->format('d.m.Y') }}
                                            <span class="d-none d-md-inline">{{ $campaign->created_at?->format('H:i') }}</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            @include('marketing-campaigns.partials.index-row-actions', [
                                                'campaign' => $campaign,
                                                'urls' => $urls,
                                            ])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div class="small text-muted">
                        {{ $campaigns->firstItem() ?? 0 }}–{{ $campaigns->lastItem() ?? 0 }}
                        z {{ $campaigns->total() }} kampanii
                        @if(request()->hasAny(['search', 'source_type_id', 'is_active']))
                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1">filtrowane</span>
                        @endif
                    </div>
                    <div>{{ $campaigns->links() }}</div>
                </div>
            @else
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-megaphone display-4 text-muted mb-3 d-block"></i>
                        <h4 class="text-muted h5">Brak kampanii</h4>
                        <p class="text-muted small mb-3">
                            @if(request()->hasAny(['search', 'source_type_id', 'is_active']))
                                Brak wyników dla wybranych filtrów.
                                <a href="{{ route('marketing-campaigns.index') }}">Wyczyść filtry</a>
                            @else
                                Dodaj pierwszą kampanię, aby generować linki UTM i śledzić zamówienia.
                            @endif
                        </p>
                        <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Dodaj kampanię
                        </a>
                    </div>
                </div>
            @endif

            @foreach ($campaigns as $campaign)
                <div class="modal fade" id="deleteModal{{ $campaign->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $campaign->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteModalLabel{{ $campaign->id }}">
                                    <i class="bi bi-exclamation-triangle"></i> Usunąć kampanię?
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">Kampania <code>{{ $campaign->campaign_code }}</code></p>
                                <div class="bg-light p-3 rounded small">
                                    <div><strong>Nazwa:</strong> {{ $campaign->name }}</div>
                                    <div><strong>Źródło:</strong> {{ $campaign->sourceType->name ?? '—' }}</div>
                                    <div><strong>Zamówienia:</strong> {{ $campaign->form_orders_count ?? 0 }} (zostaną w bazie)</div>
                                </div>
                                <p class="text-muted small mt-3 mb-0">
                                    Miękkie usunięcie — historia zamówień zachowuje kod <code>{{ $campaign->campaign_code }}</code>.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                                <form action="{{ route('marketing-campaigns.destroy', $campaign) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Usuń
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @include('marketing-campaigns.partials.campaign-links-modal')
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    });
    </script>
    @endpush
</x-app-layout>
