<small class="text-muted d-block mt-1">
    Dostępne zmienne:
    <ul class="mb-0 ps-3">
        @foreach(\App\Services\Certificate\CertificateTemplateVariableResolver::variableHelp() as $variable => $description)
            <li><code>{{ $variable }}</code> — {{ $description }}</li>
        @endforeach
    </ul>
    <span class="d-block mt-1">
        Przykład: <code>zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez</code>.
        Tekst bez zmiennych trafia na certyfikat dokładnie taki, jaki wpiszesz.
        Puste pole — brak akapitu o wydarzeniu.
    </span>
</small>
