<x-app-layout>
    <x-slot name="header">
        PNE Rozwój — Inbox
    </x-slot>

    <div class="container-fluid px-0">
        <div class="alert alert-info mb-0">
            Ten widok należał do prototypu 0.2. W etapie 0.3 Inbox prowadzi bezpośrednio do
            <a href="{{ route('growth.inbox.index') }}" class="alert-link">miejsca w projekcie webinaru</a>.
        </div>
    </div>
</x-app-layout>
