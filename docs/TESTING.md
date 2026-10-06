# Testy — pneadm (Laravel Sail)

Data aktualizacji: 2026-09-30

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
| PNE Growth OS — Etap 0.1–0.3.2 | `sail artisan test tests/Feature/GrowthOS` oraz `sail artisan test tests/Unit/GrowthOS` — flaga, role, menu; sesyjny flow webinaru; Asystent planowania (`direction_planning`, fake provider, `Http::preventStrayRequests`); bezpieczny pilotaż OpenAI bez prawdziwych requestów; kanon: [growth-os/CURRENT.md](./growth-os/CURRENT.md) |
| ClickMeeting / provision PNEDU | `--filter=ClickMeetingServiceTest`, `PneduProvisionEmailContextBuilderTest`, `ParticipantLiveAccessServiceTest`, `SystemMailConfigurationTest` |
| Edycja użytkownika pnedu.pl w ADM | `--filter=PneduUserUpdateTest`, `--filter=PneduUserSetPasswordTest`; kanon: [PNEDU_USERS_ADMIN.md](./PNEDU_USERS_ADMIN.md) |
| Dopisanie do nagrania po szkoleniu | `--filter=RecordingEnrollmentApiTest`; **pnedu:** `--filter=RecordingEnrollmentTest` (założenie konta pomijane, gdy `testing.users` nie ma `deleted_at` / `first_name` / `email_verified_at`; nie równolegle z suite pneadm); kanon: [RECORDING_ENROLLMENT.md](./RECORDING_ENROLLMENT.md) |
| ClickMeeting lista → courses | `--filter=ClickMeetingTrainingAdminTest`; kanon: [CLICKMEETING_TRAININGS.md](./CLICKMEETING_TRAININGS.md) |
| Historia wersji ADM / pnedu.pl | `--filter=ReleaseChangelog`; mail `--filter=ReleaseChangelogNotify`; kanon: [CHANGELOG_VERSIONING.md](./CHANGELOG_VERSIONING.md) |
| ClickMeeting sync `room_url` / maile live | `--filter=ClickMeetingTrainingAdminTest`, `--filter=ParticipantLiveMeetingLinkMailServiceTest` |
| Ręczne dodanie/usunięcie uczestnika + token CM | `--filter=ParticipantManualClickMeetingTest`, `--filter=ParticipantLiveAccessServiceTest`; kanon: [FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md) |
| Zaświadczenie dashboard (imię i nazwisko) | **pnedu:** `--filter=DashboardCertificateParticipantNameTest`; kanon: `pnedu/docs/CERTIFICATES.md` |
| Ustawienie hasła (nowe konto PNEDU) | **pnedu:** `sail test --filter=PasswordResetTest` |
| ClickMeeting embed PoC (local) | `docs/DEV_CLICKMEETING_EMBED_POC.md`, `--filter=ClickMeetingEmbedPocTest` |
| Osadzony pokój na pnedu (`embed_on_pnedu`) / radio live / link embed w mailu | migracje `2026_08_20_200210_*`, `2026_08_21_181500_*`, `2026_08_21_182800_*`, `2026_08_22_131100_*`; kanon: `pnedu/docs/DASHBOARD_LIVE_EMBED.md` |
| Belka zasobów na `/transmisja` (panel live ADM) | `--filter=CourseLivePanelTest` (przełączniki; zaświadczenie; lista **teraz na live**; oferta **auto-ukrycie 2 min**; gość `/live/{token}` na zamkniętych; **Na czat ClickMeeting**); `--filter=GuestLiveLinkServiceTest`; **pnedu:** `--filter=LiveTransmissionResourceBar`, `--filter=DashboardTransmisjaLiveOfferModalTest`, `--filter=GuestLiveTransmission` (formularz imię/nazwisko/e-mail → `participants` → iframe), `--filter=LiveEmbedPresence`, `--filter=LiveTransmissionMeetingStatusResourceBar`, `--filter=LiveTransmissionPresenceServiceTest`, `--filter=DashboardTransmisjaPresenceScriptTest` (poll nie ginie po `hidden`/powrocie na kartę; 401/419 → reload); ręcznie: oferta 2 min + brak przyciemnienia po Zamknij; długa inna karta → powrót bez F5 → belka i „Teraz na live”; kanon: [LIVE_EMBED_RESOURCE_BAR.md](./LIVE_EMBED_RESOURCE_BAR.md) |
| Wybór szkolenia w nowym zamówieniu FORM | `--filter=FormOrderCourseSearchTest` |
| KSeF / iFirma | `--filter=FormOrderKsefHelpersTest`, `--filter=IfirmaAdditionalEntityMapperTest`, `--filter=IfirmaKontrahentBuilderTest`, `--filter=IfirmaFormOrderKsefSyncServiceTest`, `--filter=IfirmaFormOrderKsefSubmissionServiceTest`, `--filter=IfirmaFormOrderKsefBackgroundServiceTest` (rzadki backoff, czas joba, limit prób), `--filter=FormOrderInvoiceMetadataResetTest`, `--filter=IfirmaPelnyNumerExtractionTest`, `--filter=FormOrdersNavigationFilterCountTest`, `--filter=FormOrderIfirmaInvoiceNamePrefixTest` |
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
| Omnibus na szkoleniach zakończonych | **pnedu:** `sail artisan test --filter=ArchivedCourseOmnibusTest` |
| Promocja i licznik na `/kursy` | **pnedu:** `sail artisan test --filter=test_catalog_shows_promotion_end_omnibus_and_countdown` |
| Oferta przy istniejącym dostępie | **pnedu:** `sail artisan test --filter=StorefrontOwnerAccessTest` |
| Katalog bez sprzedaży | `--filter=test_admin_can_keep_course_in_catalog_with_sales_disabled` **oraz pnedu:** `--filter=StorefrontArchiveSalesTest` |
| Zamówienia produktowe i ręczny fulfillment w ADM | `--filter=ProductOrderAdminTest` (edycja katalogu; dashboard: kolumna Produkt z nazwą kursu online; checkbox iFirma `KURS:`; recovery płatności przy nieopłaconej bramce) |
| Recovery e-mail płatności (ADM) | `--filter=FormOrderOnlinePaymentRecoveryEligibilityTest` |
| Status operacyjny zamówień produktowych | `--filter=FormOrderOperationalStatusTest`, `--filter=FormOrderOperationalStatusServiceSqlTest` |
| Publiczny katalog, checkout i fulfillment kursów nagranych | **pnedu:** `sail artisan test tests/Feature/ProductCheckoutTest.php`, `sail artisan test tests/Feature/DashboardPendingProductCoursesTest.php`, `sail artisan test tests/Unit/ProductAccessExpiryServiceTest.php`, `sail artisan test tests/Unit/ProductLegalCheckoutServiceTest.php`, `sail artisan test tests/Unit/WithdrawalWindowServiceTest.php`, `sail artisan test tests/Feature/LegalDocumentsTest.php` |
| Pełny suite | `sail test` |

