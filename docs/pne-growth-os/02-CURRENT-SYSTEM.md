# PNE Growth OS — stan obecnego systemu

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1
Status: stan faktyczny ustalony z repozytoriów

## Podsumowanie

System tworzą dwie osobne aplikacje Laravel 11, wdrażane z dwóch repozytoriów, ale współdzielące dane i procesy:

- `pnedu.pl` — publiczny portal, oferta, checkout, płatności i panel uczestnika,
- `adm.pnedu.pl` — panel administracyjny, backoffice i główny właściciel danych biznesowych.

To nie są dwa niezależne produkty. `pnedu.pl` czyta i zapisuje znaczną część danych bezpośrednio w bazie `pneadm`, a obie aplikacje komunikują się również przez wewnętrzne endpointy HTTP.

Kanon ogólny: [SYSTEM_OVERVIEW.md](../architecture/SYSTEM_OVERVIEW.md).

## Technologie

| Obszar | `pneadm` / adm.pnedu.pl | `pnedu` / pnedu.pl |
|---|---|---|
| Backend | Laravel `^11.31`, PHP `^8.2` | Laravel `^11.31`, PHP `^8.2` |
| Runtime lokalny | Laravel Sail, PHP 8.4 | Laravel Sail, PHP 8.4 |
| Runtime produkcyjny | PHP 8.2 na SeoHost | PHP 8.2 na SeoHost |
| Frontend | Blade, Bootstrap 5.3.3, Alpine.js, Vite 6 | Blade, Bootstrap 5.2.3, Sass, Vite 6 |
| Testy | PHPUnit 11 | PHPUnit 11 |
| Kolejki | głównie database; analityka konfigurowalna | database na produkcji; kolejki `default,analytics` |
| Cache | database/file, opcjonalnie Redis | database/file, opcjonalnie Redis |

## Odpowiedzialności aplikacji

### `adm.pnedu.pl`

- szkolenia terminowe i oferty bez terminu,
- trenerzy,
- kursy online i katalog produktów,
- zamówienia, uczestnicy i nadawanie dostępów,
- certyfikaty,
- ClickMeeting i Google Calendar,
- faktury, iFirma, KSeF, windykacja i import bankowy,
- kampanie marketingowe, krótkie linki i raporty,
- ankiety i moderacja rekomendacji,
- artykuły,
- analityka i dashboardy,
- użytkownicy panelu, role i uprawnienia.

Główne wzorce kodu:

- trasy: `pneadm/routes/web.php`, `routes/api.php`, `routes/console.php`,
- kontrolery: `pneadm/app/Http/Controllers/`,
- serwisy domenowe i integracyjne: `pneadm/app/Services/`,
- modele: `pneadm/app/Models/`,
- joby: `pneadm/app/Jobs/`,
- obserwery: `pneadm/app/Observers/`,
- UI: `pneadm/resources/views/`.

### `pnedu.pl`

- publiczna oferta i SEO,
- artykuły i treści publiczne,
- formularze zamówień,
- PayU i Paynow,
- rejestracja i konta uczestników,
- dashboard, szkolenia live, nagrania i kursy online,
- certyfikaty,
- ankiety publiczne,
- zapisy newsletterowe,
- tracking analityczny.

Główne wzorce kodu:

- trasy: `pnedu/routes/web.php`, `routes/auth.php`, `routes/console.php`,
- modele biznesowe z `protected $connection = 'pneadm'`,
- usługi checkoutu, płatności, dostępu i analityki,
- publiczne widoki Blade,
- dynamiczne `robots.txt`, `sitemap.xml` i `llms.txt`.

## Bazy danych i własność

| Baza | Właściciel logiczny | Główne dane |
|---|---|---|
| `pneadm` | `adm.pnedu.pl` | szkolenia, prowadzący, uczestnicy, produkty, zamówienia, płatności, certyfikaty, artykuły, kampanie, ankiety |
| `pnedu` | `pnedu.pl` | konta portalu, sesje, logowania i dane techniczne frontu |
| `pne_analytics` | wspólna warstwa analityczna | eventy bez PII, sesje, atrybucja i agregaty |
| `certgen` | legacy | historyczne zamówienia i certyfikaty |

Migracje do `pneadm` i `pne_analytics` są utrzymywane w projekcie `pneadm`. Migracje do `pnedu` są w projekcie `pnedu`.

## Auth, role i uprawnienia

### Panel ADM

- sesyjny guard `web`,
- model `App\Models\User` w bazie `pneadm`,
- role: `super_admin`, `admin`, `manager`, `user`,
- uprawnienia m.in. `users.*`, `courses.*`, `orders.*`, `reports.*`, `system.*`,
- większość panelu chroniona przez `auth` i `check.user.status`,
- część ograniczeń jest sprawdzana ręcznie w kontrolerach; nie wszystkie moduły mają granularne middleware.

