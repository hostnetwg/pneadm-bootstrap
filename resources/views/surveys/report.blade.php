<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport ankiety - {{ $survey->title }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 5mm;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
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
            margin-bottom: 8px;
            padding: 6px;
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
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 4px;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stat-card {
            display: table-cell;
            text-align: center;
            padding: 6px 4px;
            border: 1px solid #333;
            background-color: #f8f9fa;
            width: 25%;
            vertical-align: middle;
            border-radius: 3px;
        }
        
        .stat-number {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            line-height: 1.1;
            margin-bottom: 1px;
        }
        
        .stat-label {
            font-size: 8px;
            color: #666;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .question-section {
            margin-bottom: 8px;
            page-break-inside: auto;
            orphans: 2;
            widows: 2;
        }
        
        .question-title {
            font-size: 10px;
            font-weight: bold;
            color: #000;
            margin-bottom: 4px;
            padding: 3px;
            background-color: #e9ecef;
            border-left: 2px solid #000;
        }
        
        .question-type {
            font-size: 8px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .rating-stats {
            margin-bottom: 6px;
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
            margin-top: 4px;
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
            page-break-inside: auto;
        }
        
        .text-response-number {
            font-weight: bold;
            color: #666;
            margin-right: 3px;
        }
        
        .choice-stats {
            margin-bottom: 6px;
        }
        
        .grid-choice-container {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 2px;
            margin-top: 4px;
        }
        
        .grid-choice-row {
            display: table-row;
        }
        
        .grid-choice-column {
            display: table-cell;
            width: 25%;
            vertical-align: top;
            padding-right: 4px;
        }
        
        .grid-choice-column:last-child {
            padding-right: 0;
        }
        
        .grid-choice-item {
            margin-bottom: 4px;
            padding: 3px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 2px;
            font-size: 8px;
            page-break-inside: auto;
        }
        
        .grid-choice-title {
            font-weight: bold;
            color: #000;
            margin-bottom: 1px;
        }
        
        .grid-choice-responses {
            color: #666;
            font-size: 7px;
            margin-bottom: 1px;
        }
        
        .grid-choice-answer {
            color: #333;
            line-height: 1.2;
        }
        
        .choice-item {
            margin-bottom: 4px;
            padding: 3px;
            background-color: #f8f9fa;
            border-left: 1px solid #dee2e6;
            font-size: 8px;
            line-height: 1.2;
        }
        
        .choice-text {
            font-size: 8px;
            margin-bottom: 2px;
        }
        
        .choice-count {
            font-weight: bold;
            color: #000;
            font-size: 8px;
            float: right;
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
            <strong>Szkolenie:</strong> {!! $survey->course->title !!}
        </div>
        <div class="info-line">
            @if($survey->instructor)
                <strong>Trener:</strong> {{ $survey->instructor->getFullTitleNameAttribute() }} | 
            @endif
            <strong>Data szkolenia:</strong> {{ $survey->course->start_date ? $survey->course->start_date->format('d.m.Y') : $survey->imported_at->format('d.m.Y') }} | 
            <strong>Data importu:</strong> {{ $survey->imported_at->format('d.m.Y') }} | 
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
                <div class="stat-number">{{ $survey->getActualQuestionsCount() }}</div>
                <div class="stat-label">Pytań</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $averageRating > 0 ? $averageRating . '/5' : 'N/A' }}</div>
                <div class="stat-label">Średnia ocena</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $survey->course->start_date ? $survey->course->start_date->format('d.m.Y') : $survey->imported_at->format('d.m.Y') }}</div>
                <div class="stat-label">Data szkolenia</div>
            </div>
        </div>
    </div>
    
    @foreach($groupedQuestions as $group)
        @if($group['type'] === 'grid')
            @php
                // Sprawdź czy przynajmniej jedno pytanie z siatki jest zaznaczone
                $hasSelectedQuestions = $group['questions']->filter(function($question) use ($selectedQuestions) {
                    return in_array($question->id, $selectedQuestions->pluck('id')->toArray());
                })->count() > 0;
            @endphp
            
            @if($hasSelectedQuestions)
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
                                            <td class="text-center">
                                                @if($question->isRating() && !empty($ratingStats) && isset($ratingStats['average']))
                                                    <strong>{{ $ratingStats['average'] }}</strong>
                                                @else
                                                    <strong>-</strong>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($question->isRating() && !empty($ratingStats) && isset($ratingStats['count']))
                                                    {{ $ratingStats['count'] }}
                                                @else
                                                    {{ $question->getResponses()->count() }}
                                                @endif
                                            </td>
                                            @for($i = 1; $i <= 5; $i++)
                                                <td class="text-center">
                                                    @if($question->isRating() && !empty($ratingStats) && isset($ratingStats['distribution']))
                                                        {{ $ratingStats['distribution'][$i] ?? 0 }}
                                                    @else
                                                        0
                                                    @endif
                                                </td>
                                            @endfor
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($group['is_choice_grid'])
                    <!-- Siatka wielokrotnego wyboru w PDF -->
                    @php
                        $selectedGridQuestions = $group['questions']->filter(function($question) use ($selectedQuestions) {
                            return in_array($question->id, $selectedQuestions->pluck('id')->toArray());
                        });
                        $questionsCollection = $selectedGridQuestions->values();
                        $questionsPerColumn = ceil($questionsCollection->count() / 4);
                        $columns = [
                            $questionsCollection->slice(0, $questionsPerColumn),
                            $questionsCollection->slice($questionsPerColumn, $questionsPerColumn),
                            $questionsCollection->slice($questionsPerColumn * 2, $questionsPerColumn),
                            $questionsCollection->slice($questionsPerColumn * 3)
                        ];
                    @endphp
                    
                    <div class="grid-choice-container">
                        <div class="grid-choice-row">
                            @foreach($columns as $columnIndex => $columnQuestions)
                                <div class="grid-choice-column">
                                    @foreach($columnQuestions as $question)
                                        <div class="grid-choice-item">
                                            <div class="grid-choice-title">{{ $question->getGridOption() }}</div>
                                            @php
                                                $responses = $question->getResponses();
                                                $responseCounts = $responses->countBy();
                                                $totalResponses = $responses->count();
                                            @endphp
                                            <div class="grid-choice-responses">{{ $totalResponses }} odpowiedzi</div>
                                            @if($totalResponses > 0)
                                                @foreach($responseCounts->sortDesc() as $response => $count)
                                                    <div class="grid-choice-answer">
                                                        {{ Str::limit($response, 25) }} ({{ $count }})
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                </div>
            @endif
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
                @if(!empty($ratingStats) && isset($ratingStats['average']))
                    <div class="rating-stats">
                        <div style="text-align: center; margin-bottom: 8px;">
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
                <div style="margin-bottom: 6px;">
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
                @elseif(($question->isMultipleChoice() || $question->isSingleChoice()) && $responses->count() > 0)
                    @php
                        // Sprawdź czy to pytanie "Inne uwagi i sugestie" - powinno być wyświetlane jak tekst
                        $isCommentsQuestion = strpos($question->question_text, 'Inne uwagi i sugestie') !== false || 
                                             strpos($question->question_text, 'uwagi i sugestie') !== false ||
                                             strpos($question->question_text, 'inne uwagi') !== false;
                    @endphp
                    
                    @if($isCommentsQuestion)
                        <!-- Wyświetl jako tekst w 3 kolumnach -->
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
                    @else
                    <div class="choice-stats">
                        @php
                            $responseCounts = $responses->countBy();
                            $allResponses = $responseCounts->sortDesc();
                            $totalResponses = $responses->count();
                            
                            // Sprawdź czy to pytanie o źródło informacji
                            $isSourceQuestion = strpos($question->question_text, 'O szkoleniu dowiedziałam') !== false || 
                                               strpos($question->question_text, 'O szkoleniu dowiedziałem') !== false;
                        @endphp
                        
                        @if($isSourceQuestion)
                            <strong>Główne źródła informacji:</strong>
                            @php
                                // Grupuj odpowiedzi na główne kategorie
                                $facebookCount = 0;
                                $emailCount = 0;
                                $otherResponses = collect();
                                
                                foreach ($allResponses as $response => $count) {
                                    if (stripos($response, 'facebook') !== false || stripos($response, 'portal') !== false) {
                                        $facebookCount += $count;
                                    } elseif (stripos($response, 'e-mail') !== false || stripos($response, 'mail') !== false || stripos($response, 'wiadomość') !== false) {
                                        $emailCount += $count;
                                    } else {
                                        $otherResponses->put($response, $count);
                                    }
                                }
                                
                                $mainCategories = collect([
                                    'portalu Facebook' => $facebookCount,
                                    'wiadomości e-mail z ofertą' => $emailCount
                                ])->filter(function($count) {
                                    return $count > 0;
                                });
                            @endphp
                            
                            @foreach($mainCategories as $category => $count)
                                <div class="choice-item">
                                    <div class="choice-text">{{ $category }}</div>
                                    <div class="choice-count">{{ $count }}</div>
                                </div>
                            @endforeach
                            
                            @if($otherResponses->count() > 0)
                                <div style="margin-top: 8px;">
                                    <strong>Inne źródła:</strong>
                                    @foreach($otherResponses as $response => $count)
                                        <div class="choice-item">
                                            <div class="choice-text">{{ $response }}</div>
                                            <div class="choice-count">{{ $count }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
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
                        @endif
                    @endif
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