## Weryfikacja PNE Growth OS — Etap 0.1–0.3 (2026-09-29)

- `GrowthOsAccessTest`: flaga wyłączona, wartość nieprawidłowa, gość, zwykły admin, `super_admin`, menu **PNE Rozwój**.
- `GrowthOsStage02PrototypeTest` (zawiera scenariusz 0.3): Dzisiaj bez projektu, utworzenie projektu webinaru w sesji, workspace, etapy kierunek/koncepcja, materiał, Inbox linkujący do projektu, 404 dla nieznanego projektu/materiału.
- Ręcznie: włączyć `PNE_GROWTH_OS_ENABLED=true`, jako `super_admin` przejść Dzisiaj → Zaplanuj webinar TIK → Utwórz projekt → zatwierdzić kierunek → otworzyć materiał i zmienić status. Potwierdzić brak zapisu w DB i brak publikacji.
- `route:list -v --path=growth`: trasa ma middleware `web`, `auth`, `check.user.status`, `growth_os.access`.
- `view:cache`: zaliczone.
- Pint zmienionych plików PHP: zaliczony.
- Regresja `AuthenticationTest`, `ProfileTest`, `ExampleTest`, `ReleaseChangelogTest`: **14 passed, 1 failed, 1 risky**.
  - Istniejący `ReleaseChangelogTest` oczekuje `adm.pnedu.pl v 1.1`, chociaż główny changelog miał już wersję `1.2` przed Etapem 0.1.
  - Istniejący `ProfileTest::profile page is displayed` zgłasza niezamknięty output buffer.
  - Zgodnie z zakresem nie naprawiano tych niezwiązanych problemów.

