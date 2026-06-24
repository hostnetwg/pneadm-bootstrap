# Roadmapa AI

Data utworzenia/aktualizacji: 2026-06-24  
Status: plan, bez implementacji AI

## Cel Dokumentu

Dokument opisuje przyszłe wdrożenie AI w ekosystemie PNEdu. Na tym etapie nie wdrażamy AI. Najpierw budujemy dane, analitykę, agregaty i eksporty AI-safe.

## Dlaczego Nie Wdrażamy AI Od Razu

AI bez wiarygodnych danych będzie generować ogólne rekomendacje. Najpierw trzeba zebrać:

- pełną ścieżkę kampanii,
- wejścia w opis i formularz,
- start formularza,
- błędy walidacji,
- porzucenia,
- konwersje,
- płatności,
- faktury,
- skuteczność typów kampanii,
- skuteczność CTA,
- dane porównawcze pod A/B.

Dopiero po tym AI-doradca będzie mógł odpowiadać na pytania biznesowe w oparciu o dane, a nie intuicję.

## Dane Potrzebne Przed AI

Minimalny zakres:

- `analytics_daily_course_stats`,
- `analytics_daily_campaign_stats`,
- `conversion_events`,
- `order_form_sessions`,
- `order_form_abandonments`,
- wyniki A/B,
- koszty kampanii (do dodania w przyszłości),
- przychód zagregowany,
- metadane kampanii,
- metadane formularza.

## Przyszły AI-Doradca W `adm.pnedu.pl`

Docelowe pytania:

- Co promować w tym tygodniu?
- Które szkolenia mają największy potencjał?
- Które kampanie mają dużo kliknięć, ale mało zamówień?
- Gdzie tracimy sprzedaż?
- Czy prowadzić do opisu, czy bezpośrednio do formularza?
- Które CTA działa najlepiej?
- Czy GUS autofill poprawia konwersję?
- Czy wielu uczestników w jednym zamówieniu zwiększa wartość zamówienia?
- Gdzie użytkownicy mają problem z nabywcą, odbiorcą i KSeF?
- Które tematy szkoleń rozwijać?

## Pierwszy Raport AI

Rekomendowany pierwszy raport:

```text
Co promować w tym tygodniu?
```

Zakres:

- top kampanie,
- kampanie z ruchem bez sprzedaży,
- szkolenia z dużym zainteresowaniem,
- szkolenia z odpadem w formularzu,
- rekomendowane działania marketingowe,
- rekomendowane CTA,
- rekomendowane tematy do ponownej promocji.

## AI-Safe Snapshots

`analytics_snapshots` powinny zawierać:

- zakres dat,
- agregaty per kurs,
- agregaty per kampania,
- metryki konwersji,
- metryki formularza,
- porównanie `course_description` vs `order_form_direct`,
- wnioski bez danych osobowych,
- listę źródeł danych.

Nie powinny zawierać:

- e-maili,
- NIP,
- telefonów,
- adresów,
- nazwisk,
- danych faktur,
- surowych payloadów.

## AI-Safe Exports

Formaty:

- CSV,
- XLSX,
- PDF,
- Markdown.

Zastosowania:

- analiza w zewnętrznych narzędziach AI,
- przygotowanie rekomendacji kampanii,
- raporty miesięczne,
- analiza testów A/B,
- analiza formularzy i porzuceń.

## Publiczny Asystent Na `pnedu.pl`

Funkcja przyszła, nie MVP.

Możliwe zastosowania:

- dobór szkolenia,
- dobór pakietu dla szkoły,
- plan szkoleń rady pedagogicznej,
- wyjaśnienie różnic między szkoleniami,
- skierowanie do formularza zamówienia,
- lead capture.

Warunki przed wdrożeniem:

- RAG z publiczną ofertą,
- ograniczenie odpowiedzi do źródeł PNEdu,
- logowanie rozmów z minimalizacją danych,
- jasny komunikat, że AI nie udziela porad prawnych,
- zgody i polityka prywatności.

## Publiczna I Wewnętrzna Baza Wiedzy

### Publiczna Baza Wiedzy

Może zawierać:

- opisy szkoleń,
- programy,
- FAQ,
- regulaminy,
- polityki,
- bio trenerów,
- informacje o akredytacji,
- artykuły publiczne.

### Wewnętrzna Baza Wiedzy

Może zawierać:

- agregaty sprzedaży,
- wyniki kampanii,
- wyniki formularzy,
- wyniki ankiet,
- procedury obsługi,
- notatki strategiczne po pseudonimizacji.

## Zasady Niewysyłania Danych Osobowych Do API AI

Do API AI nie wysyłać:

- e-maili,
- telefonów,
- NIP,
- adresów,
- imion i nazwisk uczestników,
- danych fakturowych,
- danych płatności,
- tokenów,
- surowych logów.

Wysyłać:

- agregaty,
- metryki,
- ID techniczne,
- nazwy kursów,
- typy kampanii,
- konwersje,
- wnioski z dashboardu.

## Przyszłe Tabele AI

- `analytics_snapshots`,
- `ai_reports`,
- `ai_interactions`,
- `ai_usage_logs`,
- `ai_report_exports`,
- `ai_sources`.

## Do Aktualizacji Po Wdrożeniu

- Dopisać pierwsze snapshoty.
- Dopisać strukturę promptów.
- Dopisać politykę retencji logów AI.
- Dopisać decyzję o dostawcy modeli.
- Dopisać decyzję o RAG/vector store.
- Dopisać wynik analizy ryzyka/DPIA.
