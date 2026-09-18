# Szkolenia ClickMeeting → courses

Data utworzenia/aktualizacji: 2026-09-18  
Status: wdrożone lokalnie

## Cel

ClickMeeting jest źródłem prawdy dla płatnych szkoleń online: najpierw powstaje wydarzenie w ClickMeeting (i zaproszenie do trenera), dopiero potem rekord w `courses`.

Lista `/clickmeeting/trainings` pokazuje wydarzenia z API i oznacza, które mają już szkolenie w panelu. Z tej listy można otworzyć formularz `/courses/create` z częściowo uzupełnionymi danymi. Zapis nadal jest ręczny.

## Powiązanie

Powiązanie jest po `course_online_details.clickmeeting_event_id` = ID wydarzenia ClickMeeting.

- jeśli ID jest już w `courses` — na liście widać zieloną etykietę **W courses** i link do edycji; przycisk dodawania znika,
- jeśli ID nie występuje — przycisk **Dodaj do szkoleń** otwiera formularz w nowej karcie.

Wejście bezpośrednie na `/clickmeeting/trainings/{eventId}/create-course` przy istniejącym powiązaniu przekierowuje do edycji szkolenia.

## Prefill formularza

Źródło danych: `GET /v1/conferences/{id}` (nie lista, bo lista nie zawsze ma `ends_at` i `room_url`).

| Pole | Wartość |
|------|---------|
| Tytuł | `name` |
| Data rozpoczęcia | `starts_at` / `start_time` w `Europe/Warsaw`, format `datetime-local` |
| Data zakończenia | `ends_at` / `end_time`, jeśli jest i jest późniejsza niż start; **w przeciwnym razie puste** |
| Platforma | `ClickMeeting` |
| ID wydarzenia ClickMeeting | ID z API |
| Link do spotkania | `room_url` |
| Płatność | Płatne |
| Kategoria | Otwarte |
| Rodzaj kursu | Online |
| Wejście do pokoju | Osadzony pokój na pnedu.pl |
| Pokaż na stronie głównej pnedu.pl | odznaczone |

Pozostałe pola zostają domyślne. Operator uzupełnia trenera, opis, cenę, Sendy itd. i zapisuje ręcznie.

## Synchronizacja linku (etap 1)

Nazwa pokoju w ClickMeeting często się zmienia (limit znaków, doprecyzowanie). Wtedy CM generuje nowy `room_url`, a `course_online_details.meeting_link` zostaje stary.

**Synchronizujemy tylko link**, nie tytuł kursu (w `courses` jest publiczna pełna nazwa oferty).

Przycisk **Aktualizuj link z ClickMeeting**:

- na `/clickmeeting/trainings` tylko gdy `room_url` z API różni się od `meeting_link` (etykieta **Link nieaktualny** + przycisk),
- na `/courses/{id}/edit` obok pola „Link do spotkania”, też tylko przy rozjeździe.

Akcja:

1. `GET /v1/conferences/{event_id}` → `room_url`,
2. zapis do `course_online_details.meeting_link`,
3. aktualizacja `participant_live_access.room_url` dla tego szkolenia,
4. synchronizacja Google Calendar, jeśli włączona.

Serwis: `App\Services\ClickMeetingCourseRoomUrlSyncService`.

## Odświeżenie przy wysyłce maila (etap 2)

Przy pierwszym provisionie ClickMeeting, przy ponownej wysyłce / podglądzie maila dostępu oraz przy „Wyślij link do live” panel pobiera aktualny `room_url` z API i zapisuje snapshot **zanim** zbuduje treść maila. Gdy API nie odpowie, zostaje poprzedni link — wysyłka nie pada.

Już wysłanych maili to nie poprawi. Maile z adresem pnedu.pl (`/transmisja`) nadal działają, bo pokój jest rozwiązywany w chwili wejścia.

## Deploy

Brak migracji. Po wrzuceniu kodu: `optimize:clear` na `pneadm`.

Smoke:

1. `/clickmeeting/trainings` — etykieta przy znanym szkoleniu, „Dodaj do szkoleń” przy nowym.
2. Zmień nazwę pokoju w ClickMeeting → na liście **Link nieaktualny** → aktualizacja.
3. To samo z `/courses/{id}/edit`.
4. Provision / „Wyślij link do live” — w mailu nowy slug.

## Testy

```bash
sail test --filter=ClickMeetingTrainingAdminTest
sail test --filter=ClickMeetingServiceTest
sail test --filter=ParticipantLiveMeetingLinkMailServiceTest
```
