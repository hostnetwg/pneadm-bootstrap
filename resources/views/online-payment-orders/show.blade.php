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
                <div class="card">
                    <div class="card-header"><strong>Dane formularza (raw)</strong></div>
                    <div class="card-body">
                        <pre class="mb-0 bg-light p-2 rounded small" style="max-height: 300px; overflow: auto;">{{ json_encode($order->form_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
