# Testy — pneadm (Laravel Sail)

Data aktualizacji: 2026-09-19

## Cel

Opisuje jak uruchamiać testy lokalnie oraz konfigurację `phpunit.xml`, która izoluje bazę testową od współdzielonej bazy analityki deweloperskiej (`pne_analytics`).

## Szybki start

```bash
sail up -d
sail test                            # pełny suite — PHPUnit sam ustawia bazę `testing`
sail test --filter=NazwaTestu        # pojedyncza klasa / metoda
sail pint                            # formatowanie przed commitem
```

**Nigdy** `sail artisan migrate:fresh` ani `migrate:fresh --env=testing` bez pliku `.env.testing` z `DB_DATABASE=testing`. Sam `--env=testing` **nie** czyta `phpunit.xml` — bez `.env.testing` Artisan bierze `.env` i bazę **pneadm** (to wyczyściło lokalne dane 19.09.2026). Od 19.09 aplikacja **blokuje** `migrate:fresh` / `db:wipe` na każdej bazie innej niż `testing` (`docs/DATA_SAFETY.md`). `sail test` jest bezpieczny, bo PHPUnit nadpisuje `DB_DATABASE=testing`.

**Oczekiwany stan (2026-07-26):** po zmianie reguły duplikatów dodano `FormOrderDuplicatesDetectionTest` — uruchom `sail test --filter=FormOrderDuplicatesDetectionTest`.

## Konfiguracja `phpunit.xml`

Kluczowe zmienne środowiskowe w `<php>`:

| Zmienna | Wartość testowa | Dlaczego |
|---------|-----------------|----------|
| `DB_DATABASE` | `testing` | Baza główna odświeżana przez `RefreshDatabase` |
| `DB_ANALYTICS_DATABASE` | `testing` | Tabele analityki w tej samej bazie co migrate:fresh — unikamy błędu „table already exists” w `pne_analytics` |
| `DB_ANALYTICS_HOST` | `mysql` | Połączenie w kontenerze Sail |
| `ANALYTICS_ENABLED` | `false` | Globalny kill-switch; testy jednostkowe analityki włączają tracking lokalnie przez `config(['analytics.enabled' => true])` |

**Nie uruchamiaj** testów z `RefreshDatabase` bez tej izolacji — migracja `2026_06_24_120000_create_pne_analytics_mvp_tables.php` tworzy tabele w connection `analytics`, które domyślnie w dev wskazuje na `pne_analytics`.

## Wzorce w testach

### Użytkownicy panelu (`UserFactory`)

Fabryka ustawia `'is_active' => true`. Middleware `CheckUserStatus` wylogowuje nieaktywnych — bez tego testy auth/profile zwracają redirect na `/login`.

### Rejestracja (`RegistrationTest`)

Trasy `/register` są zakomentowane w `routes/auth.php` — testy oznaczone `markTestSkipped`.

### Soft delete użytkownika (`ProfileTest`)

Model `User` używa `SoftDeletes` — po usunięciu konta: `assertSoftDeleted($user)`, nie `assertNull($user->fresh())`.

### Analityka — dashboard / agregaty

Testy feature z własnym schematem SQLite (`AnalyticsSalesFunnelDashboardTest`, `AnalyticsOrderFormFunnelAggregationTest`) w `setUp()` nadpisują `database.connections.analytics` na `:memory:`.

Testy zależne od daty (domyślny zakres „ostatnie 14 dni”, filtry okresu faktur prowadzących) muszą przekazywać jawne `date_from` / `date_to` lub używać `Carbon::setTestNow()`.

### Sendy (`SendyServiceTest`)

Testy integracyjne z prawdziwym API Sendy — wyjątki mockowane przez `Http::fake()` (np. nieprawidłowy `list_id`).

### Przypomnienia o wygaśnięciu dostępu

`ParticipantAccessExpiryReminderServiceTest` zamraża czas (`Carbon::setTestNow('2026-06-09')`), bo serwis filtruje uczestników z `access_expires_at > now()`.

## Testy modułów (skrót)

