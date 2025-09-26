<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport ankiety - {{ $survey->title }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 15px;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
        }
        
        .header .organization {
            font-size: 20px;
            margin: 0;
            font-weight: bold;
            color: #000;
            line-height: 1.2;
        }
        
        .header h1 {
            font-size: 18px;
            margin: 10px 0 0 0;
            font-weight: bold;
            color: #000;
        }
        
        .header .subtitle {
            font-size: 15px;
            color: #333;
            margin-top: 5px;
        }
        
        .survey-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #000;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border: 1px solid #333;
            background-color: #f5f5f5;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        .question-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .question-title {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #e9ecef;
            border-left: 3px solid #000;
        }
        
        .question-type {
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .rating-stats {
            margin-bottom: 15px;
        }
        
        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .rating-label {
            width: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .rating-progress {
            flex: 1;
            height: 20px;
            background-color: #e9ecef;
            margin: 0 10px;
            position: relative;
        }
        
        .rating-fill {
            height: 100%;
            background-color: #000;
        }
        
        .rating-count {
            width: 30px;
            text-align: right;
            font-size: 11px;
        }
        
        .text-responses {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .text-response {
            margin-bottom: 8px;
            padding: 5px;
            background-color: #f8f9fa;
            border-left: 2px solid #dee2e6;
            font-size: 11px;
        }
        
        .choice-stats {
            margin-bottom: 15px;
        }
        
        .choice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .choice-text {
            flex: 1;
        }
        
        .choice-count {
            font-weight: bold;
            background-color: #000;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @page {
            margin: 15mm;
            @bottom-right {
                content: "Strona " counter(page) " z " counter(pages);
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="organization">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji</div>
        <h1>Raport ankiety</h1>
        <div class="subtitle">Wygenerowano: {{ now()->format('d.m.Y H:i') }}</div>
    </div>
    
    <div class="survey-info">
        <strong>Ankieta:</strong> {{ $survey->title }}<br>
        <strong>Szkolenie:</strong> {{ $survey->course->title }}<br>
        @if($survey->instructor)
            <strong>Instruktor:</strong> {{ $survey->instructor->getFullTitleNameAttribute() }}<br>
        @endif
        <strong>Data importu:</strong> {{ $survey->imported_at->format('d.m.Y H:i') }}<br>
        <strong>Źródło:</strong> {{ $survey->source }}
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">{{ $survey->total_responses }}</div>
            <div class="stat-label">Odpowiedzi</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $survey->questions->count() }}</div>
            <div class="stat-label">Pytań</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $averageRating > 0 ? $averageRating : 'N/A' }}</div>
            <div class="stat-label">Średnia ocena</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $survey->imported_at->format('d.m.Y') }}</div>
            <div class="stat-label">Data importu</div>
        </div>
    </div>
    
    @foreach($survey->questions as $question)
        <div class="question-section">
            <div class="question-title">{{ $question->question_text }}</div>
            <div class="question-type">
                Typ: 
                @switch($question->question_type)
                    @case('rating')
                        Ocena (1-5)
                        @break
                    @case('text')
                        Tekst
                        @break
                    @case('multiple_choice')
                        Wielokrotny wybór
                        @break
                    @case('single_choice')
                        Pojedynczy wybór
                        @break
                    @case('date')
                        Data
                        @break
                    @default
                        {{ $question->question_type }}
                @endswitch
            </div>
            
            @if($question->isRating())
                @php
                    $ratingStats = $question->getRatingStats();
                @endphp
                @if(!empty($ratingStats))
                    <div class="rating-stats">
                        <div style="text-align: center; margin-bottom: 15px;">
                            <strong>Średnia: {{ $ratingStats['average'] }}</strong> | 
                            <strong>Odpowiedzi: {{ $ratingStats['count'] }}</strong>
                        </div>
                        
                        @for($i = 1; $i <= 5; $i++)
                            <div class="rating-bar">
                                <div class="rating-label">{{ $i }}</div>
                                <div class="rating-progress">
                                    <div class="rating-fill" style="width: {{ $ratingStats['count'] > 0 ? ($ratingStats['distribution'][$i] / $ratingStats['count']) * 100 : 0 }}%"></div>
                                </div>
                                <div class="rating-count">{{ $ratingStats['distribution'][$i] }}</div>
                            </div>
                        @endfor
                    </div>
                @endif
            @else
                @php
                    $responses = $question->getResponses();
                @endphp
                <div style="margin-bottom: 10px;">
                    <strong>{{ $responses->count() }}</strong> odpowiedzi
                </div>
                
                @if($question->isText() && $responses->count() > 0)
                    <div class="text-responses">
                        <h6>Przykładowe odpowiedzi:</h6>
                        @foreach($responses->take(5) as $response)
                            <div class="text-response">{{ Str::limit($response, 200) }}</div>
                        @endforeach
                        @if($responses->count() > 5)
                            <div style="text-align: center; font-style: italic; color: #666;">
                                ... i {{ $responses->count() - 5 }} więcej odpowiedzi
                            </div>
                        @endif
                    </div>
                @elseif($question->isMultipleChoice() && $responses->count() > 0)
                    <div class="choice-stats">
                        <h6>Najczęstsze odpowiedzi:</h6>
                        @php
                            $responseCounts = $responses->countBy();
                            $topResponses = $responseCounts->sortDesc()->take(10);
                        @endphp
                        @foreach($topResponses as $response => $count)
                            <div class="choice-item">
                                <div class="choice-text">{{ $response }}</div>
                                <div class="choice-count">{{ $count }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endforeach
    
    <div class="footer">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji</strong><br>
                ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
            </div>
        </div>
    </div>
</body>
</html>
