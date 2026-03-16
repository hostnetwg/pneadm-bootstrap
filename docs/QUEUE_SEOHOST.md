# Kolejka Laravel (generowanie PDF zaświadczeń) na SeoHost.pl

Na SeoHost.pl masz **hosting współdzielony z panelem DirectAdmin**. Nie ma tam Supervisora, więc worker kolejki nie może działać jako stały proces. Zamiast tego Laravel uruchamia `queue:work --stop-when-empty` **co minutę** przez **scheduler**, a scheduler jest wywoływany przez **jedno zadanie cron**.

Dokumentacja SeoHost:
- [Konfiguracja zadań CRON w DirectAdmin](https://seohost.pl/pomoc/jak-skonfigurowac-zadania-cron-w-directadmin)
- [CRON z różnymi wersjami PHP](https://seohost.pl/pomoc/konfiguracja-zadan-cron-pod-rozne-wersje-php)

---

## Krok 1: Ustawienie kolejki w `.env` na produkcji

W katalogu projektu **pneadm-bootstrap** na serwerze (np. przez SSH lub pliki w DirectAdmin) edytuj plik `.env`:

```env
QUEUE_CONNECTION=database
```

Zapisz. Dzięki temu zadania trafiają do tabeli `jobs` w bazie i są odbierane przez scheduler (cron).

---

## Krok 2: Migracje (tabele `jobs` i `job_batches`)

Jeśli jeszcze nie były uruchomione na produkcji:

```bash
php artisan migrate --force
```

(lub przez SSH w katalogu projektu pneadm-bootstrap). Migracja `0001_01_01_000002_create_jobs_table.php` tworzy tabele `jobs` i `job_batches`.

---

## Krok 3: Ścieżka do PHP na serwerze

Na SeoHost w cronie musisz użyć **konkretnej wersji PHP** (np. 8.2 lub 8.4). Ścieżki według [dokumentacji](https://seohost.pl/pomoc/konfiguracja-zadan-cron-pod-rozne-wersje-php):

| Wersja PHP | Ścieżka do PHP        |
|------------|------------------------|
| PHP 8.4     | `/opt/alt/php84/usr/bin/php` |
| PHP 8.2     | `/opt/alt/php82/usr/bin/php` |
| PHP 8.1     | `/opt/alt/php81/usr/bin/php` |
| PHP 7.4     | `/opt/alt/php74/usr/bin/php` |

Numer wersji **bez kropki** (84, 82, 81, 74). Sprawdź w DirectAdmin, jaką wersję PHP ma domena z panelem adm (adm.pnedu.pl).

---

## Krok 4: Ścieżka do projektu na serwerze

Potrzebujesz **pełnej ścieżki** do katalogu z aplikacją pneadm-bootstrap na serwerze. W DirectAdmin zwykle jest to coś w stylu:

```text
/home/TWOJ_LOGIN/domains/adm.pnedu.pl/public_html
```

albo jeśli aplikacja jest w podkatalogu (np. `pneadm`):

```text
/home/TWOJ_LOGIN/domains/adm.pnedu.pl/public_html/pneadm
```

Ścieżkę możesz sprawdzić w DirectAdmin (np. Menedżer plików lub ustawienia domeny) albo przez SSH komendą `pwd` w katalogu projektu.

**Artisan** musi być wywoływany z tego katalogu, np.:

```text
/opt/alt/php82/usr/bin/php /home/TWOJ_LOGIN/domains/adm.pnedu.pl/public_html/artisan schedule:run
```

(jeśli `artisan` jest w `public_html`; jeśli projekt jest w podkatalogu, dopisz go, np. `.../public_html/pneadm/artisan`).

---

## Krok 5: Dodanie zadania CRON w DirectAdmin

1. Zaloguj się do **DirectAdmin** (SeoHost).
2. Wejdź w: **Funkcje zaawansowane** → **Zadania CRON**.
3. Kliknij **„Utwórz zadanie cron”** (lub „Dodaj” / „Add”).
4. Ustaw harmonogram:
   - **Minuta:** `*` (co minutę).
   - **Godzina:** `*`.
   - **Dzień miesiąca:** `*`.
   - **Miesiąc:** `*`.
   - **Dzień tygodnia:** `*`.
5. W polu **Komenda** wpisz (podmień ścieżki na swoje):

   ```bash
   /opt/alt/php82/usr/bin/php /home/TWOJ_LOGIN/domains/adm.pnedu.pl/public_html/artisan schedule:run >> /dev/null 2>&1
   ```

   - Zastąp `php82` wersją PHP, której używa Twoja domena (np. `php84`).
   - Zastąp `TWOJ_LOGIN` i ścieżkę domeny rzeczywistymi wartościami.
   - `>> /dev/null 2>&1` wyłącza wysyłanie maili z logami (opcjonalnie możesz to usunąć i podać adres e-mail w polu powiadomień).

6. Zapisz zadanie.

Od tej pory **co minutę** cron uruchomi `php artisan schedule:run`, a scheduler wykona zaplanowane polecenie `queue:work --stop-when-empty --max-time=300`, czyli przetworzy kolejkę (w tym generowanie PDF) bez ręcznego wpisywania `php artisan queue:work`.

---

## Krok 6: Po wdrożeniu nowego kodu

Po każdym `git pull` lub wgraniu zmian na produkcję warto zrestartować worker (żeby wziął nowy kod). Przy cronie nie ma „długo działającego” workera, więc wystarczy:

```bash
php artisan queue:restart
```

(nie zatrzyma to crona; kolejne uruchomienie `schedule:run` i tak użyje już nowego kodu).

---

## Podsumowanie

| Co | Gdzie / Jak |
|----|------------------|
| Kolejka | `QUEUE_CONNECTION=database` w `.env` |
| Tabele | `php artisan migrate --force` (jobs, job_batches) |
| Scheduler | W projekcie: `routes/console.php` – `queue:work --stop-when-empty` co minutę |
| Cron | DirectAdmin → Zadania CRON → co minutę: `.../php .../artisan schedule:run` |
| Ścieżka PHP | Np. `/opt/alt/php82/usr/bin/php` (dopasuj wersję) |
| Ścieżka projektu | Np. `/home/LOGIN/domains/adm.pnedu.pl/public_html` |

Jeśli masz na SeoHost **VPS** z dostępem root i możesz zainstalować Supervisora, można zamiast crona uruchomić stały proces `queue:work` – wtedy nie trzeba schedulera co minutę. Na hostingu współdzielonym powyższy schemat (cron + scheduler) jest standardowym rozwiązaniem.
