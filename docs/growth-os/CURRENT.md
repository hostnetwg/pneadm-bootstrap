# PNE Growth OS — Current State

Last updated: 2026-10-02 CEST<br>
Branch: main<br>
Commit: 108c3a4<br>
Stage: v0.2 — szkice AI opisu YouTube, posta Facebook i briefu grafiki, generator obrazu grafiki głównej, historia wersji materiałów<br>
Current blocker: none (produkcja: migracje v0.1 `Ran`, `/growth` działa — [runbook](../deploy/2026-09-pne-growth-os-stage-0-1-deploy.md))

## 1. Cel projektu

PNE Growth OS / PNE Rozwój to moduł w `adm.pnedu.pl`, który ma prowadzić właściciela krok po kroku przez planowanie i przygotowanie działań rozwojowych PNE: webinarów, treści, kampanii eksperckich, relacji i przyszłej analityki.

## Current UX snapshot

- Entry point: **PNE Rozwój → Zaplanuj webinar TIK**.
- Main workspace: **Projekt webinaru**.
- Primary UX principle: zawsze widoczny **Następny krok**.
- Supporting views: **Dzisiaj / Projekty / Pomysły / Inbox**.
- Concept stage: edycja ręczna, cofnięcie zatwierdzenia, opcjonalna propozycja OpenAI jako wariant do przyjęcia/odrzucenia.
- Materiał „Opis YouTube”: „Poproś AI o szkic” po zatwierdzeniu kierunku i koncepcji, obecny szkic obok propozycji, Zastosuj / Odrzuć.
- Materiał „Post Facebook”: ten sam wzorzec, plus checkbox hashtagów; AI korzysta z opisu YouTube tylko zatwierdzonego i wstawia `[LINK DO ZAPISU]` zamiast linku.
- Materiał „Grafika główna”: „Poproś AI o szkic” daje tekstowy brief (nagłówek, termin z aplikacji, kierunek wizualny i opcjonalne elementy z checkboxami) dla formatów 16:9 i kwadrat, z zatwierdzonym opisem YouTube jako źródłem. Pod szkicem „Generator obrazu”: format, opis obrazu (wstępnie z briefu), checkbox „Dodaj nagłówek i termin na obrazie”, „Generuj obraz”, licznik dziennego limitu z „Zresetuj limit” i galeria z Pobierz / Wybierz jako grafikę główną / Usuń, a przy obrazie poziomym „Utwórz wersję kwadratową”.
- Każdy materiał: status „Nie dotyczy” wyłącza go w tym projekcie (wyszarzony na liście, bez wpływu na następny krok).
- Każdy materiał: „Historia wersji” pod szkicem (ostatnie 20 zmian treści), podgląd w modalu i „Przywróć tę wersję”.

## 2. Aktualny etap

Etap v0.2. Pierwszy wycinek, szkic AI dla materiału `youtube-description` (DEC-024), Waldemar zweryfikował 2026-10-01, z prawdziwym AI i w symulacji. Drugi wycinek, szkic AI dla materiału `facebook-post` (DEC-026), Waldemar zweryfikował 2026-10-01. Historia wersji materiałów (DEC-027) działa na produkcji od 2026-10-01: migracja wykonana, Waldemar sprawdził ją ręcznie. Trzeci wycinek, brief grafiki głównej (DEC-028), oraz generator obrazu z briefem korzystającym z opisu YouTube (DEC-029), z modelem `gpt-image-2` i wersją kwadratową z obrazu poziomego (DEC-030), są w repozytorium od 2026-10-02. Waldemar testował generator lokalnie z prawdziwym OpenAI. Na produkcji czekają na migrację `growth_artifact_images`. Model danych v0.1 działa na produkcji. Propozycje AI zostają w przeglądarce, w której powstały.

## 3. Co już działa

