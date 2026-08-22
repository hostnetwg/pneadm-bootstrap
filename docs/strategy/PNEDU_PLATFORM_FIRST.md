# Strategia: uczestnik przez pnedu.pl (platform-first)

Data utworzenia/aktualizacji: 2026-08-22  
Status: **zaakceptowana** (decyzja Waldemara; dokument kanoniczny dla produktu i implementacji)

## Cel biznesowy

Chcemy, żeby uczestnik **nie kończył relacji ze szkoleniem na samym ClickMeeting**, tylko **wracał na platformę pnedu.pl** — przed live, w trakcie (gdy to możliwe) i po szkoleniu.

Platforma jest jednym miejscem na:

- logowanie i konto uczestnika,
- wejście na spotkanie na żywo (embed),
- nagrania i materiały,
- zaświadczenia,
- ankiety i dalszą komunikację.

**ClickMeeting** pozostaje silnikiem spotkania (API, tokeny, pokój), ale **nie jest głównym kanałem UX** dla uczestnika.

## Problem, który rozwiązujemy

Scenariusz do unikania:

1. uczestnik dostaje mail z linkiem bezpośrednio do CM,
2. ogląda live,
3. **nie zakłada konta / nie loguje się na pnedu.pl**,
4. trudno wysłać nagranie, zaświadczenie, przypomnienie o dostępie — brak powrotu na platformę.

Scenariusz docelowy:

1. zamówienie → provision (konto + uczestnik na szkoleniu),
2. mail zachęca do **pnedu.pl** (ustaw hasło / zaloguj się),
3. uczestnik wchodzi na **dashboard** i stamtąd na live (embed) lub wraca po szkoleniu po materiały,
4. CM jest dostępny jako **fallback**, nie jako jedyna ścieżka.

## Lejek docelowy (uproszczony)

```text
Zamówienie (formularz / płatność)
    → provision w adm (participants + pnedu.users + opcjonalnie CM token)
    → e-mail: konto / logowanie na pnedu.pl
    → dashboard /dashboard/szkolenia
    → live: embed na pnedu (/transmisja)  [preferowane]
              lub bezpośredni CM           [fallback]
    → po szkoleniu: nagranie, materiały, zaświadczenie, ankieta — wyłącznie przez pnedu.pl
```

## Zasady produktowe

| Zasada | Znaczenie |
|--------|-----------|
| **Platform-first** | Każda nowa funkcja live/dostępu powinna zwiększać szansę wejścia na pnedu.pl, nie omijać platformy. |
| **Konto przed materiałami** | Nagrania i materiały dla płatnych szkoleń — przez konto (provision + logowanie). |
| **CM = warstwa techniczna** | Tokeny, rejestracja API, pokój — po stronie adm; uczestnik widzi przede wszystkim pnedu.pl. |
| **Fallback CM zawsze dostępny** | Gdy embed/mobile/awaria — bezpośredni link CM w mailu i w panelu; nie blokujemy wejścia na live. |
| **Spójność kanałów** | Maile provision, „Wyślij link do live”, dostęp do nagrań — ten sam przekaz: najpierw pnedu.pl. |

## Zasady implementacji (checklist dla dev / AI)

Przed wdrożeniem funkcji live lub dostępu zadaj pytanie: *„Czy uczestnik ma realną szansę wrócić na pnedu.pl?”*

### E-maile

- Provision (nowe konto): **Ustaw hasło na pnedu.pl** przed sekcją live; redirect na `/dashboard/szkolenia`.
- Provision (istniejące konto): **Zaloguj się** (`/login?email=…`) przed linkami do spotkania.
- „Wyślij link do live”: ta sama kolejność co w provision (przy włączonym embed w mailu).
- Termin szkolenia w mailach: data/godzina **startu** + czas trwania w nawiasie — bez daty/godziny zakończenia (`App\Support\CourseAccessEmailSchedule`).

### Panel uczestnika (pnedu.pl)

- Przycisk **Dołącz do spotkania na żywo** na dashboardzie.
- Przy włączonej opcji embed w kursie: **Dołącz w pnedu.pl** → `/dashboard/szkolenia/{participant}/transmisja`.
- Bezpośredni CM obok embed (nie zamiast), gdy skonfigurowany.

### Panel administracyjny (adm)

