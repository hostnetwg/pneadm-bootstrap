# Strategia dostarczalności e-mail — Platforma Nowoczesnej Edukacji

**Status:** dokument wewnętrzny — strategia + stan wdrożenia Laravel + SES
**Ostatnia aktualizacja:** 2026-06-02
**Zakres:** ekosystem `pnedu.pl`, `adm.pnedu.pl`, subdomeny SES, Sendy, integracje Laravel
**Powiązane repozytoria:** `pneadm` (panel administracyjny), `pnedu` (serwis publiczny)

> **Zakres tego dokumentu:** strategia kanałów e-mail, wdrożenie Laravel + Amazon SES dla wiadomości systemowych, backlog poza Laravel (Sendy, iFirma, DNS, SEO). Sendy i kampanie marketingowe są konfigurowane poza repozytoriami aplikacji.

---

## 1. Cel strategii e-mail

Firma prowadzi szkolenia online, webinary (w tym cykl „TIK w pracy NAUCZYCIELA”), płatne szkolenia oraz komunikację systemową do uczestników. Historycznie wiele typów wiadomości wychodziło z tych samych lub przeciążonych adresów, co pogarszało reputację i dostarczalność.

**Cel:** rozdzielić reputację i odpowiedzialność trzech głównych kanałów wysyłki:

| Kanał | Subdomena | Przeznaczenie |
|-------|-----------|---------------|
| Marketing ogólny | `news.pnedu.pl` | oferty, newsletter, kampanie do szkół i klientów |
| Webinary TIK | `tik.pnedu.pl` | cykl TIK, baza ~62k, zapowiedzi i przypomnienia webinarów |
| Systemowy / transakcyjny | `system.pnedu.pl` | zaświadczenia, nagrania, konta, zamówienia, brak marketingu |

Osobno utrzymujemy **obsługę klienta** (`kontakt@pnedu.pl`) — nie jako masowy nadawca kampanii.

---

## 2. Kontekst migracji z Publigo

Dotychczasowy serwis sprzedażowo-kursowy działał na **nowoczesna-edukacja.pl** (Publigo). Publigo miało ograniczenia i nie pasowało do obecnego modelu biznesowego (szkolenia online na żywo, webinary, komunikacja z uczestnikami).

Nowy ekosystem operacyjny rozwijany jest wokół:

- **pnedu.pl** — front, panel uczestnika, publiczne linki tokenowe
- **adm.pnedu.pl** — panel administracyjny (Laravel)
- **system.pnedu.pl**, **news.pnedu.pl**, **tik.pnedu.pl** — tożsamości nadawcze SES / Sendy

Od ok. dwóch miesięcy nowi uczestnicy otrzymują w wiadomościach linki do nagrań, materiałów, kont i panelu w domenie **pnedu.pl**. Cofanie frontu na `nowoczesna-edukacja.pl` byłoby technicznie i komunikacyjnie bardziej złożone niż kontynuacja `pnedu.pl`.

---

## 3. Decyzja kierunkowa: pnedu.pl jako docelowy front

**Strategicznie preferowany kierunek:** `pnedu.pl` pozostaje docelową domeną nowego serwisu frontowego i aplikacyjnego.

**Nie oznacza to** natychmiastowej masowej podmiany `nowoczesna-edukacja.pl` w kodzie, treściach, SEO i dokumentach. Taka migracja to **osobny, kontrolowany etap** (patrz sekcja 4).

---

## 4. Status `nowoczesna-edukacja.pl` — domena legacy

| Aspekt | Opis |
|--------|------|
| Rola obecna | rozpoznawalna domena historyczna; dotychczasowy serwis Publigo |
| Rola docelowa | kandydat do przekierowania **301** na `pnedu.pl` po osobnym planie |
| W kodzie — maile Laravel | widoki mailowe w **pnedu** i **adm** używają `kontakt@pnedu.pl` / `www.pnedu.pl`; legacy nadal w regulaminach, RODO, stopce www, PDF certyfikatów (adm) |
| W e-mail strategy | **nie** używać jako nowego masowego From (ani marketing, ani system) |

