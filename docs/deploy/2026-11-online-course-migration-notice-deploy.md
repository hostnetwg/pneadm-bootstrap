# Deploy: jeden list o przeniesieniu kursów na pnedu.pl

Data: 2026-11-17  
Projekt: **`pneadm`** (baza `pneadm`). Front `pnedu` bez zmian.

## Cel

Na liście kursów online przycisk **Informacje o przeniesieniu kursów z nowoczesna-edukacja.pl** (`/online-courses/migration-notice`).

Jeden list na osobę, z tytułami jej ważnych kursów ze źródła `publigo_migration`. Nie zakłada konta. Zbiorcza wysyłka tylko do osób, które listu jeszcze nie dostały. Najpierw podgląd, potem zatwierdzenie.

## Migracja

```text
2026_11_17_140000_create_online_course_migration_notices_table.php
```

Nowa tabela `online_course_migration_notices`. Nie zmienia istniejących zapisów ani kont.

## Prod (SeoHost)

PHP: `/opt/alt/php82/usr/bin/php` — nigdy `sail`. Ścieżki: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md).

```bash
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan queue:restart
```

`pnedu` nie wymaga pulla. Kolejka panelu musi działać, zanim ktokolwiek kliknie wysyłkę zbiorczą. Kanon: [`PRODUCTION_QUEUE_OPS.md`](./PRODUCTION_QUEUE_OPS.md).

Poprawka okienka (kolejne „Wyślij e-mail” nie zostawia przyciemnionego ekranu) to tylko widok. Sam `git pull` nie wystarczy, jeśli widoki są już zcache'owane: po pullu trzeba `optimize:clear`, `view:clear` i `view:cache`. Nowej migracji nie ma. `migrate` i `queue:restart` nic tu nie zmieniają.

## Po deployu

1. Wejdź na `/online-courses` i otwórz **Informacje o przeniesieniu kursów z nowoczesna-edukacja.pl**.
2. Sprawdź dwa wzory listu na stronie, filtr **Konto pnedu.pl** (wszyscy, ma konto, brak konta) i filtr **Liczba kursów** (wpisana liczba, dokładnie tyle osób).
3. Wyślij **jeden** list na skrzynkę testową (przycisk przy osobie albo filtr, który zostawia jedną osobę). Sprawdź treść i kolumnę „wysłano”. Przy wysyłce zbiorczej na stronie zostaje pasek postępu i **Przerwij wysyłkę**.
4. Nie uruchamiaj wysyłki zbiorczej do całej bazy w tym samym kroku co deploy.

## Rollback

Gdy w `migrate:status` ostatnia migracja to `2026_11_17_140000_create_online_course_migration_notices_table` i nikt jeszcze nie wysłał listów:

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan migrate:rollback --step=1
```

To usuwa tylko tabelę logów tej wysyłki.