| Moduł | Filtr / plik |
|-------|----------------|
| ClickMeeting / provision PNEDU | `--filter=ClickMeetingServiceTest`, `PneduProvisionEmailContextBuilderTest`, `ParticipantLiveAccessServiceTest`, `SystemMailConfigurationTest` |
| ClickMeeting lista → courses | `--filter=ClickMeetingTrainingAdminTest`; kanon: [CLICKMEETING_TRAININGS.md](./CLICKMEETING_TRAININGS.md) |
| Historia wersji ADM / pnedu.pl | `--filter=ReleaseChangelog`; mail `--filter=ReleaseChangelogNotify`; kanon: [CHANGELOG_VERSIONING.md](./CHANGELOG_VERSIONING.md) |
| ClickMeeting sync `room_url` / maile live | `--filter=ClickMeetingTrainingAdminTest`, `--filter=ParticipantLiveMeetingLinkMailServiceTest` |
| Ustawienie hasła (nowe konto PNEDU) | **pnedu:** `sail test --filter=PasswordResetTest` |
| ClickMeeting embed PoC (local) | `docs/DEV_CLICKMEETING_EMBED_POC.md`, `--filter=ClickMeetingEmbedPocTest` |
| Osadzony pokój na pnedu (`embed_on_pnedu`) / radio live / link embed w mailu | migracje `2026_08_20_200210_*`, `2026_08_21_181500_*`, `2026_08_21_182800_*`, `2026_08_22_131100_*`; kanon: `pnedu/docs/DASHBOARD_LIVE_EMBED.md` |
| KSeF / iFirma | `--filter=FormOrderKsefHelpersTest`, `--filter=IfirmaAdditionalEntityMapperTest`, `--filter=IfirmaKontrahentBuilderTest`, `--filter=IfirmaFormOrderKsefSyncServiceTest`, `--filter=IfirmaFormOrderKsefSubmissionServiceTest`, `--filter=IfirmaFormOrderKsefBackgroundServiceTest`, `--filter=IfirmaPelnyNumerExtractionTest`, `--filter=FormOrdersNavigationFilterCountTest`, `--filter=FormOrderIfirmaInvoiceNamePrefixTest` |
| Raporty automatów | `--filter=IfirmaFormOrderKsefBackgroundServiceTest` (lista `/ops-reports`); kanon: [OPS_REPORTS.md](./OPS_REPORTS.md) |
| Windykacja | `--filter=AccountingCollectionsTest`, `--filter=AccountingDebtorsLookupKsefTest`, `--filter=IfirmaInvoicePaymentStatusServiceTest`, `--filter=IfirmaInvoicePaymentRegistrationServiceTest`, `--filter=DebtCaseAutoCloseServiceTest`, `--filter=BankStatementImportTest`, `--filter=MbankStatementParserTest`, `--filter=PaymentTitleExtractorTest`, `--filter=BankTransactionMatcherTest` |
| Analityka lejka | `--filter=AnalyticsOrderFormFunnelAggregationTest` |
| Legalny checkout / dokumenty | **pnedu:** `tests/Unit/LegalCheckoutServiceTest.php`, `tests/Unit/SendyOperationalConsentTest.php`, `tests/Feature/LegalDocumentsTest.php`, `tests/Feature/AnalyticsConsentTest.php`; **pneadm:** `tests/Unit/PneduProvisionArticle14NoticeTest.php` |
| Wielu uczestników create/edit FORM | `--filter=FormOrderAdminMultipleParticipantsTest` |
| Lejek na `/courses` | `--filter=CourseFunnelStatsServiceTest`, `--filter=CoursesIndexStatsTest` |
| Wygląd wiersza nieaktywnego na `/courses` | `--filter=CoursesIndexStatsTest` (klasa `course-row-inactive` vs `table-secondary` dla zakończonych aktywnych) |
| Ankiety | **Brak** dedykowanych testów (import CSV, PDF, bramka). Smoke ręczny: import, PDF, `pnedu.pl/ankieta/{token}` → `/rekomendacja` → `/dziekujemy`; ponowne wejście (anon: cookie / nieanon: ten sam e-mail) → „już wypełniona”. Kanon: [SURVEYS.md](./SURVEYS.md). |
| Artykuły / blog | Brak dedykowanych testów automatycznych. Smoke ręczny: `pneadm` → `Artykuły` → dodaj szkic, opublikuj z datą `published_at`, sprawdź `/blog` i `/blog/{slug}` w `pnedu`, sprawdź sitemapę; wejdź na artykuł 2× w tej samej sesji analitycznej (licznik +1), w incognito +1; wyłącz analitykę w panelu → licznik stoi; kolumna „Wyśw.” w panelu. SEO: meta title/description wg `ARTICLES.md`; GSC wg `pnedu/docs/GSC_CHECKLIST.md`. Testy: `--filter=ArticlePageViewTrackerTest`, `--filter=SeoSitemapTest`, `--filter=CourseSeoServiceTest`. Kanon: [ARTICLES.md](./ARTICLES.md), [pnedu/docs/BLOG_ARTICLES.md](../pnedu/docs/BLOG_ARTICLES.md), [pnedu/SEO.md](../pnedu/SEO.md). |
| Import CSV dostępów kursów online (Publigo) | `--filter=OnlineCourseEnrollmentPubligoImport` |
| Lista dostępów kursu online (wyszukiwarka, filtry, sort) | `--filter=OnlineCourseEnrollmentListQueryTest` |
| E-mail przeniesienia kursu online na pnedu.pl | `--filter=OnlineCourseEnrollmentPlatformMigrationMailTest`, `--filter=test_online_course_platform_migration_mail_uses_system_mailer` |
| Katalog sprzedaży i warianty cenowe kursów online | `--filter=OnlineCourseSalesCatalogTest`, `--filter=ProductPriceTest` |
| Kolejność kursów na `/kursy` | **pneadm:** `--filter=OnlineCourseCatalogOrderTest`; **pnedu:** `--filter=StorefrontCatalogOrderTest` |
| Seria — auto format/szablon zaświadczeń | `--filter=CourseSeriesCertificateSettingsTest` |
| Linki e-mail do prowadzącego | `--filter=CourseInstructorLinksEmailBodyTest` (pierwszy punkt: `Lista obecności / zaświadczenie`, gdy flaga na edycji kursu jest włączona). Kanon: [CERTIFICATES.md](./CERTIFICATES.md). |
| Omnibus — historia cen i wyłączenie wpisu | `--filter=PriceOmnibusServiceTest` |
| Promocja i licznik na `/kursy` | **pnedu:** `sail artisan test --filter=test_catalog_shows_promotion_end_omnibus_and_countdown` |
| Oferta przy istniejącym dostępie | **pnedu:** `sail artisan test --filter=StorefrontOwnerAccessTest` |
| Katalog bez sprzedaży | `--filter=test_admin_can_keep_course_in_catalog_with_sales_disabled` **oraz pnedu:** `--filter=StorefrontArchiveSalesTest` |
| Zamówienia produktowe i ręczny fulfillment w ADM | `--filter=ProductOrderAdminTest` (edycja katalogu; dashboard: kolumna Produkt z nazwą kursu online; checkbox iFirma `KURS:`; recovery płatności przy nieopłaconej bramce) |
| Recovery e-mail płatności (ADM) | `--filter=FormOrderOnlinePaymentRecoveryEligibilityTest` |
| Status operacyjny zamówień produktowych | `--filter=FormOrderOperationalStatusTest`, `--filter=FormOrderOperationalStatusServiceSqlTest` |
| Publiczny katalog, checkout i fulfillment kursów nagranych | **pnedu:** `sail artisan test tests/Feature/ProductCheckoutTest.php`, `sail artisan test tests/Feature/DashboardPendingProductCoursesTest.php`, `sail artisan test tests/Unit/ProductAccessExpiryServiceTest.php`, `sail artisan test tests/Unit/ProductLegalCheckoutServiceTest.php`, `sail artisan test tests/Unit/WithdrawalWindowServiceTest.php`, `sail artisan test tests/Feature/LegalDocumentsTest.php` |
| Pełny suite | `sail test` |