### Future domain migration: nowoczesna-edukacja.pl → pnedu.pl

Migracja wymaga **oddzielnej decyzji biznesowej i technicznej**. Plan (do opracowania później) obejmie m.in.:

- mapowanie URL-i (stare ścieżki Publigo → nowe trasy Laravel)
- przekierowania **301**
- aktualizacja linków wewnętrznych
- canonicale (`rel=canonical`)
- sitemap i `SeoController`
- Google Search Console (obie domeny)
- PDF-y zamówień, regulaminy, RODO, stopki
- schema.org / JSON-LD
- stare kampanie e-mail i materiały marketingowe
- integracje: Publigo (wygaszenie), iFirma, Sendy
- testowanie indeksacji i monitorowanie ruchu / błędów 404

**Zasada:** nie wykonywać migracji SEO przy okazji konfiguracji e-mail. Nie podmieniać hurtowo domen. Nie usuwać starych adresów bez planu cutover.

---

## 5. Aktualny routing poczty kontaktowej

W SEOHOST / DirectAdmin:

```
kontakt@pnedu.pl  →  przekierowanie  →  kontakt@nowoczesna-edukacja.pl
```

**Interpretacja operacyjna:**

- `kontakt@pnedu.pl` — publiczny adres w nowym ekosystemie (Reply-To w kampaniach i mailach systemowych)
- realna obsługa poczty może nadal odbywać się w skrzynce `kontakt@nowoczesna-edukacja.pl`
- `kontakt@nowoczesna-edukacja.pl` był historycznie intensywnie używany (marketing + system) — **nie traktujemy go jako czystego kanału reputacyjnego**
- nie używamy go jako **nowego** adresu From dla marketingu ani wiadomości systemowych

**Do rozstrzygnięcia (otwarte):** czy `kontakt@pnedu.pl` ma pozostać aliasem do legacy skrzynki, czy stać się samodzielną skrzynką operacyjną.

Analogiczne przekierowania nadawców SES (już skonfigurowane w DirectAdmin):

| Adres nadawcy | Przekierowanie (obecnie) |
|---------------|--------------------------|
| `szkolenia@news.pnedu.pl` | → `kontakt@pnedu.pl` |
| `szkolenie@news.pnedu.pl` | → `kontakt@pnedu.pl` |
| `info@system.pnedu.pl` | → `kontakt@pnedu.pl` |
| `webinary@tik.pnedu.pl` | do doprecyzowania (prawdopodobnie → `kontakt@pnedu.pl`) |

---

## 6. Mapa subdomen i domen

| Domena / subdomena | Rola | Publiczna aplikacja www | Nadawca e-mail |
|--------------------|------|-------------------------|----------------|
| **pnedu.pl** | docelowy front, panel uczestnika, linki tokenowe | tak | nie (masowo) |
| **adm.pnedu.pl** | panel administracyjny Laravel | tak (tylko admin) | **nie** — zakaz From i publicznych linków do klientów |
| **system.pnedu.pl** | kanał SES — wiadomości systemowe/transakcyjne | **nie** (tylko tożsamość nadawcza) | `info@system.pnedu.pl` |
| **news.pnedu.pl** | marketing ogólny (Sendy + SES) | nie | `szkolenia@news.pnedu.pl` |
| **tik.pnedu.pl** | marketing TIK / webinary (Sendy + SES) | nie | `webinary@tik.pnedu.pl` (docelowo) |
| **nowoczesna-edukacja.pl** | legacy / Publigo | tak (do wygaszenia) | historyczny — nie jako nowy From |
| **zdalna-lekcja.pl** | legacy TIK | — | historycznie `waldemar.grabowski@zdalna-lekcja.pl` |

---

## 7. Mapa adresów nadawczych i Reply-To

