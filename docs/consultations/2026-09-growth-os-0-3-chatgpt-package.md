# Pakiet konsultacyjny ChatGPT — PNE Growth OS 0.3

Data: 2026-09-29  
Status: pakiet do konsultacji z zewnętrznym ChatGPT  
Zakres: UX/proces produktu, bez kodowania przez konsultanta

## Jak Korzystać

Ten dokument można wkleić do zewnętrznego ChatGPT jako kontekst konsultacyjny.

Rola zewnętrznego ChatGPT:

- konsultant UX/produktu,
- może zadawać pytania Waldemarowi,
- może zadawać pytania techniczne Cursorowi,
- może sugerować kierunek, flow, statusy i ryzyka,
- **nie koduje**,
- **nie otrzymuje danych logowania, `.env`, sekretów ani dostępu produkcyjnego**.

Kod implementuje wyłącznie Cursor AI w lokalnym repozytorium.

## Kontekst Produktowy

Budowany moduł roboczy: **PNE Growth OS / PNE Rozwój** w `adm.pnedu.pl`.

Cel długofalowy:

- planowanie webinarów i innych treści,
- budowanie marki osobistej Waldemara Grabowskiego,
- wzmacnianie marki PNE,
- wykorzystanie ekspertów PNE,
- przygotowywanie treści,
- dystrybucja,
- relacje z odbiorcami,
- analityka,
- opcjonalnie sprzedaż.

Obecny etap nie jest pełnym Growth OS. Obecny etap to **prosty prototyp UX jednego przypadku: przygotowanie webinaru TIK od zera**.

## Zasada UX

System ma działać jak inteligentny producent/asystent.

Nie:

```text
AI coś przygotowało, teraz kliknij zatwierdź.
```

Tak:

```text
Planuję webinar.
System prowadzi mnie krok po kroku.
AI pomaga na każdym etapie.
Ja kontroluję kierunek i podejmuję decyzje.
```

Najważniejsze pytanie interfejsu:

```text
Co powinienem zrobić teraz?
```

## Granice Prototypu

Nie ma:

- migracji DB,
- prawdziwego OpenAI API,
- YouTube API,
- Sendy API,
- Meta API,
- Canva API,
- publikacji,
- wysyłek,
- jobów,
- side effectów.

Wszystko działa tylko w sesji HTTP. AI jest symulowane.

## Aktualna Nawigacja

Menu **PNE Rozwój**:

- Dzisiaj
- Projekty
- Pomysły
- Inbox

Nie ma jeszcze:

- Radar,
- Experts,
- Audience,
- Segments,
- Products,
- Analytics,
- Automation.

## Aktualny Flow Użytkownika

### 1. Dzisiaj

Ekran operacyjny.

Jeśli nie ma projektu:

- pokazuje CTA **Zaplanuj webinar TIK**.

Jeśli projekt istnieje:

- pokazuje projekt TIK,
- datę/godzinę live,
- temat,
- prowadzącego,
- czas do live,
- liczbę krytycznych elementów niegotowych,
- CTA **Kontynuuj przygotowanie**.

Na ekranie jest też:

- licznik decyzji w Inboxie,
- licznik pomysłów.

### 2. Zaplanuj webinar TIK

Krótki formularz:

- Co planujemy? domyślnie `Webinar TIK`,
- Data live,
- Godzina,
- Prowadzący, domyślnie `Waldemar Grabowski`,
- Cel:
  - edukacja / wartość dla odbiorców,
  - budowanie marki,
  - rozwój społeczności,
  - pozyskanie nowych odbiorców,
  - wsparcie produktu,
  - jeszcze nie wiem,
- Temat.

Obok formularza są symulowane sugestie AI, np.:

- Canva AI w pracy nauczyciela,
- AI bezpiecznie na lekcji i radzie pedagogicznej,
- TIK, który oszczędza czas przed końcem semestru.

CTA:

```text
Utwórz projekt webinaru
```

### 3. Projekt Webinaru

Główny workspace.

Na górze:

- status projektu,
- data live,
- prowadzący,
- czas do webinaru,
- krytyczne braki,
- jedno główne CTA: **Najważniejszy następny krok**.

Etapy:

- Pomysł i kierunek,
- Koncepcja,
- Materiały,
- Przygotowanie,
- LIVE,
- Follow-up.

### 4. Pomysł i Kierunek

Zawiera:

- Temat,
- Dlaczego teraz,
- Dla kogo,
- Jaki problem rozwiązujemy,
- Co uczestnik ma wynieść,
- Czy chcemy coś później sprzedawać.

Obecnie można zatwierdzić kierunek.

Problem do konsultacji:

- brakuje cofnięcia zatwierdzenia,
- brakuje edycji,
- brakuje prośby do AI o zmianę / rozbudowę.

### 5. Koncepcja Webinaru

Zawiera:

- Tytuł webinaru,
- Podtytuł,
- Główna obietnica,
- 3-5 głównych punktów,
- Plan webinaru,
- Główne CTA,
- Materiał dodatkowy / lead magnet,
- Czy webinar prowadzi do kolejnego produktu.

Obecnie można kliknąć **Koncepcja gotowa**.

Problem do konsultacji:

- to jest za bardzo jednorazowe zatwierdzenie,
- docelowo użytkownik powinien móc pracować nad koncepcją: edytować, prosić AI o wariant, przyjąć/odrzucić propozycję, cofnąć status.

### 6. Materiały

Lista materiałów:

- Opis YouTube,
- Grafika główna,
- Post Facebook,
- Mailing główny,
- Mailing przypominający,
- Formularz zapisu / landing,
- Scenariusz prowadzącego,
- Materiał dla uczestnika,
- Intro OBS,
- Follow-up.

