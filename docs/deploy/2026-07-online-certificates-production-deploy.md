# Wdrożenie produkcyjne — zaświadczenia kursów online + szablony (lipiec 2026)

Status: **do wdrożenia** (kod lokalny — przed pushem sprawdź `git status` w obu projektach).

Zakres:
- **pneadm:** API certyfikatów dla enrollments, pola cert na `online_courses`, `training_scope`, `certificate_duration_minutes`, zmienne w `event_text` szablonów, admin enrollments.
- **pnedu:** pobieranie zaświadczeń online przez API adm, profil uczestnika, dashboard kursów online.

> Bez sekretów. Tokeny i hosty — placeholdery.

---

## 1. Przed wdrożeniem (lokalnie)

> Dokumentacja systemu po wdrożeniu: [docs/CERTIFICATES.md](../CERTIFICATES.md).

### 1.1 Commit i push — **oba** repozytoria

```bash
# pneadm
cd /ścieżka/do/pneadm
git status
git add …   # tylko pliki z tego zakresu
git commit -m "feat(certificates): online course certificates, template variables, training scope"
git push origin main

# pnedu
cd /ścieżka/do/pnedu
git status
git add …
git commit -m "feat(certificates): online course certificate download via adm API"
git push origin main
```

**Uwaga:** `pnedu` — nie commituj `.phpunit.result.cache`.

### 1.2 Migracje pneadm (kolejność)

Wszystkie w bazie **pneadm** (nie w pnedu):

| Migracja | Opis |
|----------|------|
| `2026_07_07_120000_add_certificate_fields_to_online_courses_table` | Pola cert na `online_courses` |
| `2026_07_07_120001_extend_certificates_for_online_courses` | FK enrollment, nullable participant/course |
| `2026_07_08_120000_add_training_scope_to_online_courses_table` | Zakres na zaświadczeniu |
| `2026_07_08_120001_add_comment_to_courses_description_column` | Komentarz MySQL `courses.description` |
| `2026_07_08_130000_add_certificate_duration_minutes_to_online_courses_table` | Czas trwania (min) |

### 1.3 Testy lokalne (opcjonalnie)

```bash
cd pneadm && ./vendor/bin/sail artisan test tests/Unit/CertificateTemplateVariableResolverTest.php
cd pnedu && ./vendor/bin/sail test tests/Unit/UserCertificateProfileServiceTest.php
```

---

## 2. `.env` produkcja

### 2.1 pneadm (`adm.pnedu.pl`)

```env
PNEADM_API_TOKEN=<ten-sam-długi-token-co-w-pnedu>
```

Sprawdź, że token **nie jest sklejony** z poprzednią linią w `.env` (znany problem dev).

### 2.2 pnedu (`pnedu.pl`)

```env
PNEADM_API_URL=https://adm.pnedu.pl
PNEADM_API_TOKEN=<identyczny jak w pneadm>
# opcjonalnie miniatury / media z adm:
# PNEADM_PUBLIC_URL=https://adm.pnedu.pl
```

Po zmianie `.env` na obu serwerach: `php artisan config:clear` (lub `config:cache` według waszej procedury).

---

## 3. Deploy — kolejność

**Najpierw pneadm** (migracje + API), **potem pnedu** (front).

### 3.1 pneadm (adm)

```bash
cd /ścieżka/do/pneadm
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
# opcjonalnie, jeśli używacie cache config na prod:
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache
```

**Katalogi zapisywalne** (jeśli edycja szablonów Blade na prod):

```bash
chmod -R 775 storage bootstrap/cache resources/views/certificates
# właściciel www-data / sail — według serwera
```

**Vite:** ten zakres to głównie PHP + Blade admin — `npm run build` **nie jest wymagany**, chyba że zmienialiście też `resources/js` / `resources/css`.

### 3.2 pnedu

```bash
cd /ścieżka/do/pnedu
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # tylko jeśli są migracje pnedu w tym release; ten pakiet — brak migracji cert
php artisan optimize:clear
```

`npm run build` — **opcjonalny** (brak zmian w bundlu JS w tym zakresie); można pominąć lub wykonać według standardu z deployu analityki.

---

## 4. Konfiguracja po deployu (panel adm)

Ręcznie na produkcji:

1. **Kursy online** — dla każdego kursu z certyfikatami:
   - Status zaświadczeń: „Udostępnij pobieranie”
   - Szablon certyfikatu
   - **Zakres szkolenia / Zagadnienia** (`training_scope`)
   - **Czas trwania (minuty)** (`certificate_duration_minutes`)
2. **Szablony zaświadczeń** — zaktualizuj `event_text`, np.:
   ```
   zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez
   ```
   Stare szablony ze samym `zorganizowanym w dniu` **nie** dostaną już automatycznej daty — trzeba dodać zmienne.
3. W szablonie: włącz **Pokaż zakres szkolenia** / **Pokaż czas trwania** według potrzeb.

---

## 5. Smoke testy produkcji

### 5.1 API adm (z serwera lub maszyny z tokenem)

```bash
curl -sS -H "Authorization: Bearer <PNEADM_API_TOKEN>" \
  https://adm.pnedu.pl/api/certificates/health
```

Oczekiwane: JSON OK / status działający endpointu.

### 5.2 Admin adm

- [ ] `/online-courses/{id}/edit` — pola cert, zakres, minuty
- [ ] `/online-courses/{id}/enrollments` — generuj / pobierz PDF
- [ ] Podgląd szablonu PDF — `event_text` ze zmiennymi

### 5.3 pnedu (konto testowe z enrollmentem)

- [ ] `/dashboard/kursy-online` — lista kursów
- [ ] Strona kursu → zaświadczenie (profil / pobierz PDF)
- [ ] PDF zawiera zakres i czas (jeśli uzupełnione + szablon)

### 5.4 Logi przy błędzie

- pneadm: `storage/logs/laravel.log`
- pnedu: `storage/logs/laravel.log` — szukać `Invalid API token`, błędów `CertificateApiClient`

---

## 6. Rollback (ostrożnie)

```bash
# pneadm — cofnij migracje w odwrotnej kolejności (tylko jeśli konieczne)
php artisan migrate:rollback --step=1   # powtórz ×5 lub migrate:rollback do batcha

git checkout <poprzedni-commit>
composer install --no-dev
php artisan optimize:clear
```

**Uwaga:** rollback `extend_certificates_for_online_courses` przy istniejących certyfikatach online może być problematyczny — lepiej backup DB przed migrate.

---

## 7. Backup przed migrate (zalecane)

```bash
# przykład — dostosuj do hostingu
mysqldump -u USER -p pneadm > backup-pneadm-$(date +%F-%H%M).sql
```

Tabele dotknięte: `online_courses`, `certificates`, ewentualnie `certificate_templates.config` (tylko jeśli edytujecie ręcznie w panelu).

---

## 8. Checklist skrócona

- [ ] Commit + push **pneadm** i **pnedu**
- [ ] Backup bazy **pneadm**
- [ ] `PNEADM_API_TOKEN` identyczny na prod (adm + pnedu)
- [ ] Deploy **pneadm**: pull → composer → **migrate** → cache clear
- [ ] Deploy **pnedu**: pull → composer → cache clear
- [ ] Uzupełnij kursy online (zakres, minuty, status cert)
- [ ] Zaktualizuj szablony (`event_text` ze zmiennymi)
- [ ] Smoke: health API + pobranie PDF z pnedu
