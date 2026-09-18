# Deploy: historia wersji w ADM

Data: 2026-09-18  
Projekty: `pneadm` + `pnedu` (tylko `CHANGELOG.md`). Brak migracji.

## Cel

Pod **Konto**, po linii, widać `adm.pnedu.pl v 1.0` i `pnedu.pl v 1.0` jako główne pozycje menu. Kanon: [CHANGELOG_VERSIONING.md](../CHANGELOG_VERSIONING.md).

## Po deployu

### Panel

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
```

W `.env` panelu dopisz (jeśli nie ma):

```bash
PNEDU_CHANGELOG_PATH=/home/srv66127/domains/pnedu.pl/app/CHANGELOG.md
```

```bash
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```

### Front (plik historii)

```bash
cd /home/srv66127/domains/pnedu.pl/app
git pull origin main
```

Nie trzeba `optimize:clear` na pnedu, jeśli weszło tylko `CHANGELOG.md` — ADM czyta ten plik z dysku. `optimize:clear` na pnedu nie zaszkodzi.

## Smoke

1. Zaloguj się na adm.pnedu.pl.
2. Menu, pod **Konto** (po linii) — dwie pozycje z `v 1.0` i czerwonym kółkiem (pierwsze logowanie).
3. Klik ADM — aktualna 1.0 i ClickMeeting; kółko ADM znika, pnedu.pl zostaje.
4. Klik pnedu.pl — aktualna 1.0; kółko pnedu.pl znika.

## Rollback

Cofnięcie commitów na obu repo i `optimize:clear` na `pneadm`. Brak zmian w bazie.

## Dev (Sail)

Po zmianie `docker-compose.yml` (montowanie changelogu pnedu):

```bash
cd /home/hostnet/WEB-APP/pneadm
./vendor/bin/sail down
./vendor/bin/sail up -d
```