Szczegóły provision PNEDU: [FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md).

## Weryfikacja katalogu bez sprzedaży — 2026-09-13

- `pneadm` `test_admin_can_keep_course_in_catalog_with_sales_disabled`: `is_public` bez `is_active` na ofercie, produkt zostaje aktywny.
- `pnedu` `StorefrontArchiveSalesTest`: karta i oferta „Sprzedaż wyłączona”, `noindex`, checkout 404.

## Weryfikacja oferty przy istniejącym dostępie — 2026-09-13

- `pnedu` `StorefrontOwnerAccessTest`: gość widzi cennik; bezterminowy chowa ceny; czasowy ma datę i „Przedłuż dostęp”; przedsprzedaż bez „Przejdź”; wygasły zostaje przy zwykłym zakupie.

## Weryfikacja Omnibus — 2026-09-13

- migracja `2026_09_13_163000_create_price_offer_histories_table` (backfill bieżącej ceny od `created_at`),
- `pneadm` `PriceOmnibusServiceTest`: najniższa z historii, wyłączenie wpisu, pogłębienie promocji jako nowa obniżka, `sync` przy zmianie ceny,
- `pnedu` katalog: copy „Najniższa cena z 30 dni przed obniżką”; na `/kursy` sama kwota, bez „od” i bez „/ osoba”; przy kursie etykieta „Autor”, nie „Prowadzący”; niezakupione karty: „Zamawiam dostęp” i „Zobacz szczegóły”; sprzedaż zamknięta: wyszarzony, nieaktywny „Sprzedaż zamknięta”; jeden wariant płatny → „Zamawiam kurs”, kilka → „Zamawiam ten wariant”; lista po 25 kursów, paginacja Bootstrap po polsku (bez „Showing…” / `pagination.previous`); kolejność z ADM, najpierw sprzedaż otwarta.

