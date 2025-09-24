<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista zaświadczeń</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .header h2 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #34495e;
        }
        
        .header .course-info {
            font-size: 14px;
            margin: 5px 0;
        }
        
        .header .instructor-info {
            font-size: 12px;
            margin: 5px 0;
            font-style: italic;
        }
        
        .date-info {
            font-size: 12px;
            margin: 10px 0;
            text-align: right;
        }
        
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .participants-table th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
        }
        
        .participants-table td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            font-size: 10px;
        }
        
        .participants-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .participants-table tr:hover {
            background-color: #e9ecef;
        }
        
        .no-certificate {
            color: #dc3545;
            font-style: italic;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LISTA ZAŚWIADCZEŃ</h1>
        <h2>{{ $course->title }}</h2>
        
        <div class="course-info">
            <strong>Data szkolenia:</strong> 
            @if($course->start_date)
                {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y H:i') }}
            @else
                Brak daty
            @endif
        </div>
        
        @if($instructor)
            <div class="instructor-info">
                <strong>Trener:</strong> {{ $instructor->first_name }} {{ $instructor->last_name }}
            </div>
        @endif
    </div>
    
    <div class="date-info">
        <strong>Data wygenerowania:</strong> {{ \Carbon\Carbon::now()->format('d.m.Y H:i') }}
    </div>
    
    <table class="participants-table">
        <thead>
            <tr>
                <th style="width: 5%;">Lp.</th>
                <th style="width: 20%;">Imię</th>
                <th style="width: 20%;">Nazwisko</th>
                <th style="width: 15%;">Data urodzenia</th>
                <th style="width: 20%;">Miejsce urodzenia</th>
                <th style="width: 20%;">Nr zaświadczenia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $index => $participant)
                <tr>
                    <td style="text-align: center;">{{ $participant->order ?? ($index + 1) }}</td>
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
                            <span class="no-certificate">Brak zaświadczenia</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <p>Łączna liczba uczestników: {{ $participants->count() }}</p>
        <p>Uczestnicy z zaświadczeniami: {{ $participants->where('certificate', '!=', null)->count() }}</p>
        <p>Uczestnicy bez zaświadczeń: {{ $participants->where('certificate', null)->count() }}</p>
    </div>
</body>
</html>
