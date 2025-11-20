<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista szkoleń</title>
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
            color: #000;
        }
        
        td {
            font-size: 11px;
            color: #000;
        }
        
        .course-lp {
            text-align: center;
            width: 30px;
        }
        
        .course-id {
            text-align: center;
            width: 40px;
        }
        
        .course-date {
            width: 80px;
            text-align: center;
        }
        
        .course-title {
            font-weight: bold;
            width: 150px;
        }
        
        .course-type {
            width: 80px;
        }
        
        .course-location {
            width: 120px;
        }
        
        .course-instructor {
            width: 100px;
        }
        
        .course-participants {
            text-align: center;
            width: 50px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 4px;
            font-size: 9px;
            margin: 1px;
            font-weight: bold;
        }
        
        .badge-paid {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-free {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-online {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-offline {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-open {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-closed {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-active {
            border: 1px solid #000;
            color: #000;
        }
        
        .badge-inactive {
            border: 1px solid #000;
            color: #000;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 11px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        
        .page-break {
            page-break-before: always;
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
        <div class="organization">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji</div>
        <h1>Lista szkoleń</h1>
        <div class="subtitle">Wygenerowano: {{ now()->format('d.m.Y H:i') }}</div>
    </div>
    
    @if($appliedFilters)
        <div class="filters-info">
            <strong>Zastosowane filtry:</strong><br>
            @foreach($appliedFilters as $filter => $value)
                @if($value)
                    {{ ucfirst(str_replace('_', ' ', $filter)) }}: {{ $value }}<br>
                @endif
            @endforeach
        </div>
    @endif
    
    <table>
        <thead>
            <tr>
                <th class="course-lp">L.p.</th>
                <th class="course-id">ID</th>
                <th class="course-date">Data</th>
                <th class="course-title">Tytuł</th>
                <th class="course-type">Rodzaj</th>
                <th class="course-location">Lokalizacja / Dostęp</th>
                <th class="course-instructor">Instruktor</th>
                <th class="course-participants">Uczestnicy</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courses as $index => $course)
                <tr>
                    <td class="course-lp">{{ $index + 1 }}</td>
                    <td class="course-id">{{ $course->id }}</td>
                    <td class="course-date">
                        @if ($course->start_date && $course->end_date)
                            {{ date('d.m.Y H:i', strtotime($course->start_date)) }}<br>
                            @php
                                $startDateTime = \Carbon\Carbon::parse($course->start_date);
                                $endDateTime = \Carbon\Carbon::parse($course->end_date);
                                $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
                            @endphp
                            <small>{{ $durationMinutes }} min</small>
                        @else
                            {{ $course->start_date ? date('d.m.Y H:i', strtotime($course->start_date)) : 'Brak daty' }}
                        @endif
                    </td>
                    <td class="course-title">{!! $course->title !!}</td>
                    <td class="course-type">
                        <span class="badge {{ $course->is_paid ? 'badge-paid' : 'badge-free' }}">
                            {{ $course->is_paid ? 'Płatne' : 'Bezpłatne' }}
                        </span><br>
                        <span class="badge {{ $course->type === 'online' ? 'badge-online' : 'badge-offline' }}">
                            {{ $course->type === 'offline' ? 'Stacjonarne' : ucfirst($course->type) }}
                        </span><br>
                        <span class="badge {{ $course->category === 'open' ? 'badge-open' : 'badge-closed' }}">
                            {{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }}
                        </span>
                    </td>
                    <td class="course-location">
                        @if ($course->type === 'offline' && $course->location)
                            <strong>{{ $course->location->location_name ?? 'Brak nazwy lokalizacji' }}</strong><br>
                            {{ $course->location->address ?? 'Brak adresu' }}<br>
                            {{ $course->location->postal_code ?? '' }} {{ $course->location->post_office ?? '' }}
                        @elseif ($course->type === 'online' && $course->onlineDetails)
                            <strong>Platforma:</strong> {{ $course->onlineDetails->platform ?? 'Nieznana' }}<br>
                            <strong>Link:</strong> 
                            @if (strtolower($course->onlineDetails->platform ?? '') === 'youtube')
                                {{ $course->onlineDetails->meeting_link ?? 'Brak linku' }}
                            @endif
                        @else
                            Brak danych
                        @endif
                    </td>
                    <td class="course-instructor">
                        {{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}
                    </td>
                    <td class="course-participants">{{ $course->participants->count() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji</strong><br>
                ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
            </div>
        </div>
    </div>
</body>
</html>
