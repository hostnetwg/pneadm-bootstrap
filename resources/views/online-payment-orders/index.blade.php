<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Zamówienia online (pnedu.pl) – PayU / Paynow
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="mb-3">
                <div class="btn-group" role="group">
                    <a href="{{ route('online-payment-orders.index', array_merge(request()->only(['search', 'per_page']), ['status' => ''])) }}"
                       class="btn {{ $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Wszystkie
                    </a>
                    <a href="{{ route('online-payment-orders.index', array_merge(request()->only(['search', 'per_page']), ['status' => 'created'])) }}"
                       class="btn {{ $statusFilter === 'created' ? 'btn-info' : 'btn-outline-info' }}">
                        Utworzone (przekierowanie)
                        <span class="badge bg-white text-info ms-1">{{ $statusCounts['created'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('online-payment-orders.index', array_merge(request()->only(['search', 'per_page']), ['status' => 'paid'])) }}"
                       class="btn {{ $statusFilter === 'paid' ? 'btn-success' : 'btn-outline-success' }}">
                        Opłacone
                        <span class="badge bg-white text-success ms-1">{{ $statusCounts['paid'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('online-payment-orders.index', array_merge(request()->only(['search', 'per_page']), ['status' => 'pending'])) }}"
                       class="btn {{ $statusFilter === 'pending' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        Oczekujące
                        <span class="badge bg-white text-secondary ms-1">{{ $statusCounts['pending'] ?? 0 }}</span>
                    </a>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('online-payment-orders.index') }}" class="row g-3">
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="search" value="{{ $search }}"
                                   placeholder="Szukaj: ident, e-mail, imię, nazwisko, payu_order_id...">
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-select">
                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Szukaj</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Data</th>
                                    <th>Ident</th>
                                    <th>Zamawiający</th>
                                    <th>Szkolenie</th>
                                    <th>Kwota</th>
                                    <th>Status</th>
                                    <th>Bramka</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr>
                                        <td>{{ $order->id }}</td>
                                        <td>{{ $order->created_at?->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}</td>
                                        <td><code class="small">{{ Str::limit($order->ident, 12) }}</code></td>
                                        <td>
                                            <strong>{{ $order->first_name }} {{ $order->last_name }}</strong><br>
                                            <small class="text-muted">{{ $order->email }}</small>
                                        </td>
                                        <td>
                                            @if($order->course)
                                                <a href="{{ route('courses.show', $order->course_id) }}" target="_blank">
                                                    {{ Str::limit(strip_tags($order->course->title ?? ''), 40) }}
                                                </a>
                                            @else
                                                <span class="text-muted">ID: {{ $order->course_id }}</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($order->total_amount, 2, ',', ' ') }} PLN</td>
                                        <td>
                                            <span class="badge bg-{{ $order->getStatusBadgeClass() }}">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td>{{ strtoupper($order->payment_gateway ?? 'payu') }}</td>
                                        <td>
                                            <a href="{{ route('online-payment-orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Brak zamówień.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
