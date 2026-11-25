<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Powiadomienia</h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if($notifications->isEmpty())
                <p class="text-muted mb-0">Brak powiadomień.</p>
            @else
                <div class="list-group">
                    @foreach($notifications as $notification)
                        <a href="{{ route('trainer-portal.notifications.open', $notification) }}"
                           class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'list-group-item-warning' }}">
                            <div class="d-flex justify-content-between gap-3">
                                <strong>{{ $notification->title }}</strong>
                                <span class="text-muted small">{{ $notification->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                            </div>
                            <div>{{ $notification->body }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
