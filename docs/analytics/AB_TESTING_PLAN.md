# Plan Testów A/B

Data utworzenia/aktualizacji: 2026-06-24  
Status: plan, bez implementacji

## Cel

Testy A/B mają pomóc podejmować decyzje sprzedażowe na podstawie danych, a nie intuicji. Pierwszym celem jest porównanie skuteczności kampanii prowadzących do opisu szkolenia i bezpośrednio do formularza zamówienia.

## Co Będzie Testowane

### Ścieżka Kampanii

- link do opisu szkolenia,
- link bezpośrednio do formularza.

Wartości:

- `course_description`,
- `order_form_direct`.

### Typy Kampanii

- `full_offer`,
- `short_offer`,
- `video_pitch`,
- `facebook_post`,
- `meta_ad`,
- `reminder`,
- `last_call`,
- `lead_magnet`,
- `retargeting`.

### CTA

- `zobacz_opis`,
- `zapisz_sie`,
- `zamow_szkolenie`,
- `zamow_z_faktura`,
- `zarezerwuj_miejsce`.

### Formularz

- obecny formularz jednoetapowy,
- przyszły formularz wieloetapowy,
- formularz z mocniejszymi elementami zaufania,
- formularz z krótszą sekcją fakturową,
- formularz z GUS autofill,
- formularz z zapisanym profilem fakturowym.

### Opis Szkolenia

- różne nagłówki,
- różne leady sprzedażowe,
- różna kolejność sekcji,
- ekspozycja trenera,
- ekspozycja akredytacji,
- ekspozycja faktury dla instytucji,
- ekspozycja zaświadczenia i nagrania.

## Minimalna Architektura Tabel

### `ab_tests`

Cel: definicja testu.

Przykładowe kolumny:

- `id`,
- `code`,
- `name`,
- `description`,
- `status`,
- `scope`,
- `starts_at`,
- `ends_at`,
- `primary_metric`,
- `created_by`,
- `created_at`,
- `updated_at`.

### `ab_test_variants`

Cel: warianty testu.

Przykładowe kolumny:

- `id`,
- `ab_test_id`,
- `code`,
- `name`,
- `description`,
- `weight`,
- `config_json`,
- `created_at`,
- `updated_at`.

### `ab_test_assignments`

Cel: trwałe przypisanie użytkownika/sesji do wariantu.

Przykładowe kolumny:

- `id`,
- `ab_test_id`,
- `ab_variant_id`,
- `analytics_session_id`,
- `assigned_at`,
- `campaign_code`,
- `course_id`,
- `created_at`.

### `ab_test_events`

Cel: eventy w kontekście testu.

Przykładowe kolumny:

- `id`,
- `ab_test_id`,
- `ab_variant_id`,
- `analytics_session_id`,
- `event_name`,
- `course_id`,
- `campaign_code`,
- `form_order_id`,
- `occurred_at`.

### `ab_test_results`

Cel: zagregowane wyniki testu.

Przykładowe kolumny:

- `id`,
- `ab_test_id`,
- `ab_variant_id`,
- `period_start`,
- `period_end`,
- `views`,
- `form_views`,
- `form_starts`,
- `orders`,
- `paid_orders`,
- `invoiced_orders`,
- `revenue_snapshot`,
- `conversion_rate`,
- `created_at`.

## Przypisywanie Użytkownika Do Wariantu

Rekomendacja:

```text
hash(analytics_session_id + ab_test_id) → wariant
```

Zasady:

- ten sam `analytics_session_id` zawsze dostaje ten sam wariant,
- przypisanie zapisać w `ab_test_assignments`,
- dodatkowo można trzymać cookie z przypisaniami,
- nie przypisywać po e-mailu ani danych osobowych.

## Powiązanie Z Kampanią I Zamówieniem

Każdy event i konwersja powinny móc zawierać:

- `ab_test_id`,
- `ab_variant_id`,
- `campaign_code`,
- `course_id`,
- `order_form_session_id`,
- `form_order_id`.

Dzięki temu można policzyć:

- wariant → wejście w formularz,
- wariant → start formularza,
- wariant → zamówienie,
- wariant → płatność,
- wariant → faktura.

## Metryki Sukcesu

Najważniejsze metryki:

- kliknięcie → opis,
- opis → formularz,
- formularz → rozpoczęcie,
- rozpoczęcie → submit,
- submit → zamówienie,
- zamówienie → płatność,
- zamówienie → faktura,
- przychód per sesja,
- przychód per kampania,
- liczba błędów walidacji,
- porzucenia formularza.

## Jak Unikać Zafałszowania Wyników

- Nie zmieniać wariantu w trakcie testu.
- Nie mieszać kampanii o bardzo różnym intent bez segmentacji.
- Oznaczać boty i crawlery.
- Wykluczać ruch zespołu przez opt-out.
- Nie kończyć testu po kilku pierwszych konwersjach.
- Segmentować wyniki po typie kampanii i źródle.
- Nie porównywać masowych bezpłatnych wydarzeń z płatnymi szkoleniami.

## AI W Przyszłości

AI może analizować:

- które warianty mają największą konwersję,
- które warianty generują porzucenia,
- które CTA działa w danym typie kampanii,
- czy `order_form_direct` działa lepiej niż `course_description`,
- jakie hipotezy testować dalej.

AI nie powinno otrzymywać danych osobowych. Wystarczą agregaty testów.

## Ryzyka

| Ryzyko | Ograniczenie |
---|---|
| zbyt mało danych | raportować próg minimalnej liczby sesji |
| mieszanie źródeł ruchu | segmentacja po `campaign_content_depth`, `utm_source`, `landing_target` |
| błędne przypisanie wariantu | deterministyczne przypisanie po `analytics_session_id` |
| konflikt z cache | wariant musi być uwzględniony w renderowaniu lub JS po stronie klienta |
| dane osobowe | nie używać e-maila do przypisania |

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne tabele A/B.
- Dopisać pierwsze uruchomione testy.
- Dopisać wyniki testów i decyzje biznesowe.
- Dopisać zasady minimalnej próby statystycznej.
