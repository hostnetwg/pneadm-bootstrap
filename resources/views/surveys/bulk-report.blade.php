<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zbiorczy raport ankiet</title>
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
        
        .filters-info {
            margin-bottom: 18px;
            padding: 8px;
            background-color: #f9f9f9;
            border-left: 3px solid #000;
            font-size: 12px;
            color: #000;
        }
        
        .summary-stats {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f0f8ff;
            border: 1px solid #000;
            border-radius: 5px;
        }
        
        .summary-stats h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #000;
        }
        
        .summary-stats .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-stats .stats-row:last-child {
            margin-bottom: 0;
        }
        
        .survey-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 5px;
            page-break-inside: avoid;
        }
        
        .survey-header {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
            color: #000;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .survey-details {
            font-size: 11px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .survey-details .detail-row {
            margin-bottom: 3px;
        }
        
        .survey-details .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .survey-stats {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #000;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: bold;
            font-size: 12px;
        }
        
        .stat-label {
            font-size: 10px;
            color: #666;
        }
        
        .analysis-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .analysis-section h3 {
            font-size: 16px;
            margin: 0 0 15px 0;
            padding: 10px;
            background-color: #f0f8ff;
            border-left: 4px solid #000;
            color: #000;
        }
        
        .rating-question {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 5px;
        }
        
        .rating-question h4 {
            font-size: 14px;
            margin: 0 0 10px 0;
            color: #000;
        }
        
        .rating-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .rating-distribution {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }
        
        .rating-bar {
            text-align: center;
            margin: 0 2px;
        }
        
        .rating-bar-value {
            font-weight: bold;
            color: #000;
        }
        
        .open-responses {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .open-response-column {
            flex: 1;
            min-width: 200px;
            font-size: 10px;
        }
        
        .open-response-item {
            margin-bottom: 8px;
            padding: 5px;
            border-left: 2px solid #ccc;
            background-color: #f9f9f9;
        }
        
        .choice-question {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .choice-question h4 {
            font-size: 13px;
            margin: 0 0 8px 0;
            color: #000;
        }
        
        .choice-distribution {
            font-size: 11px;
        }
        
        .choice-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .footer {
            margin-top: 25px;
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
        <h1>Zbiorczy raport ankiet</h1>
        <div class="subtitle">Wygenerowano: {{ $generated_at->format('d.m.Y H:i') }}</div>
    </div>
    
    @if(!empty(array_filter($filters_applied)))
        <div class="filters-info">
            <strong>Zastosowane filtry:</strong><br>
            @if(isset($filters_applied['search']) && $filters_applied['search'])
                Wyszukiwanie: {{ $filters_applied['search'] }}<br>
            @endif
            @if(isset($filters_applied['date_from']) && $filters_applied['date_from'])
                Data od: {{ $filters_applied['date_from'] }}<br>
            @endif
            @if(isset($filters_applied['date_to']) && $filters_applied['date_to'])
                Data do: {{ $filters_applied['date_to'] }}<br>
            @endif
            @if(isset($filters_applied['course_id']) && $filters_applied['course_id'])
                Szkolenie: {{ \App\Models\Course::find($filters_applied['course_id'])->title ?? 'Nieznane' }}<br>
            @endif
            @if(isset($filters_applied['instructor_id']) && $filters_applied['instructor_id'])
                Instruktor: {{ \App\Models\Instructor::find($filters_applied['instructor_id'])->getFullTitleNameAttribute() ?? 'Nieznany' }}<br>
            @endif
        </div>
    @endif
    
    <div class="summary-stats">
        <h3>Podsumowanie</h3>
        <div class="stats-row">
            <span>Liczba ankiet:</span>
            <span><strong>{{ $total_surveys }}</strong></span>
        </div>
        <div class="stats-row">
            <span>Łączna liczba odpowiedzi:</span>
            <span><strong>{{ $total_responses }}</strong></span>
        </div>
        <div class="stats-row">
            <span>Średnia odpowiedzi na ankietę:</span>
            <span><strong>{{ $total_surveys > 0 ? round($total_responses / $total_surveys, 1) : 0 }}</strong></span>
        </div>
    </div>
    
    @foreach($surveys as $index => $survey)
        <div class="survey-item">
            <div class="survey-header">
                {{ $index + 1 }}. {{ $survey->title }}
            </div>
            
            <div class="survey-details">
                <div class="detail-row">
                    <strong>Szkolenie:</strong> {{ $survey->course->title }}
                </div>
                @if($survey->instructor)
                    <div class="detail-row">
                        <strong>Instruktor:</strong> {{ $survey->instructor->getFullTitleNameAttribute() }}
                    </div>
                @endif
                <div class="detail-row">
                    <strong>Data szkolenia:</strong> {{ $survey->course->start_date ? $survey->course->start_date->format('d.m.Y H:i') : 'Brak daty' }}
                </div>
                <div class="detail-row">
                    <strong>Data importu ankiety:</strong> {{ $survey->imported_at->format('d.m.Y H:i') }}
                </div>
                @if($survey->description)
                    <div class="detail-row">
                        <strong>Opis:</strong> {{ $survey->description }}
                    </div>
                @endif
            </div>
            
            <div class="survey-stats">
                <div class="stat-item">
                    <div class="stat-value">{{ $survey->total_responses }}</div>
                    <div class="stat-label">Odpowiedzi</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $survey->getActualQuestionsCount() }}</div>
                    <div class="stat-label">Pytań</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        @if($survey->getAverageRating() > 0)
                            {{ $survey->getAverageRating() }}
                        @else
                            N/A
                        @endif
                    </div>
                    <div class="stat-label">Średnia ocen</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $survey->source }}</div>
                    <div class="stat-label">Źródło</div>
                </div>
            </div>
        </div>
    @endforeach
    
    <!-- Szczegółowe analizy odpowiedzi -->
    @if(!empty($detailed_analysis['rating_questions']) || !empty($detailed_analysis['open_questions']) || !empty($detailed_analysis['choice_questions']))
        <div class="page-break"></div>
        
        <!-- Pytania ratingowe (skala 1-5) -->
        @if(!empty($detailed_analysis['rating_questions']))
            <div class="analysis-section">
                <h3>Pytania ratingowe (skala 1-5) - Średnie wyniki</h3>
                @foreach($detailed_analysis['rating_questions'] as $question)
                    <div class="rating-question">
                        <h4>{{ $question['question'] }}</h4>
                        <div class="rating-stats">
                            <div><strong>Średnia:</strong> {{ $question['average'] }}</div>
                            <div><strong>Liczba odpowiedzi:</strong> {{ $question['count'] }}</div>
                        </div>
                        <div class="rating-distribution">
                            @for($i = 1; $i <= 5; $i++)
                                <div class="rating-bar">
                                    <div class="rating-bar-value">{{ $i }}</div>
                                    <div>{{ $question['distribution'][$i] ?? 0 }}</div>
                                </div>
                            @endfor
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <!-- Pytania otwarte -->
        @if(!empty($detailed_analysis['open_questions']))
            <div class="analysis-section">
                <h3>Pytania otwarte - Wszystkie odpowiedzi</h3>
                @foreach($detailed_analysis['open_questions'] as $question)
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-size: 14px; margin-bottom: 10px; color: #000;">{{ $question['question'] }}</h4>
                        <div class="open-responses">
                            @php
                                $responses = $question['responses'];
                                $chunkSize = ceil(count($responses) / 3);
                                $chunks = array_chunk($responses, $chunkSize);
                            @endphp
                            @foreach($chunks as $chunk)
                                <div class="open-response-column">
                                    @foreach($chunk as $response)
                                        <div class="open-response-item">
                                            {{ $response }}
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <!-- Pytania wyboru -->
        @if(!empty($detailed_analysis['choice_questions']))
            <div class="analysis-section">
                <h3>Pytania wyboru - Rozkład odpowiedzi</h3>
                @foreach($detailed_analysis['choice_questions'] as $question)
                    <div class="choice-question">
                        <h4>{{ $question['question'] }}</h4>
                        <div class="choice-distribution">
                            @foreach($question['distribution'] as $option => $count)
                                <div class="choice-item">
                                    <span>{{ $option }}</span>
                                    <span><strong>{{ $count }}</strong> ({{ $question['responses'] ? round(($count / count($question['responses'])) * 100, 1) : 0 }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
    
    <div class="footer">
        <div>
            <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji</strong><br>
            ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
        </div>
    </div>
</body>
</html>
