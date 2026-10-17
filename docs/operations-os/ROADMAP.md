# PNE Operations — roadmapa

Status: kolejność po konsultacji 2026-10-17. Etap 1 jest przyjęty (DEC-OPS-002, DEC-OPS-009, DEC-OPS-010). Dalsza kolejność etapów 2–8 pozostaje propozycją (DEC-OPS-005).

## Zasada cięcia

Każdy etap da się wdrożyć i użyć bez następnego. Nie ma big-banga. Nie zaczynamy etapu bez akceptacji Waldemara.

## Etapy

### 0. Analiza i dokumentacja

Kanon w `docs/operations-os/`. Bez tabel i bez UI. Konsultacja czterech pytań jest zamknięta.

### 1. Zapytanie staje się sprawą szkolenia zamkniętego

Formularz pnedu (ogólny i z oferty) zapisuje konkretną sprawę w `pneadm`, potem wysyła dotychczasowy e-mail jako powiadomienie. Bez pola `type` i bez wspólnej tabeli na inne procesy.

W ADM: lista i karta. Na karcie: krótki status (nie krok negocjacji), opiekun (`pneadm.users`), następne działanie (tekst + termin), notatka i historia zdarzeń. Ręczny wpis sprawy bez formularza — w tym samym etapie, bo telefon i tak omija stronę.

Poza etapem: cena, terminy negocjacji, trener jako aktor, brief, tworzenie `courses`, portal, silnik procesów.

### 2. Negocjacja po stronie PNE

Nadal tylko ADM. PNE zapisuje wskazanego trenera, propozycje terminów, ustalenia ceny i odpowiedź szkoły. Zakończenie sprawy. Przy sukcesie człowiek otwiera istniejący formularz „Utwórz szkolenie z oferty” albo wiąże już istniejące `courses`.

To oddaje największą ulgę pamięci zanim powstanie konto trenera. Waldemar i tak dziś przenosi odpowiedź trenera z maila.

### 3. Powiązanie konta trenera

Warunek przed pokazaniem trenerowi czegokolwiek. Jawne powiązanie konta pnedu z rekordem `instructors`, włączane i odbierane przez PNE (DEC-OPS-006). Bez flagi „jest trenerem” jako jedynego uprawnienia. Bez kalendarza, materiałów, ankiet i propozycji tematów — to osobny, większy kawałek (etap 5 poniżej).

Bez tego etapu 4 nie startuje.

### 4. Trener odpowiada na brief

PNE porządkuje potrzeby i przekazuje brief, nie kartę sprawy (DEC-OPS-007). Trener odsyła: zainteresowanie, wynagrodzenie, terminy, komentarz. Wpis ląduje w historii tej samej sprawy po stronie ADM. Surowe pole `message` z formularza nie idzie do trenera.

### 5. Reszta portalu trenera

Kalendarz własnych szkoleń, linki do materiałów, wyniki własnych ankiet, propozycje nowych szkoleń. Zgodnie z [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md), po osobnych decyzjach (moderacja materiałów, kto zakłada konto).

### 6. Mechanizm procesu — dopiero z doświadczenia

`ProcessTemplate`, instancja, kroki, assignee, termin, link do instrukcji, historia — tylko jeśli codzienna praca na sprawach szkolenia zamkniętego pokaże, że sama karta już nie wystarcza (DEC-OPS-011). Instancja wskazuje istniejący obiekt (`subject_type` + `subject_id`): sprawę szkolenia zamkniętego, a nie nową wspólną tabelę spraw. Ten sam zaczep może później objąć `debt_cases`, bez przenoszenia windykacji i bez obowiązku procesu przy każdej fakturze. Growth OS zostaje osobną domeną treści. Webinar, jeśli dojdzie, jest osobnym obiektem albo osobnym wskazaniem, nie wartością `type` na sprawie szkolenia.

### 7. Drugi proces: webinar otwarty

Osobny obiekt albo wskazanie procesu, jeśli etap 6 w ogóle powstanie po praktyce. Nie jest wartością `type` na sprawie szkolenia zamkniętego. Checklisty Growth OS zostają przy przygotowaniu treści. Nie scalamy domen przy okazji.

### 8. Kolejne procesy firmy

Faktury, obsługa po szkoleniu, inne — każdy jako szablon, gdy ból jest powtarzalny.

## Co zmieniono względem propozycji konsultanta

Konsultant miał: (1) zapis sprawy, (2) negocjacja w ADM, (3) pełny portal, (4) trener w sprawie, (5) silnik procesów.

Rekomendacja: zostawić 1 i 2, rozciąć portal. Powiązanie konta jest warunkiem przekazania briefu, nie pełnej karty. Kalendarz, materiały i ankiety nie są warunkiem pierwszej ulgi operacyjnej.

Silnik procesów zostaje na końcu i tylko wtedy, gdy jeden ręczny przebieg spraw szkolenia zamkniętego pokaże powtarzalną robotę. W kodzie nie ma nic, na czym dałoby się bezpiecznie dokleić BPM.
