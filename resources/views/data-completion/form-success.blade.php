<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sukces - {{ config('app.name') }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding: 2rem 0;
        }
        .success-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-3">Dziękujemy!</h3>
                    <p class="text-muted mb-3">
                        {{ $message }}
                    </p>
                    <p class="text-muted mb-4">
                        W najbliższym czasie otrzymasz od nas wiadomość e-mail z linkiem do pobrania kompletnego zaświadczenia o ukończeniu szkolenia.
                    </p>
                    <p class="text-muted small">
                        Możesz zamknąć to okno.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

