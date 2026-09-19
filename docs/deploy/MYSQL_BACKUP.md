# Nocne kopie MySQL (SeoHost)

Data: 2026-09-19  
Status: skrypt w repo, cron na prod **do dodania ręcznie** w DirectAdmin

## Co już robi SeoHost (domyślnie, bez Twojej konfiguracji)

Nie musiałeś nic włączać. Na koncie hostingowym:

| Warstwa | Gdzie | Co obejmuje |
|---------|--------|-------------|
| Backup Manager | DirectAdmin → **Dodatkowe funkcje → SEOHOST Tools → Backup Manager** | Kopie **dzienne, tygodniowe i miesięczne** plików **i baz MySQL**. Samodzielne przywrócenie / pobranie SQL / tar.gz do `/backup_manager`. |
| Kopia usługi | Support SeoHost | Cała usługa (pliki, bazy, poczta) z **ostatnich 60 dni**. Przywrócenie przez BOK. |

Źródła: [Przywracanie kopii zapasowej na koncie](https://seohost.pl/pomoc/przywracanie-kopii-zapasowej-na-koncie), [Dlaczego warto regularnie wykonywać backup](https://seohost.pl/pomoc/dlaczego-warto-wykonywac-backup-serwera).

phpMyAdmin zostaje awaryjnym eksportem (jak zrzut z 19.09.2026), nie codziennym planem.

## Po co własny cron `mysqldump`

Backup Manager jest dobry do przywrócenia *na tym samym hostingu*. Własny zrzut SQL:

- masz plik jak z phpMyAdmin, do ARCHIWUM na „Mój dysk”,
- działa niezależnie od panelu ADM (Laravel go nie odpala),
- leży **poza** `public_html`.

SeoHost sam pisze: kopia tylko na tym samym serwerze to za mało. Cron na hostingu + ARCHIWUM offsite.

## Skrypt

`docs/deploy/scripts/prod-mysql-nightly-backup.sh`

Z `.env` pneadm: baza panelu, certgen, analityka. Z `.env` pnedu: baza frontu. Hasła tylko w pliku cnf w katalogu kopii (kasowany po zrzucie). Gzip. 14 nocy, potem kasowanie.

Katalog: `~/backups/mysql/` (`/home/srv66127/backups/mysql`).

## Włączenie na prod

1. `git pull` w `~/domains/adm.pnedu.pl/pneadm`
2. Jednorazowo:

```bash
mkdir -p ~/backups/mysql
chmod 700 ~/backups/mysql
chmod 700 ~/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh
```

3. Próba ręczna (kilka–kilkanaście minut przy ~177 MB):

```bash
/bin/bash ~/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh
ls -lh ~/backups/mysql/
```

4. DirectAdmin → **Zadania CRON** → nowe zadanie, **01:30** (jeśli cron jest w `Europe/Warsaw`; jak serwer ma UTC, ustaw 23:30 UTC):

```bash
30 1 * * * /bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh >> /home/srv66127/backups/mysql/backup.log 2>&1
```

Nie koliduje z agregacją analityki (02:15 / 03:15).

5. Co jakiś czas skopiuj `*.sql.gz` do `ARCHIWUM_pneduadm` (Google Drive). Tego cron z SeoHost sam nie zrobi.

## Przywrócenie lokalne (Sail)

Tylko do bazy **pneadm** w Dockerze, nigdy odwrotnie na prod bez osobnej decyzji:

```bash
gunzip -c plik.sql.gz | docker exec -i pneadm-mysql mysql -usail -ppassword pneadm
```

Przy zrzucie phpMyAdmin bez `DROP TABLE` najpierw `DROP DATABASE` / `CREATE DATABASE` jak 19.09.2026.

## Czego nie robimy

- pakietu backupu w Laravelu / przycisku w ADM,
- trzymania dumpów w `public` albo w git,
- automatycznego wrzucania na „Mój dysk” (osobny temat: rclone),
- `migrate:fresh` przy deployu — to nie jest kopia i kasuje dane ([DATA_SAFETY.md](../DATA_SAFETY.md)).
