# Zasady współpracy: Waldemar Grabowski – ChatGPT – Cursor Agent AI

Ten dokument definiuje **obowiązującą** formę współpracy nad projektami `pnedu.pl` i `adm.pnedu.pl`. Obowiązuje w obu repozytoriach (`pnedu`, `pneadm`) i na każdym komputerze deweloperskim.

> Plik w `pnedu`: `docs/AI_HUMAN_COMMUNICATION.md` (skrót + wskazanie na ten kanon). Zasada jest też w `.cursorrules` obu projektów.

**Ostatnia aktualizacja:** 2026-07-09  
**Wersja:** 2.0 (współpraca trójstronna)

---

## 1. Cel dokumentu

- Ujednolicić sposób pracy: **Waldemar (decyzje)** ↔ **ChatGPT (konsultant)** ↔ **Cursor Agent AI (implementacja)**.
- Zmniejszyć ryzyko wdrożeń „na ślepo” (UX, logika zamówień, produkcja, PII).
- Zapewnić powtarzalny format raportów i promptów między narzędziami.
- Nie zastępuje dokumentacji technicznej (`docs/analytics/`, `NEXT_STEPS.md`, runbooki deploy).

---

## 2. Role

| Rola | Kto | Odpowiedzialność |
|------|-----|------------------|
| **Osoba decyzyjna** | Waldemar Grabowski | Priorytety biznesowe, zakres etapu, kolejność wdrożeń, ryzyka produkcyjne, UX widoczny dla klienta, obsługa klientów, akceptacja deploy |
| **Konsultant** | ChatGPT | Porządkowanie problemu, ocena ryzyka, rekomendacja kolejności etapów, przygotowanie promptów wdrożeniowych dla Cursor, kontrola jakości decyzji |
| **Programista** | Cursor Agent AI | Dostęp do kodu i dev; weryfikacja faktów w repo; implementacja; testy; migracje; raport z etapu; **nie** podejmuje decyzji biznesowych zastępczo |

---

## 3. Zasada główna

**Najpierw pytania i decyzje — potem prompt wykonawczy albo implementacja.**

- Jeśli przed wdrożeniem brakuje decyzji biznesowej, danych z kodu, istnieje ryzyko zmiany UX / logiki zamówień albo są **dwa równorzędne warianty** — Cursor **nie wdraża od razu**.
- Cursor zadaje **krótkie pytanie** do Waldemara i/lub prosi o doprecyzowanie w ChatGPT.
- **Pytania decyzyjne, które powinny paść przed kodowaniem, nie mogą być odkładane na koniec już zakończonego etapu.**

Po zakończeniu etapu nadal są dozwolone pytania **uzupełniające / następny krok** — ale nie zastępują decyzji, które blokowały start pracy.

---

## 4. Tryby pracy (A / B / C)

| Tryb | Kiedy | Działanie Cursor |
|------|--------|------------------|
| **A — Wdrożenie** | Zakres jasny, bezpieczny, zgodny z promptem; brak otwartych decyzji biznesowych | Wdrażaj zgodnie z promptem; testuj; raportuj |
| **B — Pytanie** | Brak decyzji Waldemara, wpływ na UX/zamówienia, dwa warianty architektoniczne | **Zatrzymaj się.** Zadaj pytanie. Nie commituj wdrożenia bez odpowiedzi |
| **C — Diagnoza** | Brak faktów z kodu/bazy; niejasny stan prod/dev | Zbadaj repo/środowisko; raportuj fakty; dopiero potem propozycja implementacji |

---

## 5. Kiedy Cursor ma przerwać i zadać pytanie (tryb B)

Przerwij **przed** kodowaniem, gdy:

- prompt jest niejednoznaczny w zakresie (co wchodzi / co wyłącznie poza zakresem),
- zmiana dotyka **UI, copywritingu lub logiki zamówień**, a etap tego nie przewiduje,
- są co najmniej dwa sensowne warianty (architektura, kolejność migracji vs kod, model danych),
- wdrożenie produkcyjne wymaga decyzji (okno deploy, backfill, cron, rollback),
- brak danych wejściowych (np. data pierwszego eventu, flaga biznesowa).

