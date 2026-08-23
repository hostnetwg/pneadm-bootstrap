# Artykuły / Blog pnedu.pl

## Cel

Moduł `Artykuły` w panelu `pneadm` służy do zarządzania wpisami publikowanymi publicznie na `pnedu` pod adresem `/blog`.

## Właściciel danych

- Tabela: `articles`
- Baza danych: `pneadm`
- Migracje: `pneadm/database/migrations/`
- Panel administracyjny: `pneadm`
- Publiczny odczyt: `pnedu`, model z połączeniem `pneadm`

## Zakres etapu 1

- Menu główne `Artykuły` w panelu, nad menu `Księgowość`.
- CRUD artykułów dla każdego zalogowanego użytkownika panelu.
- **Kolejność na blogu:** kolumna `sort_order` (0 = góra listy). Przeciąganie wierszy na liście artykułów (bez filtrów). Nowy artykuł trafia na górę.
- **Wyświetlenia:** kolumna `view_count` — licznik wejść na publiczny artykuł (`/blog/{slug}`). Zliczanie respektuje ustawienia analityki z panelu (`Analityka → Ustawienia`): wyłączenie, tryb, sampling, opt-out cookie `pne_skip_analytics`, boty. Max. raz na sesję analityczną (`pne_analytics_sid`). Widoczna w liście artykułów i podglądzie w panelu.
- Statyczna podstrona `articles.example-preview` z przykładowym podglądem układu artykułu.
- Statusy:
  - `draft` - szkic niewidoczny publicznie.
  - `published` - artykuł widoczny na publicznym blogu po dacie `published_at`.
- Podgląd artykułu w panelu.
- Pola SEO: `meta_title`, `meta_description`.
- Grafika główna zapisywana na dysku `public`.
- Nazwa pliku grafiki: `slug-artykulu-xxxxxx.rozszerzenie` (SEO + unikalny sufiks przy każdym uploadzie, bez problemu cache).
- Pole `comments_enabled` przygotowane pod etap komentarzy.

## Publiczna strona pnedu

`pnedu` czyta opublikowane artykuły z bazy `pneadm`:

- `/blog` — lista opublikowanych artykułów (kolejność: `sort_order`, potem data publikacji).
- `/blog/{slug}` — szczegóły artykułu.
- **Wyświetlenia:** przy każdym kwalifikującym się wejściu na artykuł inkrementowany jest `view_count` (reguły jak w `Analityka → Ustawienia`, max. raz na sesję `pne_analytics_sid`).
- **Liczniki „nowe” przy menu Blog:** czerwona plakietka z liczbą artykułów opublikowanych po ostatniej wizycie użytkownika na blogu (stan w `localStorage` przeglądarki — znika po wejściu na `/blog` lub artykuł).
- Sitemap zawiera dynamiczne adresy opublikowanych artykułów.

## Komentarze - etap 2

Komentarze nie są częścią etapu 1. Rekomendowany następny etap:

- tabela `article_comments` w bazie `pneadm`,
- publiczny formularz komentarza w `pnedu`,
- moderacja w panelu `pneadm`,
- statusy `pending`, `approved`, `rejected`, `spam`,
- honeypot, rate limiting i minimalny czas wypełnienia formularza,
- opcjonalnie Cloudflare Turnstile lub reCAPTCHA.

Komentarze powinny być publicznie widoczne dopiero po zatwierdzeniu.
