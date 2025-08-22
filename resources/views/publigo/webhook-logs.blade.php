<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Logi Webhooków Publigo
        </h2>
    </x-slot>
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Logi Webhooków Publigo</h1>
                <p class="text-gray-600">Historia wszystkich webhooków z Publigo.pl</p>
            </div>
            <a href="{{ route('publigo.webhooks') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                ← Powrót do zarządzania
            </a>
        </div>
    </div>

    <!-- Filtry -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Filtry</h2>
        
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Typ Logu</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">Wszystkie</option>
                    <option value="received" {{ request('type') === 'received' ? 'selected' : '' }}>Otrzymane</option>
                    <option value="error" {{ request('type') === 'error' ? 'selected' : '' }}>Błędy</option>
                    <option value="success" {{ request('type') === 'success' ? 'selected' : '' }}>Sukces</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data od</label>
                <input type="date" 
                       name="date_from" 
                       value="{{ request('date_from') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data do</label>
                <input type="date" 
                       name="date_to" 
                       value="{{ request('date_to') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            
            <div class="md:col-span-3">
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Filtruj
                </button>
                <a href="{{ route('publigo.webhooks.logs') }}" 
                   class="ml-2 px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    Wyczyść
                </a>
            </div>
        </form>
    </div>

    <!-- Logi -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Logi Webhooków</h2>
            <div class="text-sm text-gray-500">
                Znaleziono {{ $logs->count() }} logów
            </div>
        </div>
        
        @if($logs->count() > 0)
            <div class="space-y-4">
                @foreach($logs as $log)
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div class="text-sm text-gray-500">{{ $log['timestamp'] }}</div>
                            <div class="text-xs">
                                @if(str_contains($log['message'], 'error'))
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded">Błąd</span>
                                @elseif(str_contains($log['message'], 'success'))
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Sukces</span>
                                @else
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">Info</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="text-gray-900 text-sm">
                            @php
                                $message = $log['message'];
                                // Usuń timestamp z początku
                                $message = preg_replace('/^\[.*?\] /', '', $message);
                                // Usuń poziom logu
                                $message = preg_replace('/^[A-Z]+: /', '', $message);
                            @endphp
                            {{ $message }}
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-6">
                    {{ $logs->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <div class="text-gray-400 text-6xl mb-4">📋</div>
                <p class="text-gray-500 text-lg mb-2">Brak logów webhooków</p>
                <p class="text-gray-400">Nie znaleziono żadnych logów spełniających kryteria wyszukiwania.</p>
            </div>
        @endif
    </div>
</div>

<script>
// Auto-refresh co 30 sekund
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
    }, 30000);
</script>
</x-app-layout>
