# RODO, Analityka I AI

Data utworzenia/aktualizacji: 2026-06-24  
Status: wersja robocza, do potwierdzenia prawnego

## Cel Dokumentu

Dokument opisuje zasady bezpieczeństwa danych, minimalizacji i RODO dla planowanej bazy `pne_analytics`, eksportów AI-safe i przyszłych funkcji AI.

To nie jest opinia prawna. Przed wdrożeniem AI lub zaawansowanego trackingu może być potrzebna osobna analiza ryzyka/DPIA.

## Zasada Nadrzędna

`pne_analytics` nie jest bazą operacyjną klientów ani faktur. Dane osobowe pozostają w bazach transakcyjnych `pneadm` i `pnedu`.

`pne_analytics` ma przechowywać:

- identyfikatory techniczne,
- pseudonimowe sesje,
- eventy bez danych osobowych,
- agregaty,
- snapshoty AI-safe.

## Dane Zakazane W `pne_analytics`

Nie wolno zapisywać:

- e-maili,
- telefonów,
- NIP,
- adresów,
- imion i nazwisk,
- nazw konkretnych osób,
- danych fakturowych,
- danych płatności,
- tokenów,
- kluczy API,
- danych dzieci lub uczniów,
- surowych payloadów bramek płatności,
- surowych logów zawierających dane osobowe,
- wartości pól formularza zamówienia.

## Dane Dozwolone

Można zapisywać:

- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `campaign_id`,
- `campaign_code`,
- `form_order_id`,
- `payment_order_id`,
- `buyer_type`,
- `payment_type`,
- `payment_gateway`,
- `has_recipient`,
- `gus_lookup_used`,
- `gus_lookup_success`,
- `participant_count`,
- `participant_count_bucket`,
- `ksef_option_selected`,
- `invoice_path_type`,
- `landing_target`,
- `campaign_content_depth`,
- `cta_type`,
- `field_key`,
- `section_key`,
- `error_rule`,
- `time_spent_seconds`,
- agregaty liczbowe.

## Pseudonimizacja

`analytics_session_id` powinien być losowym identyfikatorem, nie e-mailem ani hashem e-maila.

Nie rekomenduje się hashowania e-maila jako identyfikatora analitycznego w MVP, ponieważ nadal może to być dana osobowa lub umożliwiać łączenie danych.

Jeśli w przyszłości potrzebne będzie powiązanie z kontem użytkownika, należy rozważyć:

- osobny pseudonimowy identyfikator,
- ograniczony zakres użycia,
- jasną retencję,
- ocenę prawną.

## Formularz Zamówienia

JS tracking formularza nie może wysyłać wartości pól.

Dozwolone:

- `field_key`,
- `section_key`,
- `field_state`,
- `error_rule`,
- `buyer_type`,
- `payment_type`,
- `participant_count`,
- `has_recipient`.

Zakazane:

- wpisany e-mail,
- wpisany NIP,
- wpisany telefon,
- adres,
- nazwa szkoły,
- imię i nazwisko,
- treść uwag do faktury.

## GUS, NIP I KSeF

Dla GUS lookup zapisywać tylko:

- `gus_lookup_started`,
- `gus_lookup_success`,
- `gus_lookup_failed`,
- `lookup_target` (`buyer`/`recipient`),
- bucket czasu odpowiedzi,
- ogólną kategorię błędu.

Nie zapisywać:

- NIP,
- nazwy podmiotu,
- adresu zwróconego przez GUS.

Dla KSeF zapisywać tylko:

- wybrany typ ścieżki,
- czy użyto odbiorcy,
- neutralny kod opcji.

Nie zapisywać danych fakturowych.

## Wielu Uczestników

Można zapisywać:

- `participant_count`,
- `participant_count_bucket`,
- `participant_added`,
- `participant_removed`,
- `price_recalculated`,
- kwotę zagregowaną lub snapshot ceny.

Nie zapisywać:

- danych uczestników,
- e-maili uczestników,
- imion i nazwisk.

## Eksporty AI-Safe

Eksport AI-safe może zawierać:

- nazwy kursów,
- ID kursów,
- kody kampanii,
- typy kampanii,
- źródła UTM,
- liczby wejść,
- konwersje,
- zagregowane przychody,
- informacje o błędach formularza po `field_key`.

Eksport AI-safe nie może zawierać:

- danych osobowych,
- danych fakturowych,
- `form_order_id` (może być w `pne_analytics`, ale nie w raportach dla zewnętrznego AI),
- `payment_order_id`,
- pełnych URL z parametrami mogącymi zawierać dane,
- surowych logów,
- payloadów z integracji.

## Przyszłe API AI

Zasady:

- AI otrzymuje agregaty, nie dane surowe.
- Każde zapytanie AI powinno być logowane.
- Każda odpowiedź AI powinna być logowana.
- Koszt i model powinny być logowane.
- Źródła danych powinny być zapisywane.
- Dostęp do funkcji AI powinien być kontrolowany rolami.

## Retencja

Rekomendacja robocza:

- raw eventy JS: 3-6 miesięcy,
- sesje formularza: 12 miesięcy,
- conversion events: 24 miesiące,
- agregaty dzienne: 3+ lata,
- eksporty AI-safe: 12-24 miesiące,
- logi AI: do osobnej decyzji i DPIA.

## Ryzyka RODO

| Ryzyko | Ograniczenie |
---|---|
| zapis PII w metadata JSON | whitelist pól, sanitizer payloadów |
| eksport danych osobowych do AI | dedykowany AI-safe export service |
| identyfikacja osoby po sesji | rotacja/retencja sesji, brak e-mail hash |
| zbyt długa retencja | automatyczne czyszczenie raw eventów |
| niejasna zgoda cookies | decyzja właściciela + aktualizacja polityki cookies |
| AI z dostępem do danych operacyjnych | role, pseudonimizacja, agregaty |

## Rekomendacja DPIA

Przed wdrożeniem:

- publicznego asystenta AI,
- automatycznego odzyskiwania porzuconych formularzy,
- profilowania leadów,
- łączenia analityki z kontami użytkowników,

należy rozważyć analizę ryzyka i/lub DPIA.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalną politykę retencji.
- Dopisać decyzję o cookie consent.
- Dopisać wynik konsultacji prawnej.
- Dopisać listę pól dozwolonych w sanitizerze.
- Dopisać procedurę eksportów AI-safe.