| Adres | Kanał | From / Reply-To | Narzędzie | Uwagi |
|-------|-------|-----------------|-----------|-------|
| `szkolenia@news.pnedu.pl` | marketing | **From** | Sendy + SES | główny nadawca news |
| `szkolenie@news.pnedu.pl` | marketing | alias From | Sendy + SES | wariant nazwy |
| `webinary@tik.pnedu.pl` | TIK / webinary | **From** (docelowo) | Sendy + SES | cutover z `zdalna-lekcja.pl` |
| `info@system.pnedu.pl` | system / transakcyjny | **From** | Laravel (pnedu + adm) + SES | zero marketingu w treści |
| `kontakt@pnedu.pl` | obsługa klienta | **Reply-To** | alias / przekierowanie | publiczny Reply-To / alias; obecnie przekierowanie do legacy skrzynki `kontakt@nowoczesna-edukacja.pl`; nie masowy From |
| `kontakt@nowoczesna-edukacja.pl` | legacy | historyczny From/odbiorca | DirectAdmin | nie nowy masowy From; realna skrzynka przez alias |
| `waldemar.grabowski@zdalna-lekcja.pl` | legacy TIK | historyczny From | Sendy (poza kodem) | wygaszenie po cutover TIK |

**Reply-To docelowo (wszystkie kanały wysyłki):** `kontakt@pnedu.pl`

**Nazwa nadawcy TIK (do wyboru):** „Waldemar Grabowski \| TIK w pracy nauczyciela” lub „TIK w pracy nauczyciela”.

---

## 8. Klasyfikacja wiadomości w kodzie

Legenda kanału docelowego: **SYS** = `system.pnedu.pl`, **NEWS** = Sendy/`news`, **TIK** = Sendy/`tik`, **KONTAKT** = odbiorca obsługi, **IFIRMA** = poza etapem 1.

### 8.1. adm.pnedu.pl (`pneadm`)

| Wiadomość / mechanizm | Pliki (orientacyjnie) | Kanał docelowy | Status wdrożenia (Laravel + SES) |
|----------------------|------------------------|----------------|----------------------------------|
| Dostęp do nagrań / materiałów / zaświadczeń | `CourseAccessMail`, `SendCourseAccessEmailJob` | **SYS** | **wdrożone** — trait `UsesSystemMailSettings`; linki → `pnedu.pl`; log: `certificate_email_logs` |
| Link do listy zaświadczeń | `CertificateLinkMail`, job | **SYS** | **wdrożone** |
| Pojedyncze zaświadczenie | `CertificateSingleLinkMail`, job | **SYS** | **wdrożone** |
| Prośba o uzupełnienie danych | `DataCompletionRequestMail` | **SYS** | **wdrożone** — usunięty mailer SMTP `data_completion`; szablon maila: `kontakt@pnedu.pl`, `www.pnedu.pl` |
| Provision konta po zamówieniu | `PneduFormOrderProvisioned*` (Notification) | **SYS** | **wdrożone** — trait `UsesSystemMailSettings` w Notification |
| Reset hasła użytkownika pnedu (z adm) | `PneduFrontendResetPassword` | **SYS** | **wdrożone** |
| Linki szkoleniowe dla prowadzącego | `InstructorTrainingLinksMail` | **SYS** | **wdrożone** — potwierdzone na produkcji (SES) |
| Reset hasła admina (Breeze) | Laravel `ResetPassword` | wewnętrzna | **bez zmian** — tylko konta administracyjne adm; poza kanałem klienckim |
| Subskrypcja Sendy — płatni uczestnicy | `FormOrderSendySyncService` | **NEWS** | poza Laravel Mail |
| Panel Sendy (RSPO, ręczne API) | `SendyService`, `SendyController` | **NEWS** / **TIK** | poza Laravel Mail |

**Link publiczny wymagający migracji (bez implementacji teraz):** formularz uzupełniania danych obecnie na `adm.pnedu.pl/uzupelnij-dane/{token}` — docelowo preferencja: `pnedu.pl/uzupelnij-dane/{token}`.

### 8.2. pnedu.pl

