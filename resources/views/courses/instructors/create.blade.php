<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Dodaj nowego instruktora
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Dodaj nowego instruktora</h1>

            <!-- Komunikat o błędach -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('courses.instructors.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Wiersz z tytułem, imieniem i nazwiskiem -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="title" class="form-label">Tytuł naukowy</label>
                        <input type="text" name="title" class="form-control" id="title" placeholder="">
                    </div>
                    <div class="col-md-3">
                        <label for="first_name" class="form-label">Imię</label>
                        <input type="text" name="first_name" class="form-control" id="first_name" required>
                    </div>
                    <div class="col-md-3">
                        <label for="last_name" class="form-label">Nazwisko</label>
                        <input type="text" name="last_name" class="form-control" id="last_name" required>
                    </div>
                    <div class="col-md-3">
                        <label for="gender" class="form-label">Płeć</label>
                        <select name="gender" class="form-control" id="gender">
                            <option value="">Wybierz płeć</option>
                            @foreach(\App\Models\Instructor::getGenderOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Wiersz z e-mailem i telefonem -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" id="email" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" id="phone">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="bio" class="form-label">Krótka biografia</label>
                    <textarea name="bio" class="form-control" id="bio" rows="3" placeholder="Krótki opis instruktora (max 500 znaków)"></textarea>
                </div>

                <!-- Sekcja pełnej biografii HTML -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Pełna biografia instruktora (HTML)</h5>
                        <small class="text-muted">Sformatowana biografia wyświetlana w ofercie szkolenia</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bio_html" class="form-label">Pełna biografia w HTML</label>
                            
                            <!-- Toolbar dla edytora HTML -->
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="formatText('bold')" title="Pogrubienie">
                                        <strong>B</strong>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="formatText('italic')" title="Kursywa">
                                        <em>I</em>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="formatText('underline')" title="Podkreślenie">
                                        <u>U</u>
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertTag('h3')" title="Nagłówek 3">
                                        H3
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertTag('h4')" title="Nagłówek 4">
                                        H4
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertList('ul')" title="Lista punktowana">
                                        • Lista
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertList('ol')" title="Lista numerowana">
                                        1. Lista
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="insertLink()" title="Wstaw link">
                                        🔗 Link
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="previewHtml('bio_html')" title="Podgląd HTML">
                                        👁️ Podgląd
                                    </button>
                                </div>
                            </div>
                            
                            <textarea name="bio_html" id="bio_html" class="form-control" rows="10" placeholder="Wpisz pełną biografię instruktora z formatowaniem HTML..."></textarea>
                            <small class="form-text text-muted">
                                Możesz używać podstawowych tagów HTML: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;h3&gt;, &lt;h4&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;a&gt;
                            </small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="photo" class="form-label">Zdjęcie</label>
                    <input type="file" name="photo" class="form-control" id="photo">
                </div>

                <div class="mb-3">
                    <label for="signature" class="form-label">Podpis (grafika)</label>
                    <input type="file" name="signature" class="form-control" id="signature">
                </div>                

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <button type="submit" class="btn btn-success">Dodaj instruktora</button>
            </form>
        </div>
    </div>

    <script>
        // Funkcje dla edytora HTML
        function formatText(command) {
            const textarea = document.getElementById('bio_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            let formattedText = '';
            switch(command) {
                case 'bold':
                    formattedText = `<strong>${selectedText}</strong>`;
                    break;
                case 'italic':
                    formattedText = `<em>${selectedText}</em>`;
                    break;
                case 'underline':
                    formattedText = `<u>${selectedText}</u>`;
                    break;
            }
            
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            textarea.focus();
        }

        function insertTag(tag) {
            const textarea = document.getElementById('bio_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            let formattedText = '';
            if (selectedText) {
                formattedText = `<${tag}>${selectedText}</${tag}>`;
            } else {
                formattedText = `<${tag}></${tag}>`;
            }
            
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            textarea.focus();
        }

        function insertList(type) {
            const textarea = document.getElementById('bio_html');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            let formattedText = '';
            if (selectedText) {
                const lines = selectedText.split('\n').filter(line => line.trim());
                const listItems = lines.map(line => `    <li>${line.trim()}</li>`).join('\n');
                formattedText = `<${type}>\n${listItems}\n</${type}>`;
            } else {
                formattedText = `<${type}>\n    <li></li>\n</${type}>`;
            }
            
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            textarea.focus();
        }

        function insertLink() {
            const url = prompt('Wprowadź URL:');
            if (url) {
                const text = prompt('Wprowadź tekst linku (opcjonalnie):') || url;
                const textarea = document.getElementById('bio_html');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                
                const linkHtml = `<a href="${url}">${text}</a>`;
                textarea.value = textarea.value.substring(0, start) + linkHtml + textarea.value.substring(end);
                textarea.focus();
            }
        }

        function previewHtml(textareaId) {
            const textarea = document.getElementById(textareaId);
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
                    <title>Podgląd HTML</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        h3, h4 { color: #333; margin-top: 20px; }
                        ul, ol { margin: 10px 0; padding-left: 30px; }
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
    </script>
</x-app-layout>
