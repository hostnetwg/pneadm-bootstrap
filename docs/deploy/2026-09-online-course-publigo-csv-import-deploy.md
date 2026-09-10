# Deploy: Import CSV dostępów kursów online (Publigo)

Data: 2026-09-10  
Projekt: `pneadm` (migracja w bazie `pneadm`). `pnedu` — tylko uzupełnienie `fillable` modelu (bez migracji).

## Cel

Hurtowe nadawanie dostępów do kursu online z eksportu CSV starej platformy (nowoczesna-edukacja.pl / Publigo), z checkboxem pomijania już wygasłych terminów.

Deploy łącznie z e-mailem o przeniesieniu: [`2026-09-online-course-platform-migration-email-deploy.md`](./2026-09-online-course-platform-migration-email-deploy.md).

## Migracje

Migracja: `2026_09_10_113500_add_phone_and_legacy_publigo_user_id_to_online_course_enrollments_table.php`

- `online_course_enrollments.phone`
- `online_course_enrollments.legacy_publigo_user_id`

Lokalnie: `sail artisan migrate --force`  
Prod: `cd ~/domains/adm.pnedu.pl/pneadm` → `git pull` → `/opt/alt/php82/usr/bin/php artisan migrate --force` (nigdy `sail`).

## Po deployu

1. `optimize:clear` / cache na `pneadm` (i `pnedu` jeśli wrzucasz fillable).
2. Otwórz `/online-courses/{id}/enrollments` → **Import CSV (Publigo)**.
3. Test na kursie: wgraj mały CSV; sprawdź telefon, ID Publigo, „Bez limitu”, datę z wiersza.
4. Zaznacz **Pomiń osoby z już wygasłym dostępem** i powtórz na kopii pliku z rocznym dostępem — wygasłe nie powinny się pojawić.
5. Nie powstają konta pnedu.pl i nie idą maile.

## Rollback

```bash
# prod
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan migrate:rollback --step=1
```

(tylko jeśli na produkcji nie ma już zaimportowanych danych zależnych od nowych kolumn — rollback usuwa `phone` i `legacy_publigo_user_id`).