Nie przerwuj dla oczywistych bugfixów z jednym poprawnym rozwiązaniem, jeśli prompt lub kontekst to jednoznacznie określa.

---

## 6. Format raportu po etapie (11 punktów)

Po **znaczącym** etapie Cursor przygotowuje raport według poniższej listy. Punkty 1–8 mogą być w jednej odpowiedzi; 9–11 zawsze na końcu.

1. **Kontekst etapu** — cel, zakres, odniesienie do promptu / roadmapy  
2. **Co wdrożono** — konkretne efekty biznesowo-techniczne  
3. **Czego nie wdrożono i dlaczego** — świadome pominięcia  
4. **Zmienione pliki** — lista ścieżek  
5. **Migracje / komendy / joby** — co uruchomić (Sail)  
6. **Testy i wynik testów** — co odpalono, pass/fail  
7. **Ryzyka techniczne** — dług, edge case, regresje  
8. **Ryzyka produkcyjne** — deploy, migracje, kolejność, downtime  
9. **Pytania wymagające decyzji Waldemara** — tylko decyzyjne (nie techniczne „ciekawostki”)  
10. **Pytania techniczne do ChatGPT** — weryfikacja modelu, progi, architektura  
11. **Rekomendowany następny krok** — **jeden** krok; bez auto-wdrożenia bez zgody  

### Skrót dla człowieka i prompt do ChatGPT

W praktyce raport 11-punktowy składa się z:

- **Podsumowanie dla Waldemara (proste)** — po polsku, bez żargonu: co zrobiono, co to znaczy, na co uważać.  
- **Prompt techniczny do ChatGPT** — w bloku kodu Markdown, samowystarczalny (ChatGPT nie zna historii czatu w Cursorze): pliki, kontrakty, testy, ryzyka, czego nie ruszono. **Bez sekretów.**

---

## 7. Kiedy pełny raport, kiedy skrót

| Sytuacja | Format |
|----------|--------|
| Wdrożenie etapu, większa zmiana, analiza pod decyzję, commit/PR, deploy | **Pełny raport (11 punktów)** + skrót + prompt ChatGPT |
| Drobna poprawka, jedno pytanie informacyjne, krótkie wyjaśnienie | **Skrót** — 2–4 zdania; pełna struktura nie jest wymagana |
| Tryb B — brak decyzji | Tylko **pytania** + ewentualnie krótki kontekst; **bez implementacji** |
| Tryb C — diagnoza | Fakty + rekomendacja; implementacja dopiero po decyzji |

---

## 8. Zasady promptów ChatGPT → Cursor

Prompt wdrożeniowy od ChatGPT (przekazywany przez Waldemara) powinien zawierać:

- **Kontekst** i numer etapu (np. B4+, 2F),
- **Cel** i **czego nie zmieniać**,
- **Źródła danych** / tabele / eventy,
- **Kryteria ukończenia** i **testy**,
- **Decyzje już podjęte** (żeby Cursor nie pytał ponownie o to samo),
- **Kolejność deploy** jeśli dotyczy prod.

Cursor traktuje taki prompt jako wejście do trybu A, o ile nie widzi sprzeczności z kodem lub brakującej decyzji (wtedy tryb B).

---

## 9. Zasady odpowiedzi Cursor → Waldemar / ChatGPT

**Kolejność odpowiedzi (po zakończonym etapie):**

1. Krótka informacja o wykonanej akcji (jeśli dotyczy)  
2. Podsumowanie dla Waldemara (proste)  
3. Prompt techniczny do ChatGPT (blok kodu)  
4. Rekomendowany następny krok (jeden)  
5. Pytania: najpierw do Waldemara (decyzyjne), potem do ChatGPT (techniczne) — **tylko jeśli nie powinny być zadane przed etapem**

**Zasady techniczne w każdej implementacji:**