Wniosek dla Growth OS: potrzebne jest osobne uprawnienie modułu, a operacje wykonawcze powinny mieć jeszcze węższe uprawnienia niż sam podgląd.

### Portal publiczny

- sesyjny guard `web`,
- model `App\Models\User` w bazie `pnedu`,
- weryfikacja e-mail dla panelu uczestnika,
- brak panelowych ról administracyjnych.

## Kolejki, scheduler i operacje

- Obie aplikacje używają jobów Laravel.
- Produkcja nie ma Supervisora; workery uruchamia cron z `flock`.
- `pnedu` ma osobny `schedule:run` oraz osobny worker kolejek `default,analytics`.
- `pneadm` ma osobny worker i osobne crony dla części agregacji.
- Szczegóły: [PRODUCTION_QUEUE_OPS.md](../deploy/PRODUCTION_QUEUE_OPS.md).

Growth OS nie może zakładać natychmiastowego, stałego workera. Długie zadania muszą być idempotentne, monitorowalne i odporne na ponowne uruchomienie.

## Obecne integracje

- PayU, Paynow,
- ClickMeeting,
- Sendy,
- AWS SES/SNS,
- iFirma i KSeF,
- GUS BIR i RSPO,
- Google Calendar,
- Google Analytics / GTM i Facebook Pixel,
- Publigo i `certgen` jako elementy legacy,
- wewnętrzne API `pneadm` ↔ `pnedu`.

## Modele istotne dla Growth OS

| Obszar | Istniejące modele / tabele | Ocena |
|---|---|---|
| ekspert | `Instructor`, `instructors` | dobra kotwica; wymaga później profilu Growth |
| szkolenie/webinar | `Course`, `courses` | instancja z terminem, nie temat ani kampania |
| oferta B2B | `TrainingOffer`, `training_offers` | produktowa/ofertowa kotwica |
| kurs VOD | `OnlineCourse`, moduły i lekcje | istniejący zasób edukacyjny |
| artykuł | `Article`, `articles` | istniejący typ treści |
| materiały | `CourseVideo`, `CourseFileLink`, lekcje online | rozproszone zasoby; brak wspólnej biblioteki |
| produkt | `Product`, `products`, oferty i ceny | katalog sprzedażowy; nie obejmuje całej semantyki kampanii |
| kampania linkowa | `MarketingCampaign` | atrybucja i UTM, nie kampania strategiczna |
| osoba | `PneduUser`, `Participant`, enrollmenty, odbiorcy zamówień | wiele reprezentacji bez globalnego ID |
| szkoła | snapshoty `buyer_*` / `recipient_*` w zamówieniu | brak kanonicznej encji organizacji |
| interakcja | eventy analityczne, ankiety, logowania, activity log | rozproszone źródła |
| metryka | agregaty `pne_analytics`, statystyki kampanii/kursów | dobre źródła read-only |

## Krytyczne kolizje pojęć

1. `form_orders.product_id` oznacza historycznie `courses.id`, nie `products.id`.
2. „Produkt” występuje jako szkolenie terminowe, kurs online, rekord katalogu `products` i zasób legacy.
3. `MarketingCampaign` oznacza kampanię atrybucyjną/linkową, nie inicjatywę z celem, ekspertem i zestawem treści.
4. `users` w `pneadm` to operatorzy panelu, a `users` w `pnedu` to odbiorcy.
5. Uczestnik, konto portalu i odbiorca produktu są łączone głównie po e-mailu, bez wspólnego identyfikatora osoby.
6. Autor artykułu wskazuje operatora ADM lub tekst `author_name`, a nie zawsze `Instructor`.
7. Newsletter jest przechowywany głównie w Sendy; nie ma jednego rejestru relacji i zgód.

## Miejsca, których Growth OS nie może naruszyć

- zapis i edycja `FormOrder`,
- webhooki i statusy PayU/Paynow,
- fulfillment i provision dostępu,
- faktury, iFirma i KSeF,
- certyfikaty,
- istniejące konta i logowanie,
- semantyka `form_orders.product_id`,
- publiczne SEO i checkout,
- crony i workery bez jawnego runbooka.

## Deployment

- produkcja: SeoHost, dwa osobne katalogi aplikacji,
- migracje produkcyjne: wyłącznie `migrate --force`,
- lokalnie: wszystkie komendy PHP/Laravel przez Sail,
- backup: SeoHost Backup Manager oraz nocny dump MySQL,
- brak workflow CI w repozytoriach; wdrożenia są operowane według runbooków.

Kanon: [PRODUCTION_PATHS.md](../deploy/PRODUCTION_PATHS.md) i [DATA_SAFETY.md](../DATA_SAFETY.md).
