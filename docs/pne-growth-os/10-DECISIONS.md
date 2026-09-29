# PNE Growth OS — rejestr decyzji

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1.1
Status: decyzje koncepcyjne, decyzje Etapu 0.1 i lista decyzji otwartych

Ten plik przechowuje decyzje Growth OS. Decyzje o dużym wpływie technicznym powinny dodatkowo otrzymać osobny ADR w `docs/decisions/`.

## D-001 — `adm.pnedu.pl` jest centrum Growth OS

**Status:** zaakceptowane w założeniach właściciela
**Data:** 2026-09-29

**Decyzja:**
Growth OS działa wewnątrz `adm.pnedu.pl`. Nie tworzymy trzeciego panelu.

**Dlaczego:**
Właściciel ma jedno miejsce decyzji, a `pneadm` już zarządza główną bazą biznesową, rolami, audytem, kolejkami i integracjami.

**Konsekwencja:**
Nowy moduł respektuje stack Laravel/Blade/Bootstrap i konwencje `pneadm`.

## D-002 — PNE pozostaje source of truth

**Status:** zaakceptowane w założeniach właściciela
**Data:** 2026-09-29

**Decyzja:**
Stan kampanii, treści, approval i operacji jest przechowywany w PNE. Systemy zewnętrzne są wykonawcami.

**Dlaczego:**
Logika rozproszona między Sendy, n8n, Canva, Meta i skryptami byłaby trudna do audytu, odtworzenia i wyłączenia.

**Konsekwencja:**
Każda integracja wymaga adaptera i lokalnego rekordu wyniku.

## D-003 — Rozwój addytywny

**Status:** zaakceptowane w założeniach właściciela
**Data:** 2026-09-29

**Decyzja:**
Growth OS rozszerza system nowymi modułami, usługami i tabelami. Nie refaktoryzuje istniejących procesów bez osobnej potrzeby i zgody.

**Dlaczego:**
System produkcyjny obsługuje realnych klientów i procesy finansowe.

**Konsekwencja:**
Każdy etap ma regresję, rollback i test flagi wyłączonej.

## D-004 — Human approval dla operacji wysokiego ryzyka

**Status:** zaakceptowane w założeniach właściciela
**Data:** 2026-09-29

**Decyzja:**
Mailing, publikacja, reklama, cena, oferta B2B i wypowiedź eksperta wymagają podglądu i zatwierdzenia człowieka.

**Dlaczego:**
AI ma pomagać, a nie odbierać kontrolę ani publikować nieautoryzowane treści.

**Konsekwencja:**
Draft, approval i wykonanie są osobnymi stanami.

## D-005 — Growth Campaign i MarketingCampaign to różne pojęcia

**Status:** rekomendacja v0.1, do potwierdzenia przed modelem danych
**Data:** 2026-09-29

**Decyzja:**
Nie rozszerzać automatycznie istniejącego `MarketingCampaign` do roli centralnej kampanii Growth.

**Dlaczego:**
Obecny model służy kodom linków, UTM, landing target i atrybucji do kursu. Kampania Growth ma temat, cel, eksperta, wiele treści i wiele kanałów.

**Konsekwencja:**
Nowy obiekt będzie jawnie nazwany technicznie, prawdopodobnie z prefiksem `growth_`, i może linkować wiele kampanii marketingowych.

## D-006 — Expert rozszerza Instructor

**Status:** rekomendacja v0.1, do potwierdzenia przed modelem danych
**Data:** 2026-09-29

**Decyzja:**
`Instructor` pozostaje kanoniczną osobą prowadzącą. Growth OS może dodać profil 1:1 zamiast drugiego niezależnego eksperta.

**Dlaczego:**
`Instructor` jest już połączony ze szkoleniami, ofertami, kursami online, ankietami i rozliczeniami.

**Konsekwencja:**
Nie duplikujemy danych kontaktowych, bio i historii szkoleń.

## D-007 — Feature flag i osobne uprawnienia

**Status:** feature flag wdrożony lokalnie; osobne permission odłożone
**Data:** 2026-09-29