## Weryfikacja bezpłatnego zapisu kursów — 2026-09-13

- migracja `2026_09_13_234500_add_is_complimentary_to_product_prices`,
- `pneadm` `OnlineCourseSalesCatalogTest`: flaga zeruje cenę i wyłącza promocję,
- `pnedu` `FreeCourseSignupTest`: CTA bez 0,00 zł, checkout 404 dla wariantu bezpłatnego, nowy e-mail dostaje konto i mail, powtórny zapis bez duplikatu, przedsprzedaż zostawia datę startu, zalogowany zapis odświeża licznik „Kursy online (N)” (cache 120 s),
- `pnedu` `ProductCheckoutTest`: GET `/kursy/{slug}/zamowienie?price=` nie może dostać 500 po odfiltrowaniu wariantów bezpłatnych; checkout ma 4 kroki (Profil / Kontakt / Faktura / Płatność) jak formularz szkolenia V2; nowy zakup startuje od profilu „Osoba prywatna”; przy osobie prywatnej jest przełącznik kopiowania danych zamawiającego do uczestnika i faktury; pola Imię / Nazwisko / e-mail / telefon są w jednym wierszu (`col-md-3`); telefon zamawiającego jest wymagany jak na szkoleniu; ponowny submit online/odroczony nie tworzy duplikatu; mail potwierdzenia dopiero po udanym starcie bramki albo po FV odroczonej.

## Weryfikacja skróconego copy checkoutu kursów — 2026-09-13

- `pnedu` `ProductCheckoutTest`: szkoła ma ukryty blok 14 dni i oświadczenie; osoba/JDG dostaje krótki akapit + link `/odstapienie-od-umowy`; nowa treść oświadczenia niezaznaczona i weryfikowana backendem; potwierdzenie pokazuje tekst z zamówienia, nie aktualną etykietę; gwarancja używa liczby dni z oferty;
- `pnedu` `ProductLegalCheckoutServiceTest`: pending / niezatwierdzona kwalifikacja zostaje przy `service_and_digital` i wersji `2026-09-13-course-v1`;
- `pnedu` `LegalCheckoutServiceTest`: szkolenia live nadal mają brzmienie „realizacji szkolenia” / `2026-09-08-v2`.

## Weryfikacja przedsprzedaży i gwarancji — 2026-09-12

