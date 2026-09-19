# Deploy: belka zasobów i oferta na osadzonej transmisji (v 1.1)

Data: 2026-09-19  
Projekty: `pneadm` + `pnedu`. Mail `changelog:notify-admins` — **nie**.

## Cel

Panel ADM `/courses/{id}/live`: niezależne włączniki materiałów, ankiety i zaświadczenia + oferta kolejnego szkolenia. Na `/transmisja` belki wchodzą i schodzą bez odświeżania. **Rejestracja: lista obecności** na belce jest ukryta (uczestnik już na liście).

Kanon: [LIVE_EMBED_RESOURCE_BAR.md](../LIVE_EMBED_RESOURCE_BAR.md), [CHANGELOG_VERSIONING.md](../CHANGELOG_VERSIONING.md). Ścieżki: [PRODUCTION_PATHS.md](./PRODUCTION_PATHS.md).

## Migracje

Tylko **pneadm** (baza `pneadm`):

- `course_online_details`: flagi belki + oferta
- `participant_live_access.embed_last_seen_at` (`2026_09_19_231700_…`) — kto jest teraz na `/transmisja`

pnedu **bez** migracji (czyta te same kolumny).

**Kolejność:** najpierw `pneadm` (`git pull` + `migrate --force`), potem `pnedu`. Nie odwrotnie — nowy kod frontu czyta kolumny belki.

`npm run build` **nie jest wymagany** (Blade + `course-select-fallback.js`).

## Po deployu

### Panel

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```

### Front

```bash
cd /home/srv66127/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
```

## Smoke

1. Menu ADM: `adm.pnedu.pl v 1.1` i `pnedu.pl v 1.1`.
2. Szkolenie z osadzonym pokojem → **Panel live**.
3. Włącz **Materiały** (gdy jest link) — na `/transmisja` żółty przycisk bez F5 (ok. 5 s).
4. **Wyświetl uczestnikom** inną ofertę — złota belka, **Zamawiam szkolenie** → opis kursu.
5. **Rejestracja: lista obecności** na panelu szara; na belce jej nie ma.
6. Wejście uczestnika na `/transmisja` → na panelu live widać imię i e-mail w **Teraz na osadzonym live**. Zamknięcie karty → znika.

## Rollback

Cofnięcie commitów na obu repo + `optimize:clear`. Kolumny belki mogą zostać (domyślnie OFF). Nie rollbackować migracji na prod bez decyzji.

## Dev (Sail)

```bash
cd /home/hostnet/WEB-APP/pneadm && ./vendor/bin/sail artisan migrate
cd /home/hostnet/WEB-APP/pneadm && ./vendor/bin/sail test --filter=CourseLivePanelTest
cd /home/hostnet/WEB-APP/pnedu && ./vendor/bin/sail test --filter=LiveEmbedPresence
```
