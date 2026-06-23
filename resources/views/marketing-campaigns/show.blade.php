<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły kampanii marketingowej') }}
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

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0">Kampania <code>{{ $marketingCampaign->campaign_code }}</code></h4>
                        <div class="small text-muted mt-1">{{ $marketingCampaign->name }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <a href="{{ route('marketing-campaigns.edit', $marketingCampaign) }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-pencil"></i> Edytuj
                        </a>
                        <button type="button" class="btn btn-danger btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i> Usuń
                        </button>
                        <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Lista
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h5 class="h6 text-muted text-uppercase fw-semibold mb-3">Informacje</h5>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted pe-3" style="width: 38%;">Kod kampanii</td>
                                    <td><code>{{ $marketingCampaign->campaign_code }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Typ źródła</td>
                                    <td>
                                        @if($marketingCampaign->sourceType)
                                            <a href="{{ route('marketing-source-types.show', $marketingCampaign->sourceType) }}"
                                               class="badge text-decoration-none"
                                               style="background-color: {{ $marketingCampaign->sourceType->color }}; color: white;">
                                                {{ $marketingCampaign->sourceType->name }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary">Nieznany</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status</td>
                                    <td>
                                        @if($marketingCampaign->is_active)
                                            <span class="badge bg-success">Aktywna</span>
                                        @else
                                            <span class="badge bg-secondary">Nieaktywna</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($marketingCampaign->course)
                                    <tr>
                                        <td class="text-muted">Szkolenie</td>
                                        <td>
                                            <a href="{{ route('courses.show', $marketingCampaign->course_id) }}" class="text-decoration-none">
                                                #{{ $marketingCampaign->course_id }}
                                            </a>
                                            · {{ Str::limit(strip_tags($marketingCampaign->course->title), 50) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Strona docelowa</td>
                                        <td>
                                            @if(($marketingCampaign->landing_target ?? 'course_show') === 'order_form')
                                                Formularz zamówienia
                                            @else
                                                Opis szkolenia
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted">Utworzona</td>
                                    <td>{{ $marketingCampaign->created_at->format('d.m.Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Zaktualizowana</td>
                                    <td>{{ $marketingCampaign->updated_at->format('d.m.Y H:i') }}</td>
                                </tr>
                            </table>
                            @if($marketingCampaign->description)
                                <h5 class="h6 text-muted text-uppercase fw-semibold mt-4 mb-2">Opis</h5>
                                <p class="small mb-0">{{ $marketingCampaign->description }}</p>
                            @endif
                        </div>
                        <div class="col-lg-6">
                            <h5 class="h6 text-muted text-uppercase fw-semibold mb-3">Statystyki</h5>
                            <div class="row g-2 text-center">
                                <div class="col-6">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body py-3">
                                            <div class="fs-3 fw-bold">{{ $marketingCampaign->formOrders->count() }}</div>
                                            <div class="small">Zamówień</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body py-3">
                                            <div class="fs-3 fw-bold">{{ $marketingCampaign->formOrders()->withInvoice()->count() }}</div>
                                            <div class="small">Z fakturą</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mt-2 mb-0">Liczniki za całą historię kampanii.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-success-subtle">
                <div class="card-header bg-success-subtle py-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1 fw-semibold"><i class="bi bi-link-45deg"></i> Linki do publikacji</h5>
                        <p class="small text-muted mb-0">
                            Skopiuj właściwy wariant: pełny UTM do newslettera, krótki do social mediów.
                        </p>
                    </div>
                    <a href="{{ route('marketing.help.links') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-book"></i> Pomoc: UTM
                    </a>
                </div>
                <div class="card-body">
                    @include('marketing-campaigns.partials.campaign-links', [
                        'campaignUrls' => $campaignUrls,
                        'marketingCampaign' => $marketingCampaign,
                        'showHeading' => false,
                        'showDocsLink' => true,
                        'verifyShortLinkUrl' => route('marketing-campaigns.verify-short-link', $marketingCampaign),
                        'idPrefix' => 'showCampaign',
                    ])
                </div>
            </div>

            @if($formOrders->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Zamówienia z tej kampanii ({{ $formOrders->total() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Data zamówienia</th>
                                        <th>Uczestnik</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($formOrders as $order)
                                        <tr>
                                            <td>{{ $order->id }}</td>
                                            <td>{{ $order->formatOrderDateLocal() ?? '—' }}</td>
                                            <td>
                                                @if($order->primaryParticipant)
                                                    {{ $order->primaryParticipant->first_name }} {{ $order->primaryParticipant->last_name }}
                                                @else
                                                    {{ $order->first_name }} {{ $order->last_name }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($order->primaryParticipant)
                                                    {{ $order->primaryParticipant->email }}
                                                @else
                                                    {{ $order->email }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($order->status_completed)
                                                    <span class="badge bg-success">Zakończone</span>
                                                @else
                                                    <span class="badge bg-warning">W trakcie</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Podgląd
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $formOrders->links() }}
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                        <h4 class="text-muted h5">Brak zamówień</h4>
                        <p class="text-muted small mb-0">Ta kampania nie ma jeszcze żadnych zamówień.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal potwierdzenia usunięcia --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć kampanię <strong>#{{ $marketingCampaign->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły kampanii:</h6>
                        <ul class="mb-0 small">
                            <li><strong>Kod kampanii:</strong> {{ $marketingCampaign->campaign_code }}</li>
                            <li><strong>Nazwa:</strong> {{ $marketingCampaign->name }}</li>
                            <li><strong>Typ źródła:</strong> {{ $marketingCampaign->sourceType->name ?? 'Brak' }}</li>
                            <li><strong>Opis:</strong> {{ $marketingCampaign->description ? Str::limit($marketingCampaign->description, 100) : 'Brak' }}</li>
                            <li><strong>Status:</strong> {{ $marketingCampaign->is_active ? 'Aktywna' : 'Nieaktywna' }}</li>
                            <li><strong>Data utworzenia:</strong> {{ $marketingCampaign->created_at ? $marketingCampaign->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3 small mb-0">
                        <i class="bi bi-info-circle"></i>
                        Kampania zostanie przeniesiona do kosza (soft delete) i będzie można ją przywrócić.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('marketing-campaigns.destroy', $marketingCampaign) }}"
                          method="POST"
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń kampanię
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
