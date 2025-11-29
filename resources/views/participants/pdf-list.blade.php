<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista uczestników</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .organization {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #000;
        }
        
        .platform {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #000;
        }
        
        .title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            text-transform: uppercase;
        }
        
        .course-info {
            background-color: #f5f5f5;
            padding: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #000;
            border: 1px solid #000;
            font-size: 13px;
        }
        
        .search-info {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #000;
            border: 1px solid #000;
            font-size: 12px;
            color: #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #e0e0e0;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            color: #000;
        }
        
        td {
            font-size: 12px;
            color: #000;
        }
        
        .no-certificate {
            color: #333;
            font-style: italic;
        }
        
        .footer {
            position: fixed;
            bottom: 8px;
            left: 10px;
            right: 10px;
            text-align: center;
            font-size: 11px;
            color: #000;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        
        .date-info {
            text-align: right;
            font-size: 12px;
            color: #000;
            margin-bottom: 15px;
        }
        
        @page {
            margin: 15mm 10mm 25mm 10mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="organization">Niepubliczny Ośrodek Doskonalenia Nauczycieli</div>
        <div class="platform">"Platforma Nowoczesnej Edukacji"</div>
        <div class="title">Lista uczestników</div>
    </div>
    
    <div class="course-info">
        <strong>Szkolenie:</strong> {!! $course->title !!}<br>
        <strong>Trener:</strong> 
        @if($course->instructor)
            {{ $course->instructor->getFullTitleNameAttribute() }}
        @else
            Brak trenera
        @endif
        <br>
        <strong>Data:</strong> 
        @if($course->start_date)
            {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y H:i') }}
            @if($course->end_date)
                @php
                    $startDateTime = \Carbon\Carbon::parse($course->start_date);
                    $endDateTime = \Carbon\Carbon::parse($course->end_date);
                    $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
                @endphp
                <br><strong>Czas trwania:</strong> {{ $durationMinutes }} minut
            @endif
        @else
            Brak daty
        @endif
        <br>
        <strong>Liczba uczestników:</strong> {{ $totalCount }}
    </div>
    
    @if($searchTerm)
        <div class="search-info">
            <strong>Wyniki wyszukiwania dla:</strong> "{{ $searchTerm }}"<br>
            <strong>Znaleziono:</strong> {{ $totalCount }} {{ $totalCount == 1 ? 'uczestnik' : ($totalCount < 5 ? 'uczestników' : 'uczestników') }}
        </div>
    @endif
    
    <div class="date-info">
        <strong>Wygenerowano:</strong> {{ \Carbon\Carbon::now()->format('d.m.Y H:i') }}
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 6%;">L.p.</th>
                <th style="width: 7%;">ID</th>
                <th style="width: 18%;">Imię</th>
                <th style="width: 18%;">Nazwisko</th>
                <th style="width: 13%;">Data urodzenia</th>
                <th style="width: 15%;">Miejsce urodzenia</th>
                <th style="width: 23%;">Nr certyfikatu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $index => $participant)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $participant->id }}</td>
                    <td>{{ $participant->first_name }}</td>
                    <td>{{ $participant->last_name }}</td>
                    <td>
                        @if($participant->birth_date)
                            {{ \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $participant->birth_place ?? '-' }}</td>
                    <td>
                        @if($participant->certificate)
                            <strong>{{ $participant->certificate->certificate_number }}</strong>
                        @else
                            <span class="no-certificate">Brak certyfikatu</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <div style="display: flex; justify-content: center; align-items: center;">
            <div>
                <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli "Platforma Nowoczesnej Edukacji"</strong><br>
                ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
            </div>
        </div>
    </div>
</body>
</html>
