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
                                                                        <span class="badge bg-primary">{{ $ratingStats['average'] }}</span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <small class="text-muted">{{ $ratingStats['count'] }}</small>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            @for($i = 1; $i <= 5; $i++)
                                                                                <div class="me-1 text-center" style="min-width: 20px;">
                                                                                    <div class="small">{{ $i }}</div>
                                                                                    <div class="badge bg-secondary">{{ $ratingStats['distribution'][$i] }}</div>
                                                                                </div>
                                                                            @endfor
                                                                        </div>
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
                                                @if(!empty($ratingStats))
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
                                                                             style="width: {{ $ratingStats['count'] > 0 ? ($ratingStats['distribution'][$i] / $ratingStats['count']) * 100 : 0 }}%">
                                                                        </div>
                                                                    </div>
                                                                    <span class="badge bg-secondary">{{ $ratingStats['distribution'][$i] }}</span>
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
                                                            <h6>Przykładowe odpowiedzi:</h6>
                                                            @foreach($responses->take(3) as $index => $response)
                                                                <div class="border rounded p-2 mb-2 bg-light">
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
                                                            
                                                            <!-- Wszystkie odpowiedzi (ukryte domyślnie) -->
                                                            <div id="all-responses-{{ $question->id }}" style="display: none;">
                                                                <h6>Wszystkie odpowiedzi:</h6>
                                                                @foreach($responses as $index => $response)
                                                                    <div class="border rounded p-2 mb-2 bg-light">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <small class="flex-grow-1">{{ $response }}</small>
                                                                            <small class="text-muted ms-2">#{{ $index + 1 }}</small>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            
                                                            <div class="text-center mt-2">
                                                                <button class="btn btn-sm btn-outline-primary" onclick="toggleAllResponses({{ $question->id }})">
                                                                    <i class="fas fa-eye"></i> Pokaż wszystkie
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @elseif($question->isMultipleChoice() && $responses->count() > 0)
                                                        <div class="responses-preview">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6>Najczęstsze odpowiedzi:</h6>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="toggleChart('chart-{{ $question->id }}')">
                                                                    <i class="fas fa-chart-pie"></i> Wykres
                                                                </button>
                                                            </div>
                                                            
                                                            @php
                                                                $responseCounts = $responses->countBy();
                                                                $topResponses = $responseCounts->sortDesc()->take(10);
                                                                $totalResponses = $responses->count();
                                                            @endphp
                                                            
                                                            <div id="list-{{ $question->id }}">
                                                                @foreach($topResponses->values() as $index => $response)
                                                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="me-2" style="width: 16px; height: 16px; background-color: {{ $colors[$index % count($colors)] }}; border-radius: 50%;"></div>
                                                                            <div class="flex-grow-1">
                                                                                <small class="fw-medium">{{ $response }}</small>
                                                                            </div>
                                                                            <span class="badge bg-primary fs-6">{{ $count = $responseCounts[$response] }}</span>
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
                                                                    @foreach($topResponses->values() as $index => $response)
                                                                        @php
                                                                            $count = $responseCounts[$response];
                                                                            $percentage = round(($count / $totalResponses) * 100, 1);
                                                                            $color = $colors[$index % count($colors)];
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
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6>Najczęstsze odpowiedzi:</h6>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-primary" onclick="toggleChart('chart-{{ $question->id }}')">
                                                                        <i class="fas fa-chart-pie"></i> Wykres
                                                                    </button>
                                                                    <button class="btn btn-outline-info" onclick="toggleAllSingleChoiceResponses({{ $question->id }})">
                                                                        <i class="fas fa-list"></i> Wszystkie
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            @php
                                                                $responseCounts = $responses->countBy();
                                                                $topResponses = $responseCounts->sortDesc()->take(10);
                                                                $totalResponses = $responses->count();
                                                            @endphp
                                                            
                                                            <div id="list-{{ $question->id }}">
                                                                @foreach($topResponses->values() as $index => $response)
                                                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="me-2" style="width: 16px; height: 16px; background-color: {{ $colors[$index % count($colors)] }}; border-radius: 50%;"></div>
                                                                            <div class="flex-grow-1">
                                                                                <small class="fw-medium">{{ $response }}</small>
                                                                            </div>
                                                                            <span class="badge bg-primary fs-6">{{ $count = $responseCounts[$response] }}</span>
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
                                                                    @foreach($topResponses->values() as $index => $response)
                                                                        @php
                                                                            $count = $responseCounts[$response];
                                                                            $percentage = round(($count / $totalResponses) * 100, 1);
                                                                            $color = $colors[$index % count($colors)];
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
                                                                <h6>Wszystkie odpowiedzi:</h6>
                                                                @foreach($responses as $index => $response)
                                                                    <div class="border rounded p-2 mb-2 bg-light">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <small class="flex-grow-1">{{ $response }}</small>
                                                                            <small class="text-muted ms-2">#{{ $index + 1 }}</small>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