| Wiadomość | Pliki | Kanał docelowy | Status wdrożenia (Laravel + SES) |
|-----------|-------|----------------|----------------------------------|
| Potwierdzenie zamówienia (PDF) | `OrderNotificationMail`, `CourseController` | **SYS** | **wdrożone** — From/Reply-To systemowe; PDF bez legacy domeny; **wysyłka z pnedu**; produkcja: `MAIL_MAILER=ses` |
| Formularz kontaktowy | `ContactFormMail`, `ContactController` | **KONTAKT** | **wdrożone** — systemowy From; Reply-To nadawcy formularza; odbiorca `config('mail.system.reply_to_address')` → `kontakt@pnedu.pl` |
| Powiadomienie o płatności online | `PaymentNotificationMail` | wewnętrzna | **wdrożone** — systemowy From/Reply-To; odbiorca admin (wewnętrzny) |
| Weryfikacja e-mail konta | `SystemVerifyEmail` | **SYS** | **wdrożone** |
| Reset hasła (z pnedu) | `SystemResetPassword` | **SYS** | **wdrożone** |
| Zapis na listę Sendy — TIK | `SendyService::subscribeCourseRegistration` | **TIK** | poza Laravel Mail |
| Zapis na listę — zamówienie per kurs | `sendy_suppression_list_id` | **NEWS** lub **TIK** | poza Laravel Mail |
| Zgoda marketingowa — rejestracja zaświadczenia | `LIST_NAUCZYCIELE` | **NEWS** | poza Laravel Mail |

### 8.3. Sendy — mapowanie list (decyzja strategiczna)

| Lista | ID w kodzie (referencja) | Subdomena nadawcza |
|-------|--------------------------|---------------------|
| TIK / bezpłatne szkolenia | `BkxVCp9892qphCpbeP892xmhdQ` (`LIST_TIK_NAUCZYCIEL`) | **tik.pnedu.pl** |
| Nauczyciele / marketing / zaświadczenia | `K0w2hUq5uwwrkvtlgGyl4Q` (`LIST_NAUCZYCIELE`) | **news.pnedu.pl** |
| Uczestnicy płatnych szkoleń | `dncdl0kfUMnk43BysMa892NQ` (adm `SENDY_PAID_TRAININGS_LIST_ID`) | **news.pnedu.pl** |
| Per-kurs `sendy_suppression_list_id` | pole w `courses` | płatne → **news**; TIK/webinar → **tik** |

Instancja Sendy: `https://sendyhost.net` (konfiguracja w obu projektach).

### 8.4. iFirma (poza etapem 1)

Faktury i proformy: `IfirmaApiService::sendInvoiceByEmail`, nadawca `IFIRMA_SENDER_EMAIL`. Klasyfikacja: transakcyjne/finansowe, zewnętrzny system. Osobna decyzja: legacy vs spójność z `system.pnedu.pl` / `kontakt@pnedu.pl`.

### 8.5. Decyzje strategiczne (B1–B5) — potwierdzone 2026-05

| ID | Wiadomość | Klasyfikacja | From docelowo | Reply-To | Aplikacja / uwagi |
|----|-----------|--------------|---------------|----------|-------------------|
| **B1** | `OrderNotificationMail` | systemowa / transakcyjna | `info@system.pnedu.pl` | `kontakt@pnedu.pl` | **wdrożone** (pnedu); wysyłka z pnedu.pl |
| **B2** | Weryfikacja konta, reset hasła, provision (`PneduFormOrderProvisioned*`) | systemowa / konto | `info@system.pnedu.pl` | `kontakt@pnedu.pl` | **wdrożone** (pnedu + adm); provision z adm zawiera link live ClickMeeting / inne platformy — patrz `docs/FORM_ORDERS_PNEDU_PROVISION.md`; reset admina adm — bez zmian |
| **B3** | `DataCompletionRequestMail` | systemowa | `info@system.pnedu.pl` | `kontakt@pnedu.pl` | **wdrożone** (adm, commit `26237ca`); usunięty legacy mailer SMTP |
| **B4** | Faktury / proformy iFirma | transakcyjne / finansowe | poza etapem 1 | — | Osobna decyzja: `IFIRMA_SENDER_EMAIL` legacy vs spójność z system / kontakt |
| **B5** | `InstructorTrainingLinksMail` | systemowa / organizacyjna | `info@system.pnedu.pl` | `kontakt@pnedu.pl` | **wdrożone** (adm); potwierdzone na produkcji |

