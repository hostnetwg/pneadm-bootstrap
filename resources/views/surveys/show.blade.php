<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły ankiety') }}
        </h2>
    </x-slot>
    
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .chart-legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #ffffff;
            border-radius: 20px;
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .chart-legend-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .chart-legend-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        
        .progress-mini {
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-mini .progress-bar {
            transition: width 0.3s ease;
        }
        
        /* Style dla przeglądania pojedynczych odpowiedzi */
        .single-response-card {
            transition: all 0.3s ease;
        }
        
        .single-response-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .response-answer {
            min-height: 40px;
            display: flex;
            align-items: center;
        }
        
        .navigation-buttons {
            transition: all 0.2s ease;
        }
        
        .navigation-buttons:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .response-counter {
            font-weight: 600;
            color: #495057;
        }
    </style>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {!! session('success') !!}
                </div>
            @endif

            <!-- Nawigacja -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $survey->title }}</h4>
                    <p class="text-muted mb-0">
                        Szkolenie: <a href="{{ route('courses.show', $survey->course_id) }}">{{ $survey->course->title }}</a>
                        @if($survey->instructor)
                            | Instruktor: {{ $survey->instructor->getFullTitleNameAttribute() }}
                        @endif
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('surveys.index') }}" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Wszystkie ankiety
                    </a>
                    <a href="{{ route('courses.show', $survey->course_id) }}" class="btn btn-outline-primary">
                        <i class="fas fa-graduation-cap"></i> Szkolenie
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Statystyki ogólne -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> Statystyki ogólne
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h3 class="text-primary">{{ $survey->total_responses }}</h3>
                                    <small class="text-muted">Odpowiedzi</small>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-success">{{ $survey->getActualQuestionsCount() }}</h3>
                                    <small class="text-muted">Pytań</small>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-warning">{{ $averageRating > 0 ? $averageRating : 'N/A' }}</h3>
                                    <small class="text-muted">Średnia ocena</small>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-info">{{ $survey->imported_at->format('d.m.Y') }}</h3>
                                    <small class="text-muted">Data importu</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pytania i odpowiedzi -->
                    @if($survey->questions->count() > 0)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle"></i> Pytania i odpowiedzi
                                </h5>
                            </div>
                            <div class="card-body">
                                @foreach($groupedQuestions as $group)
                                    @if($group['type'] === 'grid')
                                        <!-- Siatka pytań -->
                                        <div class="mb-4 pb-3 border-bottom">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-table"></i> {{ $group['main_text'] }}
                                                <span class="badge bg-info ms-2">Siatka</span>
                                            </h6>
                                            
                                            @if($group['is_rating_grid'])
                                                <!-- Siatka ratingowa -->
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 50%;">Pytanie</th>
                                                                <th style="width: 10%;" class="text-center">Średnia</th>
                                                                <th style="width: 10%;" class="text-center">Odp.</th>
                                                                <th style="width: 30%;">Rozkład ocen</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($group['questions'] as $question)
                                                                @php
                                                                    $ratingStats = $question->getRatingStats();
                                                                @endphp
                                                                <tr>
                                                                    <td>
                                                                        <strong>{{ $question->getGridOption() }}</strong>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @if($question->isRating() && !empty($ratingStats['average']))
                                                                            <span class="badge bg-primary">{{ $ratingStats['average'] }}</span>
                                                                        @else
                                                                            <span class="badge bg-secondary">-</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @if($question->isRating() && !empty($ratingStats['count']))
                                                                            <small class="text-muted">{{ $ratingStats['count'] }}</small>
                                                                        @else
                                                                            <small class="text-muted">{{ $question->getResponses()->count() }}</small>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @if($question->isRating() && !empty($ratingStats['distribution']))
                                                                            <div class="d-flex align-items-center">
                                                                                @for($i = 1; $i <= 5; $i++)
                                                                                    <div class="me-1 text-center" style="min-width: 20px;">
                                                                                        <div class="small">{{ $i }}</div>
                                                                                        <div class="badge bg-secondary">{{ $ratingStats['distribution'][$i] ?? 0 }}</div>
                                                                                    </div>
                                                                                @endfor
                                                                            </div>
                                                                        @else
                                                                            <small class="text-muted">Brak danych ratingowych</small>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @elseif($group['is_choice_grid'])
                                                <!-- Siatka wielokrotnego wyboru -->
                                                <div class="row">
                                                    @foreach($group['questions'] as $question)
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
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <!-- Pojedyncze pytanie -->
                                        @php $question = $group['question']; @endphp
                                        <div class="mb-4 pb-3 border-bottom">
                                            <h6 class="text-primary">{{ $question->question_text }}</h6>
                                            <small class="text-muted">
                                                Typ: 
                                                <span class="badge bg-secondary">
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
                                                </span>
                                            </small>

                                            @if($question->isRating())
                                                @php
                                                    $ratingStats = $question->getRatingStats();
                                                @endphp
                                                @if($question->isRating() && !empty($ratingStats) && isset($ratingStats['average']))
                                                    <div class="mt-2">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>Średnia: <strong>{{ $ratingStats['average'] }}</strong></span>
                                                            <span>Odpowiedzi: <strong>{{ $ratingStats['count'] }}</strong></span>
                                                        </div>
                                                        
                                                        <!-- Wykres słupkowy -->
                                                        <div class="rating-chart">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                <div class="d-flex align-items-center mb-1">
                                                                    <span class="me-2" style="width: 20px;">{{ $i }}</span>
                                                                    <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                                        <div class="progress-bar bg-primary" 
                                                                             style="width: {{ $ratingStats['count'] > 0 ? (($ratingStats['distribution'][$i] ?? 0) / $ratingStats['count']) * 100 : 0 }}%">
                                                                        </div>
                                                                    </div>
                                                                    <span class="badge bg-secondary">{{ $ratingStats['distribution'][$i] ?? 0 }}</span>
                                                                </div>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                @php
                                                    $responses = $question->getResponses();
                                                @endphp
                                                <div class="mt-2">
                                                    <div class="mb-2">
                                                        <strong>{{ $responses->count() }}</strong> odpowiedzi
                                                    </div>
                                                    
                                                    @if($question->isText() && $responses->count() > 0)
                                                        <div class="responses-preview">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0">Odpowiedzi tekstowe:</h6>
                                                                @if($responses->count() > 3)
                                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                            onclick="toggleAllResponses({{ $question->id }})">
                                                                        <i class="fas fa-eye" id="icon-{{ $question->id }}"></i>
                                                                        <span id="text-{{ $question->id }}">Pokaż wszystkie</span>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                            
                                                            <!-- Pierwsze 3 odpowiedzi (zawsze widoczne) -->
                                                            <div id="preview-{{ $question->id }}">
                                                                @foreach($responses->take(3) as $index => $response)
                                                                    <div class="alert alert-light mb-2">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <small class="flex-grow-1">{{ Str::limit($response, 200) }}</small>
                                                                            <small class="text-muted ms-2">#{{ $index + 1 }}</small>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                                @if($responses->count() > 3)
                                                                    <div class="text-center">
                                                                        <small class="text-muted">... i {{ $responses->count() - 3 }} więcej</small>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            
                                                            <!-- Wszystkie odpowiedzi (ukryte domyślnie) -->
                                                            @if($responses->count() > 3)
                                                                <div id="all-{{ $question->id }}" style="display: none;">
                                                                    @foreach($responses as $index => $response)
                                                                        <div class="alert alert-light mb-2">
                                                                            <div class="d-flex justify-content-between align-items-start">
                                                                                <small class="flex-grow-1">{{ $response }}</small>
                                                                                <small class="text-muted ms-2">#{{ $index + 1 }}</small>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @elseif($question->isMultipleChoice() && $responses->count() > 0)
                                                        <div class="responses-preview">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0">Odpowiedzi wielokrotnego wyboru:</h6>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                        onclick="toggleChart('chart-{{ $question->id }}')">
                                                                    <i class="fas fa-chart-pie" id="chart-icon-{{ $question->id }}"></i>
                                                                    <span id="chart-text-{{ $question->id }}">Pokaż wykres</span>
                                                                </button>
                                                            </div>
                                                            
                                                            @php
                                                                $responseCounts = $responses->countBy();
                                                                $topResponses = $responseCounts->sortDesc();
                                                                $totalResponses = $responses->count();
                                                                $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'];
                                                                $topResponsesArray = $topResponses->toArray();
                                                                $responsesList = array_keys($topResponsesArray);
                                                                $countsList = array_values($topResponsesArray);
                                                            @endphp
                                                            
                                                            <!-- Lista odpowiedzi -->
                                                            <div id="list-{{ $question->id }}">
                                                                @foreach($topResponses as $response => $count)
                                                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                                                        <span class="fw-medium">{{ $response }}</span>
                                                                        <div class="d-flex align-items-center gap-3">
                                                                            <div class="progress-mini" style="width: 120px;">
                                                                                <div class="progress-bar bg-primary" 
                                                                                     style="width: {{ ($count / $totalResponses) * 100 }}%"></div>
                                                                            </div>
                                                                            <span class="badge bg-primary fs-6">{{ $count }}</span>
                                                                            <small class="text-muted fw-medium">({{ round(($count / $totalResponses) * 100, 1) }}%)</small>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            
                                                            <!-- Wykres kołowy -->
                                                            <div id="chart-{{ $question->id }}" style="display: none;">
                                                                <div class="chart-container">
                                                                    <canvas id="pieChart-{{ $question->id }}"></canvas>
                                                                </div>
                                                                <div class="chart-legend">
                                                                    @foreach($responsesList as $index => $response)
                                                                        @php
                                                                            $count = $countsList[$index];
                                                                            $percentage = round(($count / $totalResponses) * 100, 1);
                                                                            $colorIndex = $index % count($colors);
                                                                            $color = $colors[$colorIndex];
                                                                        @endphp
                                                                        <div class="chart-legend-item">
                                                                            <div class="chart-legend-color" style="background-color: {{ $color }};"></div>
                                                                            <span class="small fw-medium">{{ $response }}</span>
                                                                            <span class="small text-muted">({{ $count }}, {{ $percentage }}%)</span>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif($question->isSingleChoice() && $responses->count() > 0)
                                                        <div class="responses-preview">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0">Odpowiedzi jednokrotnego wyboru:</h6>
                                                                <div class="btn-group" role="group">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                            onclick="toggleChart('chart-{{ $question->id }}')">
                                                                        <i class="fas fa-chart-pie" id="chart-icon-{{ $question->id }}"></i>
                                                                        <span id="chart-text-{{ $question->id }}">Pokaż wykres</span>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                            onclick="toggleAllSingleChoiceResponses({{ $question->id }})">
                                                                        <i class="fas fa-list" id="responses-icon-{{ $question->id }}"></i>
                                                                        <span id="responses-text-{{ $question->id }}">Wszystkie odpowiedzi</span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            @php
                                                                $responseCounts = $responses->countBy();
                                                                $topResponses = $responseCounts->sortDesc();
                                                                $totalResponses = $responses->count();
                                                                $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'];
                                                                $topResponsesArray = $topResponses->toArray();
                                                                $responsesList = array_keys($topResponsesArray);
                                                                $countsList = array_values($topResponsesArray);
                                                            @endphp
                                                            
                                                            <!-- Lista odpowiedzi -->
                                                            <div id="list-{{ $question->id }}">
                                                                @foreach($topResponses as $response => $count)
                                                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                                                        <span class="fw-medium">{{ $response }}</span>
                                                                        <div class="d-flex align-items-center gap-3">
                                                                            <div class="progress-mini" style="width: 120px;">
                                                                                <div class="progress-bar bg-primary" 
                                                                                     style="width: {{ ($count / $totalResponses) * 100 }}%"></div>
                                                                            </div>
                                                                            <span class="badge bg-primary fs-6">{{ $count }}</span>
                                                                            <small class="text-muted fw-medium">({{ round(($count / $totalResponses) * 100, 1) }}%)</small>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            
                                                            <!-- Wykres kołowy -->
                                                            <div id="chart-{{ $question->id }}" style="display: none;">
                                                                <div class="chart-container">
                                                                    <canvas id="pieChart-{{ $question->id }}"></canvas>
                                                                </div>
                                                                <div class="chart-legend">
                                                                    @foreach($responsesList as $index => $response)
                                                                        @php
                                                                            $count = $countsList[$index];
                                                                            $percentage = round(($count / $totalResponses) * 100, 1);
                                                                            $colorIndex = $index % count($colors);
                                                                            $color = $colors[$colorIndex];
                                                                        @endphp
                                                                        <div class="chart-legend-item">
                                                                            <div class="chart-legend-color" style="background-color: {{ $color }};"></div>
                                                                            <span class="small fw-medium">{{ $response }}</span>
                                                                            <span class="small text-muted">({{ $count }}, {{ $percentage }}%)</span>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Wszystkie odpowiedzi -->
                                                            <div id="all-responses-{{ $question->id }}" style="display: none;">
                                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                                    <h6 class="mb-0">Wszystkie odpowiedzi ({{ $responses->count() }}):</h6>
                                                                    <small class="text-muted">Kliknij "Wszystkie odpowiedzi" aby ukryć</small>
                                                                </div>
                                                                <div class="row">
                                                                    @foreach($responses as $index => $response)
                                                                        <div class="col-md-6 mb-2">
                                                                            <div class="alert alert-light mb-2">
                                                                                <div class="d-flex justify-content-between align-items-start">
                                                                                    <small class="flex-grow-1">{{ $response }}</small>
                                                                                    <small class="text-muted ms-2">#{{ $index + 1 }}</small>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Przeglądanie odpowiedzi pojedynczo -->
                    @if($survey->responses->count() > 0)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user"></i> Przeglądanie odpowiedzi pojedynczo
                                </h5>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Przejrzyj odpowiedzi jednej osoby na wszystkie pytania
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <label for="response-selector" class="form-label mb-0 me-2">Odpowiedź:</label>
                                            <select class="form-select" id="response-selector" onchange="showSingleResponse()">
                                                <option value="">Wybierz odpowiedź...</option>
                                                @foreach($survey->responses as $index => $response)
                                                    <option value="{{ $index }}">Odpowiedź #{{ $index + 1 }} - {{ $response->submitted_at->format('d.m.Y H:i') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm navigation-buttons" id="prev-btn" onclick="navigateResponse(-1)" disabled>
                                                <i class="fas fa-chevron-left"></i> Poprzednia
                                            </button>
                                            <span class="align-self-center mx-2 response-counter">
                                                <span id="current-response">-</span> z <span id="total-responses">{{ $survey->responses->count() }}</span>
                                            </span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm navigation-buttons" id="next-btn" onclick="navigateResponse(1)" disabled>
                                                Następna <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sekcja z odpowiedziami jednej osoby -->
                                <div id="single-response-container" style="display: none;">
                                    <div class="card border-primary single-response-card">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user-circle"></i> 
                                                <span id="response-header">Odpowiedź #1</span>
                                                <small class="float-end" id="response-date"></small>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                @foreach($survey->questions as $question)
                                                    <div class="col-md-6 mb-3">
                                                        <div class="border rounded p-3 h-100">
                                                            <h6 class="text-primary mb-2">
                                                                <i class="fas fa-question-circle"></i> 
                                                                {{ $question->question_text }}
                                                            </h6>
                                                            <div class="response-answer" data-question-id="{{ $question->id }}">
                                                                <span class="badge bg-secondary">Brak odpowiedzi</span>
                                                            </div>
                                                            <small class="text-muted mt-1 d-block">
                                                                Typ: 
                                                                @if($question->isRating())
                                                                    <span class="badge bg-warning">Ocena</span>
                                                                @elseif($question->isText())
                                                                    <span class="badge bg-info">Tekst</span>
                                                                @elseif($question->isSingleChoice())
                                                                    <span class="badge bg-success">Jednokrotny wybór</span>
                                                                @elseif($question->isMultipleChoice())
                                                                    <span class="badge bg-primary">Wielokrotny wybór</span>
                                                                @endif
                                                            </small>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Wszystkie odpowiedzi -->
                    @if($survey->responses->count() > 0)
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">
                                        <i class="fas fa-list"></i> Wszystkie odpowiedzi ({{ $survey->responses->count() }})
                                    </h5>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Przewiń poziomo, aby zobaczyć wszystkie pytania. 
                                        Kolumna "Data" pozostaje widoczna podczas przewijania.
                                    </small>
                                </div>
                                @if($survey->responses->count() > 10)
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="toggleAllResponsesTable()">
                                        <i class="fas fa-eye" id="table-icon"></i>
                                        <span id="table-text">Pokaż wszystkie</span>
                                    </button>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 500px; overflow-x: auto; overflow-y: auto;">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th style="min-width: 120px; position: sticky; left: 0; background-color: #343a40; z-index: 10;">Data</th>
                                                @foreach($survey->questions as $question)
                                                    <th style="min-width: 200px; white-space: nowrap;">{{ Str::limit($question->question_text, 40) }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($survey->responses as $index => $response)
                                                <tr class="{{ $index >= 10 ? 'table-row-all' : 'table-row-preview' }}" 
                                                    {{ $index >= 10 ? 'style=display:none;' : '' }}>
                                                    <td style="position: sticky; left: 0; background-color: #f8f9fa; z-index: 5; border-right: 1px solid #dee2e6;">
                                                        <strong>{{ $response->submitted_at->format('d.m.Y') }}</strong><br>
                                                        <small class="text-muted">{{ $response->submitted_at->format('H:i') }}</small>
                                                    </td>
                                                    @foreach($survey->questions as $question)
                                                        <td style="max-width: 200px; word-wrap: break-word;">
                                                            @php
                                                                $answer = $response->getAnswerForQuestion($question->question_text);
                                                            @endphp
                                                            @if($question->isRating())
                                                                <span class="badge bg-primary">{{ $answer }}</span>
                                                            @elseif($question->isText())
                                                                <small>{{ Str::limit($answer, 100) }}</small>
                                                            @else
                                                                <small>{{ Str::limit($answer, 50) }}</small>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($survey->responses->count() > 10)
                                    <div class="text-center mt-3" id="table-info">
                                        <small class="text-muted">Pokazano 10 z {{ $survey->responses->count() }} odpowiedzi</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <!-- Informacje o ankiecie -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Źródło:</strong></td>
                                    <td>{{ $survey->source }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Zaimportowano:</strong></td>
                                    <td>{{ $survey->imported_at->format('d.m.Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Przez:</strong></td>
                                    <td>{{ $survey->importedBy->name ?? 'Nieznany' }}</td>
                                </tr>
                                @if($survey->description)
                                    <tr>
                                        <td><strong>Opis:</strong></td>
                                        <td>{{ $survey->description }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Akcje -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs"></i> Akcje
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('surveys.report.form', $survey->id) }}" class="btn btn-success">
                                    <i class="fas fa-file-pdf"></i> Generuj raport PDF
                                </a>
                                <a href="{{ route('surveys.edit', $survey->id) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edytuj ankietę
                                </a>
                                <form action="{{ route('surveys.destroy', $survey->id) }}" method="POST" 
                                      onsubmit="return confirm('Czy na pewno chcesz usunąć tę ankietę?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-trash"></i> Usuń ankietę
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Statystyki szczegółowe -->
                    @if($averageRating > 0)
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star"></i> Oceny
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-4 text-warning">{{ $averageRating }}</div>
                                <p class="text-muted">Średnia ocena</p>
                                
                                @php
                                    $ratingQuestions = $survey->questions->where('question_type', 'rating');
                                @endphp
                                @if($ratingQuestions->count() > 0)
                                    <small class="text-muted">
                                        Na podstawie {{ $ratingQuestions->count() }} pytań ratingowych
                                    </small>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAllResponses(questionId) {
            const previewDiv = document.getElementById('preview-' + questionId);
            const allDiv = document.getElementById('all-' + questionId);
            const icon = document.getElementById('icon-' + questionId);
            const text = document.getElementById('text-' + questionId);
            
            if (allDiv && allDiv.style.display === 'none') {
                // Pokaż wszystkie odpowiedzi
                if (previewDiv) previewDiv.style.display = 'none';
                allDiv.style.display = 'block';
                if (icon) icon.className = 'fas fa-eye-slash';
                if (text) text.textContent = 'Ukryj wszystkie';
            } else if (allDiv) {
                // Ukryj wszystkie odpowiedzi
                if (previewDiv) previewDiv.style.display = 'block';
                allDiv.style.display = 'none';
                if (icon) icon.className = 'fas fa-eye';
                if (text) text.textContent = 'Pokaż wszystkie';
            }
        }

        function toggleAllResponsesTable() {
            const previewRows = document.querySelectorAll('.table-row-preview');
            const allRows = document.querySelectorAll('.table-row-all');
            const icon = document.getElementById('table-icon');
            const text = document.getElementById('table-text');
            const info = document.getElementById('table-info');
            
            // Sprawdź czy wszystkie wiersze są ukryte (domyślny stan)
            const allRowsHidden = allRows.length > 0 && allRows[0].style.display === 'none';
            
            if (allRowsHidden) {
                // Pokaż wszystkie odpowiedzi
                allRows.forEach(row => {
                    row.style.display = '';
                });
                if (icon) icon.className = 'fas fa-eye-slash';
                if (text) text.textContent = 'Ukryj wszystkie';
                if (info) info.style.display = 'none';
            } else {
                // Ukryj wszystkie odpowiedzi (pokazuj tylko pierwsze 10)
                allRows.forEach(row => {
                    row.style.display = 'none';
                });
                if (icon) icon.className = 'fas fa-eye';
                if (text) text.textContent = 'Pokaż wszystkie';
                if (info) info.style.display = 'block';
            }
        }

        // Funkcja do przełączania wykresów kołowych
        function toggleChart(chartId) {
            const chartElement = document.getElementById(chartId);
            const listElement = document.getElementById(chartId.replace('chart-', 'list-'));
            const icon = document.getElementById(chartId.replace('chart-', 'chart-icon-'));
            const text = document.getElementById(chartId.replace('chart-', 'chart-text-'));
            
            if (chartElement && listElement && icon && text) {
                if (chartElement.style.display === 'none') {
                    // Pokaż wykres
                    chartElement.style.display = 'block';
                    listElement.style.display = 'none';
                    icon.className = 'fas fa-list';
                    text.textContent = 'Pokaż listę';
                    
                    // Utwórz wykres kołowy
                    createPieChart(chartId);
                } else {
                    // Pokaż listę
                    chartElement.style.display = 'none';
                    listElement.style.display = 'block';
                    icon.className = 'fas fa-chart-pie';
                    text.textContent = 'Pokaż wykres';
                }
            }
        }

        // Funkcja do tworzenia wykresów kołowych
        function createPieChart(chartId) {
            console.log('Creating pie chart for:', chartId);
            const canvasId = chartId.replace('chart-', 'pieChart-');
            const canvas = document.getElementById(canvasId);
            
            if (!canvas) {
                console.error('Canvas not found:', canvasId);
                return;
            }
            
            // Sprawdź czy wykres już istnieje
            if (window.chartInstances && window.chartInstances[canvasId]) {
                console.log('Chart already exists, destroying old one');
                window.chartInstances[canvasId].destroy();
            }
            
            // Pobierz dane z legendy
            const legendItems = document.querySelectorAll(`#${chartId} .chart-legend-item`);
            const labels = [];
            const data = [];
            const colors = [];
            
            console.log('Found legend items:', legendItems.length);
            
            legendItems.forEach((item, index) => {
                const spans = item.querySelectorAll('span');
                const colorDiv = item.querySelector('.chart-legend-color');
                
                if (spans.length >= 2 && colorDiv) {
                    const responseText = spans[0].textContent.trim();
                    const countText = spans[1].textContent.trim();
                    
                    // Parsuj tekst: "(liczba, procent%)"
                    const match = countText.match(/\((\d+),\s*([\d.]+)%\)/);
                    if (match) {
                        labels.push(responseText);
                        data.push(parseInt(match[1]));
                        colors.push(colorDiv.style.backgroundColor);
                        console.log(`Item ${index}: ${responseText} - ${match[1]} (${match[2]}%)`);
                    }
                }
            });
            
            console.log('Chart data:', { labels, data, colors });
            
            if (labels.length === 0) {
                console.error('No data found for chart');
                return;
            }
            
            // Utwórz wykres
            const ctx = canvas.getContext('2d');
            const chart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // Używamy własnej legendy
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Zapisz instancję wykresu
            if (!window.chartInstances) {
                window.chartInstances = {};
            }
            window.chartInstances[canvasId] = chart;
            console.log('Chart created successfully');
        }

        // Funkcja do przełączania wszystkich odpowiedzi dla pytań jednokrotnego wyboru
        function toggleAllSingleChoiceResponses(questionId) {
            const responsesElement = document.getElementById(`all-responses-${questionId}`);
            const icon = document.getElementById(`responses-icon-${questionId}`);
            const text = document.getElementById(`responses-text-${questionId}`);
            
            if (responsesElement && icon && text) {
                if (responsesElement.style.display === 'none') {
                    // Pokaż wszystkie odpowiedzi
                    responsesElement.style.display = 'block';
                    icon.className = 'fas fa-eye-slash';
                    text.textContent = 'Ukryj odpowiedzi';
                } else {
                    // Ukryj wszystkie odpowiedzi
                    responsesElement.style.display = 'none';
                    icon.className = 'fas fa-list';
                    text.textContent = 'Wszystkie odpowiedzi';
                }
            }
        }

        // Dane odpowiedzi do przeglądania pojedynczo
        const surveyResponses = @json($survey->responses->map(function($response) {
            return [
                'id' => $response->id,
                'submitted_at' => $response->submitted_at->format('d.m.Y H:i'),
                'response_data' => $response->response_data
            ];
        }));

        const surveyQuestions = @json($survey->questions->map(function($question) {
            return [
                'id' => $question->id,
                'text' => $question->question_text,
                'type' => $question->question_type
            ];
        }));

        let currentResponseIndex = -1;

        // Funkcja do wyświetlania pojedynczej odpowiedzi
        function showSingleResponse() {
            const selector = document.getElementById('response-selector');
            const index = parseInt(selector.value);
            
            if (index >= 0 && index < surveyResponses.length) {
                currentResponseIndex = index;
                displayResponse(index);
                updateNavigationButtons();
            }
        }

        // Funkcja do wyświetlania odpowiedzi
        function displayResponse(index) {
            const response = surveyResponses[index];
            const container = document.getElementById('single-response-container');
            const header = document.getElementById('response-header');
            const date = document.getElementById('response-date');
            const currentResponseSpan = document.getElementById('current-response');
            
            // Aktualizuj nagłówek
            header.textContent = `Odpowiedź #${index + 1}`;
            date.textContent = response.submitted_at;
            currentResponseSpan.textContent = index + 1;
            
            // Wyczyść poprzednie odpowiedzi
            document.querySelectorAll('.response-answer').forEach(element => {
                element.innerHTML = '<span class="badge bg-secondary">Brak odpowiedzi</span>';
            });
            
            // Wyświetl odpowiedzi
            surveyQuestions.forEach(question => {
                const answerElement = document.querySelector(`[data-question-id="${question.id}"]`);
                const answer = response.response_data[question.text];
                
                if (answer !== null && answer !== undefined && answer !== '') {
                    if (question.type === 'rating') {
                        answerElement.innerHTML = `<span class="badge bg-warning fs-6">${answer}/5</span>`;
                    } else if (question.type === 'text') {
                        answerElement.innerHTML = `<div class="alert alert-light mb-0"><small>${answer}</small></div>`;
                    } else if (question.type === 'single_choice') {
                        answerElement.innerHTML = `<span class="badge bg-success">${answer}</span>`;
                    } else if (question.type === 'multiple_choice') {
                        if (Array.isArray(answer)) {
                            answerElement.innerHTML = answer.map(a => `<span class="badge bg-primary me-1">${a}</span>`).join('');
                        } else {
                            answerElement.innerHTML = `<span class="badge bg-primary">${answer}</span>`;
                        }
                    }
                }
            });
            
            // Pokaż kontener
            container.style.display = 'block';
        }

        // Funkcja do nawigacji między odpowiedziami
        function navigateResponse(direction) {
            if (currentResponseIndex >= 0) {
                const newIndex = currentResponseIndex + direction;
                if (newIndex >= 0 && newIndex < surveyResponses.length) {
                    currentResponseIndex = newIndex;
                    document.getElementById('response-selector').value = newIndex;
                    displayResponse(newIndex);
                    updateNavigationButtons();
                }
            }
        }

        // Funkcja do aktualizacji przycisków nawigacji
        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            
            prevBtn.disabled = currentResponseIndex <= 0;
            nextBtn.disabled = currentResponseIndex >= surveyResponses.length - 1;
        }

        // Obsługa klawiatury
        document.addEventListener('keydown', function(e) {
            if (currentResponseIndex >= 0) {
                if (e.key === 'ArrowLeft') {
                    navigateResponse(-1);
                } else if (e.key === 'ArrowRight') {
                    navigateResponse(1);
                }
            }
        });

    </script>
</x-app-layout>
