# Historia zmian — adm.pnedu.pl

Krótka numeracja panelu. Szczegóły zawsze w `docs/`. Nowy numer wersji (np. 1.1) tylko po potwierdzeniu Waldemara. Hotfixy dopisujemy do bieżącej wersji.

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
