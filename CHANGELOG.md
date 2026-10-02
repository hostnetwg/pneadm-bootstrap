# Historia zmian — adm.pnedu.pl

Krótka numeracja panelu. Szczegóły zawsze w `docs/`. Nowy numer wersji (np. 1.1) tylko po potwierdzeniu Waldemara. Hotfixy dopisujemy do bieżącej wersji.

## 1.3 — 2026-09-29

PNE Growth OS — pilotaż prawdziwego AI w etapie Koncepcja webinaru.

- Na [PNE Rozwój](/growth) w projekcie webinaru TIK etap **Koncepcja** może opcjonalnie korzystać z OpenAI do przygotowania propozycji zmiany / rozbudowy treści. AI tworzy tylko wariant — aktualna koncepcja zmienia się dopiero po kliknięciu **Zastosuj**.
- Propozycja pojawia się bez przeładowania strony, z animacją pracy i krótkim dźwiękiem po sukcesie. Błąd AI nie blokuje ręcznej edycji.
- Integracja ma osobną flagę `GROWTH_AI_ENABLED`, centralnie ustawiany model, walidację structured output, limity, timeout, retry i log metadanych bez treści koncepcji ani danych osobowych.
- Kanon: [PNE Growth OS Current](docs/growth-os/CURRENT.md), [AI](docs/pne-growth-os/06-AI.md)
- **Hotfix 2026-09-30:** wyczyszczenie numeru faktury na [zamówieniu FORM](/form-orders) usuwa także stare ID iFirma i dane KSeF. Po korekcie anulującej nowa faktura z czerwonego przycisku dostaje nowe ID/numer i jest ponownie wysyłana do KSeF. Kanon: [KSEF_FORM_ORDERS.md](docs/KSEF_FORM_ORDERS.md)
- **Hotfix 2026-09-30:** ograniczono liczbę requestów na szczegółach zamówienia: brak ciągłego pollingu KSeF, najwyżej 3 kontrole w widocznej karcie, a numer KSeF dociąga niezależna kolejka z coraz dłuższymi odstępami. Kanon: [KSEF_FORM_ORDERS.md](docs/KSEF_FORM_ORDERS.md)
- **Hotfix 2026-10-01:** na [PNE Rozwój](/growth), w materiale **Opis YouTube**, jest przycisk **Poproś AI o szkic** (po zatwierdzeniu kierunku i koncepcji). Propozycja pojawia się obok obecnego szkicu; materiał zmienia się dopiero po **Zastosuj** i wraca do statusu Draft. **Odrzuć** niczego nie zmienia. AI może wymienić prowadzącego (imię i nazwisko z projektu), ale nie dostaje innych materiałów. Opcja **Dodaj emotikony do opisu** jest domyślnie włączona; można też dopisać własną instrukcję dla AI. Ekrany projektu i materiału są czytelniejsze: wyraźne pola edycji na tle strony. Zatwierdzone materiały i gotowe etapy (kierunek, koncepcja) są wyróżnione na zielono. Przycisk **Poproś AI o szkic** jest też w materiale **Post Facebook**: AI korzysta z zatwierdzonego opisu YouTube, zamiast linku wstawia [LINK DO ZAPISU], a hashtagi można wyłączyć. Każdy materiał ma **Historię wersji** (ostatnie 20 zmian treści) z podglądem i przyciskiem **Przywróć tę wersję**. W materiale **Grafika główna** AI przygotowuje brief grafiki (nagłówek, termin z dniem tygodnia, kierunek wizualny i elementy do wyboru) dla formatów 16:9 i kwadrat, korzystając z zatwierdzonego opisu YouTube. Pod briefem jest **Generator obrazu** (OpenAI): format poziomy 1920×1080 albo kwadrat 1080×1080, opis obrazu z briefu do poprawienia, opcjonalny napis z nagłówkiem i terminem oraz galeria z podglądem, pobieraniem, wyborem grafiki głównej i usuwaniem. Obrazy robi najnowszy model `gpt-image-2`, a z obrazu poziomego można jednym kliknięciem **Utwórz wersję kwadratową** z tymi samymi elementami rozmieszczonymi na nowo, bez przycinania. Materiał, z którego nie korzystasz w danym webinarze, można wyłączyć statusem **Nie dotyczy**: zostaje wyszarzony na liście i nie wskazuje go „Następny krok”. Kanon: [PNE Growth OS Current](docs/growth-os/CURRENT.md), [AI](docs/pne-growth-os/06-AI.md)

## 1.2 — 2026-09-23

Dopisanie do nagrania po szkoleniu.

