# ClickMeeting embed PoC (local)

Strona testowa: `http://localhost:8083/dev/clickmeeting-embed-poc?key=...`

Działa tylko przy `APP_ENV=local` + poprawnym `CLICKMEETING_POC_SECRET`.

**Produkcyjny embed na pnedu.pl** (radio w kursie, `/transmisja`, tokeny): `pnedu/docs/DASHBOARD_LIVE_EMBED.md`.

## .env

```env
CLICKMEETING_API_TOKEN=...
CLICKMEETING_API_URL=https://api.clickmeeting.com/v1/
CLICKMEETING_POC_SECRET=wygeneruj-losowy-ciag
CLICKMEETING_POC_ROOM_ID=
CLICKMEETING_POC_EMAIL=
```

## Ważne odkrycie PoC

`embed_room_url` z API ClickMeeting to **adres skryptu** (`Content-Type: application/javascript`), nie HTML.

- ❌ `<iframe src="{embed_room_url}">` → przeglądarka pokazuje kod JS
- ✅ oficjalnie: `<script src="{embed_room_url}">` (skrypt wstawia iframe)
- ✅ praktycznie: iframe na `{host}/{room_pin}?popup=off&lang=pl` (+ opcjonalnie `&l={autologin_hash}`)

## Warianty na stronie PoC

| Wariant | Opis |
|---------|------|
| `iframe_autologin` | iframe na room_pin + auto-login (domyślny) |
| `iframe_plain` | iframe na room_pin bez auto-login |
| `official_script` | oficjalny `<script src="embed_room_url">` |

## Testy

```bash
sail test --filter=ClickMeetingEmbedPocTest
sail test --filter=ClickMeetingServiceTest
```
