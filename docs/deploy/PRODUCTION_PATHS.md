# Ścieżki produkcyjne (SeoHost)

Kanoniczne ścieżki aplikacji na serwerze produkcyjnym. Używaj ich w runbookach deploy i w komendach zamiast placeholderów typu `/ścieżka/do/…`.

| Aplikacja | Domena | Ścieżka (względem home `~`) | Pełna (login `srv66127`) |
|-----------|--------|-----------------------------|---------------------------|
| **pneadm** (panel) | [adm.pnedu.pl](https://adm.pnedu.pl) | `~/domains/adm.pnedu.pl/pneadm` | `/home/srv66127/domains/adm.pnedu.pl/pneadm` |
| **pnedu** (front) | [pnedu.pl](https://pnedu.pl) | `~/domains/pnedu.pl/app` | `/home/srv66127/domains/pnedu.pl/app` |

Skrót (z katalogu domowego po SSH):

```text
domains/adm.pnedu.pl/pneadm   → adm.pnedu.pl
domains/pnedu.pl/app          → pnedu.pl
```

PHP na prod (hosting współdzielony):

```text
/opt/alt/php82/usr/bin/php
```

## Typowy deploy

```bash
# Panel
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force   # gdy są migracje
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache

# Front
cd ~/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

## Powiązane

- Kolejka / cron: [`PRODUCTION_QUEUE_OPS.md`](./PRODUCTION_QUEUE_OPS.md)
- Forma komunikacji (docs po etapie): [`../AI_HUMAN_COMMUNICATION.md`](../AI_HUMAN_COMMUNICATION.md)

Zapis: 2026-08-10 (Waldemar).