**Decyzja:**
Growth OS jest domyślnie wyłączony i niewidoczny bez dostępu. Etap 0.1 używa istniejącej roli `super_admin`, ponieważ dodanie `growth_os.view` wymagałoby zmiany danych autoryzacyjnych.

**Dlaczego:**
Pozwala bezpiecznie wdrożyć szkielet i natychmiast odłączyć moduł bez migracji albo seedowania produkcyjnych permission.

**Konsekwencja:**
Administrator bez roli `super_admin` nie ma jeszcze dostępu. Granularne permission wymaga osobnego, świadomie zaakceptowanego etapu.

## D-008 — Bez PII w analityce i domyślnie bez PII w AI

**Status:** zgodne z istniejącymi ADR; rozszerzenie AI wymaga decyzji prawnej
**Data:** 2026-09-29

**Decyzja:**
Growth OS respektuje brak PII w `pne_analytics`. Zewnętrzne AI otrzymuje domyślnie publiczne treści i agregaty, nie dane klientów.

**Dlaczego:**
Minimalizacja ryzyka RODO i niekontrolowanego profilowania.

**Konsekwencja:**
Customer Graph i Relationship Engine nie wchodzą do pierwszych etapów.

## D-009 — Dokumentacja jest częścią zakończenia etapu

**Status:** zaakceptowane w założeniach właściciela
**Data:** 2026-09-29

**Decyzja:**
Etap Growth OS nie jest zakończony bez aktualizacji odpowiednich dokumentów, testów i raportu.

**Dlaczego:**
Dokumentacja ma pozostać zgodna z rzeczywistym systemem.

**Konsekwencja:**
Każda większa zmiana aktualizuje ten katalog, `SYSTEM_OVERVIEW`, `NEXT_STEPS` i — gdy potrzeba — runbook/ADR.

## D-010 — Growth OS można całkowicie wyłączyć

**Status:** zaakceptowane i wdrożone lokalnie w Etapie 0.1
**Data:** 2026-09-29

**Decyzja:**
Growth OS jest chroniony fail-closed feature flagiem `PNE_GROWTH_OS_ENABLED` i może zostać całkowicie wyłączony bez wpływu na istniejące funkcje `adm.pnedu.pl`.

**Dlaczego:**
Nowa warstwa musi być odwracalna i niezależna od procesów produkcyjnych.

**Konsekwencja:**
Przy braku flagi, wartości `false` lub wartości nieprawidłowej trasa `/growth` zwraca 404, a pozycja menu nie jest renderowana. Stare moduły nie odczytują konfiguracji Growth OS.

## Decyzje otwarte przed pierwszym modelem danych

1. Czy operacyjne tabele Growth mają być w `pneadm`, czy w osobnej bazie?
   - Rekomendacja v0.1: `pneadm` dla prostoty i transakcyjnego stanu modułu; duże artefakty poza DB.
2. Ostateczne techniczne nazwy kampanii i tabel.
3. Model wersjonowania draftów i approval.
4. Typ relacji ContentAsset do istniejących modeli.
5. Retencja treści roboczych, transkrypcji i wyników AI.
6. Zakres activity log i dedykowanego logu operacji.

## Decyzje otwarte przed AI

1. Dostawca, region i warunki użycia danych.
2. Lista dozwolonych modeli i zadań.
3. Limity kosztów.
4. Retencja promptów i odpowiedzi.
5. Polityka źródeł i cytowania.
6. Czy potrzebna jest DPIA dla danego przypadku.

## Decyzje otwarte przed Customer Graph / CRM

1. Podstawa prawna i cel łączenia danych.
2. Model tożsamości osoby i obsługa konfliktów.
3. Model zgód i preferencji komunikacji.
4. Retencja interakcji.
5. Reguły tworzenia i scalania szkół/organizacji.
6. Czy i jak backfillować historyczne zamówienia.

## Kolejne ADR-y

Po akceptacji wdrożenia należy rozważyć:

- ADR: lokalizacja danych operacyjnych Growth OS,
- ADR: rozdzielenie Growth Campaign od MarketingCampaign,
- ADR: model approval i idempotentnego execution,
- ADR: dostawca i polityka danych AI,
- ADR: identity resolution i Customer Graph.