---

## 9. Aktualny stan Amazon SES (Frankfurt)

| Parametr | Wartość |
|----------|---------|
| Region | **Europe (Frankfurt) / `eu-central-1`** |
| Production access | **aktywny** (nie sandbox) |
| Zweryfikowane tożsamości domen | `news.pnedu.pl`, `system.pnedu.pl`, `tik.pnedu.pl` |
| Easy DKIM | włączony; konfiguracja **Successful** (po korekcie rekordów CNAME) |
| Laravel `.env.example` (pnedu + adm) | `AWS_DEFAULT_REGION=eu-central-1` |
| Laravel produkcja — pnedu.pl | `MAIL_MAILER=ses` — **potwierdzone** |
| Laravel produkcja — adm.pnedu.pl | `MAIL_SYSTEM_MAILER=ses` — **potwierdzone** (linki instruktora); pełna migracja w kodzie `26237ca` |

**Nie wdrożono jeszcze (plan):**

- custom MAIL FROM: `bounce.news.pnedu.pl`, `bounce.system.pnedu.pl`, `bounce.tik.pnedu.pl` (rekordy MX/SPF z SES)
- configuration sets: `pne-news`, `pne-system`, `pne-tik`
- event publishing (SNS / webhooki do Laravel): send, delivery, bounce, complaint, reject; opcjonalnie open/click

---

## 10. DNS / DKIM / DMARC — stan obecny

- Subdomeny dodane w DirectAdmin jako osobne domeny (zarządzanie DNS i pocztą).
- **DKIM:** rekordy CNAME poprawione — wartość docelowa musi kończyć się na `dkim.amazonses.com.` (historyczny błąd: `...amazonses.com.news.pnedu.pl`).
- **DMARC:** tryb początkowy `v=DMARC1; p=none;` dla subdomen. **Nie** zaostrzać do `quarantine`/`reject` bez monitoringu. **Nie** duplikować rekordów DMARC dla tej samej subdomeny.
- **Catch-all:** nie stosować.
- **Google Postmaster Tools:** do rozważenia w przyszłości dla `pnedu.pl` i subdomen (osobna weryfikacja).

---

## 11. Zasady zakazane

1. **Nie** używać `adm.pnedu.pl` jako domeny From w mailach do klientów.
2. **Nie** tworzyć adresów typu `nagranie@adm.pnedu.pl`, `zaswiadczenie@adm.pnedu.pl`.
3. **Nie** kierować uczestników linkami na `adm.pnedu.pl`, jeśli da się tego uniknąć (docelowo tokeny na `pnedu.pl`).
4. **Nie** używać `kontakt@nowoczesna-edukacja.pl` jako **nowego** masowego From (marketing ani system).
5. **Nie** używać `kontakt@pnedu.pl` jako masowego From kampanii.
6. **Nie** mieszać treści marketingowych z kanałem `system.pnedu.pl` (brak upselli, newsletterów, „przy okazji”).
7. **Nie** wysyłać wiadomości systemowych z kanałów `news` / `tik`.
8. **Nie** stosować catch-all na subdomenach nadawczych.
9. **Nie** zaostrzać DMARC bez monitoringu bounce/complaint i raportów.
10. **Nie** usuwać starych domen/adresów (`zdalna-lekcja.pl`, legacy From) bez planu migracji i rozgrzewki.
11. **Nie** wykonywać migracji SEO `nowoczesna-edukacja.pl` → `pnedu.pl` przy okazji konfiguracji e-mail.
12. **Nie** robić natychmiastowego cutover całej bazy TIK (~62k) na `tik.pnedu.pl`.

---

## 12. Ryzyka i backlog (stan na 2026-06-02)

