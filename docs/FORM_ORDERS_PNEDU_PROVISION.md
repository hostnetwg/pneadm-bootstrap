# Provision PNEDU z zamówienia formularza („Dodaj tylko do PNEDU”)

Data aktualizacji: 2026-08-11 (ważność linku ustawienia hasła: 2 miesiące)  


Runbook deploy: [deploy/2026-07-participant-live-access-and-tests.md](./deploy/2026-07-participant-live-access-and-tests.md)

Panel: `/form-orders/{id}` → przycisk **Dodaj tylko do PNEDU** (lub wariant z Sendy).

Serwis: `App\Services\FormOrderPneduProvisionService`  
Endpoint: `POST /form-orders/{id}/pnedu/provision`

## Kroki procesu (kolejność)

| Krok | Operacja | Uwagi |
|------|----------|--------|
| **1** | Rekord w `participants` + konto w `pnedu.users` (utworzenie lub powiązanie) | W jednej transakcji DB; **bez** wysyłki e-maila. Gdy uczestnik z tym e-mailem **już jest** na szkoleniu (np. po resecie statusu) — **ponowne powiązanie** zamiast błędu. |
| **2** | ClickMeeting (best-effort) | Tylko gdy `course_online_details.platform = clickmeeting` i jest `clickmeeting_event_id` |
| **3** | E-mail do uczestnika | Zawsze próbowany po kroku 2, niezależnie od wyniku ClickMeeting |

Status w panelu: `form_orders.pnedu_provisioned_at`, `pnedu_user_existed_before`, pola `pnedu_clickmeeting_*`.

## Liczniki na liście szkoleń

Panel `/courses`, kolumna **U**, pokazuje dwa niezależne liczniki operacyjne dla zamówień FORM danego szkolenia:

| Badge | Znaczenie | Link |
|-------|-----------|------|
| `U` | Ważne zamówienia, w których trzeba jeszcze dodać uczestnika do szkolenia | `/form-orders/{latestId}?filter_no_participant=1&course_id={courseId}` (najwyższe id z tego zbioru) |
| `FV` | Ważne zamówienia bez wystawionej faktury i bez oznaczenia „Bezpłatny dostęp - bez FV” | `/form-orders?quick=all&filter=needs_invoice&course_id={courseId}` |

Zamówienie może jednocześnie zwiększać oba liczniki, dopóki nie zostanie zamknięty zarówno dostęp uczestnika, jak i rozliczenie. Anulowane zamówienia (`cancelled_at`) oraz zamówienia zamknięte legacy (`legacy_handled_at`) nie są liczone w tych badge. Oznaczenie `invoice_exempt_at` zamyka tylko etap faktury; jeśli uczestnik nie został dodany, zamówienie nadal może widnieć w liczniku `U`.

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

### Pola w bazie

**Snapshot na zamówieniu (`form_orders`)** — widoczny w kroku 2 panelu zamówienia:

| Kolumna | Opis |
|---------|------|
| `pnedu_clickmeeting_status` | `success`, `failed`, `skipped_not_clickmeeting`, `skipped_missing_event_id` |
| `pnedu_clickmeeting_synced_at` | Ostatnia próba integracji |
| `pnedu_clickmeeting_message` | Szczegóły dla panelu |

**Token i link na żywo (`participant_live_access`)** — źródło prawdy per uczestnik:

| Kolumna | Opis |
|---------|------|
| `participant_id` | FK → `participants` (unikalny rekord na uczestnika) |
| `form_order_id` | nullable — skąd przyszedł provision |
| `course_id` | FK → `courses` |
| `token` | Token CM (gdy `access_type = 3`) |
| `room_url`, `access_type`, `clickmeeting_event_id` | Kontekst linku |
| `status`, `message`, `synced_at` | Status integracji |
| `expires_at` | Koniec szkolenia — rekord kasowany przez `participants:cleanup-live-access` |

Serwis: `App\Services\ParticipantLiveAccessService`

Migracje: `2026_04_09_000003_*`, `2026_07_13_210000_create_participant_live_access_table.php`, `2026_07_13_210001_migrate_clickmeeting_tokens_to_participant_live_access.php`

**Token ClickMeeting:** przypisany do **e-maila uczestnika** — blokuje równoległe wejście tą samą parą e-mail+token; po wyjściu ze spotkania można użyć ponownie (zgodnie z testami prod/dev). Nie mylić z trybem „Hasło” / „Dostępny dla wszystkich”, gdzie link można swobodnie udostępniać.

### Panel uczestnika (pnedu)

Na `/dashboard/szkolenia` uczestnik widzi przycisk **Dołącz do spotkania na żywo** (przed startem i w trakcie), z licznikiem czasu oraz opcjonalnym hasłem — szczegóły: `pnedu/docs/DASHBOARD_LIVE_MEETING.md`.

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

