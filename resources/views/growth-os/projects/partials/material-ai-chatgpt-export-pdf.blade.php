<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .meta { color: #444; margin-bottom: 12px; }
        .hint { background: #f5f5f5; padding: 8px; margin: 10px 0 14px; }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Materiał: {{ $materialName }}<br>
        Temat: {{ $topic }}<br>
        Wygenerowano: {{ $generatedAt }} ({{ config('app.timezone') }})
    </div>
    <div class="hint">{{ $hint }}</div>

    @if($includePrompt)
        <h2>1. Prompt / instrukcja systemowa aplikacji</h2>
        <pre>{{ $prompt }}</pre>

        <h2>2. Dane projektu (JSON wejścia do modelu)</h2>
        <pre>{{ $inputJson }}</pre>
    @else
        <h2>Dane projektu (JSON — bez promptu aplikacji)</h2>
        <p>Dopisz własny prompt na ChatGPT.com. Poniżej są wyłącznie dane z Growth OS.</p>
        <pre>{{ $inputJson }}</pre>
    @endif
</body>
</html>