- Edycja kursu: radio **Osadzony pokój na pnedu.pl** + opcjonalnie checkbox **Link w e-mailu do osadzonego pokoju**.
- Provision i lista uczestników: operacje live i maile zgodne ze strategią platform-first.

### Czego unikać

- Maila, w którym **jedynym** CTA jest link CM (bez wzmianki o pnedu.pl), o ile szkolenie ma provision i konto.
- Flow „tylko CM” jako domyślnego dla **nowych** szkoleń — dopiero po zakończeniu **pilota embed** (obecnie: ręczne włączenie na wybranych szkoleniach).
- Funkcji, które wysyłają nagrania/zaświadczenia **poza** kontem pnedu, gdy uczestnik mógł dostać provision.

## Wyjątki i fallback

| Sytuacja | Zachowanie |
|----------|------------|
| Brak konta / brak provision | Dopuszczalny mail z samym linkiem CM (legacy, wyjątki operacyjne). |
| Embed wyłączony w kursie | Maile i panel z bezpośrednim CM; nadal zachęcamy do logowania, jeśli konto istnieje. |
| Mobile / RWD embed | Redirect do CM z strony transmisji — uczestnik i tak wraca na dashboard przy kolejnych wizytach. |
| Awaria embed / CM | Fallback link w mailu i w panelu; wsparcie może podać bezpośredni link. |
| Szkolenie zakończone | W mailach provision/live **brak** sekcji spotkania; komunikat o materiałach na pnedu.pl. |

## Odporność i fallback (co jest dziś, czego nie ma)

### Dziś wdrożone

| Mechanizm | Gdzie |
|-----------|--------|
| **Bezpośredni link CM w mailu** (obok embed) | Maile provision i „link do live”, gdy włączony embed w mailu |
| **Przycisk CM na dashboardzie** obok wejścia embed | `/dashboard/szkolenia` — dwa warianty „Dołącz…” |
| **Mobile → redirect do CM** | `/transmisja` wykrywa mobile i robi `redirect` na auto-login CM |
| **Link „Otwórz w ClickMeeting”** na stronie transmisji | Gdy nie uda się zbudować iframe |
| **Limit 1 aktywnej sesji embed** / uczestnik | `LiveTransmissionPresenceService` — ogranicza równoległe wejścia |

### Czego **nie ma** (świadome ograniczenie)

- **Automatycznego przekierowania**, gdy **całe pnedu.pl jest niedostępne** (pad hostingu, 502, timeout). Link embed w mailu też prowadzi na pnedu — wtedy uczestnik musi użyć **alternatywnego linku CM z tego samego maila** (dlatego na razie **nie ukrywamy** go w treści).
- **Operacyjny plan B:** ponowne wysłanie zaproszenia z panelu ClickMeeting (zakładka **Zaproszenia** przy wydarzeniu) — działa niezależnie od awarii pnedu.pl.
- **Auto-failover** typu „ten sam URL embed → CM bez interakcji użytkownika” — **nie zaimplementowany**; wymagałby osobnej warstwy (health-check, edge redirect) albo zmiany linku w mailu na trackowany redirect.

**Decyzja Waldemara (2026-08-22):** obawy o stabilność pnedu i obciążenie przy wielu uczestnikach są uzasadnione. **Bezpośredni CM w mailu zostaje** do czasu pewności co do embed. Docelowo możliwe: ukrycie CM w mailu **tylko** gdy embed jest stabilny **i** istnieje sensowny plan awaryjny (min. link CM w panelu + procedura operacyjna).

**Kolejny krok techniczny (opcjonalny, po pilocie):** event analityczny przy wejściu embed vs CM; ewentualnie strona błędu `/transmisja` z przyciskiem / auto-redirectem na CM (nie zastępuje awarii całego serwisu).

## Metryki i statystyki (stan + plan)

**Cel pomiaru:** wiedzieć, ilu uczestników korzysta z **osadzonego pokoju na pnedu.pl**, a ilu wchodzi **bezpośrednio do ClickMeeting** — oraz czy po provision wracają na platformę.