### Rozwiązane w ramach Laravel + SES

| Temat | Status |
|-------|--------|
| Brak SES w Laravel (pnedu + adm) | **rozwiązane** — `aws/aws-sdk-php`, `MAIL_MAILER=ses` / `MAIL_SYSTEM_MAILER=ses` |
| Mailer `data_completion` (osobny SMTP, `biuro@`) | **usunięty** z adm (`26237ca`) |
| Legacy From/Reply-To w mailach systemowych | **rozwiązane** w widokach mailowych obu aplikacji |
| Region AWS w `.env.example` | **eu-central-1** w pnedu i adm |
| Odbiorca formularza kontaktowego | **pnedu** — `ContactController` → `kontakt@pnedu.pl` |

### Otwarty backlog (poza zamkniętym zakresem Laravel + SES)

| Ryzyko | Opis | Priorytet |
|--------|------|-----------|
| Stara domena w treściach www / PDF | regulaminy, RODO, stopka pnedu, **PDF certyfikatów** (adm) — nadal `nowoczesna-edukacja.pl` | wysoki (etap SEO/treści) |
| Link formularza uzupełniania danych | `/uzupelnij-dane/{token}` na `adm.pnedu.pl` — docelowo `pnedu.pl` | średni |
| Brak eventów SES | `certificate_email_logs` — status joba Laravel, nie bounce/complaint z AWS | średni |
| UX vs kod — rejestracja TIK | komunikat o „potwierdzeniu e-mailem”, kod tylko zapisuje Sendy | średni |
| Reset hasła admina adm | domyślny Laravel Breeze, bez traitu systemowego | niski (wewnętrzny) |
| Hardcoded admin e-mail | `waldemar.grabowski@hostnet.pl` w pnedu (zamówienia, płatności) | niski (wewnętrzne) |
| iFirma sender | `faktury@system.pnedu.pl` / `IFIRMA_SENDER_EMAIL` — osobny kanał | do decyzji |
| Custom MAIL FROM / configuration sets | bounce subdomeny, SNS — patrz sekcja 9 | planowany etap |
| Dokument `.ai/DOMAIN_MIGRATION_STRATEGY.md` | sprzeczny z kierunkiem `pnedu.pl` | informacyjny |

---

## 13. Plan etapów wdrożenia

| Etap | Zakres | Status |
|------|--------|--------|
| **0** | Dokumentacja wewnętrzna (ten plik) | **zakończony** |
| **1** | Inwentaryzacja + pilotaż SES **pnedu** (`OrderNotificationMail`, PDF) | **zakończony** — produkcja potwierdzona |
| **1B** | PDF zamówienia pnedu — brand `pnedu.pl` | **zakończony** |
| **1C** | Pozostałe maile **pnedu** (kontakt, płatności, auth) | **zakończony** |
| **2A** | Inwentaryzacja maili **adm** | **zakończony** |
| **2B** | Pilotaż SES adm — `InstructorTrainingLinksMail` | **zakończony** — produkcja potwierdzona |
| **2C** | Pełna migracja maili klienckich **adm** na SES | **zakończony w kodzie** (`26237ca`); weryfikacja produkcyjna pozostałych typów maili — w toku |
| **3** | SES: custom MAIL FROM + rekordy DNS (bounce.*) | **plan** — nie teraz |
| **4** | SES: configuration sets `pne-news`, `pne-system`, `pne-tik` | **plan** — nie teraz |
| **5** | Event publishing SES → SNS/webhooki → log bounce/complaint | **plan** — nie teraz |
| **6** | Sendy: sender identity i kampanie na `news.pnedu.pl` | **poza repozytorium** — Sendy |
| **7** | Sendy: cutover TIK na `tik.pnedu.pl` | **poza repozytorium** — Sendy |
| **8** | Testy deliverability (Gmail, Outlook, WP, domeny szkolne) | **plan** |
| **9** | Publiczne endpointy tokenowe na `pnedu.pl` (m.in. uzupełnianie danych) | **plan** |
| **10** | **Future domain migration:** SEO, 301, treści www, PDF certyfikatów | **osobny program** |

