# Diagnostyka problemu z wysyłką emaili

## Status
Email jest wysyłany pomyślnie przez Laravel (brak błędów w logach).

## Możliwe przyczyny braku dostarczenia:

### 1. Email trafia do spamu
- Sprawdź folder SPAM w skrzynce waldemar.grabowski@hostnet.pl
- Sprawdź filtry antyspamowe

### 2. Serwer SMTP odrzuca email
- Sprawdź logi serwera SMTP (tekyon.civ.pl)
- Może być problem z autoryzacją lub SPF/DKIM

### 3. Konfiguracja SMTP
Obecna konfiguracja:
- Host: tekyon.civ.pl
- Port: 587
- Encryption: TLS
- Username: kontakt@nowoczesna-edukacja.pl
- FROM: kontakt@nowoczesna-edukacja.pl (zgodny z autoryzacją)
- ReplyTo: biuro@nowoczesna-edukacja.pl

## Jak sprawdzić:

1. **Sprawdź logi aplikacji:**
```bash
sail artisan tail
# lub
tail -100 storage/logs/laravel.log | grep -i "email\|mail\|smtp"
```

2. **Sprawdź Mailpit (jeśli używasz lokalnie):**
http://localhost:8026

3. **Test bezpośredni:**
```bash
sail artisan data-completion:test-email EMAIL --course-id=ID
```

4. **Sprawdź skrzynkę spam** w waldemar.grabowski@hostnet.pl

## Następne kroki:

Jeśli email nadal nie dociera:
1. Sprawdź logi serwera SMTP (tekyon.civ.pl)
2. Skontaktuj się z administratorem serwera SMTP
3. Sprawdź czy serwer SMTP wymaga dodatkowej konfiguracji dla biuro@nowoczesna-edukacja.pl

