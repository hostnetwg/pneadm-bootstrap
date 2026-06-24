# Następne Kroki

Data utworzenia/aktualizacji: 2026-06-24  
Status: plan roboczy, do potwierdzenia przez właściciela

## Cel Dokumentu

Dokument określa najbliższe kroki po utworzeniu dokumentacji. Ma chronić projekt przed chaotycznym wdrożeniem analityki i przypominać, że obecny etap dotyczy dokumentacji, nie kodu.

## Lokalny dev na nowym komputerze (checklist)

Po sklonowaniu ostatniego commita:

- [x] Sieć `pne-network` i kontener `pneadm-mysql` (wspólny MySQL dla obu projektów),
- [x] Baza `pne_analytics` w `pneadm-mysql`,
- [x] Zmienne `DB_ANALYTICS_*` / `ANALYTICS_*` w `.env` obu projektów,
- [x] Migracje analityczne uruchomione tylko z `pneadm` (`sail artisan migrate`),
- [x] Testy `sail artisan test --filter=Analytics` w obu projektach,
- [ ] Ręczna weryfikacja panelu `/analytics/debug-events` po zalogowaniu jako admin,
- [ ] Worker kolejki `analytics` (opcjonalnie lokalnie): `sail artisan queue:work redis --queue=analytics`.

Szczegóły: `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md` → sekcja „Lokalna konfiguracja `pne_analytics` na nowym komputerze developerskim”.

## Najbliższe 10 Kroków

1. Otworzyć `adm.pnedu.pl -> Analityka -> Lejek sprzedaży` i zweryfikować dane po `analytics:aggregate-daily`.
2. Porównać nowe agregaty ze starymi `course_page_stats_daily` / `marketing_campaign_stats_daily` (tylko odczyt).
3. Zaplanować cron produkcyjny dla `analytics:aggregate-daily` (np. 02:15 Europe/Warsaw).
4. Wpisać produkcyjne hasło `pne_analytics` wyłącznie w `.env` produkcji obu aplikacji.
5. Po konfiguracji produkcji zrotować ujawnione w rozmowie hasło do bazy analitycznej.
6. Zweryfikować connection `analytics`, worker kolejki `analytics`, dashboard i panel debug na produkcji.
7. Przetestować filtry dashboardu (daty, kampania, kurs, landing target) na realnych danych.
8. Etap 2A-1 wdrożony (`online_payment_selected`, `deferred_invoice_selected`). Następny krok płatności: `payment_order_created`, potem `payment_status_changed`.
9. Rozważyć progi alertów dashboardu po pierwszych tygodniach obserwacji.
10. Skonsultować z ChatGPT kolejny etap (płatności, JS, porzucenia) po akceptacji właściciela.

## Następny Etap

Etap 0 został wdrożony lokalnie. Kolejnym krokiem jest konfiguracja produkcyjna i przygotowanie Etapu 1.

Etap 0:

- status: wdrożony lokalnie,
- connection `analytics` w obu projektach: wykonane,
- `.env.example` w obu projektach: wykonane,
- lokalna baza `pne_analytics` w MySQL `pneadm-mysql`: wykonane,
- migracje MVP w projekcie `pneadm`: wykonane lokalnie,
- modele analityczne: wykonane,
- enumy/stałe: wykonane,
- `AnalyticsPayloadSanitizer`: wykonane,
- `AnalyticsModeResolver`: wykonane,
- `AnalyticsService`: wykonane,
- `StoreAnalyticsEventJob`: wykonane,
- konfiguracja Redis queue `analytics`: wykonane,
- testy połączenia, sanitizera, mode resolvera i fail-silent: wykonane.

Etap 1:

- backend tracking krótkich linków: wdrożone w 1A,
- UTM: wdrożone w 1A,
- opis szkolenia: wdrożone w 1A,
- wejście w formularz: wdrożone w 1A,
- techniczny panel debug eventów w `adm.pnedu.pl`: wdrożone w 1A-Debug,
- submit: wdrożone w 1B-1,
- walidacja: wdrożone w 1B-1,
- tworzenie zamówienia: wdrożone w 1B-2 (`form_order_created`, deferred i online),
- agregaty dzienne: wdrożone w 1C (`analytics:aggregate-daily` w `adm.pnedu.pl`),
- dashboard lejka sprzedaży: wdrożony w 1D (`/analytics/sales-funnel`),
- wybór płatności: wdrożono w 2A-1 (`online_payment_selected`, `deferred_invoice_selected`),
- status płatności: nie wdrożono (`payment_order_created`, `payment_status_changed`),
- agregaty/dashboard płatności: nie wdrożono,
- faktura: nie wdrożono.

## Decyzje Właściciela Do Podjęcia

