# Provision PNEDU z zamówienia formularza („Dodaj tylko do PNEDU”)

Panel: `/form-orders/{id}` → przycisk **Dodaj tylko do PNEDU** (lub wariant z Sendy).

Serwis: `App\Services\FormOrderPneduProvisionService`  
Endpoint: `POST /form-orders/{id}/pnedu/provision`

## Kroki procesu (kolejność)

| Krok | Operacja | Uwagi |
|------|----------|--------|
| **1** | Rekord w `participants` + konto w `pnedu.users` (utworzenie lub powiązanie) | W jednej transakcji DB; **bez** wysyłki e-maila |
| **2** | ClickMeeting (best-effort) | Tylko gdy `course_online_details.platform = clickmeeting` i jest `clickmeeting_event_id` |
| **3** | E-mail do uczestnika | Zawsze próbowany po kroku 2, niezależnie od wyniku ClickMeeting |

Status w panelu: `form_orders.pnedu_provisioned_at`, `pnedu_user_existed_before`, pola `pnedu_clickmeeting_*`.

## ClickMeeting

### Integracja API

- Serwis: `App\Services\ClickMeetingService`
- Konfiguracja: `.env` → `CLICKMEETING_API_TOKEN`, `CLICKMEETING_API_URL` (domyślnie `https://api.clickmeeting.com/v1/`)
- Dodanie uczestnika: `POST .../conferences/{event_id}/invitation/email/pl` (fallback: `POST .../registration`)
- Pobranie tokenu (gdy `access_type = 3`): `POST .../conferences/{event_id}/token` + fallback `GET .../tokens`
- Dane wydarzenia: `GET .../conferences/{event_id}` → `room_url`, `access_type`

Stałe `access_type` (API ClickMeeting):

| Wartość | Znaczenie |
|---------|-----------|
| `1` | Otwarty dostęp |
| `2` | Hasło |
| `3` | Token (jednorazowy na uczestnika) |

### Pola w bazie (`form_orders`)

| Kolumna | Opis |
|---------|------|
| `pnedu_clickmeeting_status` | `success`, `failed`, `skipped_not_clickmeeting`, `skipped_missing_event_id` |
| `pnedu_clickmeeting_synced_at` | Ostatnia próba integracji |
| `pnedu_clickmeeting_message` | Szczegóły dla panelu |
| `pnedu_clickmeeting_token` | Token dostępu (gdy pobrany) |

Migracje: `2026_04_09_000003_*`, `2026_07_13_160000_add_pnedu_clickmeeting_token_to_form_orders_table.php`

### Link do spotkania w e-mailu

Builder: `App\Services\PneduProvisionEmailContextBuilder`

- **ClickMeeting + sukces API:** `room_url` z API; przy `access_type = 3` link `{room_url}/{token}` (np. `https://pnedu.clickmeeting.com/wydarzenie-testowe/MCHK7N`)
- **Fallback `room_url`:** `course_online_details.meeting_link` (gdy API nie zwróci URL)
- **Hasło:** `course_online_details.meeting_password` (gdy ustawione lub `access_type = 2`)
- **Inne platformy** (YouTube, Google Meet, Zoom…): `meeting_link` z kursu
- **Szkolenie zakończone** (`end_date` w przeszłości lub sam `start_date` minął bez `end_date`): **brak** sekcji spotkania na żywo — tylko materiały na pnedu.pl
- **ClickMeeting fail** lub brak tokenu przy evencie tokenowym: **brak** sekcji ClickMeeting w mailu (bez błędu dla uczestnika)

Kontekst maila: `App\Support\PneduProvisionLiveAccessContext`  
Formatowanie HTML: `App\Notifications\Concerns\FormatsPneduProvisionLiveAccess`

Notyfikacje: `PneduFormOrderProvisionedExistingUser`, `PneduFormOrderProvisionedNewUser` (nadawca systemowy: `UsesSystemMailSettings`).

## E-mail lokalnie (Mailpit)

W projekcie **pneadm** (Sail):

| Usługa | URL / port |
|--------|------------|
| Mailpit UI | http://localhost:8026 |
| SMTP (z kontenera Laravel) | `mailpit:1025` |
| SMTP (z hosta WSL) | `localhost:1027` |

Wymagane w `.env` (dev):

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_SYSTEM_MAILER=smtp
```

Po zmianie: `sail artisan config:clear`

Maile provision używają **`MAIL_SYSTEM_MAILER`** — przy wartości `log` trafiają do `storage/logs/laravel.log`, nie do Mailpit.

## Reset statusu PNEDU

Admin / super_admin: przycisk **Resetuj status PNEDU** — czyści m.in. `pnedu_provisioned_at`, `pnedu_clickmeeting_*` (w tym token).

## Testy

```bash
sail test --filter=ClickMeetingServiceTest
sail test --filter=PneduProvisionEmailContextBuilderTest
```

## Konfiguracja kursu online

W edycji kursu (`/courses/{id}/edit`):

- **Platforma:** `clickmeeting` (małymi literami — wymagane przez provision)
- **ID wydarzenia ClickMeeting:** `room_id` z panelu CM
- **Link do spotkania:** opcjonalny fallback / inne platformy
- **Hasło do spotkania:** gdy wydarzenie na hasło
