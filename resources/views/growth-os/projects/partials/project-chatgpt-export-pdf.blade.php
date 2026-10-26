<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
        .meta { color: #444; margin-bottom: 10px; }
        .hint { background: #f5f5f5; padding: 8px; margin: 10px 0 14px; }
        .label { color: #555; }
        .block { margin: 0 0 8px; }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            line-height: 1.35;
        }
        ul { margin: 4px 0 8px 16px; padding: 0; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Temat: {{ $topic }}<br>
        Wygenerowano: {{ $generatedAt }} ({{ config('app.timezone') }})
    </div>
    <div class="hint">{{ $hint }}</div>

    <h2>1. Kampania</h2>
    <div class="block"><span class="label">Temat:</span> {{ $snapshot['campaign']['topic'] }}</div>
    <div class="block"><span class="label">Typ:</span> {{ $snapshot['campaign']['type'] }}</div>
    <div class="block"><span class="label">Cel:</span> {{ $snapshot['campaign']['goal'] }}</div>
    <div class="block"><span class="label">Termin:</span> {{ $snapshot['campaign']['live_label'] }} ({{ $snapshot['campaign']['live_date'] }} {{ $snapshot['campaign']['live_time'] }})</div>
    <div class="block"><span class="label">Prowadzący:</span> {{ $snapshot['campaign']['host_name'] !== '' ? $snapshot['campaign']['host_name'] : '—' }}</div>
    <div class="block"><span class="label">Forma zwrotu:</span> {{ $snapshot['campaign']['address_form'] }}</div>
    <div class="block"><span class="label">Link do zapisów:</span> {{ $snapshot['campaign']['registration_url'] !== '' ? $snapshot['campaign']['registration_url'] : '—' }}</div>
    <div class="block"><span class="label">YouTube live:</span> {{ $snapshot['campaign']['youtube_live_url'] !== '' ? $snapshot['campaign']['youtube_live_url'] : '—' }}</div>

    <h2>2. Kierunek @if($snapshot['direction']['approved']) (zatwierdzony) @else (jeszcze niezatwierdzony) @endif</h2>
    @if($snapshot['direction']['approved'] && is_array($snapshot['direction']['fields']))
        @foreach($snapshot['direction']['fields'] as $key => $value)
            <div class="block"><span class="label">{{ $key }}:</span> {{ $value !== '' ? $value : '—' }}</div>
        @endforeach
    @else
        <p>Kierunek nie jest jeszcze zatwierdzony — nie eksportujemy jego treści.</p>
    @endif

    <h2>3. Koncepcja @if($snapshot['concept']['approved']) (zatwierdzona) @else (jeszcze niezatwierdzona) @endif</h2>
    @if($snapshot['concept']['approved'] && is_array($snapshot['concept']['fields']))
        @foreach($snapshot['concept']['fields'] as $key => $value)
            @if(is_array($value))
                <div class="block"><span class="label">{{ $key }}:</span></div>
                <ul>
                    @forelse($value as $item)
                        <li>{{ $item }}</li>
                    @empty
                        <li>—</li>
                    @endforelse
                </ul>
            @else
                <div class="block"><span class="label">{{ $key }}:</span> {{ $value !== '' ? $value : '—' }}</div>
            @endif
        @endforeach
    @else
        <p>Koncepcja nie jest jeszcze zatwierdzona — nie eksportujemy jej treści.</p>
    @endif

    <h2>4. Materiały zatwierdzone</h2>
    @forelse($snapshot['materials_approved'] as $material)
        <h3>{{ $material['name'] }} ({{ $material['status'] }})</h3>
        <pre>{{ $material['draft'] !== '' ? $material['draft'] : '(pusty szkic)' }}</pre>
    @empty
        <p>Brak zatwierdzonych materiałów.</p>
    @endforelse

    <h2>5. Co jeszcze do zrobienia</h2>
    @if($pendingNames !== [])
        <ul>
            @foreach($pendingNames as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ul>
    @else
        <p>Wszystkie materiały są zatwierdzone, opublikowane albo oznaczone jako „Nie dotyczy”.</p>
    @endif
    @if(($snapshot['materials_skipped'] ?? []) !== [])
        <p class="label">Nie dotyczy:</p>
        <ul>
            @foreach($snapshot['materials_skipped'] as $material)
                <li>{{ $material['name'] }}</li>
            @endforeach
        </ul>
    @endif

    <h2>6. Dane strukturalne (JSON)</h2>
    <p>Ta sama treść w JSON — wygodna do wklejenia lub wgrania jako kontekst dla modelu.</p>
    <pre>{{ $snapshotJson }}</pre>
</body>
</html>