- Czy produkcyjna baza `srv66127_pne_analytics` jest docelową nazwą na stałe?
- Czy po ujawnieniu hasła w rozmowie zostanie ono zrotowane przed końcowym uruchomieniem produkcyjnym?
- Czy lokalna baza ma nazywać się `pne_analytics`, a nie `pne_analytics_dev`?
- Jaka ma być retencja raw eventów? Rekomendacja: 180 dni.
- Jaka ma być retencja sesji formularza? Rekomendacja: 365 dni.
- Jaka ma być retencja agregatów? Rekomendacja: minimum 3 lata albo bezterminowo.
- Jaka ma być retencja eksportów AI-safe? Rekomendacja: 180-365 dni.
- Czy raw eventy mają być backupowane tak samo długo jak agregaty?
- Czy tracking własny wymaga zmian w polityce cookies?
- Czy domyślny tryb dla płatnych szkoleń to `standard`? Rekomendacja: tak.
- Czy strategiczne kampanie sprzedażowe mają mieć `full`? Rekomendacja: tak.
- Czy bezpłatne webinary dyrektorskie mają mieć `standard` czy `full`?
- Czy masowe webinary TIK mają mieć zawsze `aggregate_only`? Rekomendacja: tak.
- Czy eksporty AI-safe mają być dostępne tylko dla właściciela/admina? Rekomendacja: tak.
- Czy AI-doradca ma być dostępny tylko w `adm.pnedu.pl` dla administratorów?
- Czy łączenie zapisanych profili fakturowych z e-mailem wymaga dodatkowej zgody i analizy prawnej? Rekomendacja: tak.

## Czego Nie Robimy Na Razie

Na tym etapie nie robimy:

- endpointów,
- zmian w `.env`,
- zmian w formularzu zamówienia,
- zmian w płatnościach,
- zmian w fakturach,
- zmian w KSeF,
- zmian w iFirma,
- wdrożenia AI,
- publicznego asystenta,
- automatycznego odzyskiwania porzuconych formularzy,
- testów A/B w produkcji.

Uwaga: migracja MVP, modele analityczne i klasy bazowe zostały utworzone w Etapie 0. Powyższe ograniczenie dotyczy teraz Etapu 1 i oznacza: nie tworzyć nowych migracji ani modeli poza uzasadnioną korektą fundamentu bez osobnej decyzji.

## Rekomendowany Prompt Do ChatGPT Do Konsultacji

Ten prompt służy do konsultacji kolejnego etapu z ChatGPT na `https://chatgpt.com/`:

```text
Etap 0–1D analityki PNEdu zostały wdrożone lokalnie w Cursorze.

Wdrożono m.in.:
- backend eventy w pnedu.pl (1A, 1B),
- panel debug eventów,
- agregaty dzienne analytics:aggregate-daily (1C),
- dashboard Lejek sprzedaży /analytics/sales-funnel (1D).

Nie wdrożono jeszcze:
- eventów płatności i faktur,
- JS trackingu, porzuceń, A/B,
- AI, eksportów AI-safe.

Przygotuj plan Etapu 2: eventy płatności (payment_order_created, payment_status_changed)
albo rozbudowy agregacji landing_target per kampania.
```

## Zadanie Implementacyjne Dla Cursora

Kolejne zadanie po akceptacji właściciela:

```text
Na podstawie dokumentacji w docs/ wdroż eventy płatności analityki PNEdu (Etap 2):
payment_order_created, payment_status_changed. Nie wdrażaj faktur, JS, A/B, AI.
Zachowaj fail-silent i RODO. Wszystkie komendy Laravel/PHP uruchamiaj przez sail.
```

## Status Implementacji Etapu 0/1

Etap 0 jest wdrożony lokalnie i opisany w:

- `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md`,
- `docs/analytics/DATABASE_SCHEMA_PLAN.md`,
- `docs/analytics/ANALYTICS_ROADMAP.md`.

Najważniejsze ustalenia:

- connection w kodzie ma nazywać się `analytics`,
- baza dev ma być dostępna dla obu projektów,
- produkcyjne dane dostępowe nie trafiają do repozytorium,
- migracje MVP powstają w projekcie `pneadm`,
- brak FK do `pneadm`,
- pierwsza implementacja nie dotyka procesów sprzedaży,
- backend eventy są dopiero Etapem 1.

## Decyzje Nadal Wymagające Potwierdzenia

- retencja raw eventów,
- retencja sesji formularza,
- retencja agregatów,
- retencja eksportów AI-safe,
- domyślne tryby analityki,
- backup raw eventów,
- aktualizacja polityki cookies,
- zakres dostępu do eksportów AI-safe,
- analiza prawna profili fakturowych powiązanych z e-mailem.
- czy Etap 1A wdrażać jako pierwszy osobny commit,
- nazwa cookie dla `analytics_session_id`, rekomendacja `pne_analytics_sid`,
- czas życia `analytics_session_id`, rekomendacja 7-30 dni,
- sposób utrzymania `order_form_session_id`, rekomendacja 24h cookie/session per kurs,
- czy przy `ANALYTICS_ENABLED=false` tworzyć cookie sesji analitycznej; rekomendacja: nie.

## Zasady Aktualizacji Dokumentacji

Po każdym większym etapie należy zaktualizować:

- `docs/analytics/ANALYTICS_ROADMAP.md`,
- `docs/analytics/DATABASE_SCHEMA_PLAN.md`,
- `docs/analytics/TRACKING_IMPLEMENTATION_PLAN.md`,
- odpowiednie ADR-y,
- `docs/NEXT_STEPS.md`.

Po każdej decyzji właściciela należy:

- zmienić status w ADR,
- dopisać datę decyzji,
- opisać konsekwencje.

Po każdej implementacji należy:

- dopisać realne klasy i pliki,
- dopisać realne trasy,
- dopisać realne komendy,
- dopisać testy,
- oznaczyć rozbieżności między planem i kodem.

## Do Aktualizacji Po Wdrożeniu

- Odhaczyć wykonane kroki.
- Dopisać decyzje właściciela.
- Dopisać linki do wdrożonych migracji i klas.
- Dopisać rekomendację kolejnego etapu po Etapie 1.
