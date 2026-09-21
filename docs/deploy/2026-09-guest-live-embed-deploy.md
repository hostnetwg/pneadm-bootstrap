# Deploy: live bez logowania (`/live/{token}`)

Data: 2026-09-19  
Projekty: `pneadm` + `pnedu`. Mail `changelog:notify-admins` — **nie**.

## Cel

Szkolenie zamknięte + osadzony pokój: sekretny link `pnedu.pl/live/{token}` (formularz imię / nazwisko / e-mail → rekord uczestnika → iframe ClickMeeting „Dla wszystkich” + belka). Widoczny w ADM (karta + panel live). **Nie** w katalogu pnedu.pl, **nie** w mailu z ADM.

Kanon: [LIVE_EMBED_RESOURCE_BAR.md](../LIVE_EMBED_RESOURCE_BAR.md), [CLICKMEETING_TRAININGS.md](../CLICKMEETING_TRAININGS.md). Ścieżki: [PRODUCTION_PATHS.md](./PRODUCTION_PATHS.md).

## Migracje

Tylko **pneadm**: `course_online_details.guest_live_token` (`2026_09_19_235500_…`).

pnedu **bez** migracji (czyta tę kolumnę).

**Kolejność:** najpierw `pneadm` (`git pull` + `migrate --force`), potem `pnedu`.

`npm run build` **nie jest wymagany**.

## Po deployu

### Panel

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
```

### Front

```bash
cd /home/srv66127/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan route:clear
/opt/alt/php82/usr/bin/php artisan view:clear
```

Token powstaje przy otwarciu karty / panelu live zamkniętego szkolenia z osadzonym pokojem.
