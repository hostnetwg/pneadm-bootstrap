# PNE Growth OS — integracje

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1
Status: architektura integracji, bez implementacji

## Zasada nadrzędna

`adm.pnedu.pl` przechowuje kanoniczny stan kampanii, treści, approval i wykonania. System zewnętrzny jest wykonawcą lub źródłem odczytowym.

Nie wolno przechowywać jedynej kopii istotnej decyzji lub statusu w n8n, Make, Sendy, Canva, YouTube albo przypadkowym skrypcie.

## Wspólny kontrakt adaptera

Każda integracja powinna definiować:

- dozwolone operacje,
- wymagane uprawnienia,
- timeout,
- strategię retry,
- klucz idempotencji,
- mapowanie statusów,
- bezpieczny log,
- limit szybkości i kosztu,
- tryb testowy/dry-run,
- sposób wyłączenia,
- procedurę ręcznego ponowienia i rollbacku.

## Cykl operacji

```text
draft
→ preview
→ approval
→ integration operation queued
→ request sent
→ provider result recorded
→ completed / failed / requires attention
```

Każde wykonanie powinno mieć własny rekord stanu. Sam job kolejki nie jest historią biznesową.

## Integracje docelowe

### OpenAI lub inny dostawca AI

Zastosowania:

- research,
- generowanie i redakcja,
- analiza transkrypcji,
- klasyfikacja,
- rekomendacje.

Wymagania:

- minimalizacja danych,
- wersjonowanie promptów,
- limit kosztów,
- zapis źródeł i modelu,
- brak automatycznej publikacji,
- osobna decyzja dostawcy i polityki danych.

### Sendy / Amazon SES

Stan obecny:

- Sendy obsługuje listy i newsletter,
- SES/SNS obsługuje wysyłkę i część informacji zwrotnych,
- brakuje jednego rejestru relacji i zgód.

Growth OS może później przygotować i przekazać zatwierdzony mailing. Nie może traktować samego zapisu w Sendy jako kompletnego profilu klienta.

Przed wysyłką:

- finalny podgląd odbiorców i wykluczeń,
- liczba odbiorców,
- temat, nadawca i treść,
- testowa wysyłka,
- potwierdzenie nieodwracalności,
- idempotency, aby retry nie wysłał duplikatu.

### YouTube

Możliwe operacje:

- pobranie metadanych i statystyk,
- przygotowanie draftu tytułu/opisu,
- upload jako prywatny lub niepubliczny,
- publikacja po approval.

Nie przechowywać tokenów OAuth w bazie treści. Sekrety i refresh tokeny korzystają z istniejącego systemu konfiguracji środowiska lub bezpiecznego magazynu.

### Meta / Facebook

Rozpocząć od odczytu metryk i przygotowania draftów. Publikacja oraz reklamy wymagają osobnych zakresów, uprawnień, limitów i approval.

Uruchomienie reklamy, zmiana budżetu lub grupy odbiorców nigdy nie może być efektem zwykłego „zatwierdź treść”.

### Canva

Canva może być wykonawcą szablonu lub źródłem eksportu. Growth OS powinien przechowywać:

- odniesienie do projektu,
- wersję/eksport,
- osobę zatwierdzającą,
- plik lub URL finalnego assetu, jeśli licencja i API na to pozwalają.

### Google Drive

Drive może przechowywać źródłowe pliki i nagrania. PNE powinno zachować indeks, uprawnienia, właściciela, status przetworzenia i trwałe odniesienie.

Link udostępniony publicznie nie jest mechanizmem autoryzacji Growth OS.

### GA4 i Search Console

Traktować jako źródła analityczne, nie źródło prawdy sprzedaży.

- dane pobierać okresowo,
- zapisywać zakres i moment synchronizacji,
- rozróżniać świeże dane od niekompletnych,
- nie łączyć automatycznie identyfikatorów Google z osobami PNE.

### n8n / Make

Możliwe jako executor prostych integracji, jeśli:

- workflow nie zawiera kanonicznej logiki biznesowej,
- wejście i wynik są zapisane w PNE,
- operacja ma idempotency,
- sekretami zarządza bezpieczne środowisko,
- istnieje właściciel i wersja workflow,
- wyłączenie n8n nie blokuje starego systemu.

## Integracje istniejące

PayU, Paynow, iFirma, KSeF, ClickMeeting, GUS, Publigo i certgen nie są częścią pierwszych etapów Growth OS. Nowy moduł nie powinien zmieniać ich kodu ani statusów.

Google Calendar i obecna integracja Sendy mogą dostarczyć wzorców obsługi konfiguracji, ale nie należy rozszerzać ich „przy okazji” bez osobnego zakresu.

## Webhooki

Każdy przyszły webhook:

- weryfikuje podpis lub sekret,
- zapisuje idempotency key,
- szybko potwierdza odbiór,
- ciężką pracę przenosi do kolejki,
- nie loguje pełnych payloadów z PII,
- ma retencję i limit rozmiaru,
- rozróżnia duplikat, retry i zdarzenie nieznane.

## Błędy i retry

Kategorie:

- przejściowy błąd dostawcy — automatyczny retry,
- limit — retry po czasie wskazanym przez dostawcę,
- błąd danych — bez retry, wymaga poprawy,
- brak autoryzacji — stop i komunikat do administratora,
- niejednoznaczny wynik — weryfikacja statusu przed ponowieniem.

Retry nie może powtarzać nieidempotentnej publikacji lub wysyłki bez wcześniejszego sprawdzenia statusu.

## Sekrety

- wyłącznie env/config lub późniejszy dedykowany secret store,
- nigdy w repozytorium, promptach, logach i activity log,
- osobne dane dostępowe dla produkcji i testów,
- minimalne scope,
- możliwość rotacji bez migracji danych domenowych.

## Kolejność wdrażania integracji

1. adapter bez side effectu / tryb mock,
2. sandbox lub odczyt read-only,
3. pojedyncza operacja wykonawcza z ręcznym approval,
4. idempotency i retry,
5. monitoring i runbook,
6. dopiero potem szersza automatyzacja.
