# Kontekst Projektu PNEdu

Data utworzenia/aktualizacji: 2026-06-24  
Status: wersja robocza, do potwierdzenia przez właściciela

## Cel Dokumentu

Ten dokument opisuje kontekst biznesowy i techniczny ekosystemu PNEdu. Ma być punktem startowym dla kolejnych rozmów z AI, prac w Cursorze oraz planowania wdrożeń analityki eventowej, testów A/B i przyszłych funkcji AI.

Dokumentacja w `docs/` ma być traktowana jako pamięć projektu i źródło prawdy. Po każdym większym etapie wdrożenia należy ją aktualizować.

## Kontekst Biznesowy

Platforma Nowoczesnej Edukacji / NODN Platforma Nowoczesnej Edukacji prowadzi szkolenia dla edukacji:

- dyrektorów szkół,
- wicedyrektorów,
- nauczycieli,
- rad pedagogicznych,
- przedszkoli,
- szkół podstawowych i ponadpodstawowych,
- placówek oświatowych.

Firma posiada akredytację Mazowieckiego Kuratora Oświaty i działa jako Niepubliczny Ośrodek Doskonalenia Nauczycieli.

Najważniejsze obszary oferty:

- szkolenia dla dyrektorów,
- szkolenia dla nauczycieli,
- szkolenia dla rad pedagogicznych,
- SIO,
- KSeF,
- CRU,
- prawo oświatowe,
- dokumentacja szkolna,
- kadry i płace,
- zarządzanie szkołą,
- JST,
- CUW,
- administracja publiczna,
- AI w edukacji,
- cyberbezpieczeństwo,
- compliance.

## Cele Rozwoju

Głównym celem biznesowym jest rozwój PNEdu jako ogólnopolskiej marki szkoleniowej.

Priorytet krótkoterminowy:

- lepsza analityka kampanii,
- lepsze zrozumienie formularza zamówienia,
- wykrywanie miejsc utraty sprzedaży,
- przygotowanie dashboardu właściciela,
- uporządkowanie danych pod przyszłego AI-doradcę.

Priorytet średnioterminowy:

- testy A/B opisów szkoleń, CTA i formularzy,
- porównywanie kampanii prowadzących do opisu szkolenia i bezpośrednio do formularza,
- eksporty AI-safe do analizy poza systemem.

Priorytet długoterminowy:

- AI-doradca w `adm.pnedu.pl`,
- publiczny asystent wyboru szkolenia na `pnedu.pl`,
- RAG i bezpieczna baza wiedzy,
- ekspansja do JST, CUW, administracji publicznej, księgowości budżetowej, kadr, płac, zamówień publicznych i compliance.

## Systemy

### `pnedu.pl`

Publiczny portal sprzedażowy i uczestnikowski.

Odpowiada za:

- prezentację oferty szkoleń,
- opisy szkoleń,
- formularz zamówienia,
- płatności online PayU/Paynow,
- panel uczestnika,
- certyfikaty dostępne dla uczestników,
- zapisy newsletterowe,
- publiczne strony SEO.

### `adm.pnedu.pl`

Wewnętrzny panel administracyjny/backoffice.

Odpowiada za:

- zarządzanie szkoleniami,
- zarządzanie uczestnikami,
- zarządzanie zamówieniami,
- kampanie marketingowe,
- krótkie linki i UTM,
- certyfikaty,
- płatności,
- faktury i iFirma,
- KSeF,
- trenerów,
- ankiety,
- raporty,
- przyszły dashboard właściciela.

## Bazy Danych

### `pneadm`

Główna baza biznesowa.

Zawiera m.in.:

- `courses`,
- `course_price_variants`,
- `form_orders`,
- `form_order_participants`,
- `participants`,
- `online_payment_orders`,
- `marketing_campaigns`,
- `marketing_source_types`,
- `marketing_campaign_stats_daily`,
- `course_page_stats_daily`,
- `certificates`,
- `surveys`,
- `activity_logs`.

### `pnedu`

Baza portalu publicznego i użytkowników.

Zawiera głównie:

- konta użytkowników portalu,
- sesje,
- dane logowań,
- kolejki i dane techniczne właściwe dla `pnedu.pl`.

### `certgen`

Baza historyczna / legacy używana przez część importów i archiwów.

Status: istnieje w konfiguracji i integracjach, zakres dalszego użycia wymaga potwierdzenia przy kolejnych pracach.

### `pne_analytics`

Planowana osobna baza analityczna.

Ma służyć do:

- eventów analitycznych,
- sesji analitycznych,
- trackingów formularza,
- porzuceń,
- agregatów dziennych,
- testów A/B,
- eksportów AI-safe,
- przyszłego AI-doradcy.

`pne_analytics` nie może przechowywać niepotrzebnych danych osobowych.

## Dane Zakazane W `pne_analytics`

Nie zapisywać:

- e-maili,
- telefonów,
- NIP,
- adresów,
- imion i nazwisk,
- danych fakturowych,
- danych płatności,
- tokenów,
- danych dzieci lub uczniów,
- surowych logów z danymi osobowymi.

## Dane Dozwolone W `pne_analytics`

Można zapisywać:

- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `campaign_id`,
- `campaign_code`,
- `form_order_id`,
- `payment_order_id`,
- snapshoty bez danych osobowych,
- `buyer_type`,
- `payment_type`,
- `has_recipient`,
- `gus_lookup_used`,
- `gus_lookup_success`,
- `participant_count`,
- `ksef_option_selected`,
- `invoice_path_type`.

## Założenia Techniczne

- Laravel 11.
- PHP 8.2+.
- MySQL/MariaDB.
- Redis dostępny.
- Osobna kolejka Redis `analytics`.
- Zapis eventów asynchroniczny.
- Analityka nie może blokować zamówienia.
- W razie awarii Redis lub `pne_analytics` sprzedaż działa normalnie.

## Do Aktualizacji Po Wdrożeniu

- Potwierdzić rzeczywiste nazwy połączeń do baz w obu aplikacjach.
- Dopisać finalne decyzje właściciela dotyczące retencji danych.
- Dopisać finalne zasady cookie consent dla analityki własnej.
- Dopisać linki do migracji i klas po ich utworzeniu.
