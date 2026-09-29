# PNE Growth OS — roadmapa

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1.1
Status: Etapy 0 i 0.1 wykonane lokalnie; kolejne etapy wymagają osobnej decyzji

## Zasada realizacji

Każdy etap przechodzi:

```text
analiza
→ plan
→ ocena ryzyka
→ backup / migration plan
→ implementacja
→ test
→ regresja
→ dokumentacja
→ raport
→ decyzja o kolejnym etapie
```

Akceptacja roadmapy nie oznacza automatycznej akceptacji wszystkich etapów.

## Etap 0 — dokumentacja v0.1 — zakończony

Zakres:

- analiza dwóch aplikacji i baz,
- mapa istniejących modeli,
- granice Growth OS,
- koncepcja domeny, UX, AI, integracji i bezpieczeństwa,
- rejestr decyzji,
- roadmapa.

Bez kodu, tabel, konfiguracji i integracji.

Kryterium zakończenia:

- właściciel otrzymuje raport A–J,
- dokumentacja odróżnia fakty od rekomendacji,
- implementacja czeka na osobną zgodę.

## Etap 0.1 — najmniejszy bezpieczny szkielet — wdrożony lokalnie

Wdrożono:

- konfiguracja `PNE_GROWTH_OS_ENABLED=false`,
- tymczasowy dostęp tylko dla istniejącej roli `super_admin`, bez zmian danych autoryzacyjnych,
- warunkowa sekcja menu,
- read-only ekran „PNE Growth OS” z informacją o statusie,
- brak tabel domenowych,
- brak AI, jobów, cronów i integracji,
- automatyczny test flagi i autoryzacji.

Cel:

- potwierdzić sposób bezpiecznego osadzenia modułu,
- przetestować kill switch i role,
- nie dotykać danych biznesowych.

Rollback:

- wyłączenie flagi,
- usunięcie trasy/menu w deployu zwrotnym,
- brak migracji i danych do cofania.

Przed produkcją:

- ustawić `PNE_GROWTH_OS_ENABLED=false`,
- odświeżyć cache konfiguracji,
- wykonać smoke logowania i menu,
- opcjonalne włączenie tylko po świadomej decyzji właściciela.

## Etap 0.2 — prototyp UX bez side effectu

Opcjonalny przed modelem danych:

- klikalny prototyp Pulpitu, Inboxu i Kampanii,
- dane fikcyjne lub fixture wyłącznie lokalne,
- test z Waldemarem: język, statusy, decyzje, gęstość informacji.

Brak zapisu i integracji.

## v0.1 — Campaign + Producer + AI w trybie draft

Zakres:

- Topic,
- Growth Campaign,
- cel kampanii,
- przypisanie istniejącego `Instructor`,
- ręczny plan formatów,
- draft briefu i wybranych treści,
- approval bez publikacji,
- pierwszy adapter AI tylko do draftów.

Warunki wejścia:

- zaakceptowany model danych,
- dostawca AI i polityka danych,
- limity kosztów i retencja,
- test kill switcha,
- decyzja o bazie tabel Growth.

Poza zakresem:

- automatyczna publikacja,
- Customer Graph,
- zmiany produktów i cen.

## v0.2 — Experts + Content Library

Zakres:

- profil Growth 1:1 dla `Instructor`,
- specjalizacje i grupy odbiorców,
- indeks ContentAsset,
- linkowanie artykułów, wideo, materiałów i kursów,
- wyszukiwanie po ekspercie, temacie, formacie, dacie i statusie,
- ręczne relacje między treściami.

Warunek:

- brak kopiowania treści bez uzasadnienia,
- jasny właściciel i status każdego assetu.

## v0.3 — Publishing integrations

Zakres wdrażany osobno per kanał:

- najpierw preview/dry-run,
- potem jedna zatwierdzana operacja,
- status i retry,
- monitoring i runbook.

Sugerowana kolejność:

1. eksport/transfer bez publikacji,
2. YouTube jako prywatny/niepubliczny draft,
3. mailing testowy,
4. dopiero później publikacja i wysyłka masowa.

Meta Ads i budżety reklamowe pozostają osobnym projektem wysokiego ryzyka.

## v0.4 — Post-production / repurposing

Zakres:

- import lub wskazanie nagrania,
- transkrypcja,
- segmentacja i tematy,
- propozycje klipów, FAQ, artykułów i newsletterów,
- relacje `derived_from`,
- approval każdego materiału.

Warunki:

- retencja nagrań i transkrypcji,
- prawa do treści,
- limity kosztów,
- ocena jakości polskiej transkrypcji.

## v0.5 — Radar

Zakres:

- jawna lista źródeł,
- data publikacji i data weryfikacji,
- wykrywanie duplikatów,
- uzasadnienie „dlaczego ważne”,
- odbiorca, ekspert i rekomendowane formaty,
- klasyfikacja: marka / edukacja / społeczność / sprzedaż.

Radar nie może automatycznie tworzyć lub publikować kampanii.

## v0.6 — Customer Graph / Relationship Engine

Najwyższe ryzyko danych osobowych.

Przed implementacją:

- osobna analiza prawna/DPIA,
- model zgód i retencji,
- identity resolution z możliwością korekty,
- definicja Next Best Experience,
- zakaz dark patterns i automatycznej presji sprzedażowej.

Rozpocząć od zagregowanych segmentów, nie pełnego profilu osoby.

## v0.7 — CRM szkół

Zakres:

- Organization,
- kontakty i role,
- zapytania, oferty, terminy i szkolenia,
- prosty pipeline,
- kolejne działanie,
- ręczne scalanie duplikatów.

Pierwszy krok powinien wykorzystać przyszły zapis zapytań z `TrainingOffer`, który dziś wysyła wiadomość bez rekordu CRM.

Backfill szkół ze starych zamówień jest osobnym, opcjonalnym etapem z dry-run.

## v0.8 — Analytics / Next Best Experience

Zakres:

- marka, relacja, eksperci, treści, produkty, sprzedaż, B2B i lojalność,
- biznesowe wyjaśnienia wyników,
- jakość i kompletność źródeł,
- rekomendacja następnego doświadczenia,
- pomiar skutku rekomendacji.

Nie zastępuje istniejących dashboardów sprzedaży. Korzysta z ich agregatów i dodaje warstwę interpretacji.

## Ryzyka przekrojowe

- zbyt wczesny Customer Graph,
- podwójne pojęcie kampanii,
- rozrost scope pierwszej wersji,
- przeniesienie logiki do narzędzia automatyzacji,
- automatyzacja publikacji bez idempotency,
- niekontrolowane koszty AI,
- dokumentacja wyprzedzająca stan faktyczny,
- obciążenie kolejki na hostingu współdzielonym,
- brak właściciela jakości treści.

## Zasada stop

Etap zatrzymuje się przed implementacją, jeśli brakuje:

- decyzji właściciela zmieniającej UX lub proces biznesowy,
- danych potrzebnych do bezpiecznej migracji,
- rollbacku,
- testu regresji,
- polityki danych/AI,
- sandboxa lub sposobu bezpiecznego przetestowania integracji.