- Feature flag `PNE_GROWTH_OS_ENABLED` i dostęp tymczasowo tylko dla `super_admin`.
- Menu **PNE Rozwój**: Dzisiaj, Projekty, Pomysły, Inbox.
- Formularz **Zaplanuj webinar TIK**.
- Sesyjny projekt webinaru (`DemoTikWebinarProject`).
- Modele Eloquent v0.1 i relacje: właściciel, instruktor, materiał, zadanie, decyzja. Workspace używa kampanii, kierunku, koncepcji, decyzji przy kierunku i koncepcji, 9 zadań operacyjnych i 10 materiałów roboczych.
- Testy integralności: unikalny klucz materiału w kampanii, puste relacje, usuwanie kampanii razem z dziećmi, czyszczenie opcjonalnych powiązań.
- Utworzenie projektu zapisuje `growth_campaigns` razem z `host_name`. „Zapisz prowadzącego” zmienia to imię. „Zapisz kierunek” i zatwierdzenie kierunku zapisują artifact `direction`. Ręczny zapis koncepcji i „Zastosuj” zapisują jeden artifact `concept`. „Odrzuć” i sama propozycja AI nie zapisują koncepcji.
- Workspace projektu z etapami, materiałami, checklistą czasową i jednym głównym CTA.
- Checklista pokazuje 9 zadań operacyjnych z checkboxem. Pozostałe punkty osi czasu są informacją i nie mają checkboxa.
- Dziesięć materiałów ma edytowalny szkic i status. „Zapisz materiał” zapisuje artifact `material`. Samo otwarcie ekranu nie tworzy wiersza.
- Status „Nie dotyczy” (DEC-031, `SKIPPED`, w bazie `archived`) wyłącza materiał w tym jednym projekcie. Materiał zostaje na swoim miejscu na liście, wyszarzony. Nie liczy się do „Następnego kroku” ani elementów krytycznych. Szkic jest tylko do odczytu, a „Poproś AI o szkic”, „Zastosuj”, przywracanie wersji i generator obrazu są zablokowane. Wyłączony opis YouTube nie jest źródłem dla posta i briefu grafiki. Zmiana statusu na inny włącza materiał ze szkicem sprzed wyłączenia.
- v0.2: brief AI grafiki głównej (DEC-028), trzeci profil `material_draft`: osobne pola, termin z dniem tygodnia liczony przez aplikację, opcjonalne elementy z checkboxami, formaty 16:9 i kwadrat.
- Historia wersji materiałów (DEC-027): tabela `growth_artifact_versions`, nowa wersja przy każdej zmianie treści („Zapisz materiał”, „Zastosuj” szkicu AI, przywrócenie), bez wersji przy samej zmianie statusu, ostatnie 20 na materiał. Materiał sprzed wdrożenia dostaje przy pierwszej zmianie wersję „Stan sprzed historii”. Przywrócenie po potwierdzeniu w modalu zapisuje stary tekst jako nową wersję ze statusem Draft. Wersja powtarzająca wcześniejszy tekst ma znaczek „ten sam tekst co vN”.
- Etap **Kierunek**: edycja pięciu pól, zatwierdzenie i cofnięcie. Stała podpowiedź AI nie jest zapisywana.
- Etap **Koncepcja**: edycja ręczna, status Do dopracowania / Gotowe, cofnięcie zatwierdzenia.
- **Poproś AI o zmianę**: przy wyłączonej fladze działa symulacja lokalna, a przy włączonej — ustrukturyzowana propozycja OpenAI.
- Propozycja AI nigdy nie nadpisuje bieżącej koncepcji aż do jawnego „Zastosuj”; można ją odrzucić.
- **Poproś AI o szkic** („Opis YouTube”; „Post Facebook” opisuje punkt niżej): wymaga zatwierdzonego kierunku i koncepcji. Zadanie `material_draft`, prompt `material_youtube_description_v2`, schema `material_youtube_description_schema_v1`. Checkbox „Dodaj emotikony do opisu” (domyślnie włączony) wysyła `style.emojis`. Opcjonalna „Dodatkowa instrukcja dla AI” (do 1000 znaków, blokada danych osobowych) wysyła `instruction`; zasady promptu mają pierwszeństwo. Przy wyłączonej fladze — symulacja lokalna z tym samym UX. Propozycja w sesji, obok obecnego szkicu. „Zastosuj” sprawdza, czy kierunek, koncepcja, szkic i prowadzący się nie zmienili, potem zapisuje szkic, status `DRAFT` i decyzję `material_ai_apply`. „Odrzuć” zapisuje tylko decyzję `material_ai_reject`. Sześć materiałów nie ma AI (mailing przypominający, landing, scenariusz, materiał dla uczestnika, intro OBS, follow-up).
- **Poproś AI o szkic** dla „Post Facebook” (DEC-026): prompt `material_facebook_post_v1`, ten sam UX, dodatkowo checkbox hashtagów. AI dostaje opis YouTube tylko ze statusem Zatwierdzone lub Opublikowane i wstawia `[LINK DO ZAPISU]`. Propozycje są w sesji osobno dla każdego materiału.
- **Poproś AI o szkic** dla „Grafika główna” (DEC-028, DEC-029): prompt `material_graphic_brief_v2`. AI zwraca osobne pola briefu, aplikacja składa z nich szkic z etykietami i sama wstawia termin z dniem tygodnia oraz prowadzącego. Checkboxy: podtytuł, prowadzący, wezwanie do działania, opis obrazu dla AI, tekst alternatywny (domyślnie włączone). AI dostaje zatwierdzony opis YouTube, jeśli istnieje.
- **Poproś AI o szkic** dla „Mailing główny” (DEC-032): prompt `material_main_mail_v1`. AI zwraca 3 tematy, preheader i treść, a aplikacja składa szkic z etykietami („Temat:”, „Inne propozycje tematu:”, „Preheader:”). Zwrot „Dzień dobry,” i „Państwo”, podpis prowadzącego i „Zespół PNE”, `[LINK DO ZAPISU]` zamiast linku, bez stopki prawnej. Przełącznik „Długość maila” (krótki 150–250 słów albo dłuższy 300–450 słów), emotikony domyślnie wyłączone, dodatkowa instrukcja. AI dostaje zatwierdzony opis YouTube, jeśli istnieje. Szkic jest edytowany w osobnych polach Temat, Preheader i Treść (zapisywany jako jeden tekst z etykietami), z listą „Propozycje tematu od AI” (wszystkie 3 tematy, także pierwotny, z przyciskiem „Użyj” i oznaczeniem „w polu”), licznikami znaków i przyciskiem „Kopiuj kod HTML preheadera” do Sendy. Bez wysyłki i Sendy.
- **Generator obrazu** dla „Grafika główna” (DEC-029, DEC-030): OpenAI `gpt-image-2`, jakość `medium`, jeden obraz w formacie poziomym (natywnie 16:9, 1920×1080 bez przycinania) albo kwadratowym (1080×1080). Przy obrazie poziomym jest „Utwórz wersję kwadratową”: te same elementy przekomponowane do kwadratu przez edycję obrazu w OpenAI, z tymi samymi napisami co oryginał. Generator pokazuje dzisiejsze zużycie limitu i ma przycisk „Zresetuj limit” (modal, wpis w logu). Aplikacja dokleja do opisu zasady bez tekstu, logotypów i rozpoznawalnych osób. Z checkboxem dokleja nagłówek z briefu i termin. Pliki JPEG są prywatne (`storage/app/private/growth-os/images`), tabela `growth_artifact_images`, galeria 10 ostatnich (wybrana grafika zostaje zawsze), limit 10 obrazów dziennie, osobny obwód awaryjny, log bez opisu obrazu. Przy wyłączonej fladze powstaje obraz zastępczy bez OpenAI.
- Osobna, domyślnie wyłączona flaga `GROWTH_AI_ENABLED`; prawdziwe AI jest dostępne tylko dla `super_admin`.
- Provider i model są konfigurowane centralnie i widoczne w UI; logika Growth OS korzysta z abstrakcji providera.
- Walidacja structured output, allowlisty danych (koncepcja; opis YouTube z `host_name` — DEC-025; post Facebook dodatkowo z zatwierdzonym opisem YouTube — DEC-026; poza tym bez innych materiałów), blokada e-maili/telefonów/sekretów i linków spoza wejścia, timeout, jeden retry, limity wywołań i prosty circuit breaker. Limit dzienny i circuit breaker są wspólne dla obu zadań.
- Osobny log techniczny zawiera tylko metadane wywołania (w tym `task_type`: `concept_revision`, `material_draft` albo `material_image`) — bez promptu, odpowiedzi, treści, PII i sekretów.
- Prosta historia wersji koncepcji w sesji (do 5 pozycji).
- Pozostałe pomysły i sugestie AI są nadal symulowane.
- Inbox prowadzi do miejsca w projekcie, nie jest głównym flow.