Admin / super_admin: przycisk **Resetuj status PNEDU** — czyści m.in. `pnedu_provisioned_at`, `pnedu_user_existed_before`, `pnedu_clickmeeting_*` oraz rekord `participant_live_access` powiązany z uczestnikiem zamówienia.

**Nie usuwa** rekordu z tabeli `participants` ani konta `pnedu.users`.  
Ponowne **Dodaj uczestnika do PNEDU** odnajduje uczestnika po `course_id` + e-mail, wiąże go z zamówieniem, ustawia `pnedu_provisioned_at` i ponawia kroki ClickMeeting + e-mail.

## Ponowna wysyłka e-maila (krok 3)

Na `/form-orders/{id}` przy przyznanym PNEDU: przycisk **Prześlij dostęp ponownie** (obok resetu).

1. Modal z podglądem treści (jak krok 3: nowe konto → link ustawienia hasła; istniejące → mail informacyjny).
2. **Anuluj** / **Skopiuj treść** / **Wyślij**.
3. Endpoints: `GET …/pnedu/access-email-preview`, `POST …/pnedu/resend-access-email`.
4. Przy „nowe konto” świeży token resetu hasła powstaje dopiero przy **Wyślij** (w podglądzie placeholder).  
   Ponowna wysyłka **unieważnia** poprzedni link ustawienia hasła (Laravel: jeden aktywny token na e-mail).

## Ważność linku „ustaw hasło”

Link w mailu dla nowego konta to standardowy token resetu Laravel (`password_reset_tokens`).  
Ważność: `PASSWORD_RESET_EXPIRE_MINUTES` w `.env` **pnedu** i **pneadm** (domyślnie **86400** = 60 dni ≈ **2 miesiące**).

- Walidacja przy kliknięciu odbywa się na **pnedu.pl** (`config/auth.php` → `passwords.users.expire`).
- Po zmianie na produkcji: ustaw `.env`, potem `php artisan config:clear` i ewentualnie `config:cache` na **obu** aplikacjach.
- Komunikat „link nieprawidłowy lub wygasł” obejmuje też: token już użyty, nowszy token z „Prześlij dostęp” / „Nie pamiętam hasła”, uszkodzony URL.

W mailu: informacja o 2 miesiącach + link awaryjny do `/forgot-password`.

## Uczestnicy kursu — ręczna rejestracja ClickMeeting

Lista: `/courses/{id}/participants` → przycisk **ClickMeeting** (gdy platforma = clickmeeting, jest event_id, szkolenie nie zakończone).

Route: `POST /courses/{course}/participants/{participant}/provision-live-access`

Gdy widoczny jest token (`CM: …`):

- **Unieważnij token** — `DELETE` w API ClickMeeting (`…/conferences/{event_id}/tokens` + lista tokenów), potem czyszczenie lokalnego `participant_live_access.token`. Status CM OK zostaje; ponowne pobranie przez przycisk CM OK.
- **Wyślij link do live** — e-mail systemowy z bezpośrednim linkiem do spotkania (Notification `ParticipantLiveMeetingLinkNotification`, log `certificate_email_logs.type = live_meeting_link`).

Routes:

- `POST /courses/{course}/participants/{participant}/invalidate-live-access-token`
- `POST /courses/{course}/participants/{participant}/send-live-meeting-link`
- `POST /courses/{course}/participants/send-live-meeting-links-bulk` — zbiorcza wysyłka (tryby `unsent` / `resend_all`)
  - pokój z tokenami (`access_type = 3`): tylko uczestnicy z tokenem
  - pokój bez tokenu: wszyscy z e-mailem, wspólny `room_url` / `meeting_link`
  - kolejka: `SendLiveMeetingLinkEmailJob`, log `certificate_email_logs.type = live_meeting_link`

## Cleanup tokenów po szkoleniu

```bash
sail artisan participants:cleanup-live-access
sail artisan participants:cleanup-live-access --dry-run
```

Cron: codziennie 04:15 (`routes/console.php`) — usuwa całe rekordy `participant_live_access` z `expires_at` w przeszłości.

## Testy

```bash
sail test --filter=ClickMeetingServiceTest
sail test --filter=PneduProvisionEmailContextBuilderTest
sail test --filter=ParticipantLiveAccessServiceTest
sail test --filter=FormOrderPneduProvisionRelinkTest
sail test --filter=FormOrderPneduAccessEmailResendTest
sail test   # pełny suite — patrz docs/TESTING.md
```

Konfiguracja testów (izolacja bazy analityki, `is_active` w fabryce): **[TESTING.md](./TESTING.md)**.

## Konfiguracja kursu online

W edycji kursu (`/courses/{id}/edit`):

- **Platforma:** `clickmeeting` (małymi literami — wymagane przez provision)
- **ID wydarzenia ClickMeeting:** `room_id` z panelu CM
- **Link do spotkania:** opcjonalny fallback / inne platformy
- **Hasło do spotkania:** gdy wydarzenie na hasło
