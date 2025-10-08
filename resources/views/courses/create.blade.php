<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe szkolenie') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Formularz dodawania kursu -->
            <form action="{{ route('courses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł kursu</label>
                    <input type="text" name="title" id="title" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis</label>
                    <textarea name="description" id="description" class="form-control"></textarea>
                </div>

                <!-- Sekcja opisu oferty -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Opis oferty dla klientów</h5>
                        <small class="text-muted">Pełny opis oferty wyświetlany na stronie pnedu.pl</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="offer_summary" class="form-label">Krótkie podsumowanie oferty</label>
                            <textarea name="offer_summary" class="form-control" id="offer_summary" rows="2" placeholder="Krótki opis oferty (max 500 znaków)"></textarea>
                            <div class="form-text">Krótkie podsumowanie wyświetlane w liście szkoleń</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="offer_description_html" class="form-label">Pełny opis oferty (HTML)</label>
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')" title="Pogrubienie">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')" title="Kursywa">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')" title="Podkreślenie">
                                        <i class="fas fa-underline"></i>
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTag('h3')" title="Nagłówek 3">
                                        H3
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTag('h4')" title="Nagłówek 4">
                                        H4
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ul')" title="Lista punktowana">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ol')" title="Lista numerowana">
                                        <i class="fas fa-list-ol"></i>
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertLink()" title="Link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewHtml()" title="Podgląd">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea name="offer_description_html" class="form-control" id="offer_description_html" rows="10" placeholder="Wpisz pełny opis oferty z formatowaniem HTML..."></textarea>
                            <div class="form-text">
                                Możesz używać podstawowych tagów HTML: &lt;strong&gt;, &lt;em&gt;, &lt;h3&gt;, &lt;h4&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;a&gt;, &lt;p&gt;, &lt;br&gt;
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row mb-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="datetime-local" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Data zakończenia</label>
                        <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $course->end_date ?? '') }}">
                        @error('end_date')
                            <div class="text-danger">{{ $message }}</div> <!-- ✅ Wyświetlanie błędu -->
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label">Płatność</label>
                        <select name="is_paid" class="form-control" id="is_paid" required>
                            <option value="1">Płatne</option>
                            <option value="0">Bezpłatne</option>
                        </select>
                    </div>                    
                    <div class="col-md-2">
                        <label for="category" class="form-label">Kategoria</label>
                        <select name="category" class="form-control" id="category" required>
                            <option value="open">Otwarte</option>
                            <option value="closed">Zamknięte</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rodzaj kursu</label>
                        <select name="type" id="type" class="form-control" onchange="toggleCourseFields()">
                            <option value="online" selected>Online</option>
                            <option value="offline">Stacjonarne</option>
                        </select>
                    </div>
                </div>

                <!-- Pola dla kursów online -->
                <div id="onlineFields" class="row">
                    <div class="col-md-4">
                        <label for="platform" class="form-label">Platforma</label>
                        <input type="text" name="platform" id="platform" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_link" class="form-label">Link do spotkania</label>
                        <input type="url" name="meeting_link" id="meeting_link" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                        <input type="text" name="meeting_password" id="meeting_password" class="form-control">
                    </div>
                </div>


                <!-- Pola dla kursów stacjonarnych -->
                <div id="offlineFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="location_name">Nazwa lokalizacji</label>
                            <input type="text" name="location_name" id="location_name" class="form-control" value="{{ old('location_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="address">Adres</label>
                            <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}">
                        </div>
                    </div>
                    <div class="row mt-2">                    
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" name="post_office" id="post_office" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" name="country" id="country" class="form-control" value="Polska">
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="instructor_id" class="form-label">Instruktor</label>
                            <select name="instructor_id" id="instructor_id" class="form-control">
                                <option value="">Brak</option>
                                @foreach ($instructors as $instructor)
                                    <option value="{{ $instructor->id }}">{{ $instructor->first_name }} {{ $instructor->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="image" class="form-label">Obrazek</label>
                            <input type="file" name="image" id="image" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="certificate_format">Format numeracji certyfikatów</label>
                    <input type="text" name="certificate_format" id="certificate_format" class="form-control" 
                           value="{{ old('certificate_format', isset($course) ? $course->certificate_format : '{nr}/{course_id}/{year}/PNE') }}" 
                           placeholder="Wpisz format, np. RL/{nr}/{course_id}/2/{year}/PNE">
                    <small class="form-text text-muted">
                        Możesz używać zmiennych: <code>{nr}</code>, <code>{course_id}</code>, <code>{year}</code>.
                    </small>
                </div>

                <div class="form-group mb-3">
                    <label for="certificate_template_id">Szablon certyfikatu</label>
                    <select name="certificate_template_id" id="certificate_template_id" class="form-control">
                        <option value="">Domyślny szablon</option>
                        @foreach($certificateTemplates as $template)
                            <option value="{{ $template->id }}" {{ old('certificate_template_id') == $template->id ? 'selected' : '' }}>
                                {{ $template->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Wybierz szablon wyglądu certyfikatu dla tego kursu.
                        <a href="{{ route('admin.certificate-templates.index') }}" target="_blank">Zarządzaj szablonami</a>
                    </small>
                </div>

                <!-- Pola dla integracji ze starym systemem -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_old" class="form-label">ID ze starej bazy</label>
                        <input type="text" name="id_old" class="form-control" id="id_old" value="{{ old('id_old') }}" placeholder="np. 12345">
                        <div class="form-text">ID kursu w zewnętrznym systemie (opcjonalne)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="source_id_old" class="form-label">Źródło danych</label>
                        <select name="source_id_old" class="form-control" id="source_id_old">
                            <option value="">Brak</option>
                            <option value="certgen_Publigo" {{ old('source_id_old') == 'certgen_Publigo' ? 'selected' : '' }}>Publigo</option>
                            <option value="certgen_NODN" {{ old('source_id_old') == 'certgen_NODN' ? 'selected' : '' }}>NODN</option>
                            <option value="BD:Certgen-education" {{ old('source_id_old') == 'BD:Certgen-education' ? 'selected' : '' }}>Webinar TIK</option>
                        </select>
                        <div class="form-text">Źródło danych kursu (opcjonalne)</div>
                    </div>
                </div>                

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <button type="submit" class="btn btn-primary">Dodaj kurs</button>
                <a href="{{ route('courses.index') }}" class="btn btn-secondary">Anuluj</a>
            </form>
        </div>
    </div>

    <script>
        function toggleCourseFields() {
            const type = document.getElementById('type').value;
            document.getElementById('onlineFields').style.display = (type === 'online') ? 'flex' : 'none';
            document.getElementById('offlineFields').style.display = (type === 'offline') ? 'block' : 'none';
        }

        // Funkcje edytora HTML
        function formatText(command) {
            const textarea = document.getElementById('offer_description_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            let formattedText = '';
            switch(command) {
                case 'bold':
                    formattedText = `<strong>${selectedText || 'pogrubiony tekst'}</strong>`;
                    break;
                case 'italic':
                    formattedText = `<em>${selectedText || 'tekst kursywą'}</em>`;
                    break;
                case 'underline':
                    formattedText = `<u>${selectedText || 'podkreślony tekst'}</u>`;
                    break;
            }
            
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            textarea.focus();
        }

        function insertTag(tag) {
            const textarea = document.getElementById('offer_description_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            const formattedText = `<${tag}>${selectedText || 'nagłówek'}</${tag}>`;
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            textarea.focus();
        }

        function insertList(type) {
            const textarea = document.getElementById('offer_description_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            
            const listHtml = `<${type}>
    <li>Pierwszy punkt</li>
    <li>Drugi punkt</li>
    <li>Trzeci punkt</li>
</${type}>`;
            
            textarea.value = textarea.value.substring(0, start) + listHtml + textarea.value.substring(end);
            textarea.focus();
        }

        function insertLink() {
            const textarea = document.getElementById('offer_description_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            const url = prompt('Podaj URL:', 'https://');
            if (url) {
                const linkText = selectedText || 'tekst linku';
                const linkHtml = `<a href="${url}">${linkText}</a>`;
                textarea.value = textarea.value.substring(0, start) + linkHtml + textarea.value.substring(end);
                textarea.focus();
            }
        }

        function previewHtml() {
            const textarea = document.getElementById('offer_description_html');
            const htmlContent = textarea.value;
            
            if (!htmlContent.trim()) {
                alert('Brak treści do podglądu');
                return;
            }
            
            const newWindow = window.open('', '_blank', 'width=800,height=600');
            newWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Podgląd opisu oferty</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        h3, h4 { color: #333; margin-top: 20px; }
                        ul, ol { margin-left: 20px; }
                        a { color: #007bff; text-decoration: none; }
                        a:hover { text-decoration: underline; }
                    </style>
                </head>
                <body>
                    ${htmlContent}
                </body>
                </html>
            `);
            newWindow.document.close();
        }

        // Wywołanie funkcji przy załadowaniu strony, aby ukryć/pokazać odpowiednie pola
        document.addEventListener('DOMContentLoaded', function () {
            toggleCourseFields();
        });
    </script>

</x-app-layout>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let startDateInput = document.querySelector('input[name="start_date"]');
        let endDateInput = document.querySelector('input[name="end_date"]');

        function validateDates() {
            let startDate = new Date(startDateInput.value);
            let endDate = new Date(endDateInput.value);

            if (endDate <= startDate) {
                alert("Data zakończenia musi być późniejsza niż data rozpoczęcia!");
                endDateInput.value = ""; // Resetowanie błędnej wartości
            }
        }

        endDateInput.addEventListener("change", validateDates);
    });
</script>