## Weryfikacja PNE Growth OS — Etap 0.3.2 (2026-09-29)

- `GrowthAiPilotTest`: wyłączona flaga bez wywołania providera, dostęp wyłącznie `super_admin`, proposal bez automatycznego nadpisania, Zastosuj/Odrzuć, invalid payload i awaria połączenia bez uszkodzenia sesji, dalsza edycja ręczna.
- `OpenAiProviderTest`: Responses API, `store: false`, structured output, jedna ponowna próba dla 429/5xx, bezpieczny błąd po wyczerpaniu retry i odrzucenie błędnego JSON.
- Wszystkie testy używają fake/mock HTTP lub fake providera; `Http::preventStrayRequests()` blokuje prawdziwe i płatne wywołania.
- Wynik: testy jednostkowe Growth OS **5 passed / 13 assertions**; testy feature Growth OS **20 passed / 109 assertions**; razem **25 passed / 122 assertions**.

## Weryfikacja PNE Growth OS — model v0.1 (2026-09-29)

- `GrowthOsV01SchemaTest`: cztery tabele, brak tabel tematów i ekspertów, unikalny klucz materiału w kampanii.
- `GrowthOsV01RelationsTest`: kampania, właściciel, instruktor, materiał, zadanie i decyzja.
- `GrowthOsV01IntegrityTest`: ten sam klucz w drugiej kampanii, puste relacje, usunięcie kampanii kasuje dzieci, usunięcie materiału lub zadania czyści powiązania, usunięcie instruktora czyści wskazanie, usunięcie właściciela jest zablokowane, brak `SoftDeletes` na tabelach Growth OS.
- Wynik `GrowthOsV01IntegrityTest`: **6 passed / 39 assertions**.
- `GrowthOsConceptPersistenceTest`: utworzenie projektu zapisuje kampanię; ręczny zapis i „Zastosuj” zapisują artifact `concept`; sama propozycja i „Odrzuć” nie zmieniają koncepcji; inna sesja właściciela odtwarza kampanię, koncepcję i decyzję „Koncepcja gotowa”; zatwierdzenie kierunku nie tworzy decyzji; cofnięcie oznacza poprzednią decyzję jako zastąpioną.
- `GrowthOsOperationalTasksTest`: nowa kampania dostaje 9 zadań ze stabilnym `key`; ponowna inicjalizacja nie dubluje; `due_at` liczy się od `live_at`, a bez terminu zostaje puste; checkbox przełącza `done` i czyści `completed_at`; odhaczenie nie tworzy decyzji i nie zmienia następnego kroku; samo otwarcie ekranu nie dopisuje zadań.
- `GrowthOsMaterialPersistenceTest`: samo otwarcie materiału nie tworzy artifactu; zapis dziesięciu materiałów utrwala status i szkic bez decyzji; etykieta publikacji zostaje w payloadzie, a kolumna dostaje `approved`; nowa sesja odtwarza materiały i nie odtwarza niezapisanej zmiany kierunku.
- `GrowthOsDirectionPersistenceTest`: samo otwarcie projektu nie zapisuje kierunku; „Zapisz kierunek” utrwala pięć pól bez decyzji; zatwierdzenie i prowadzący wracają po nowej sesji; edycja oraz cofnięcie zastępują decyzję zatwierdzenia.
- Zapis prowadzącego: utworzenie projektu ustawia `host_name`, zmiana na ekranie projektu aktualizuje imię, `primary_instructor_id` zostaje puste.
- Ręczny smoke test z prawdziwym OpenAI wymaga lokalnego `OPENAI_API_KEY` i jawnego `GROWTH_AI_ENABLED=true`; nie jest częścią automatycznego suite.

Szczegóły provision PNEDU: [FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md).

## Weryfikacja katalogu bez sprzedaży — 2026-09-13

