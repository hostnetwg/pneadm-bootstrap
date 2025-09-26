<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Generowanie raportu ankiety') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Przycisk powrotu -->
            <div class="mb-4">
                <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Wróć
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-file-pdf"></i> Wybierz pytania do raportu
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('surveys.report', $survey->id) }}" method="POST" id="report-form">
                                @csrf
                                
                                <!-- Informacje o ankiecie -->
                                <div class="alert alert-info mb-4">
                                    <h6 class="mb-2">
                                        <i class="fas fa-info-circle"></i> Informacje o ankiecie
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Ankieta:</strong> {{ $survey->title }}<br>
                                            <strong>Szkolenie:</strong> {{ $survey->course->title }}<br>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Odpowiedzi:</strong> {{ $survey->total_responses }}<br>
                                            <strong>Pytania:</strong> {{ $survey->getActualQuestionsCount() }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Kontrolki wyboru -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Wybierz pytania do raportu:</h6>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                                            <i class="fas fa-check-square"></i> Zaznacz wszystkie
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                                            <i class="fas fa-square"></i> Odznacz wszystkie
                                        </button>
                                    </div>
                                </div>

                                <!-- Lista pytań -->
                                <div class="questions-list">
                                    @foreach($survey->questions as $index => $question)
                                        <div class="card mb-3 question-card">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input class="form-check-input question-checkbox" 
                                                           type="checkbox" 
                                                           name="selected_questions[]" 
                                                           value="{{ $question->id }}" 
                                                           id="question_{{ $question->id }}"
                                                           {{ !in_array($index + 1, [10, 11, 12, 13, 14, 15, 16, 17]) ? 'checked' : '' }}
                                                           data-question-id="{{ $question->id }}"
                                                           data-question-number="{{ $index + 1 }}">
                                                    <label class="form-check-label w-100" for="question_{{ $question->id }}">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-1">
                                                                    <span class="badge bg-secondary me-2">{{ $index + 1 }}</span>
                                                                    {{ $question->question_text }}
                                                                </h6>
                                                                <small class="text-muted">
                                                                    Typ: 
                                                                    @if($question->isRating())
                                                                        <span class="badge bg-warning">Ocena (1-5)</span>
                                                                    @elseif($question->isText())
                                                                        <span class="badge bg-info">Tekst</span>
                                                                    @elseif($question->isSingleChoice())
                                                                        <span class="badge bg-success">Jednokrotny wybór</span>
                                                                    @elseif($question->isMultipleChoice())
                                                                        <span class="badge bg-primary">Wielokrotny wybór</span>
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">
                                                                    @php
                                                                        $responses = $question->getResponses();
                                                                    @endphp
                                                                    {{ $responses->count() }} odpowiedzi
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Przyciski -->
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Powrót do ankiety
                                        </a>
                                        <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-list"></i> Wszystkie ankiety
                                        </a>
                                    </div>
                                    <button type="submit" class="btn btn-success" id="generate-btn">
                                        <i class="fas fa-file-pdf"></i> Generuj raport PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Podgląd wyboru -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-eye"></i> Podgląd wyboru
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="display-6 text-primary" id="selected-count">0</div>
                                <small class="text-muted">z {{ $survey->getActualQuestionsCount() }} pytań</small>
                            </div>
                            
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-primary" 
                                     id="selection-progress" 
                                     style="width: 0%"></div>
                            </div>
                            
                            <div id="selected-questions-list">
                                <small class="text-muted">Brak wybranych pytań</small>
                            </div>
                        </div>
                    </div>

                    <!-- Informacje o raporcie -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success"></i>
                                    <small>Raport zostanie pobrany jako PDF</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success"></i>
                                    <small>Otworzy się w nowej zakładce</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success"></i>
                                    <small>Zawiera wykresy i statystyki</small>
                                </li>
                                <li>
                                    <i class="fas fa-check text-success"></i>
                                    <small>Profesjonalny layout</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .question-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .question-card:hover {
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .question-card.selected {
            border-color: #198754;
            background-color: #f8fff9;
        }
        
        .form-check-input:checked + .form-check-label .question-card {
            border-color: #198754;
        }
        
        .form-check-input:checked + .form-check-label {
            color: #198754;
        }
        
        .form-check-input:checked + .form-check-label h6 {
            color: #198754;
        }
    </style>

    <script>
        // Aktualizuj podgląd wyboru
        function updatePreview() {
            const checkboxes = document.querySelectorAll('.question-checkbox:checked');
            const totalQuestions = document.querySelectorAll('.question-checkbox').length;
            const selectedCount = checkboxes.length;
            
            // Aktualizuj licznik
            document.getElementById('selected-count').textContent = selectedCount;
            
            // Aktualizuj pasek postępu
            const progress = (selectedCount / totalQuestions) * 100;
            document.getElementById('selection-progress').style.width = progress + '%';
            
            // Aktualizuj listę wybranych pytań
            const selectedList = document.getElementById('selected-questions-list');
            if (selectedCount === 0) {
                selectedList.innerHTML = '<small class="text-muted">Brak wybranych pytań</small>';
            } else {
                let html = '<div class="list-group list-group-flush">';
                checkboxes.forEach((checkbox, index) => {
                    const label = checkbox.nextElementSibling;
                    const questionText = label.querySelector('h6').textContent.trim();
                    html += `
                        <div class="list-group-item px-0 py-1">
                            <small class="text-primary">${index + 1}.</small>
                            <small>${questionText.substring(0, 50)}${questionText.length > 50 ? '...' : ''}</small>
                        </div>
                    `;
                });
                html += '</div>';
                selectedList.innerHTML = html;
            }
            
            // Aktualizuj przycisk generowania
            const generateBtn = document.getElementById('generate-btn');
            if (selectedCount === 0) {
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-ban"></i> Wybierz przynajmniej jedno pytanie';
            } else {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="fas fa-file-pdf"></i> Generuj raport PDF';
            }
        }
        
        // Zaznacz wszystkie pytania
        function selectAll() {
            document.querySelectorAll('.question-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updatePreview();
        }
        
        // Odznacz wszystkie pytania
        function deselectAll() {
            document.querySelectorAll('.question-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updatePreview();
        }
        
        // Obsługa zmiany checkboxów
        document.addEventListener('DOMContentLoaded', function() {
            // Debugowanie - sprawdź numery pytań
            console.log('=== DEBUG: Numery pytań ===');
            document.querySelectorAll('.question-checkbox').forEach(checkbox => {
                const questionNumber = parseInt(checkbox.dataset.questionNumber);
                const questionId = parseInt(checkbox.dataset.questionId);
                const isChecked = checkbox.checked;
                const shouldBeChecked = ![10, 11, 12, 13, 14, 15, 16, 17].includes(questionNumber);
                console.log(`Pytanie #${questionNumber} (ID: ${questionId}), Zaznaczone: ${isChecked}, Powinno być: ${shouldBeChecked}`);
                
                // Jeśli checkbox nie jest w prawidłowym stanie, popraw go
                if (isChecked !== shouldBeChecked) {
                    checkbox.checked = shouldBeChecked;
                    console.log(`Poprawiono pytanie #${questionNumber} na ${shouldBeChecked}`);
                }
            });
            
            document.querySelectorAll('.question-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updatePreview);
            });
            
            // Inicjalizuj podgląd
            updatePreview();
        });
        
        // Obsługa formularza
        document.getElementById('report-form').addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.question-checkbox:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Proszę wybrać przynajmniej jedno pytanie do raportu.');
                return false;
            }
        });
    </script>
</x-app-layout>