Do tej sekcji wpisujemy wyłącznie rzeczy faktycznie istniejące w aktualnym kodzie/prototypie. Wizja, planowane API, przyszłe modele i pomysły konsultacyjne należą do `vision.md` albo `roadmap.md`.

## 4. Aktualny flow UX

```text
Zaplanuj TIK
→ Pomysł i kierunek
→ Koncepcja (edycja / AI / zatwierdź / cofnij)
→ Materiały
→ Przygotowanie
→ LIVE
→ Follow-up
```

## 5. Najważniejsze decyzje

- Start UX to **Zaplanuj webinar TIK**, nie abstrakcyjny Topic.
- Projekt webinaru jest głównym workspace.
- Inbox jest pomocniczy.
- AI draftuje i sugeruje; człowiek zatwierdza.
- Propozycja AI dla koncepcji tworzy **wariant**, a nie nadpisuje od razu.
- OpenAI jest pierwszym providerem pilotażu, ale kod domenowy nie zależy bezpośrednio od jego API.
- Do AI trafia wyłącznie allowlista pól koncepcji; PII, dane klientów, zamówień i płatności są zabronione.
- Na górze projektu zawsze ma być widoczny najważniejszy następny krok.
- Nie budujemy dużego dashboardu ani pełnej platformy na zapas.

