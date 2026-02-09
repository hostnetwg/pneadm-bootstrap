<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Zamówienie online #{{ $order->id }}</h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('online-payment-orders.index') }}">Zamówienia online</a></li>
                    <li class="breadcrumb-item active">#{{ $order->id }}</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <span class="badge bg-{{ $order->getStatusBadgeClass() }} fs-6">{{ $order->status }}</span>
                <a href="{{ route('online-payment-orders.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Lista</a>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Zamawiający</strong></div>
                        <div class="card-body">
                            <p class="mb-1"><strong>{{ $order->first_name }} {{ $order->last_name }}</strong></p>
                            <p class="mb-1">E-mail: {{ $order->email }}</p>
                            <p class="mb-0">Tel: {{ $order->phone ?? '-' }}</p>
                            @if($order->order_comment)
                                <hr>
                                <p class="mb-0 text-muted"><small>{{ $order->order_comment }}</small></p>
                            @endif
                        </div>
                    </div>

                    @if($order->address_data)
                        <div class="card mb-3">
                            <div class="card-header"><strong>Adres</strong></div>
                            <div class="card-body">
                                <pre class="mb-0 small">{{ json_encode($order->address_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @endif

                    @if($order->course)
                        <div class="card mb-3">
                            <div class="card-header"><strong>Szkolenie</strong></div>
                            <div class="card-body">
                                <a href="{{ route('courses.show', $order->course_id) }}" target="_blank">{{ $order->course->title }}</a>
                                <span class="text-muted ms-2">(ID: {{ $order->course_id }})</span>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Metadane</strong></div>
                        <div class="card-body">
                            <p class="mb-1 small"><strong>Bramka:</strong> {{ strtoupper($order->payment_gateway ?? 'payu') }}</p>
                            <p class="mb-1 small"><strong>Ident:</strong> <code>{{ $order->ident }}</code></p>
                            @if($order->payu_order_id)
                                <p class="mb-1 small"><strong>PayU orderId:</strong> <code>{{ $order->payu_order_id }}</code></p>
                            @endif
                            <p class="mb-1 small"><strong>IP:</strong> {{ $order->ip_address ?? '-' }}</p>
                            <p class="mb-1 small"><strong>Utworzono:</strong> {{ $order->created_at?->setTimezone('Europe/Warsaw')->format('d.m.Y H:i:s') }}</p>
                            <p class="mb-0 small"><strong>Zaktualizowano:</strong> {{ $order->updated_at?->setTimezone('Europe/Warsaw')->format('d.m.Y H:i:s') }}</p>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white"><strong>{{ number_format($order->total_amount, 2, ',', ' ') }} {{ $order->currency }}</strong></div>
                    </div>
                </div>
            </div>

            @if($order->form_data && count($order->form_data) > 0)
                <div class="card mb-3">
                    <div class="card-header"><strong>Dane formularza (raw)</strong></div>
                    <div class="card-body">
                        <pre class="mb-0 bg-light p-2 rounded small" style="max-height: 300px; overflow: auto;">{{ json_encode($order->form_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <strong>Historia webhooków</strong>
                    <span class="badge bg-secondary ms-2">{{ $webhookLogs->count() }}</span>
                </div>
                <div class="card-body">
                    @if($webhookLogs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Bramka</th>
                                        <th>Status</th>
                                        <th>Status zmapowany</th>
                                        <th>Podpis</th>
                                        <th>IP</th>
                                        <th>Błąd</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($webhookLogs as $log)
                                        <tr>
                                            <td>
                                                <small>{{ $log->created_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i:s') }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $log->payment_gateway === 'payu' ? 'primary' : 'info' }}">
                                                    {{ strtoupper($log->payment_gateway) }}
                                                </span>
                                            </td>
                                            <td>
                                                <code class="small">{{ $log->status ?? '-' }}</code>
                                            </td>
                                            <td>
                                                @if($log->status_mapped)
                                                    <span class="badge bg-{{ $log->status_mapped === 'paid' ? 'success' : ($log->status_mapped === 'cancelled' ? 'danger' : 'secondary') }}">
                                                        {{ $log->status_mapped }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($log->signature_valid !== null)
                                                    @if($log->signature_valid)
                                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> OK</span>
                                                    @else
                                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Błąd</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $log->ip_address ?? '-' }}</small>
                                            </td>
                                            <td>
                                                @if($log->error_message)
                                                    <span class="text-danger small" title="{{ $log->error_message }}">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#payload-{{ $log->id }}" aria-expanded="false">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8" class="p-0">
                                                <div class="collapse" id="payload-{{ $log->id }}">
                                                    <div class="card card-body bg-light m-2">
                                                        <strong>Payload:</strong>
                                                        <pre class="mb-0 small" style="max-height: 200px; overflow: auto;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        @if($log->gateway_payment_id)
                                                            <p class="mb-0 mt-2"><strong>Gateway Payment ID:</strong> <code>{{ $log->gateway_payment_id }}</code></p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Brak webhooków dla tego zamówienia.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
