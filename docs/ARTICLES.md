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

---

## Zarządzanie w panelu (`adm.pnedu.pl`)

### Wejście

- Menu główne: **Artykuły** (nad **Księgowość**).
- Lista: `/articles`
- Dodawanie: **Dodaj artykuł**
- Podgląd / edycja / usuwanie: akcje w wierszu tabeli.

### Typowy workflow redakcyjny

1. **Utwórz szkic** (`draft`) — treść niewidoczna na `pnedu.pl`.
2. Uzupełnij: tytuł, slug, excerpt, treść HTML, opcjonalnie okładkę.
3. Uzupełnij **SEO**: `meta_title`, `meta_description` (patrz sekcja poniżej).
4. Ustaw **status `published`** i **datę publikacji** (`published_at`) — może być teraz lub w przyszłości (zaplanowany start).
5. **Podgląd w panelu** → sprawdź układ.
6. **Publicznie:** `https://pnedu.pl/blog/{slug}` (tylko gdy opublikowany i data ≤ teraz).

### Kolejność na blogu

- Kolumna **Kolej.** — widoczna **bez filtrów** na liście artykułów.
- Przeciągnij wiersz lub użyj strzałek góra/dół.
- `sort_order = 0` = góra listy `/blog`.
- Nowy artykuł domyślnie trafia **na górę**.

### Wyświetlenia (statystyka)

- Kolumna **Wyśw.** na liście i pole w podglądzie artykułu.
- Liczy wejścia na publiczny URL `/blog/{slug}`.
- **Reguły zliczania** = jak analityka w panelu (**Analityka → Ustawienia**): wyłączenie, tryb, sampling, cookie `pne_skip_analytics`, boty.
- Max. **raz na sesję** `pne_analytics_sid` (nie każde odświeżenie strony).
- Licznik w nagłówku artykułu na froncie też pochodzi z `view_count`.

### Pola formularza — skrót

| Pole | Znaczenie |
|------|-----------|
| Tytuł | Widoczny na blogu; podstawa slug i SEO, gdy brak meta |
| Slug | URL: `/blog/{slug}` — unikalny, SEO-friendly |
| Krótki opis (excerpt) | Lead na liście i w hero artykułu |
| Treść HTML | Główna treść; w edytorze `&nbsp;` zapisuje się poprawnie |
| Status | `draft` / `published` |
| Data publikacji | Wymagana do widoczności publicznej |
| Meta title / Meta description | SEO (nadpisują domyślne z tytułu/opisu) |
| Grafika główna | Opcjonalna okładka; plik SEO na dysku `public` |
| Komentarze włączone | Przygotowanie pod etap 2 (brak publicznego formularza) |
| Notatki wewnętrzne | Tylko panel, nigdy na froncie |

### `&nbsp;` w tytule i treści

- W formularzu można wpisywać `&nbsp;` — przy zapisie trafia jako twarda spacja (U+00A0).
- W edytorze przy ponownym otwarciu widać z powrotem `&nbsp;` (czytelność dla redaktora).

---

## Wytyczne SEO dla artykułów (redaktor / admin)

Pełny kanon techniczny frontu: **`pnedu/SEO.md`**. Checklista Search Console: **`pnedu/docs/GSC_CHECKLIST.md`**.

### Meta title

- **Cel:** ok. **50–65 znaków** (główna fraza + sens, bez przeładowania marką).
- Jeśli puste → używany tytuł artykułu + sufiks layoutu.
- **Unikaj** końcówki typu „- Platforma Nowoczesnej Edukacji” w `meta_title` — layout dodaje markę osobno; raport SEO wskazywał zbyt długie title (95+ znaków).

### Meta description

- **Cel:** ok. **120–160 znaków**.
- Jedno zdanie odpowiedzi na intencję użytkownika + konkret (termin, procedura, dla kogo).
- Jeśli puste → excerpt, potem skrót treści.

### Treść i nagłówki

- **Jeden `h1`** — tytuł artykułu (generowany z pola tytułu).
- Logiczna hierarchia **`h2` / `h3`** — nie pomijaj poziomów.
- Krótkie akapity, listy, tabele tam gdzie ułatwiają skanowanie (ważne pod AI Overviews).
- **Linki wewnętrzne:** z artykułu do powiązanych szkoleń (`/courses/{id}`) i innych artykułów (klastry tematyczne).
- **Obrazy:** sensowny `alt` (w treści HTML).

### Slug

- Krótki, po polsku, bez zbędnych końcówek (`-1`, `-2`) — jeśli slug już w indeksie, zmiana wymaga **301** (decyzja biznesowa).
- Slug generuje się z tytułu; można edytować ręcznie przed pierwszą publikacją.

### Po publikacji (operacyjnie)

1. Sprawdź `https://pnedu.pl/blog/{slug}`.
2. W **Google Search Console** → Inspekcja URL → **Poproś o indeksowanie** (świeże artykuły).
3. Upewnij się, że sitemap działa: `https://pnedu.pl/sitemap.xml` (HTTP 200).
4. Po **~3–7 dniach** sprawdź w GSC → Skuteczność (zapytania, strony).

### Co robi front automatycznie

- JSON-LD: `BlogPosting`, `BreadcrumbList`, `WebPage`.
- Wpis w **dynamicznej sitemapie** (opublikowane artykuły).
- Wpis w **`/llms.txt`** (20 najnowszych).
- Udostępnianie (FB, LinkedIn, X, e-mail, kopiuj link).
- Licznik wyświetleń w nagłówku (przy aktywnej analityce).

---

## Migracje (pneadm)

| Migracja | Opis |
|----------|------|
| `2026_08_23_000001_create_articles_table.php` | Tabela `articles` |
| `2026_08_23_000002_add_sort_order_to_articles_table.php` | Kolejność na blogu |
| `2026_08_23_000003_add_view_count_to_articles_table.php` | Licznik wyświetleń |

Prod (panel):

```bash
cd ~/domains/adm.pnedu.pl/pneadm
/opt/alt/php82/usr/bin/php artisan migrate --path=database/migrations/2026_08_23_000003_add_view_count_to_articles_table.php --force
```

(lub `--force` pełne migrate, jeśli brakuje wcześniejszych).

---

## Powiązana dokumentacja

| Dokument | Projekt | Temat |
|----------|---------|--------|
| [BLOG_ARTICLES.md](../../pnedu/docs/BLOG_ARTICLES.md) | pnedu | Front publiczny, trasy, schema |
| [SEO.md](../../pnedu/SEO.md) | pnedu | Wytyczne SEO całego serwisu |
| [GSC_CHECKLIST.md](../../pnedu/docs/GSC_CHECKLIST.md) | pnedu | Google Search Console po audycie |
| [TESTING.md](./TESTING.md) | pneadm | Smoke test artykułów / bloga |

*Ostatnia aktualizacja: 2026-08-23.*
