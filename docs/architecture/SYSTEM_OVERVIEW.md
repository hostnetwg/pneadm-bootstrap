# Przegląd Architektury Systemu

Data utworzenia/aktualizacji: 2026-07-13  
Status: wersja robocza, do potwierdzenia przez właściciela

## Cel Dokumentu

Dokument opisuje aktualną architekturę dwóch aplikacji Laravel: `pnedu.pl` i `adm.pnedu.pl`. Jest punktem odniesienia dla planowanej analityki eventowej w `pne_analytics`.

## Ogólny Układ Systemu

```text
użytkownik / klient
    ↓
pnedu.pl
    ├─ oferta szkoleń
    ├─ opis szkolenia
    ├─ formularz zamówienia
    ├─ płatności online
    └─ panel uczestnika

adm.pnedu.pl
    ├─ szkolenia
    ├─ zamówienia
    ├─ kampanie
    ├─ uczestnicy
    ├─ certyfikaty
    ├─ faktury / iFirma / KSeF
    └─ raporty

bazy danych
    ├─ pnedu
    ├─ pneadm
    ├─ certgen
    └─ pne_analytics (planowana)
```

## Aplikacje

### `pnedu.pl`

Publiczny portal i panel uczestnika.

Kluczowe obszary:

- strona główna,
- listy szkoleń,
- szczegóły szkolenia,
- formularz zamówienia,
- płatności PayU/Paynow,
- panel użytkownika,
- dostęp do nagrań i materiałów,
- certyfikaty,
- newsletter,
- SEO.

Najważniejsze miejsca w kodzie:

- `routes/web.php`,
- `app/Http/Controllers/CourseController.php`,
- `app/Http/Controllers/PaymentController.php`,
- `app/Http/Controllers/CertificateController.php`,
- `app/Services/MarketingAttributionService.php`,
- `app/Services/MarketingCampaignLinkResolver.php`,
- `app/Services/MarketingCampaignLinkTracker.php`,
- `app/Services/CoursePageViewTracker.php`,
- `resources/views/courses/show.blade.php`,
- `resources/views/courses/order-form.blade.php`.

### `adm.pnedu.pl`

Panel administracyjny i backoffice.

Kluczowe obszary:

- CRUD szkoleń,
- zarządzanie wariantami cen,
- trenerzy,
- uczestnicy,
- zamówienia,
- faktury,
- iFirma,
- KSeF,
- płatności,
- kampanie marketingowe,
- źródła marketingowe,
- raporty,
- certyfikaty,
- ankiety,
- użytkownicy i role.

Najważniejsze miejsca w kodzie:

- `routes/web.php`,
- `routes/api.php`,
- `app/Http/Controllers/CoursesController.php`,
- `app/Http/Controllers/FormOrdersController.php`,
- `app/Http/Controllers/MarketingCampaignController.php`,
- `app/Http/Controllers/MarketingFunnelController.php`,
- `app/Services/MarketingCampaignUrlBuilder.php`,
- `app/Services/MarketingCampaignStatsService.php`,
- `app/Services/CourseFunnelStatsService.php`,
- `app/Services/FormOrderPneduProvisionService.php`.
- `app/Services/ParticipantLiveAccessService.php`.

Szczegóły tokenów ClickMeeting i provision: `docs/FORM_ORDERS_PNEDU_PROVISION.md`.

## Bazy Danych I Relacje

| Baza | Rola | Główne dane |
|---|---|---|
| `pnedu` | portal publiczny | użytkownicy, sesje, dane techniczne portalu |
| `pneadm` | główna baza biznesowa | kursy, zamówienia, uczestnicy, kampanie, certyfikaty, płatności |
| `certgen` | legacy / archiwum | historyczne dane certyfikatów i zamówień |
| `pne_analytics` | planowana analityka | eventy, sesje, agregaty, testy A/B, eksporty AI-safe |

`pnedu.pl` korzysta z własnej bazy `pnedu`, ale modele biznesowe czytają i zapisują dane w `pneadm`.

`adm.pnedu.pl` zarządza `pneadm` i ma połączenie do `pnedu` dla administracji użytkownikami portalu.

## Moduły Biznesowe

| Moduł | Główne tabele | Główna aplikacja |
|---|---|---|
| szkolenia | `courses`, `course_price_variants`, `course_online_details` | `adm.pnedu.pl`, widoczne w `pnedu.pl` |
| trenerzy | `instructors` | `adm.pnedu.pl` |
| zamówienia | `form_orders`, `form_order_participants` | oba |
| płatności | `online_payment_orders`, `payment_webhook_logs` | oba |
| kampanie | `marketing_campaigns`, `marketing_source_types` | `adm.pnedu.pl`, wejścia w `pnedu.pl` |
| statystyki obecne | `marketing_campaign_stats_daily`, `course_page_stats_daily` | oba |
| uczestnicy | `participants`, `participant_emails`, `participant_live_access` | `adm.pnedu.pl`, panel w `pnedu.pl` |
| certyfikaty | `certificates`, `certificate_templates`, `certificate_email_logs` | oba |
| ankiety | `surveys`, `survey_questions`, `survey_responses` | `adm.pnedu.pl` |
| LMS | `online_courses`, `online_course_modules`, `online_course_lessons` | oba |

## Obecne Integracje

- PayU,
- Paynow,
- Publigo,
- iFirma,
- KSeF,
- Sendy,
- ClickMeeting,
- Google Calendar,
- AWS SES,
- legacy `certgen`.

## Miejsca Krytyczne

### Zamówienia

Najważniejsze ryzyko: analityka nie może wpływać na formularz zamówienia, zapis `FormOrder`, płatność online ani fakturę.

Miejsca przyszłego backend trackingu:

- `CourseController@orderForm`,
- `CourseController@storeOrderForm`,
- tworzenie `FormOrder`,
- tworzenie `OnlinePaymentOrder`,
- webhooki PayU/Paynow,
- akcje fakturowe w `FormOrdersController`.

### Płatności

Tracking płatności powinien zapisywać wyłącznie identyfikatory i statusy:

- `payment_order_id`,
- `form_order_id`,
- `payment_gateway`,
- `payment_status`,
- `amount_snapshot`.

Nie zapisywać danych karty, danych płatnika ani szczegółów bramki zawierających dane osobowe.

### Faktury, KSeF I iFirma

Tracking powinien zapisywać tylko neutralne metadane:

- `invoice_path_type`,
- `ksef_option_selected`,
- `has_recipient`,
- `buyer_type`,
- `invoice_created`.

Nie zapisywać NIP, adresów, nazw instytucji ani danych fakturowych w `pne_analytics`.

### Kampanie

Obecnie kampanie mają `campaign_code` i `landing_target`. Docelowa analityka ma mierzyć skuteczność ścieżek:

- `course_description`,
- `order_form_direct`.

### Certyfikaty

Certyfikaty są krytyczne operacyjnie, ale nie są pierwszym zakresem MVP analityki eventowej.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalny connection name dla `pne_analytics`.
- Dopisać linki do migracji analitycznych po ich utworzeniu.
- Dopisać finalne klasy trackingowe i joby.
- Dopisać mapę dashboardów po wdrożeniu etapu 3.
