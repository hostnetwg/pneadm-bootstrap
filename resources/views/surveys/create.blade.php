<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj ankietę') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {!! session('success') !!}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {!! session('error') !!}
                </div>
            @endif

            <!-- Nawigacja -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>Dodaj nową ankietę</h4>
                    <p class="text-muted mb-0">Utwórz nową ankietę dla wybranego szkolenia</p>
                </div>
                <a href="{{ route('surveys.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Powrót do listy
                </a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-plus"></i> Nowa ankieta
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('surveys.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                
                                <!-- Sekcja wczytywania pliku -->
                                <div class="mb-4">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-upload"></i> Automatyczne wyszukiwanie szkolenia na podstawie daty
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="survey_file" class="form-label">Wybierz plik ankiety</label>
                                                <input type="file" class="form-control @error('survey_file') is-invalid @enderror" 
                                                       id="survey_file" name="survey_file" accept=".csv,.xlsx,.xls">
                                                <div class="form-text">
                                                    Obsługiwane formaty: CSV, Excel (.xlsx, .xls). Nazwa pliku powinna zawierać datę w nawiasach, np. "Ankieta (2024-01-15).csv" lub "Raport (15.01.2024).xlsx"
                                                    <br><strong>Uwaga:</strong> Pliki CSV zostaną automatycznie zaimportowane z danymi ankiety.
                                                </div>
                                                @error('survey_file')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            
                                            <div id="file-info" class="alert alert-info" style="display: none;">
                                                <h6><i class="fas fa-info-circle"></i> Informacje z pliku:</h6>
                                                <div id="parsed-info"></div>
                                            </div>
                                            
                                            <div id="course-selection" class="mt-3" style="display: none;">
                                                <h6><i class="fas fa-list"></i> Wybierz szkolenie:</h6>
                                                <div id="courses-list"></div>
                                                <button type="button" id="select-course-btn" class="btn btn-primary btn-sm mt-2" style="display: none;">
                                                    <i class="fas fa-check"></i> Wybierz szkolenie
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Tytuł ankiety <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Opis ankiety</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Szkolenie <span class="text-danger">*</span></label>
                                    <select class="form-select @error('course_id') is-invalid @enderror" 
                                            id="course_id" name="course_id" required>
                                        <option value="">Wybierz szkolenie</option>
                                        @foreach($courses as $course)
                                            <option value="{{ $course->id }}" 
                                                    {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                                {{ $course->title }} 
                                                ({{ $course->start_date ? $course->start_date->format('d.m.Y') : 'Brak daty' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('course_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="instructor_id" class="form-label">Instruktor</label>
                                    <select class="form-select @error('instructor_id') is-invalid @enderror" 
                                            id="instructor_id" name="instructor_id">
                                        <option value="">Wybierz instruktora (opcjonalnie)</option>
                                        @foreach($instructors as $instructor)
                                            <option value="{{ $instructor->id }}" 
                                                    {{ old('instructor_id') == $instructor->id ? 'selected' : '' }}>
                                                {{ $instructor->getFullTitleNameAttribute() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('instructor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="source" class="form-label">Źródło <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('source') is-invalid @enderror" 
                                           id="source" name="source" value="{{ old('source', 'Google Forms') }}" required>
                                    <div class="form-text">
                                        Np. Google Forms, Microsoft Forms, itp.
                                    </div>
                                    @error('source')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="{{ route('surveys.index') }}" class="btn btn-secondary me-md-2">
                                        Anuluj
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Utwórz ankietę
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                Utworzenie ankiety pozwoli Ci na:
                            </p>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-check text-success"></i> Importowanie danych z pliku CSV</li>
                                <li><i class="fas fa-check text-success"></i> Analizę odpowiedzi</li>
                                <li><i class="fas fa-check text-success"></i> Generowanie raportów</li>
                                <li><i class="fas fa-check text-success"></i> Porównywanie wyników</li>
                            </ul>

                            <div class="alert alert-info mt-3">
                                <small>
                                    <i class="fas fa-lightbulb"></i>
                                    <strong>Wskazówka:</strong> Po utworzeniu ankiety będziesz mógł zaimportować dane z pliku CSV.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('survey_file');
            const fileInfo = document.getElementById('file-info');
            const parsedInfo = document.getElementById('parsed-info');
            const courseSelect = document.getElementById('course_id');
            const instructorSelect = document.getElementById('instructor_id');
            const titleInput = document.getElementById('title');
            const courseSelection = document.getElementById('course-selection');
            const coursesList = document.getElementById('courses-list');
            const selectCourseBtn = document.getElementById('select-course-btn');

            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    parseFileName(file.name);
                } else {
                    hideFileInfo();
                }
            });

            function parseFileName(fileName) {
                // Usuń rozszerzenie pliku
                const nameWithoutExtension = fileName.replace(/\.[^/.]+$/, "");
                
                // Szukaj daty w nawiasach - format: (YYYY-MM-DD) lub (DD.MM.YYYY)
                const datePattern = /\((\d{4}-\d{2}-\d{2})\)|\((\d{1,2}\.\d{1,2}\.\d{4})\)/;
                const dateMatch = nameWithoutExtension.match(datePattern);
                
                let extractedDate = null;
                
                if (dateMatch) {
                    // Wyciągnij datę
                    extractedDate = dateMatch[1] || dateMatch[2];
                    
                    // Konwertuj datę DD.MM.YYYY na YYYY-MM-DD jeśli potrzeba
                    if (extractedDate.includes('.')) {
                        const parts = extractedDate.split('.');
                        extractedDate = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
                    }
                }
                
                // Wyświetl informacje (tylko datę)
                showFileInfo(extractedDate);
                
                // Automatycznie wypełnij pola na podstawie daty
                autoFillForm(extractedDate);
            }

            function showFileInfo(date) {
                let infoHtml = '';
                if (date) {
                    infoHtml += `<strong>Data z pliku:</strong> ${date}<br>`;
                    infoHtml += `<strong>Status:</strong> <span id="search-status">Wyszukuję szkolenie na podstawie daty...</span>`;
                } else {
                    infoHtml += `<strong>Status:</strong> <span class="text-warning">Nie znaleziono daty w nawiasach w nazwie pliku</span>`;
                }
                
                parsedInfo.innerHTML = infoHtml;
                fileInfo.style.display = 'block';
            }

            function hideFileInfo() {
                fileInfo.style.display = 'none';
                courseSelection.style.display = 'none';
            }

            function updateSearchStatus(message, isSuccess = false) {
                const statusElement = document.getElementById('search-status');
                if (statusElement) {
                    statusElement.textContent = message;
                    statusElement.className = isSuccess ? 'text-success' : 'text-warning';
                }
            }

            function autoFillForm(date) {
                // Wyszukaj szkolenie w bazie danych tylko na podstawie daty
                if (date) {
                    searchForCourseByDate(date);
                }
            }

            function searchForCourseByDate(date) {
                // Przygotuj dane do wysłania
                const searchData = {
                    date: date,
                    _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                };

                // Wyślij żądanie AJAX
                fetch('{{ route("surveys.search-course") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': searchData._token
                    },
                    body: JSON.stringify(searchData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.single_course) {
                            // Znaleziono jedno szkolenie - automatycznie je wybierz
                            selectSingleCourse(data.course, data.instructor);
                        } else if (data.multiple_courses) {
                            // Znaleziono kilka szkoleń - pokaż listę do wyboru
                            showCourseSelection(data.courses, data.message);
                        }
                    } else {
                        // Nie znaleziono szkolenia
                        updateSearchStatus(data.message || 'Nie znaleziono szkolenia na podaną datę');
                    }
                })
                .catch(error => {
                    console.error('Błąd wyszukiwania:', error);
                    updateSearchStatus('Błąd podczas wyszukiwania szkolenia');
                });
            }

            function selectSingleCourse(course, instructor) {
                // Automatycznie wybierz pojedyncze szkolenie
                courseSelect.value = course.id;
                updateSearchStatus(`Znaleziono szkolenie: ${course.title}`, true);
                
                // Jeśli znaleziono instruktora, ustaw go
                if (instructor) {
                    instructorSelect.value = instructor.id;
                }
                
                // Ustaw tytuł ankiety na podstawie znalezionego szkolenia
                if (!titleInput.value) {
                    titleInput.value = `Ankieta - ${course.title}`;
                }
                
                // Wywołaj event change dla course select
                courseSelect.dispatchEvent(new Event('change'));
            }

            function showCourseSelection(courses, message) {
                // Pokaż listę szkoleń do wyboru
                updateSearchStatus(message);
                
                let coursesHtml = '<div class="list-group">';
                courses.forEach((course, index) => {
                    const instructorInfo = course.instructor ? ` - ${course.instructor.name}` : '';
                    const timeInfo = course.start_date ? ` (${course.start_date})` : '';
                    
                    coursesHtml += `
                        <div class="list-group-item">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="selected_course" 
                                       id="course_${course.id}" value="${course.id}">
                                <label class="form-check-label" for="course_${course.id}">
                                    <strong>${course.title}</strong>${instructorInfo}${timeInfo}
                                </label>
                            </div>
                        </div>
                    `;
                });
                coursesHtml += '</div>';
                
                coursesList.innerHTML = coursesHtml;
                courseSelection.style.display = 'block';
                selectCourseBtn.style.display = 'inline-block';
                
                // Dodaj event listener do przycisku wyboru
                selectCourseBtn.onclick = function() {
                    const selectedCourseId = document.querySelector('input[name="selected_course"]:checked');
                    if (selectedCourseId) {
                        const selectedCourse = courses.find(c => c.id == selectedCourseId.value);
                        if (selectedCourse) {
                            selectSingleCourse(selectedCourse, selectedCourse.instructor);
                            courseSelection.style.display = 'none';
                        }
                    } else {
                        alert('Proszę wybrać szkolenie z listy.');
                    }
                };
            }
        });
    </script>
</x-app-layout>