Statusy materiałów:

- Nie rozpoczęto,
- Draft,
- Do sprawdzenia,
- Zatwierdzone,
- Opublikowane / zaplanowane.

Każdy materiał jest klikalny.

Widok materiału pokazuje:

- typ materiału,
- status,
- draft/sugestię AI,
- informację, że nic nie jest publikowane,
- formularz zmiany statusu w sesji.

### 7. Checklista Czasowa

Przykładowe grupy:

- T-7 dni: temat, kierunek, koncepcja,
- T-5 dni: YouTube Live, grafika, landing,
- T-3 dni: mailing główny, Facebook,
- T-1 dzień: scenariusz, materiały, test techniczny,
- T-3 godziny: przypomnienie, social reminder,
- LIVE: webinar, formularz zaświadczenia,
- T+1: nagranie, transkrypcja, zagadnienia do zaświadczenia,
- T+2 / T+3: follow-up, content repurposing.

### 8. Inbox

Inbox nie jest głównym flow.

Odpowiada tylko na pytanie:

```text
Co wymaga mojej decyzji?
```

Kliknięcie elementu Inboxa prowadzi do konkretnego miejsca w projekcie, np.:

- Pomysł i kierunek webinaru,
- Koncepcja webinaru,
- materiał w statusie Do sprawdzenia.

## Mapa Kodu Dla Konsultanta

Konsultant nie musi czytać całego repo. Jeśli potrzebuje kodu, wystarczą te pliki:

- `app/Support/GrowthOS/DemoTikWebinarProject.php`  
  Sesyjny model prototypu: projekt, etapy, materiały, statusy, Inbox, next action, checklista.

- `app/Http/Controllers/GrowthOS/DashboardController.php`  
  Dane dla ekranu Dzisiaj.

- `app/Http/Controllers/GrowthOS/ProjectController.php`  
  Lista projektów, formularz startowy, tworzenie projektu w sesji, workspace, etap, materiał, status materiału.

- `app/Http/Controllers/GrowthOS/ApprovalController.php`  
  Pomocniczy Inbox.

- `app/Http/Controllers/GrowthOS/IdeaController.php`  
  Minimalne Pomysły.

- `routes/web.php`  
  Grupa tras `/growth`.

- `resources/views/growth-os/dashboard.blade.php`  
  Ekran Dzisiaj.

- `resources/views/growth-os/projects/create.blade.php`  
  Formularz Zaplanuj webinar TIK.

- `resources/views/growth-os/projects/show.blade.php`  
  Główny workspace projektu.

- `resources/views/growth-os/projects/material.blade.php`  
  Widok materiału.

- `resources/views/growth-os/approvals/index.blade.php`  
  Inbox.

- `resources/views/growth-os/ideas/index.blade.php`  
  Pomysły.

- `resources/views/layouts/navigation.blade.php`  
  Menu PNE Rozwój.

- `tests/Feature/GrowthOS/GrowthOsAccessTest.php`  
  Dostęp, feature flag, menu.

- `tests/Feature/GrowthOS/GrowthOsStage02PrototypeTest.php`  
  Testy flow 0.3; nazwa pliku została technicznie po etapie 0.2.

## Aktualne Trasy

```text
GET  /growth
GET  /growth/projects
GET  /growth/projects/create
POST /growth/projects
GET  /growth/projects/{project}
POST /growth/projects/{project}/steps/{step}
GET  /growth/projects/{project}/materials/{material}
POST /growth/projects/{project}/materials/{material}/status
GET  /growth/ideas
GET  /growth/inbox
```

## Aktualne Pytanie Produktowe

Obecny prototyp pokazuje proces, ale etapy są jeszcze za bardzo jednorazowe:

```text
Zatwierdź kierunek
Koncepcja gotowa
```

Brakuje prawdziwej pracy nad treścią:

- edycji ręcznej,
- cofnięcia zatwierdzenia,
- prośby do AI o zmianę,
- wariantów AI,
- historii wersji,
- statusu „do dopracowania”.

## Pytania Do ChatGPT

1. Jak powinien wyglądać najlepszy UX pracy nad etapem **Koncepcja webinaru**?
2. Jak dodać edycję ręczną bez robienia ciężkiego formularza CRM?
3. Jak dodać **Poproś AI o zmianę**, skoro AI ma być asystentem, a nie decydentem?
4. Jak zaprojektować cofnięcie zatwierdzenia etapu?
5. Jakie statusy powinien mieć etap: np. Draft, Do dopracowania, Gotowe, Zatwierdzone?
6. Czy „Zastosuj propozycję AI” powinno nadpisywać treść, tworzyć wariant, czy robić wersję roboczą?
7. Jak prosto pokazać historię wersji w prototypie sesyjnym?
8. Jak uniknąć sytuacji, że workspace stanie się ciężkim CRM-em albo systemem ticketowym?
9. Jaki powinien być najmniejszy następny etap prototypu 0.3.1?
10. Co koniecznie przetestować z Waldemarem przed migracjami DB?

## Prośba Do ChatGPT

Odpowiedz jako konsultant UX/produktu.

Nie pisz kodu Laravel.

Podaj:

- rekomendowany flow,
- układ akcji na ekranie,
- statusy,
- minimalny zakres etapu 0.3.1,
- czego nie robić jeszcze teraz,
- pytania do Waldemara,
- pytania techniczne do Cursora.

Założenie: implementację wykona wyłącznie Cursor AI po decyzji Waldemara.
