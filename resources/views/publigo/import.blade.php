<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Import danych z CSV</h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h2 class="mb-4">Importuj dane z pliku CSV</h2>

            <form id="importForm" enctype="multipart/form-data">
                @csrf
                <input type="file" name="csv_file" id="csv_file" required>
                <button type="submit" class="btn btn-primary">Importuj CSV</button>
            </form>
            
            <!-- Miejsce na komunikat -->
            <div id="message" class="mt-3"></div>
            
            <script>
            document.getElementById('importForm').addEventListener('submit', function(e) {
                e.preventDefault(); // Zatrzymujemy domyślne przeładowanie strony
            
                let formData = new FormData(this);
            
                fetch("{{ route('publigo.import') }}", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('input[name=_token]').value
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('message').innerHTML = 
                            `<div class="alert alert-success">${data.message}</div>`;
                    } else {
                        document.getElementById('message').innerHTML = 
                            `<div class="alert alert-danger">Wystąpił błąd.</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('message').innerHTML = 
                        `<div class="alert alert-danger">Błąd podczas przesyłania pliku.</div>`;
                });
            });
            </script>
<script>
    document.getElementById('importForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Zatrzymujemy domyślne przeładowanie strony
    
        let formData = new FormData(this);
    
        fetch("{{ route('publigo.import') }}", {
            method: "POST",
            body: formData,
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name=_token]').value
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('message').innerHTML = 
                    `<div class="alert alert-success">${data.message}</div>`;
            } else {
                document.getElementById('message').innerHTML = 
                    `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            document.getElementById('message').innerHTML = 
                `<div class="alert alert-danger">Błąd: ${error.message}</div>`;
        });
    });
    </script>
                
        </div>
    </div>
</x-app-layout>