## 6. Czego świadomie jeszcze NIE robimy

- Prototyp nie zapisuje jeszcze propozycji AI do bazy.
- Brak zapisu wywołań AI do DB; propozycja pozostaje w sesji HTTP.
- Brak Anthropic, Gemini, OpenRouter, automatycznego routingu modeli i fallbacku między providerami.
- Brak YouTube API.
- Brak Sendy API.
- Brak Meta / Canva API.
- Brak publikacji, wysyłek, jobów i biznesowych side effectów.
- Propozycje AI działają w sesji HTTP. Poza sesją zostaje kampania, prowadzący, zapisany kierunek, zapisana koncepcja, decyzje przy kierunku, koncepcji i szkicach AI materiałów, 9 zadań operacyjnych, 10 materiałów (po jawnym zapisie) z historią do 20 wersji i techniczny log metadanych AI.
- Brak AI poza etapem Koncepcja oraz materiałami „Opis YouTube”, „Post Facebook”, „Grafika główna” (brief i obraz) i „Mailing główny”. Jedynym innym materiałem w kontekście AI jest zatwierdzony opis YouTube dla posta, briefu grafiki i mailingu.
- Brak wysyłki maili i integracji z Sendy: mailing główny to tekst do skopiowania.
- Brak integracji z Canvą i innych dostawców obrazów niż OpenAI. Edycja obrazu służy tylko do wersji kwadratowej z obrazu poziomego (DEC-030). Brak dowolnej edycji, wariantów w jednym kliknięciu i obu formatów naraz (Waldemar ma Canva Pro i Education).
- Brak ostrzeżenia na zapisanym materiale, że kierunek lub koncepcja zmieniły się po jego przygotowaniu (opcjonalne w DEC-024, nie zrobione).
- `Topic` i `Expert` nie należą do v0.1. Ekspert wskazuje opcjonalnie istniejący `Instructor` przez `primary_instructor_id`.

## 7. Otwarte pytania

- Czy później dodać projekt w Canvie (Autofill) obok obrazu z OpenAI.
## 8. Następny krok

