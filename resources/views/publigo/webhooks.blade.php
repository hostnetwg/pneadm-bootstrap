<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Zarządzanie Webhookami Publigo
        </h2>
    </x-slot>
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Zarządzanie Webhookami Publigo</h1>
        <p class="text-gray-600">Konfiguracja i monitorowanie webhooków z Publigo.pl</p>
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-blue-800 text-sm">
                    <strong>Uwaga:</strong> Webhook obsługuje tylko kursy z <code>source_id_old = "certgen_Publigo"</code>. 
                    Kursy bez tego ustawienia nie będą automatycznie zapisywać uczestników.
                </p>
            </div>
        </div>
    </div>

    <!-- Konfiguracja Webhooka -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Konfiguracja Webhooka</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">URL Webhooka</label>
                <div class="flex">
                    <input type="text" 
                           value="{{ $webhookUrl }}" 
                           readonly 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-gray-900">
                    <button onclick="copyToClipboard('{{ $webhookUrl }}')" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700">
                        Kopiuj
                    </button>
                </div>
                <p class="text-sm text-gray-500 mt-1">Skopiuj ten URL i wklej w panelu Publigo.pl</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Token Webhooka</label>
                <div class="flex">
                    <input type="password" 
                           value="{{ $webhookToken ?? 'Nie skonfigurowano' }}" 
                           readonly 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-gray-900">
                    <button onclick="togglePasswordVisibility(this)" 
                            class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700">
                        Pokaż
                    </button>
                </div>
                <p class="text-sm text-gray-500 mt-1">Token do weryfikacji webhooków (opcjonalny)</p>
            </div>
        </div>
    </div>

    <!-- Test Webhooka -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Test Webhooka</h2>
        
        <form action="{{ route('publigo.test-webhook') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ID Kursu</label>
                    <select name="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Wybierz kurs</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id_old ?? $course->id }}">
                                {{ $course->title }} ({{ $course->start_date ? $course->start_date->format('Y-m-d') : 'Brak daty' }}) - ID: {{ $course->id_old ?? $course->id }}
                            </option>
                        @endforeach
                    </select>
                    @if($courses->count() === 0)
                        <p class="text-red-600 text-sm mt-1">Brak kursów z source_id_old = "certgen_Publigo"</p>
                    @endif
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Testowy</label>
                    <input type="email" 
                           name="email" 
                           value="test@example.com" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Imię</label>
                    <input type="text" 
                           name="first_name" 
                           value="Jan" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nazwisko</label>
                    <input type="text" 
                           name="last_name" 
                           value="Kowalski" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
            </div>
            
            <button type="submit" 
                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                Wyślij Testowy Webhook
            </button>
        </form>
    </div>

    <!-- Ostatnie Logi -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Ostatnie Logi Webhooków</h2>
            <a href="{{ route('publigo.webhooks.logs') }}" 
               class="text-blue-600 hover:text-blue-800 text-sm">
                Zobacz wszystkie logi →
            </a>
        </div>
        
        @if($recentLogs->count() > 0)
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($recentLogs as $log)
                    <div class="p-3 bg-gray-50 rounded-md text-sm">
                        <div class="text-gray-500 text-xs mb-1">{{ $log['timestamp'] }}</div>
                        <div class="text-gray-900">{{ $log['message'] }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-center py-8">Brak logów webhooków</p>
        @endif
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('URL skopiowany do schowka!');
    });
}

function togglePasswordVisibility(button) {
    const input = button.previousElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = 'Ukryj';
    } else {
        input.type = 'password';
        button.textContent = 'Pokaż';
    }
    }
</script>
</x-app-layout>
