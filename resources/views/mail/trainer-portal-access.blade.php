Witaj {{ $pneduUser->first_name }},

Administrator włączył Ci panel trenera na Platformie Nowoczesnej Edukacji.
To osobny widok, obok panelu uczestnika.

@if($createdAccount)
Ustaw hasło, a potem wejdziesz do panelu:
{{ $actionUrl }}
@else
Logujesz się dotychczasowym hasłem:
{{ $actionUrl }}
@endif
