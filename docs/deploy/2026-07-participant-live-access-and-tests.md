# Deploy / changelog: participant_live_access, KSeF UI, pełny sail test

Data: 2026-07-13  
Repozytorium: `pneadm` (`adm.pnedu.pl`)  
Commity: `852950e` … `8963e23` (branch `main`)

## Podsumowanie dla Waldemara

Wdrożono trzy obszary:

1. **Tokeny ClickMeeting poza `form_orders`** — link na żywo trzymany w tabeli `participant_live_access` (per uczestnik), z ręczną rejestracją z listy uczestników i automatycznym czyszczeniem po zakończeniu szkolenia.
2. **KSeF / iFirma na zamówieniu** — dwufazowy flow: najpierw numer faktury w panelu, potem wysyłka do KSeF; po sukcesie zostaje podgląd etapów.
3. **Testy** — pełny `sail test` przechodzi na zielono (510 passed); baza analityki w testach izolowana od dev `pne_analytics`.

**Na produkcję (adm):** wymagane migracje + ewentualnie jednorazowy migrate danych tokenów. Worker kolejki bez zmian.

**Na później (nie wdrożone):** hurtowa/indywidualna wysyłka maila z linkiem live z listy uczestników.

---

## 1. participant_live_access (ClickMeeting)

### Decyzja biznesowa

- Token **nie** w `form_orders` — osobna tabela powiązana z `participants`.
- Cleanup = **kasowanie całego rekordu** po `expires_at` (koniec szkolenia).
- Semantyka tokenu: przypisany do **e-maila uczestnika**; blokuje równoległe wejście; po wyjściu ze spotkania można użyć ponownie.

### Co wdrożono

| Element | Opis |
|---------|------|
| Tabela `participant_live_access` | `participant_id` (unique), opcjonalnie `form_order_id`, `course_id`, token, `room_url`, `access_type`, status, `expires_at` |
| Migracja danych | `2026_07_13_210001_*` — przenosi tokeny z usuniętej kolumny `form_orders.pnedu_clickmeeting_token` |
| Serwis | `ParticipantLiveAccessService` |
| Provision | `FormOrderPneduProvisionService` zapisuje do nowej tabeli |
| UI zamówienia | `form-orders/show` — snapshot kroku 2 (`pnedu_clickmeeting_*`), reset PNEDU kasuje rekord live access |
| Lista uczestników | Przycisk **ClickMeeting** → `POST .../provision-live-access` |
| Cron | `participants:cleanup-live-access` codziennie 04:15 (`routes/console.php`) |

### Dokumentacja modułu

[FORM_ORDERS_PNEDU_PROVISION.md](../FORM_ORDERS_PNEDU_PROVISION.md)

### Komendy deploy (prod)

```bash
cd /ścieżka/do/pneadm
git pull origin main   # min. commit e7f4389
php artisan migrate --force
php artisan participants:cleanup-live-access --dry-run   # weryfikacja
php artisan optimize:clear
```

### Rollback (ostrożnie)

Migracja danych jest jednokierunkowa. Rollback schematu wymaga przywrócenia backupu lub ręcznej rekonstrukcji tokenów z `participant_live_access` przed `down()`.

---

## 2. KSeF / iFirma (form_orders)

### Co wdrożono

- Dwufazowy submit: `phase=create` → numer faktury w UI → `phase=ksef`.
- Serwis: `IfirmaFormOrderKsefSubmissionService`.
- Po sukcesie KSeF: podgląd etapów + podsumowanie na dole strony zamówienia (commit `97518ff`).

### Dokumentacja

[KSEF_FORM_ORDERS.md](../KSEF_FORM_ORDERS.md)

---

## 3. Naprawa pełnego suite testów (commit `8963e23`)

### Problem

- `RefreshDatabase` + migracje analityki do `pne_analytics` → konflikt „already exists”.
- Testy Breeze nie uwzględniały `is_active`, SoftDeletes, wyłączonej rejestracji, filtrów dat, mocka Sendy.

### Rozwiązanie

- `phpunit.xml`: `DB_ANALYTICS_DATABASE=testing`, `ANALYTICS_ENABLED=false`.
- Poprawki w fabryce użytkownika i 12 plikach testów.
- Dokumentacja: [TESTING.md](../TESTING.md).

### Weryfikacja

```bash
sail test
# Tests: 510 passed, 3 skipped, 0 failed
```

---

## Pliki kluczowe (implementacja)

| Obszar | Pliki |
|--------|--------|
| Live access | `app/Models/ParticipantLiveAccess.php`, `app/Services/ParticipantLiveAccessService.php`, `database/migrations/2026_07_13_210000_*`, `2026_07_13_210001_*` |
| Provision | `app/Services/FormOrderPneduProvisionService.php`, `resources/views/form-orders/show.blade.php` |
| Uczestnicy | route `participants.provision-live-access`, widok listy uczestników |
| Cleanup | `app/Console/Commands/CleanupParticipantLiveAccessCommand.php`, `routes/console.php` |
| KSeF | `app/Services/IfirmaFormOrderKsefSubmissionService.php`, `FormOrdersController` |
| Testy | `phpunit.xml`, `database/factories/UserFactory.php`, `tests/**` |

---

## Ryzyka produkcyjne

| Ryzyko | Mitygacja |
|--------|-----------|
| Brak migracji `participant_live_access` | Provision CM zapisze błąd; uruchomić `migrate` przed pierwszym provision po deploy |
| Stare tokeny tylko w `form_orders` | Migracja `210001` — sprawdzić liczbę przeniesionych rekordów w logu migrate |
| Cron cleanup | Upewnić się, że `schedule:run` / cron hosta obejmuje `routes/console.php` (04:15) |

---

## Następny krok

1. Deploy na prod adm (migrate + smoke: provision testowy + lista uczestników CM).
2. Opcjonalnie: push commita `8963e23` jeśli jeszcze nie na remote.
3. Backlog: wysyłka linków live z listy uczestników (mail hurtowy/indywidualny).
