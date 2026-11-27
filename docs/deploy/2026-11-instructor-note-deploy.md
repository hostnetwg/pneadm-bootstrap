# Deploy: notatka o instruktorze na liście szkoleń

Data: 2026-11-27  
Projekt: **pneadm**. Front pnedu.pl bez zmian.

## Cel

Super Administrator na `/courses` dopisuje notatkę o prowadzącym dane szkolenie. Kanon: [COURSE_INSTRUCTOR_NOTES.md](../COURSE_INSTRUCTOR_NOTES.md).

## Prod (SeoHost)

PHP: `/opt/alt/php82/usr/bin/php`. Ścieżka: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md).

Tylko panel. Nowej zmiennej `.env` nie ma. Migracja dodaje tabelę `course_instructor_notes`. Nic nie kasuje i nie zmienia `courses.notatki`.

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull --ff-only origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

## Po deployu

Zaloguj się jako Super Administrator. Na liście szkoleń, w kolumnie instruktora, pod rozliczeniem jest **Notatka**. **Generuj** wstawia nagłówek ze spacją na końcu. **USUŃ** pyta o potwierdzenie i kasuje notatkę. Konto bez roli Super Administratora tej notatki nie widzi.

## Rollback

`git checkout` poprzedniego commita i te same komendy cache. `migrate:rollback` nie jest potrzebny: stary kod nie czyta nowej tabeli. Nie rób rollbacku migracji, gdy notatki są już zapisane.