Ręczna weryfikacja briefu grafiki i generatora obrazu przez Waldemara: brief z zatwierdzonym opisem YouTube i bez niego, obraz poziomy i kwadratowy, z napisem i bez, wersja kwadratowa z obrazu poziomego, wybór i usuwanie w galerii. Na produkcji: backup, migracja `growth_artifact_images`, smoke z runbooka (kroki 1–7) i sprawdzenie, czy konto OpenAI ma dostęp do `gpt-image-2`. Ręczna weryfikacja szkicu AI mailingu głównego (DEC-032): krótki i dłuższy, z opisem YouTube i bez. Potem kolejne materiały (mailing przypominający, landing) albo zapis propozycji AI w bazie. Propozycje AI zostają w sesji. Zadania operacyjne nie sterują głównym CTA.

## 9. Ostatnie zmiany

- Szkic AI mailingu głównego (DEC-032): czwarty profil `material_draft` (`main_mail_v1`), osobne pola tematu, preheadera i treści, przełącznik długości, bez migracji.

- Status materiału „Nie dotyczy” (DEC-031): wyłączenie materiału w projekcie bez migracji (`SKIPPED` w payloadzie, `archived` w `growth_artifacts.status`), blokada edycji i AI, pomijanie w następnym kroku i elementach krytycznych.

- Generator obrazu grafiki głównej (DEC-029): nowa tabela `growth_artifact_images` (migracja `2026_10_02_000000`), `OpenAiImageProvider`, `GrowthImageService`, prywatne pliki JPEG, galeria z wyborem grafiki głównej. Brief grafiki korzysta z zatwierdzonego opisu YouTube (prompt `material_graphic_brief_v2`). DEC-030: domyślny model `gpt-image-2` z natywnym 16:9 oraz wersja kwadratowa z obrazu poziomego (`POST /images/edits`, kolumny `source_image_id`, `overlay_headline`, `overlay_date`).

- Historia wersji materiałów (DEC-027): nowa tabela `growth_artifact_versions` (migracja `2026_10_01_230000`), ostatnie 20 zmian treści na materiał, podgląd i przywracanie w modalu Bootstrap.
- v0.2: szkic AI dla materiału `facebook-post` (DEC-026), drugi profil zadania `material_draft`. Korzysta tylko z zatwierdzonego opisu YouTube, wstawia `[LINK DO ZAPISU]`, ma opcje emotikon i hashtagów. Propozycje AI są w sesji osobno dla każdego materiału.
- v0.2: szkic AI dla materiału `youtube-description` (zadanie `material_draft`, DEC-024). `GrowthAiService` obsługuje dwa zadania przez mały kontrakt `GrowthAiTask`; `concept_revision` działa bez zmian.
- Zatwierdzone karty „Pomysł i kierunek” i „Koncepcja”, gotowe etapy przygotowania oraz blok z tytułem (gdy wszystkie etapy są gotowe) mają jasnozielone tło i zielony pasek z lewej.
- Statusy materiałów mają kolory: zielony „✓ Zatwierdzone” z zielonym paskiem na liście, żółty „Do sprawdzenia”, niebieski „✓ Opublikowane / zaplanowane”, szary Draft i jasny „Nie rozpoczęto”.
- Opis YouTube: opcja emotikon i dodatkowa instrukcja dla AI (prompt v2). AI dostaje prowadzącego (`host_name`) i może go wymienić (DEC-025); zmiana prowadzącego unieważnia starą propozycję. Ekrany projektu i materiału mają czytelniejszy wygląd: szare tło strony, białe karty, wyraźne obramowanie pól i pogrubione etykiety (`growth-os/partials/readable-styles.blade.php`).
- Dodano Zastosuj / Odrzuć szkicu jako decyzje `material_ai_apply` / `material_ai_reject`; nieaktualna propozycja (zmieniony kierunek, koncepcja lub szkic) jest odrzucana.

