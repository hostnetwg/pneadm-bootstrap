# Deploy: ClickMeeting → courses (oznaczenie, prefill, sync linku)

Data: 2026-09-18  
Projekt: `pneadm` (bez migracji). `pnedu` bez zmian.

## Cel

Na `/clickmeeting/trainings` widać, które wydarzenia mają już szkolenie w `courses`. Brakujące można dodać z prefillem. Gdy ClickMeeting zmieni `room_url` (po zmianie nazwy pokoju), operator aktualizuje link w ADM, w dostępach uczestników i w kalendarzu Google. Wysyłka maili live / provision też odświeża `room_url` z API.

Kanon: [CLICKMEETING_TRAININGS.md](../CLICKMEETING_TRAININGS.md).

## Migracje

Brak.

## Po deployu

1. `cd ~/domains/adm.pnedu.pl/pneadm` → `git pull` → `/opt/alt/php82/usr/bin/php artisan optimize:clear`
2. Otwórz `/clickmeeting/trainings`.
3. Wydarzenie już w `courses` — etykieta **W courses**, bez przycisku dodawania; przy rozjeździe linku **Link nieaktualny**.
4. **Aktualizuj link z ClickMeeting** na liście i na `/courses/{id}/edit` — tylko gdy adresy się różnią.
5. Provision, ponowna wysyłka maila dostępu albo „Wyślij link do live” — mail ma aktualny slug.

## Rollback

Cofnięcie commita na `pneadm` i `optimize:clear`. Brak zmian w bazie.
