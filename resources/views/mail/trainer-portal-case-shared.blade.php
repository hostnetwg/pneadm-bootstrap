Nowe zapytanie o szkolenie zamknięte.

Temat: {{ $case->trainingTopicLabel() }}
Placówka: {{ $case->institution ?: 'nie podano' }}

Kontakt do osoby pytającej jest w panelu trenera:
{{ $panelUrl }}
@if(filled($case->trainer_panel_message))

Wiadomość od administratora:
{{ $case->trainer_panel_message }}
@endif

{{ $senderName }}
NODN "Platforma Nowoczesnej Edukacji"