- Dodano opcjonalny OpenAI Responses API dla zadania `concept_revision`.
- Dodano abstrakcję providera i wersjonowany prompt/schema.
- Dodano structured output, walidację i bezpieczny zapis propozycji dopiero po pełnej walidacji.
- Dodano flagę, timeout, jeden retry, limity, circuit breaker i metadane kosztowe.
- Dodano AJAX: propozycja pojawia się bez przeładowania strony, spinner resetuje się, a dźwięk odtwarza się bezpośrednio po sukcesie.
- Zwiększono odporność parsera odpowiedzi OpenAI na różne formaty `output_text` i niepełne odpowiedzi.
- Prowadzący zapisuje się w `growth_campaigns.host_name` przy utworzeniu projektu i przy „Zapisz prowadzącego”. Nie tworzy powiązania z instruktorem.
- „Zapisz kierunek” utrwala pięć pól. „Zatwierdź kierunek” i cofnięcie zapisują decyzję `direction_approval`.
- „Zapisz materiał” utrwala status i szkic dziesięciu materiałów jako artifact `material`. Etykieta publikacji zostaje w payloadzie.
- Nowa kampania dostaje 9 zadań operacyjnych ze stabilnym `key`. Checkbox przełącza `todo` i `done`. Istniejące kampanie uzupełnia komenda `growth:seed-operational-tasks`.
- Zastosuj, Odrzuć, „Koncepcja gotowa” i cofnięcie zatwierdzenia zapisują decyzję człowieka.
- Zalogowanie bez sesji odtwarza ostatnią kampanię właściciela i artifact `concept`.
- Utworzenie projektu zapisuje kampanię, a zapis koncepcji i „Zastosuj” zapisują artifact `concept`.
- Dodano testy integralności v0.1: unikalność klucza, puste relacje, cascade kampanii, `nullOnDelete` i blokada usunięcia właściciela.
- Dodano modele `App\Models\GrowthOS` i relacje dla 4 tabel v0.1.
- Dodano migrację `2026_09_29_191500_create_growth_os_v0_1_tables` dla 4 tabel v0.1.
- Zmieniono zaakceptowany zakres modelu danych v0.1 na 4 tabele: GrowthCampaign, Artifact, Task i Decision.
- Topic i Expert przeniesiono poza pierwszą migrację; ekspert v0.1 opiera się o istniejący `Instructor`.
- Doprecyzowano kontrakt artifactu: `key`, `type`, `schema_version`, `payload`, bez osobnej tabeli wersji w v0.1.
- Zachowano symulację lokalną przy wyłączonym prawdziwym AI.
- Dodano automatyczne testy bez prawdziwych i płatnych requestów.
- Wcześniej: edycja, cofnięcie zatwierdzenia i historia koncepcji w sesji.
- Uporządkowano dokumentację `docs/growth-os/`.
- Wcześniej: sesyjny flow webinaru TIK od zera, menu Dzisiaj/Projekty/Pomysły/Inbox.

## 10. Pliki referencyjne

- [vision.md](./vision.md)
- [architecture.md](./architecture.md)
- [decisions.md](./decisions.md)
- [roadmap.md](./roadmap.md)
- [CONSULTING.md](./CONSULTING.md)
- [consultations/2026-09-growth-os-0-3-chatgpt-package.md](./consultations/2026-09-growth-os-0-3-chatgpt-package.md)
- [consultations/2026-09-29-growth-os-operational-tasks-decision.md](./consultations/2026-09-29-growth-os-operational-tasks-decision.md)
- [consultations/2026-09-29-growth-os-material-artifacts-decision.md](./consultations/2026-09-29-growth-os-material-artifacts-decision.md)
- [consultations/2026-09-29-growth-os-direction-decision.md](./consultations/2026-09-29-growth-os-direction-decision.md)
- [consultations/2026-09-29-growth-os-host-name-decision.md](./consultations/2026-09-29-growth-os-host-name-decision.md)
- [consultations/2026-10-01-growth-os-youtube-description-ai-draft.md](./consultations/2026-10-01-growth-os-youtube-description-ai-draft.md)

## Question for consultant

Current question: none