- Na [Liście szkoleń](/courses), w edycji szkolenia, jest karta **Dopisanie do nagrania**. Włączenie daje jeden link dla dyrektora. Formularz na pnedu.pl dopisuje uczestnika. Nowy adres od razu dostaje konto z hasłem z formularza. Gdy konto już jest, hasła nie zmieniamy — osoba loguje się dotychczasowym.
- Kanon: [RECORDING_ENROLLMENT.md](docs/RECORDING_ENROLLMENT.md)
- **Hotfix 2026-09-28:** na [zamówieniu FORM](/form-orders) przy roli JST / grupa VAT i identyfikatorze wewnętrznym (IDWew) nie trzeba już NIP szkoły. KSeF przyjmuje NIP **lub** IDWew. Kanon: [KSEF_FORM_ORDERS.md](docs/KSEF_FORM_ORDERS.md)
- **Hotfix 2026-09-28:** na [karcie użytkownika pnedu.pl](/admin/pnedu-users) można poprawić imię, nazwisko, e-mail, datę i miejsce urodzenia. Zmiana e-maila aktualizuje też uczestników szkoleń. Kanon: [PNEDU_USERS_ADMIN.md](docs/PNEDU_USERS_ADMIN.md)
- **Hotfix 2026-09-29:** PNE Growth OS Etap 0.3 — prototyp „Zaplanuj webinar TIK” od zera: Dzisiaj, Projekty, Pomysły, Inbox, workspace projektu, checklista czasowa i materiały w sesji. Kanon: [PNE Growth OS](docs/pne-growth-os/README.md)
- **Hotfix 2026-09-29:** PNE Growth OS Etap 0.2 — klikalny prototyp „Do zatwierdzenia” wokół demonstracyjnego webinaru TIK (bez bazy i wysyłek). Menu: **PNE Rozwój**. Kanon: [PNE Growth OS](docs/pne-growth-os/README.md)
- **Dokumentacja 2026-09-29:** zakończono Etap 0 PNE Growth OS — analizę architektury, granic bezpieczeństwa, modelu domenowego i roadmapy. Ten wpis nie oznacza wdrożenia funkcji biznesowych Growth OS. Kanon: [PNE Growth OS](docs/pne-growth-os/README.md)

## 1.1 — 2026-09-19

Belka zasobów i oferta na osadzonej transmisji.

- Na szkoleniu z danymi online jest **Panel live** (`/courses/{id}/live`; skrót na karcie szkolenia i liście uczestników). Operator włącza niezależnie materiały, ankietę i **Pobierz zaświadczenie** — tylko gdy dany zasób już jest na szkoleniu. Linki wchodzą i schodzą u uczestnika bez odświeżania transmisji.
- **Rejestracja: lista obecności** zostaje na panelu, ale jest nieaktywna: na obecnym osadzonym live uczestnik jest już zalogowany i na liście. Wróci przy live bez konta pnedu.
- Oferta kolejnego szkolenia: wybór innego kursu + **Wyświetl uczestnikom** / **Ukryj ofertę**.
- Kanon: [LIVE_EMBED_RESOURCE_BAR.md](docs/LIVE_EMBED_RESOURCE_BAR.md)

## 1.0 — 2026-09-18

Start numeracji. Stan produkcji z 18.09.2026.

- [Lista ClickMeeting](/clickmeeting/trainings) pokazuje, które wydarzenia mają już szkolenie w panelu ([Lista szkoleń](/courses)); brakujące można dodać z uzupełnionym formularzem (zapis ręczny). Przy powiązanym szkoleniu widać start z courses; gdy różni się od ClickMeeting — ostrzeżenie (bez automatycznej zmiany daty). Kolumna **Dostęp CM**; szkolenie zamknięte bez dostępu „Dla wszystkich” w ClickMeeting ma ostrzeżenie na liście i w edycji. Snapshot typu dostępu uczestników dopasowuje się w tle (bez zmiany ustawień w CM).
- Link do pokoju aktualizuje się tylko przy rozjeździe z ClickMeeting ([lista](/clickmeeting/trainings) i [edycja szkolenia](/courses)) oraz przy wysyłce maili live, provisionu i ponownej wysyłce dostępu ([Zamówienia FORM](/form-orders)).
- W menu, pod Kontem (po linii), jest historia wersji [ADM](/changelog/adm) i [pnedu.pl](/changelog/pnedu).
- Przy każdej z tych pozycji jest czerwone kółko z liczbą nieprzeczytanych punktów; po wejściu w historię kółko gaśnie (stan w koncie operatora). Mail do administratorów i superadministratorów tylko przy nowym numerze wersji, po decyzji Waldemara.
- Pełny opis: [CLICKMEETING_TRAININGS.md](docs/CLICKMEETING_TRAININGS.md), [CHANGELOG_VERSIONING.md](docs/CHANGELOG_VERSIONING.md)
- Nocny zrzut MySQL na SeoHost (skrypt + runbook): [MYSQL_BACKUP.md](docs/deploy/MYSQL_BACKUP.md). Cron w DirectAdmin dodaje operator.
- Blokada `migrate:fresh` / `db:wipe` na bazach z danymi (nie `testing`): [DATA_SAFETY.md](docs/DATA_SAFETY.md).
