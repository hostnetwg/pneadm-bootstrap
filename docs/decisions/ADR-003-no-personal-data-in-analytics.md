# ADR-003: Brak Danych Osobowych W `pne_analytics`

Data utworzenia/aktualizacji: 2026-06-24  
Status: zaakceptowane koncepcyjnie, do potwierdzenia prawnego

## Kontekst

Analityka eventowa ma mierzyć kampanie, formularz, konwersje i przyszłe testy A/B. Nie potrzebuje danych osobowych klientów ani uczestników.

System operacyjny nadal będzie przechowywał dane osobowe w bazach transakcyjnych zgodnie z potrzebami biznesowymi:

- `pneadm`,
- `pnedu`.

`pne_analytics` ma być bazą analityczną.

## Decyzja

`pne_analytics` nie przechowuje danych osobowych.

W bazie analitycznej zapisujemy tylko:

- identyfikatory techniczne,
- pseudonimowe sesje,
- identyfikatory biznesowe,
- bezpieczne metadane,
- agregaty,
- snapshoty bez danych osobowych.

## Dane Zakazane

Nie zapisywać:

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
- surowych logów z danymi osobowymi,
- wartości pól formularza,
- surowych payloadów bramek płatności,
- surowych payloadów GUS.

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
- `recipient_section_used`,
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
- `amount_snapshot`.

## Konsekwencje Dla Raportowania

Raporty nie będą odpowiadały na pytania typu:

- który konkretny klient porzucił formularz,
- jaki NIP miał problem,
- jaki adres został wpisany,
- który konkretny uczestnik miał błąd.

Raporty będą odpowiadały na pytania typu:

- które pole najczęściej powoduje błąd,
- która kampania konwertuje,
- gdzie odpadają użytkownicy,
- czy GUS lookup pomaga,
- czy KSeF komplikuje formularz,
- czy wielu uczestników zwiększa wartość zamówienia.

## Konsekwencje Dla AI

AI może otrzymywać:

- agregaty,
- statystyki,
- snapshoty,
- wyniki testów,
- nazwy kursów,
- kody kampanii.

AI nie może otrzymywać:

- danych klientów,
- danych faktur,
- danych uczestników,
- danych płatności,
- surowych logów.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalną whitelistę metadanych.
- Dopisać testy sanitizacji.
- Dopisać wynik konsultacji prawnej.
- Dopisać decyzję o retencji identyfikatorów sesji.