---

## 14. Otwarte decyzje

| # | Temat |
|---|--------|
| 1 | Kiedy `nowoczesna-edukacja.pl` zacznie przekierowywać 301 na `pnedu.pl` |
| 2 | Czy `kontakt@pnedu.pl` pozostaje aliasem do legacy skrzynki, czy staje się samodzielną skrzynką |
| 3 | Kiedy całkowicie wygasić użycie `kontakt@nowoczesna-edukacja.pl` jako widocznego nadawcy w treściach (osobny od cutover Reply-To) |
| 4 | Harmonogram cutover TIK: segmenty, rozgrzewka, moment wyłączenia `waldemar.grabowski@zdalna-lekcja.pl` |
| 5 | iFirma: `IFIRMA_SENDER_EMAIL` — legacy vs `system.pnedu.pl` |
| 6 | Potwierdzenie zapisu na bezpłatne szkolenie — wyłącznie Sendy/TIK vs dodatkowy mail transakcyjny SYS |
| 7 | Nazwa nadawcy TIK (Waldemar vs sama marka cyklu) |
| 8 | Alias `webinary@tik.pnedu.pl` → `kontakt@pnedu.pl` (przekierowanie pocztowe) |
| 9 | Czy tworzyć reguły Cursor (`.cursor/rules/email-architecture.mdc`) — decyzja zespołu |
| 10 | Aktualizacja `.ai/DOMAIN_MIGRATION_STRATEGY.md` pod kierunek `pnedu.pl` |

---

## 15. Minimalny bezpieczny plan wdrożenia (e-mail)

Fazy można wykonywać sekwencyjnie z przerwami na monitoring reputacji.

### Faza A — przygotowanie (bez wysyłki masowej)

1. Zatwierdzić tę dokumentację i otwarte decyzje (sekcja 14).
2. Spisać produkcyjne wartości `MAIL_*` / SMTP (adm + pnedu) — bez zmian.
3. Zweryfikować w Sendy obecne From dla kampanii TIK i news (baseline przed cutover).

### Faza B — infrastruktura SES (niska objętość)

4. Dodać custom MAIL FROM i configuration sets (DNS + SES) — **bez** zmiany From w aplikacjach.
5. Skonfigurować event publishing do logów (najpierw odczyt, bez automatycznych akcji).
6. Ustawić `AWS_DEFAULT_REGION=eu-central-1` w środowiskach docelowych (przy wdrożeniu mailera).

### Faza C — kanał systemowy (Laravel + SES)

7. ~~W Laravel (pnedu, potem adm): mailer systemowy → SES, From `info@system.pnedu.pl`, Reply-To `kontakt@pnedu.pl`.~~ **Zrobione** (2026-06).
8. ~~Przepiąć maile systemowe (sekcja 8).~~ **Zrobione w kodzie**; na produkcji adm — potwierdzić wszystkie typy (zaświadczenia, dostęp, data completion, provision, reset).
9. Monitorować bounce/complaint na `system.pnedu.pl` przez min. 2–4 tygodnie stabilnej wysyłki transakcyjnej (wymaga etapu 5 — event publishing).

### Faza D — Sendy news

10. Ustawić From `szkolenia@news.pnedu.pl` w Sendy; rozgrzewać wolumen; listy NEWS (sekcja 8.3).
11. Nie wysyłać z news wiadomości, które należą do SYS.

### Faza E — Sendy TIK (ostrożnie)

12. Nowy From `webinary@tik.pnedu.pl`; małe segmenty; utrzymanie rozpoznawalności Waldemara / cyklu TIK.
13. Równoległa obserwacja starego nadawcy `zdalna-lekcja.pl`; wygaszenie dopiero po stabilnych metrykach.
14. **Nie** migrować jednorazowo 62k kontaktów.

### Faza F — treści i domena (osobno)

15. Program „Future domain migration” (sekcja 4) — nie blokować faz B–E, ale nie mieszać z nimi w jednym deployu.