- `pneadm` `test_admin_can_keep_course_in_catalog_with_sales_disabled`: `is_public` bez `is_active` na ofercie, produkt zostaje aktywny.
- `pnedu` `StorefrontArchiveSalesTest`: karta i oferta „Sprzedaż wyłączona”, `noindex`, checkout 404.

## Weryfikacja oferty przy istniejącym dostępie — 2026-09-13

- `pnedu` `StorefrontOwnerAccessTest`: gość widzi cennik; bezterminowy chowa ceny; czasowy ma datę i „Przedłuż dostęp”; przedsprzedaż bez „Przejdź”; wygasły zostaje przy zwykłym zakupie.

## Weryfikacja Omnibus — 2026-09-21

- `pnedu` `ArchivedCourseOmnibusTest`: karta w sekcji „Szkolenia zakończone” oraz strona kursu pokazują „Najniższa cena z 30 dni przed obniżką” przy aktywnej promocji,
- Omnibus dopięty też do formularza V2 (`order-form-v2-offer-price`) i `pay-online`, oraz do listy wariantów na stronie kursu.

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

## Weryfikacja PNE Growth OS — szablony mailingu głównego (DEC-049, 2026-10-04)

- `sail artisan test --filter=GrowthOsMainMailTemplateTest`
- `sail artisan test --filter=MailHtmlFormatterTest`
- Ręcznie: na mailingu głównym widać Klasyczny PNE, Osobisty i Minimalny oraz cofnij i ponów. Kod HTML pokazuje gotowy mail. Nie klikać generowania przy włączonym AI bez potrzeby. Mailing przypominający nie ma wyboru szablonu.

## Weryfikacja PNE Growth OS — edytor treści maila (DEC-048, 2026-10-04)

- `sail artisan test --filter=test_mail_page_offers_length_switch`
- Ręcznie: na mailingu są przyciski Edycja i Kod HTML oraz pogrubienie. Przełączenie trybu nie zapisuje szkicu.

## Weryfikacja PNE Growth OS — HTML mailingu głównego (DEC-047, 2026-10-04)

- `sail artisan test --filter=test_main_mail_html_option_formats_the_plain_body`
- Ręcznie: na mailingu głównym checkbox „Profesjonalny HTML maila” jest zaznaczony. Nie klikać generowania przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — reset dziennego limitu AI (DEC-046, 2026-10-03)

- `sail artisan test --filter=test_daily_limit_message_offers_a_reset`
- Ręcznie: po komunikacie o limicie widać „Zresetuj limit”. Potwierdzenie jest w oknie, nie w przeglądarce.

## Weryfikacja PNE Growth OS — poprawka mailingu głównego (DEC-045, 2026-10-03)

- `sail artisan test --filter=test_main_mail_refine_sends_the_unsaved_fields`
- Ręcznie: na mailingu głównym widać „Poproś AI o nowy szkic” i „Popraw mój szkic”. Nie klikać przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — poprawka posta Facebook (DEC-044, 2026-10-03)

- `sail artisan test --filter=test_facebook_refine_sends_the_unsaved_post`
- Ręcznie: na poście Facebook widać „Poproś AI o nowy szkic” i „Popraw mój szkic”. Nie klikać przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — scenariusz: bez YouTube + PDF ChatGPT (2026-10-06)

- `sail artisan test --filter='GrowthOsMaterialAiDraftTest::test_host_script'`
- Ręcznie: `/growth/projects/…/materials/host-script` — brak badge YouTube; dwie ikony PDF obok „Poproś AI o szkic” (bundle / data). Nie klikać prawdziwego AI bez potrzeby.

## Weryfikacja PNE Growth OS — poprawka grafiki (DEC-043, 2026-10-03)

- `sail artisan test --filter='test_graphic_refine_sends_the_unsaved_brief|test_image_description_refine_apply|test_revise_edits_the_clean_image'`
- Ręcznie: na grafice głównej widać „Popraw mój brief”, „Popraw ten opis” i przy obrazie „Popraw ten obraz”. Nie klikać przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — logo na grafice (DEC-042, 2026-10-03)