| Metryka | Stan (2026-08-22) | Uwagi |
|---------|-------------------|--------|
| Logowania po provision (`last_login_at`, `login_count`) | **jest** (baza `pnedu.users`) | Można raportować ręcznie / w panelu użytkownika PNEDU |
| Wejścia na `/dashboard/szkolenia` | **częściowo** | `participant_training_page_views` — otwarcia strony szkolenia, nie rozróżnia embed vs CM |
| Wejścia przez `/transmisja` (embed) | **brak dedykowanego eventu** | Do dodania w analityce (np. `live_join_embed`) |
| Kliknięcia bezpośredniego linku CM (mail / dashboard) | **brak dedykowanego eventu** | Do dodania (np. `live_join_direct_cm`) — w mailu trudniejsze bez linków trackowanych |

**Decyzja Waldemara (2026-08-22):** statystyki embed vs bezpośredni CM są **pożądane**, ale **nie priorytet na dziś** — najpierw pilot embed na kilku szkoleniach; po stabilizacji — prosty raport w adm (per kurs: embed / CM / nieznane).

Metryki nie blokują wdrożeń — służą ocenie, czy lejek działa.

## Powiązana dokumentacja

| Dokument | Rola |
|----------|------|
| [FORM_ORDERS_PNEDU_PROVISION.md](../FORM_ORDERS_PNEDU_PROVISION.md) | Runbook provision, maile, CM, embed |
| [architecture/SYSTEM_OVERVIEW.md](../architecture/SYSTEM_OVERVIEW.md) | Architektura pnedu + adm |
| `pnedu/docs/DASHBOARD_LIVE_EMBED.md` | Embed, transmisja, mobile fallback |
| [email-deliverability-strategy.md](../email-deliverability-strategy.md) | Dostarczalność maili systemowych |

## Kluczowy kod (orientacja)

| Obszar | Projekt | Pliki / serwisy |
|--------|---------|-----------------|
| Provision + maile | pneadm | `FormOrderPneduProvisionService`, `PneduFormOrderProvisioned*User` |
| Link live (pojedynczy / zbiorczy) | pneadm | `ParticipantLiveMeetingLinkMailService`, `ParticipantLiveMeetingLinkNotification` |
| Termin w mailach | pneadm | `CourseAccessEmailSchedule` |
| Tokeny CM | pneadm | `ParticipantLiveAccessService`, `ClickMeetingService` |
| Dashboard + embed | pnedu | `DashboardController`, `LiveTransmissionService`, widoki transmisji |

## Decyzje (Waldemar, 2026-08-22)

### 1. Domyślny embed dla nowych szkoleń

**Na razie: NIE.**  
Embed włączamy **ręcznie** na wybranych **najbliższych szkoleniach z mniejszą frekwencją** (faza pilotażowa).  
**Po** potwierdzeniu stabilności i braku istotnych błędów — embed ma stać się **domyślną opcją dla nowych szkoleń online z ClickMeeting** (decyzja wtórna, wpis do tego dokumentu).

### 2. Statystyki embed vs bezpośredni ClickMeeting

**Tak — o to chodzi:** ile osób weszło przez **osadzony pokój pnedu.pl**, a ile przez **link bezpośredni CM** (mail lub przycisk zewnętrzny).  
Dodatkowo pomocne: kto **w ogóle zalogował się** na pnedu po provision (`last_login_at`).  
**Priorytet:** po pilocie embed; dziś brak dedykowanych eventów — patrz sekcja „Metryki i statystyki”.

### 3. Ukrycie bezpośredniego CM w mailu

**Na razie: NIE** — link CM w mailu **zostaje widoczny** (fallback).  
Powód: obawy o stabilność pnedu.pl i obciążenie przy wielu uczestnikach.  
**Docelowo:** prawdopodobnie tak (ukryć lub obniżyć CM w mailu), ale dopiero przy stabilnym embed **i** jasnym planie awaryjnym (link CM w panelu, mobile redirect, ewent. procedura wsparcia).  
**Uwaga:** pełna awaria pnedu.pl **nie** przekierowuje automatycznie embed → CM; w takiej sytuacji uczestnik korzysta z **alternatywnego linku CM z maila**.

## Historia

| Data | Zmiana |
|------|--------|
| 2026-08-22 | Decyzje Waldemara: pilot embed, statystyki embed vs CM (plan), CM w mailu zostaje; sekcja odporności/fallback. |
| 2026-08-22 | Pierwsza wersja strategii platform-first (Waldemar + implementacja embed, maile, synchroniczny bulk live). |