---

## Załącznik A — konfiguracja Laravel (stan wdrożony)

### Wspólny model systemowy

| Element | Wartość |
|---------|---------|
| From | `info@system.pnedu.pl` / „Platforma Nowoczesnej Edukacji” |
| Reply-To | `kontakt@pnedu.pl` |
| Region SES | `eu-central-1` |
| Brand w mailach | `MAIL_BRAND_PUBLIC_URL=https://pnedu.pl`, `MAIL_BRAND_PUBLIC_LABEL=www.pnedu.pl` |

### adm.pnedu.pl (`pneadm`)

- `config/mail.php`: sekcje `system`, `brand`; mailery `ses`, `log`, `smtp` (smtp tylko legacy / nieużywany dla klientów); **brak** mailera `data_completion`.
- Trait `App\Mail\Concerns\UsesSystemMailSettings` — wszystkie Mailable (`withSystemMailSettings()` → `mail.system.mailer` + From/Reply-To).
- Trait `App\Notifications\Concerns\UsesSystemMailSettings` — Notification klienckie (`configureSystemMail()`).
- Zależność: `aws/aws-sdk-php` ^3.383; `composer.json` → `config.platform.php` = `8.3.27`.
- Lokalnie: `MAIL_MAILER=log`, `MAIL_SYSTEM_MAILER=log`.
- Produkcja: `MAIL_MAILER=ses`, `MAIL_SYSTEM_MAILER=ses`, `AWS_*`.
- Kolejka: `QUEUE_CONNECTION=database`; joby `SendCertificateLinkEmailJob`, `SendCourseAccessEmailJob` — po `config:cache` wymagany `queue:restart`.
- Log wysyłki: `CertificateEmailLog`.
- Testy: `tests/Feature/Mail/InstructorTrainingLinksMailTest.php`, `SystemMailConfigurationTest.php`.
- Commits referencyjne: `3fd8c62` (pilotaż SES + infrastruktura), `26237ca` (pełna migracja maili klienckich).

### pnedu.pl

- `config/mail.php`: sekcje `system`, `brand`; mailer `ses`; globalny From spójny z systemowym.
- Maile: `OrderNotificationMail`, `ContactFormMail`, `PaymentNotificationMail` — From/Reply-To z `mail.system.*`.
- Auth: `SystemVerifyEmail`, `SystemResetPassword` w modelu `User`.
- Lokalnie: `MAIL_MAILER=log`; produkcja: `MAIL_MAILER=ses`, `AWS_*`.
- Zależność: `aws/aws-sdk-php`; platform PHP 8.3.27 w `composer.json`.
- Testy: `OrderNotificationMailTest`, `SystemMailConfigurationTest`.
- Sendy: `config/sendy.php` — **bez zmian** w ramach strategii Laravel + SES.

### `.env` produkcyjny — minimalny zestaw (adm)

```env
MAIL_MAILER=ses
MAIL_SYSTEM_MAILER=ses
MAIL_FROM_ADDRESS=info@system.pnedu.pl
MAIL_SYSTEM_FROM_ADDRESS=info@system.pnedu.pl
MAIL_SYSTEM_REPLY_TO_ADDRESS=kontakt@pnedu.pl
AWS_DEFAULT_REGION=eu-central-1
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
```

Usunąć z produkcji adm: `MAIL_HOST`/`MAIL_USERNAME` (tekyon), `MAIL_DATA_COMPLETION_*`.

---

## Załącznik B — pliki reguł Cursor (propozycja, niewdrożone)

Po decyzji zespołu możliwy skrót w `.cursor/rules/email-architecture.mdc`:

- mapowanie kanał → subdomena → From
- zakazy z sekcji 11
- klasy Mailable → kanał docelowy (sekcja 8)

**Nie utworzono** bez explicit request.

---

*Dokument utrzymywany w repozytorium `pneadm`. Wersja skrócona dla frontu: `pnedu/docs/email-deliverability-strategy.md`.*
