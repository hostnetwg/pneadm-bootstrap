<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport ankiety - {{ $survey->title }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 10mm;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
        }
        
        .header .organization {
            font-size: 14px;
            margin: 0;
            font-weight: bold;
            color: #000;
            line-height: 1.1;
        }
        
        .header h1 {
            font-size: 12px;
            margin: 5px 0 0 0;
            font-weight: bold;
            color: #000;
        }
        
        .header .subtitle {
            font-size: 9px;
            color: #333;
            margin-top: 3px;
        }
        
        .survey-info {
            margin-bottom: 12px;
            padding: 8px;
            background-color: #f9f9f9;
            border-left: 2px solid #000;
            font-size: 9px;
        }
        
        .info-line {
            margin-bottom: 4px;
            line-height: 1.3;
        }
        
        .info-line:last-child {
            margin-bottom: 0;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            border-collapse: separate;
            border-spacing: 6px;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stat-card {
            display: table-cell;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #333;
            background-color: #f8f9fa;
            width: 25%;
            vertical-align: middle;
            border-radius: 3px;
        }
        
        .stat-number {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            line-height: 1.1;
            margin-bottom: 2px;
        }
        
        .stat-label {
            font-size: 9px;
            color: #666;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .question-section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        
        .question-title {
            font-size: 10px;
            font-weight: bold;
            color: #000;
            margin-bottom: 6px;
            padding: 4px;
            background-color: #e9ecef;
            border-left: 2px solid #000;
        }
        
        .question-type {
            font-size: 8px;
            color: #666;
            margin-bottom: 6px;
        }
        
        .rating-stats {
            margin-bottom: 8px;
        }
        
        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 2px;
        }
        
        .rating-label {
            width: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }
        
        .rating-progress {
            flex: 1;
            height: 12px;
            background-color: #e9ecef;
            margin: 0 6px;
            position: relative;
        }
        
        .rating-fill {
            height: 100%;
            background-color: #000;
        }
        
        .rating-count {
            width: 20px;
            text-align: right;
            font-size: 8px;
        }
        
        .text-responses {
            margin-top: 8px;
        }
        
        .text-responses-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px;
        }
        
        .text-responses-row {
            display: table-row;
        }
        
        .text-responses-column {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            padding-right: 4px;
        }
        
        .text-responses-column:last-child {
            padding-right: 0;
        }
        
        .text-response {
            margin-bottom: 4px;
            padding: 3px;
            background-color: #f8f9fa;
            border-left: 1px solid #dee2e6;
            font-size: 8px;
            line-height: 1.2;
            page-break-inside: avoid;
        }
        
        .text-response-number {
            font-weight: bold;
            color: #666;
            margin-right: 3px;
        }
        
        .choice-stats {
            margin-bottom: 8px;
        }
        
        .choice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
            padding: 1px 0;
        }
        
        .choice-text {
            flex: 1;
            font-size: 8px;
        }
        
        .choice-count {
            font-weight: bold;
            background-color: #000;
            color: #fff;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 7px;
        }
        
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @page {
            margin: 10mm;
            @bottom-right {
                content: "Strona " counter(page) " z " counter(pages);
                font-family: "DejaVu Sans", sans-serif;
                font-size: 8px;
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
        <div class="info-line">
            <strong>Ankieta:</strong> {{ $survey->title }}
        </div>
        <div class="info-line">
            <strong>Szkolenie:</strong> {{ $survey->course->title }}
        </div>
        <div class="info-line">
            @if($survey->instructor)
                <strong>Trener:</strong> {{ $survey->instructor->getFullTitleNameAttribute() }} | 
            @endif
            <strong>Data:</strong> {{ $survey->course->start_date ? $survey->course->start_date->format('d.m.Y') : $survey->imported_at->format('d.m.Y') }} | 
            <strong>Źródło:</strong> {{ $survey->source }}
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stats-row">
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
    </div>
    
    @foreach($groupedQuestions as $group)
        @if($group['type'] === 'grid')
            <!-- Siatka pytań -->
            <div class="question-section">
                <div class="question-title">
                    <i class="fas fa-table"></i> {{ $group['main_text'] }}
                    <span class="badge bg-info ms-2">Siatka</span>
                </div>
                <div class="question-type">
                    Typ: 
                    @if($group['is_rating_grid'])
                        Siatka ratingowa
                    @elseif($group['is_choice_grid'])
                        Siatka wielokrotnego wyboru
                    @endif
                </div>
                
                @if($group['is_rating_grid'])
                    <!-- Siatka ratingowa w PDF -->
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60%;">Pytanie</th>
                                    <th style="width: 15%;" class="text-center">Średnia</th>
                                    <th style="width: 15%;" class="text-center">Odp.</th>
                                    <th style="width: 10%;" class="text-center">1</th>
                                    <th style="width: 10%;" class="text-center">2</th>
                                    <th style="width: 10%;" class="text-center">3</th>
                                    <th style="width: 10%;" class="text-center">4</th>
                                    <th style="width: 10%;" class="text-center">5</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['questions'] as $question)
                                    @if(in_array($question->id, $selectedQuestions->pluck('id')->toArray()))
                                        @php
                                            $ratingStats = $question->getRatingStats();
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $question->getGridOption() }}</strong></td>
                                            <td class="text-center"><strong>{{ $ratingStats['average'] }}</strong></td>
                                            <td class="text-center">{{ $ratingStats['count'] }}</td>
                                            @for($i = 1; $i <= 5; $i++)
                                                <td class="text-center">{{ $ratingStats['distribution'][$i] }}</td>
                                            @endfor
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($group['is_choice_grid'])
                    <!-- Siatka wielokrotnego wyboru w PDF -->
                    <div class="row">
                        @foreach($group['questions'] as $question)
                            @if(in_array($question->id, $selectedQuestions->pluck('id')->toArray()))
                                <div class="col-md-6 mb-3">
                                    <div class="card border">
                                        <div class="card-body p-3">
                                            <h6 class="card-title text-primary mb-2">
                                                <i class="fas fa-check-square"></i> {{ $question->getGridOption() }}
                                            </h6>
                                            @php
                                                $responses = $question->getResponses();
                                                $responseCounts = $responses->countBy();
                                                $totalResponses = $responses->count();
                                            @endphp
                                            <div class="mb-2">
                                                <span class="badge bg-success">{{ $totalResponses }}</span> odpowiedzi
                                            </div>
                                            @if($totalResponses > 0)
                                                <div class="choice-stats">
                                                    @foreach($responseCounts->sortDesc()->take(5) as $response => $count)
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small>{{ $response ?: 'Brak odpowiedzi' }}</small>
                                                            <span class="badge bg-primary">{{ $count }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <!-- Pojedyncze pytanie -->
            @php $question = $group['question']; @endphp
            @if(in_array($question->id, $selectedQuestions->pluck('id')->toArray()))
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
                        <strong>Wszystkie odpowiedzi ({{ $responses->count() }}):</strong>
                        @php
                            $responsesArray = $responses->toArray();
                            $responsesPerColumn = ceil(count($responsesArray) / 3);
                            $columns = [
                                array_slice($responsesArray, 0, $responsesPerColumn),
                                array_slice($responsesArray, $responsesPerColumn, $responsesPerColumn),
                                array_slice($responsesArray, $responsesPerColumn * 2)
                            ];
                        @endphp
                        
                        <div class="text-responses-grid">
                            <div class="text-responses-row">
                                @foreach($columns as $columnIndex => $columnResponses)
                                    <div class="text-responses-column">
                                        @foreach($columnResponses as $index => $response)
                                            @php
                                                $responseNumber = $columnIndex * $responsesPerColumn + $index + 1;
                                            @endphp
                                            <div class="text-response">
                                                <span class="text-response-number">{{ $responseNumber }}.</span>
                                                {{ Str::limit($response, 120) }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif($question->isMultipleChoice() || $question->isSingleChoice() && $responses->count() > 0)
                    <div class="choice-stats">
                        <strong>Najczęstsze odpowiedzi:</strong>
                        @php
                            $responseCounts = $responses->countBy();
                            $topResponses = $responseCounts->sortDesc()->take(8);
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
            @endif
        @endif
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