- `sail artisan test --filter=test_logos_are_placed_on_the_finished_image`
- Ręcznie: na grafice głównej widać logo Platformy i miejsce na logo sponsora. Nie klikać „Generuj obraz” bez potrzeby.

## Weryfikacja PNE Growth OS — kierunek jako granica koncepcji (DEC-041, 2026-10-03)

- `sail artisan test --filter='test_revision_sends_saved_direction|test_valid_response_creates_proposal'`
- Ręcznie: przy wypełnionej koncepcji opis pod listą mówi, że każda opcja dostaje pomysł z kierunkiem. Nie klikać przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — koncepcja z kierunku (2026-10-03)

- `sail artisan test --filter=test_from_direction`
- Ręcznie: pusta koncepcja, lista „Wygeneruj lub zmień” ma na górze „Wygeneruj na podstawie pomysłu i kierunku”. Nie klikać przy włączonym AI bez potrzeby.

## Weryfikacja PNE Growth OS — asystent kierunku w projekcie (DEC-039, 2026-10-03)

- `sail artisan test --filter=GrowthOsDirectionWorkspaceAiTest`
- Ręcznie: otwarty projekt, karta „Pomysł i kierunek” — „Przygotuj od nowa” / „Popraw propozycję” oraz checkbox „Wyszukiwanie w sieci” w ⚙ (bez klikania prawdziwego AI, jeśli flaga jest włączona). Przy „Gotowe” widać prośbę o cofnięcie zatwierdzenia. „Zastosuj” nie zatwierdza kierunku.

## Weryfikacja PNE Growth OS — usuwanie projektów (DEC-038, 2026-10-03)

- `sail artisan test --filter=GrowthOsProjectDeleteTest`
- Ręcznie: `/growth/projects` pokazuje wszystkie kampanie; **Usuń** otwiera modal (nie `confirm()`); po potwierdzeniu projekt znika, lista się odświeża.

## Weryfikacja PNE Growth OS — Asystent planowania (DEC-037 / DEC-053, 2026-10-02)

- `sail artisan test tests/Feature/GrowthOS tests/Unit/GrowthOS` — bez prawdziwego OpenAI (`Http::preventStrayRequests`).
- Zakres: `GrowthOsDirectionPlanningTest` (sesja, szkic DRAFT, puste defaulty, CTA, soft-fail web_search, wspólny limit) oraz `OpenAiProviderTest` (`web_search` opcjonalny, źródła z API, soft-fail bez `web_search_call`).
- Ręcznie: **nie klikać** „Przeanalizuj temat” przy `GROWTH_AI_ENABLED=true` bez decyzji — to kosztuje. Przy wyłączonej fladze: temat NotebookLM → symulacja bez listy źródeł → Utwórz projekt → puste materiały, kierunek z symulacji dopiero po „Użyj tego kierunku”.

## Weryfikacja minimalnego legalnego checkoutu — 2026-09-08

- `pnedu`: 24 testy zakresowe / 109 asercji — zaliczone.
- `pneadm`: `PneduProvisionArticle14NoticeTest` — 1 test / 2 asercje — zaliczony.
- Pint zmienionych plików obu aplikacji — zaliczony.
- Kompilacja Blade i produkcyjny build Vite obu aplikacji — zaliczone.
- Migracja dowodów checkoutu zastosowana poprawnie wyłącznie w lokalnej bazie deweloperskiej; migracja produkcyjna nie była wykonywana.
- Pełny `pnedu`: 412 zaliczonych, 110 niezaliczonych. Błędy są związane głównie z nieaktualnym schematem testowej tabeli `users` (brak `first_name`) oraz testami wybierającymi bieżące, zmienne dane kursów.
- Pełny `pneadm`: 766 zaliczonych, 42 niezaliczone, 24 ryzykowne, 2 pominięte. Test modułu art. 14 jest zielony; pozostałe błędy dotyczą wcześniejszych rozbieżności schematu/stanu danych modułów bankowych, księgowych i provision.

Nie oznaczać pełnych zestawów jako zielone do czasu naprawy izolacji i migracji baz testowych. Zakresowa weryfikacja checkoutu nie wymaga migracji produkcyjnej.
