# PNE Operations — roadmapa

Status: propozycja po analizie kodu (2026-10-17). Kolejność konsultanta jest co do zasady dobra. Jedna zmiana: pełny portal trenera nie blokuje negocjacji w ADM.

## Zasada cięcia

Każdy etap da się wdrożyć i użyć bez następnego. Nie ma big-banga. Nie zaczynamy etapu bez akceptacji Waldemara.

## Etapy

### 0. Analiza i dokumentacja — ten etap

Kanon w `docs/operations-os/`. Bez tabel i bez UI.

### 1. Zapytanie staje się sprawą

Formularz pnedu (ogólny i z oferty) zapisuje rekord w `pneadm`, potem wysyła dotychczasowy e-mail jako powiadomienie.

W ADM: lista i karta. Na karcie: status, opiekun (`pneadm.users`), następne działanie (tekst + termin), notatka i historia zdarzeń. Ręczny wpis sprawy bez formularza — w tym samym etapie, bo telefon i tak omija stronę.

Poza etapem: cena, terminy negocjacji, trener jako aktor, tworzenie `courses`, portal.

### 2. Negocjacja po stronie PNE

Nadal tylko ADM. PNE zapisuje wskazanego trenera, propozycje terminów, ustalenia ceny i odpowiedź szkoły. Zakończenie sprawy. Przy sukcesie człowiek otwiera istniejący formularz „Utwórz szkolenie z oferty” albo wiąże już istniejące `courses`.

To oddaje największą ulgę pamięci zanim powstanie konto trenera. Waldemar i tak dziś przenosi odpowiedź trenera z maila.

### 3. Cienkie konto trenera

Warunek przed pokazaniem trenerowi czegokolwiek. Osobna zdolność na pnedu.pl, powiązanie jawne z `instructors`, najmniej uprawnień. Bez kalendarza, materiałów, ankiet i propozycji tematów — to osobny, większy kawałek (etap 5 poniżej).

Bez tego etapu 4 nie startuje.

### 4. Trener odpowiada w sprawie

PNE klika „zapytaj trenera”. Trener widzi tylko tę sprawę i odsyła: zainteresowanie, wynagrodzenie, terminy, komentarz. Wpis ląduje w historii tej samej sprawy.

### 5. Reszta portalu trenera

Kalendarz własnych szkoleń, linki do materiałów, wyniki własnych ankiet, propozycje nowych szkoleń. Zgodnie z [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md), po osobnych decyzjach (moderacja materiałów, kto zakłada konto).

### 6. Mechanizm procesu — dopiero z doświadczenia

`ProcessTemplate`, instancja, kroki, assignee, termin, link do instrukcji, historia. Instancja wskazuje istniejący obiekt (`subject_type` + `subject_id`): najpierw sprawę szkolenia zamkniętego. Ten sam zaczep obejmuje później `debt_cases`, bez przenoszenia windykacji do nowej tabeli i bez obowiązku procesu przy każdej fakturze. Growth OS zostaje osobną domeną treści.

### 7. Drugi proces: webinar otwarty

Ten sam mechanizm, inny szablon. Checklisty Growth OS zostają przy przygotowaniu treści. Operations przejmuje organizację konkretnego webinaru jako szkolenia, jeśli Waldemar tak zdecyduje po etapie 6. Nie scalamy domen „przy okazji”.

### 8. Kolejne procesy firmy

Faktury, obsługa po szkoleniu, inne — każdy jako szablon, gdy ból jest powtarzalny.

## Co zmieniono względem propozycji konsultanta

Konsultant miał: (1) zapis sprawy, (2) negocjacja w ADM, (3) pełny portal, (4) trener w sprawie, (5) silnik procesów.

Rekomendacja: zostawić 1 i 2, rozciąć portal. Cienkie konto jest warunkiem wpuszczenia trenera do sprawy. Kalendarz, materiały i ankiety nie są warunkiem pierwszej ulgi operacyjnej i opóźniłyby pętlę szkoła–PNE–trener.

Silnik procesów zostaje na końcu, po jednym przerobionym procesie ręcznym. To jest zgodne z YAGNI i z tym, że w kodzie nie ma nic, na czym dałoby się bezpiecznie „dokleić BPM”.