- migracja `2026_09_12_104200_add_presale_and_satisfaction_guarantee` zastosowana lokalnie (batch 109),
- `pnedu` `ProductCheckoutTest`: katalog pokazuje 30 dni gwarancji, osoba prywatna bez oświadczenia przy natychmiastowym starcie dostaje błąd, przedsprzedaż pomija oświadczenie i zapisuje snapshot startu,
- `pnedu` `DashboardPendingProductCoursesTest`: enrollment przed datą startu pokazuje „Dostęp od…” i blokuje lekcje,
- `pnedu` `ProductLegalCheckoutServiceTest` + `ProductAccessExpiryServiceTest` (okres od późniejszej daty: nadanie / start),
- `pneadm` `OnlineCourseSalesCatalogTest`: domyślna gwarancja 30, zapis `access_starts_at` / `access_note`.

## Weryfikacja sprzedaży kursów nagranych — 2026-09-12

- migracje katalogu i zamówień zastosowane lokalnie w bazie `pneadm` (batch 107 i 108),
- `pnedu` `ProductCheckoutTest`: katalog, oferta, faktura odroczona, PDF/edycja podsumowania, prefill e-maila zalogowanego użytkownika, PayU, PayNow, snapshoty, webhook `paid` i idempotentny fulfillment,
- `pnedu` `DashboardPendingProductCoursesTest` — karta oczekująca tylko dla e-maila uczestnika, komunikat po FV odroczonej, „Dokończ płatność” / rezygnacja tylko przy nieopłaconym online, rezygnacja kasuje tylko kartę tej osoby, zniknięcie po nadaniu dostępu,
- `pnedu` `ProductAccessExpiryServiceTest`: nowy, wygasły, aktywny, bezterminowy, stała data i start z przyszłości,
- `pneadm` `ProductOrderAdminTest`: panel produktu, fulfill per osoba / wszyscy, wycofanie dostępu (admin), sync e-mailu odbiorcy po edycji uczestnika, edycja zamówienia produktowego bez `course_id` (select katalogu `products`), checkbox iFirma „KURS:” zamiast „SZKOLENIE:”, przycisk recovery przy nieopłaconej bramce,
- `pneadm` `FormOrderIfirmaInvoiceNamePrefixTest`: prefiks `SZKOLENIE` / `KURS` / `E-BOOK` bez duplikacji,
- `pneadm` status operacyjny produktów: filtry „Do obsługi / Nieprzetworzone / Przetworzone” liczą `order_fulfillments`, a nie uczestników szkoleń live,
- Pint uruchamiany na zmienionych plikach PHP obu aplikacji,
- kompilacja Blade pnedu.

## Po zmianach w kodzie

1. `sail test` (lub `--filter=` dla dotkniętego modułu),
2. `sail pint` na zmienionych plikach PHP,
3. aktualizacja dokumentacji — patrz [AI_HUMAN_COMMUNICATION.md](./AI_HUMAN_COMMUNICATION.md) sekcja 14.

## Weryfikacja minimalnego legalnego checkoutu — 2026-09-08

- `pnedu`: 24 testy zakresowe / 109 asercji — zaliczone.
- `pneadm`: `PneduProvisionArticle14NoticeTest` — 1 test / 2 asercje — zaliczony.
- Pint zmienionych plików obu aplikacji — zaliczony.
- Kompilacja Blade i produkcyjny build Vite obu aplikacji — zaliczone.
- Migracja dowodów checkoutu zastosowana poprawnie wyłącznie w lokalnej bazie deweloperskiej; migracja produkcyjna nie była wykonywana.
- Pełny `pnedu`: 412 zaliczonych, 110 niezaliczonych. Błędy są związane głównie z nieaktualnym schematem testowej tabeli `users` (brak `first_name`) oraz testami wybierającymi bieżące, zmienne dane kursów.
- Pełny `pneadm`: 766 zaliczonych, 42 niezaliczone, 24 ryzykowne, 2 pominięte. Test modułu art. 14 jest zielony; pozostałe błędy dotyczą wcześniejszych rozbieżności schematu/stanu danych modułów bankowych, księgowych i provision.

Nie oznaczać pełnych zestawów jako zielone do czasu naprawy izolacji i migracji baz testowych. Zakresowa weryfikacja checkoutu nie wymaga migracji produkcyjnej.
