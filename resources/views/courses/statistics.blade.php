<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki szkoleń</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 15px;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
        }
        
        .header .organization {
            font-size: 20px;
            margin: 0;
            font-weight: bold;
            color: #000;
            line-height: 1.2;
        }
        
        .header h1 {
            font-size: 18px;
            margin: 10px 0 0 0;
            font-weight: bold;
            color: #000;
        }
        
        .header .subtitle {
            font-size: 15px;
            color: #333;
            margin-top: 5px;
        }
        
        .filters-info {
            margin-bottom: 18px;
            padding: 8px;
            background-color: #f9f9f9;
            border-left: 3px solid #000;
            font-size: 12px;
            color: #000;
        }
        
        .main-statistics {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 3px solid #007bff;
        }
        
        .main-statistics h3 {
            margin: 0 0 15px 0;
            color: #000;
            font-size: 18px;
            font-weight: bold;
        }
        
        .main-statistics p {
            margin: 8px 0;
            font-size: 14px;
            color: #000;
            line-height: 1.4;
        }
        
        .statistics-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .statistics-grid-3 {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            border: 2px solid #000;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            flex: 1;
            min-width: 0;
        }
        
        .stat-card-4 {
            flex: 0 0 calc(25% - 12px);
        }
        
        .stat-card-3 {
            flex: 0 0 calc(33.333% - 10px);
        }
        
        .stat-card h3 {
            font-size: 16px;
            margin: 0 0 10px 0;
            color: #000;
            font-weight: bold;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card.primary {
            background-color: #e3f2fd;
            border-color: #1976d2;
        }
        
        .stat-card.success {
            background-color: #e8f5e8;
            border-color: #388e3c;
        }
        
        .stat-card.warning {
            background-color: #fff3e0;
            border-color: #f57c00;
        }
        
        .stat-card.info {
            background-color: #e0f2f1;
            border-color: #00796b;
        }
        
        .stat-card.danger {
            background-color: #ffebee;
            border-color: #d32f2f;
        }
        
        .stat-card.secondary {
            background-color: #f3e5f5;
            border-color: #7b1fa2;
        }
        
        .courses-list {
            margin-top: 25px;
        }
        
        .courses-list h3 {
            font-size: 16px;
            margin: 0 0 15px 0;
            padding: 10px;
            background-color: #f0f8ff;
            border-left: 4px solid #000;
            color: #000;
        }
        
        .course-item {
            margin-bottom: 15px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fafafa;
        }
        
        .course-title {
            font-weight: bold;
            font-size: 14px;
            color: #000;
            margin-bottom: 5px;
        }
        
        .course-details {
            font-size: 11px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 11px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        
        @page {
            margin: 15mm;
            @bottom-right {
                content: "Strona " counter(page) " z " counter(pages);
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="organization">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>"Platforma Nowoczesnej Edukacji"</div>
        <h1>Statystyki szkoleń</h1>
        <div class="subtitle">Wygenerowano: {{ $generated_at->format('d.m.Y H:i') }}</div>
    </div>
    
    @if(!empty(array_filter($filters_applied)))
        <div class="filters-info">
            <strong>Zastosowane filtry:</strong><br>
            @if(isset($filters_applied['search']) && $filters_applied['search'])
                Wyszukiwanie: {{ $filters_applied['search'] }}<br>
            @endif
            @if(isset($filters_applied['date_from']) && $filters_applied['date_from'])
                Data od: {{ $filters_applied['date_from'] }}<br>
            @endif
            @if(isset($filters_applied['date_to']) && $filters_applied['date_to'])
                Data do: {{ $filters_applied['date_to'] }}<br>
            @endif
            @if(isset($filters_applied['is_paid']) && $filters_applied['is_paid'] !== '')
                Płatność: {{ $filters_applied['is_paid'] == '1' ? 'Płatne' : 'Bezpłatne' }}<br>
            @endif
            @if(isset($filters_applied['type']) && $filters_applied['type'])
                Rodzaj: {{ $filters_applied['type'] === 'offline' ? 'Stacjonarne' : ucfirst($filters_applied['type']) }}<br>
            @endif
            @if(isset($filters_applied['category']) && $filters_applied['category'])
                Kategoria: {{ $filters_applied['category'] === 'open' ? 'Otwarte' : 'Zamknięte' }}<br>
            @endif
            @if(isset($filters_applied['instructor_id']) && $filters_applied['instructor_id'])
                Instruktor: {{ \App\Models\Instructor::find($filters_applied['instructor_id'])->getFullTitleNameAttribute() ?? 'Nieznany' }}<br>
            @endif
            @if(isset($filters_applied['date_filter']) && $filters_applied['date_filter'] !== 'all')
                Termin: {{ $filters_applied['date_filter'] === 'upcoming' ? 'Nadchodzące' : 'Archiwalne' }}<br>
            @endif
        </div>
    @endif
    
    <!-- Statystyki główne - tekst -->
    <div class="main-statistics">
        <h3>Podsumowanie statystyk:</h3>
        <p><strong>Łączna liczba szkoleń:</strong> {{ $statistics['total_courses'] }} ({{ $statistics['paid_courses'] }} płatnych, {{ $statistics['free_courses'] }} bezpłatnych)</p>
        <p><strong>Typy szkoleń:</strong> {{ $statistics['online_courses'] }} online, {{ $statistics['offline_courses'] }} stacjonarnych, {{ $statistics['open_courses'] }} otwartych, {{ $statistics['closed_courses'] }} zamkniętych</p>
        <p><strong>Łączna liczba uczestników:</strong> {{ $statistics['total_participants'] }}</p>
        <p><strong>Godziny szkoleń:</strong> {{ $statistics['total_hours_paid'] }}h (płatne), {{ $statistics['total_hours_free'] }}h (bezpłatne)</p>
    </div>
    
    <!-- Lista szkoleń -->
    <div class="courses-list">
        <h3>Lista szkoleń ({{ $courses->count() }})</h3>
        @foreach($courses as $course)
            <div class="course-item">
                <div class="course-title">{!! $course->title !!}</div>
                <div class="course-details">
                    <div>
                        <strong>Data:</strong> {{ $course->start_date ? $course->start_date->format('d.m.Y H:i') : 'Brak daty' }} |
                        <strong>Typ:</strong> {{ $course->type === 'offline' ? 'Stacjonarne' : ucfirst($course->type) }} |
                        <strong>Kategoria:</strong> {{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }} |
                        <strong>Płatność:</strong> {{ $course->is_paid ? 'Płatne' : 'Bezpłatne' }}
                    </div>
                    <div>
                        <strong>Czas trwania:</strong> {{ $course->start_date && $course->end_date ? $course->start_date->diffInMinutes($course->end_date) : 0 }} min |
                        <strong>Uczestnicy:</strong> {{ $course->participants->count() }}
                    </div>
                    @if($course->instructor)
                    <div>
                        <strong>Trener:</strong> {{ $course->instructor->title }} {{ $course->instructor->first_name }} {{ $course->instructor->last_name }}
                    </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
    <div class="footer">
        <div>
            <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli "Platforma Nowoczesnej Edukacji"</strong><br>
            ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
        </div>
    </div>
</body>
</html>