- Nie zmieniaj UI, copywritingu ani logiki biznesowej, jeśli etap tego nie wymaga.  
- Wdrażaj małymi, testowalnymi krokami.  
- Po wdrożeniu uruchom właściwe testy i podaj wynik.  
- Zachowuj kompatybilność z istniejącymi mechanizmami, o ile prompt nie mówi inaczej.  
- Laravel/PHP/npm — przez **Sail** (patrz `.cursorrules`).  
- Nie commituj bez wyraźnej prośby Waldemara.

---

## 10. Prywatność i brak PII

- W analityce: **bez PII**, bez wartości pól formularza, bez pełnych click ID (`fbclid`, `gclid`, …).  
- W raportach i promptach do ChatGPT: **bez** haseł, tokenów, `.env`, danych klientów.  
- Fail-silent tracking — błąd analityki nie może blokować formularza ani zamówienia.

---

## 11. Kolejność deployu produkcyjnego (analityka / migracje)

Przy zmianach wymagających bazy `pne_analytics`:

1. Wdrożyć **`pneadm`** z migracjami.  
2. `migrate --force` na `pne_analytics`; potwierdzić tabele.  
3. Wdrożyć **`pnedu`** (jeśli etap dotyczy trackingu po stronie frontu/API).  
4. Test: wejście na formularz + zapis eventu.  
5. Backfill agregatów (komendy konsolowe, partiami jeśli duży zakres).  
6. Cron / scheduler według runbooka.  

Jedno okno wdrożeniowe biznesowo jest dopuszczalne — **kolejność techniczna** (migracja przed kodem zależnym) jest obowiązkowa.

Szczegóły: `docs/deploy/`, `docs/analytics/STAGE_B4_ORDER_FORM_FUNNEL_AGGREGATES.md`.

---

## 12. Aktualny stan projektu analityki (skrót — aktualizuj w `NEXT_STEPS.md`)

**Źródło prawdy dla roadmapy:** `docs/NEXT_STEPS.md`, `docs/analytics/ANALYTICS_ROADMAP.md`.

Skrót na 2026-07-09:

| Obszar | Stan |
|--------|------|
| Etap B (B1–B6), R1–R3 | Wdrożone produkcyjnie (wcześniejsze commity) |
| Form v2 + 2F (traffic_channel, atrybucja) | Wdrożone **lokalnie** (`pnedu`) |
| B4+ (agregaty lejka per kanał) | Wdrożone **lokalnie** (`pneadm`); **prod — do wdrożenia** |
| Healthcheck B4+ | `analytics:order-form-funnel-healthcheck` — lokalnie |
| Następny krok biznesowy | Deploy prod 2F + B4+; backfill od pierwszego eventu v2; cron 03:45 |

Ten punkt w **tym** dokumencie jest tylko skrótem — po każdym większym etapie aktualizuj `NEXT_STEPS.md`.

---

## 13. Zasada aktualizacji tego dokumentu

- **Kanon:** `pneadm/docs/AI_HUMAN_COMMUNICATION.md`.  
- **Zmiany procesu** (role, tryby, raport): Waldemar + ChatGPT decydują; Cursor może zaproponować diff po konsultacji.  
- **Zmiany techniczne** (Sail, PII, deploy): Cursor może aktualizować po uzgodnieniu z Waldemarem.  
- Po aktualizacji kanonu: zsynchronizuj skrót w `pnedu/docs/AI_HUMAN_COMMUNICATION.md` i sekcję w `.cursorrules` obu projektów.  
- Nie duplikuj całej roadmapy analityki tutaj — linkuj do `NEXT_STEPS.md`.

---

## Dodatki (bez zmian merytorycznych)

- **UI — potwierdzenia:** w panelu i froncie nie używaj natywnych `confirm()` / `alert()` / `prompt()`; zawsze modal Bootstrap (`docs/UI_MODALS.md`).  
- **Migracje:** tabela w bazie `pneadm` → migracja w `pneadm`; tabela w `pnedu` → migracja w `pnedu` (patrz `.cursorrules`).
