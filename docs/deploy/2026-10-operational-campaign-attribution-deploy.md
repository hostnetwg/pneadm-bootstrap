# Operacyjna atrybucja kampanii zamówień — deploy 2026-10

## Cel

Zamówienia z linków UTM i `/l/{campaign_code}` zachowują `form_orders.fb_source` również wtedy, gdy klient nie udzielił zgody na Google Analytics. GA/GTM i cookie `pne_marketing` pozostają zależne od zgody.

## Zakres

### `pnedu`

- nowy `App\Services\OrderCampaignAttributionService`;
- zapis kodu kampanii w bieżącej sesji Laravel przed bramką zgody;
- formularze legacy/V2, faktura odroczona, PayU i Paynow korzystają z operacyjnego kodu;
- aktualizacja informacji o cookies pierwszostronnych;
- testy `OperationalCampaignAttributionTest` i stabilny fixture `MarketingCampaignLinkTrackerTest`.

### `pneadm`

- doprecyzowanie kolumny „Zam.”: rekordy zamówień, nie płatności ani uczestnicy;
- objaśnienie „Złoż.” i „Fakt.” w tabeli kampanii lejka marketingowego;
- aktualizacja dokumentacji marketingowej.

## Migracje i konfiguracja

Brak migracji i nowych zmiennych `.env`.

Nie dodajemy nowego cookie. Kod jest przechowywany w istniejącej sesji Laravel. Dotychczasowe `MARKETING_ATTRIBUTION_DAYS` i `pne_marketing` nie zmieniają działania.

## Kolejność wdrożenia

1. Wdrożyć `pnedu`.
2. Wyczyścić cache aplikacji:

```bash
sail artisan optimize:clear
```

3. Wdrożyć `pneadm`.
4. Wyczyścić cache aplikacji:

```bash
sail artisan optimize:clear
```

Nie jest wymagany restart workerów do samego zapisu atrybucji.

## Testy przed wdrożeniem

W `pnedu`:

```bash
sail artisan test --filter='(OperationalCampaignAttributionTest|MarketingCampaignLinkTrackerTest)'
sail pint --test app/Services/OrderCampaignAttributionService.php app/Http/Middleware/CaptureMarketingSource.php app/Http/Controllers/CourseController.php tests/Feature/OperationalCampaignAttributionTest.php tests/Feature/MarketingCampaignLinkTrackerTest.php
```

W `pneadm`:

```bash
sail artisan test --filter=MarketingCampaignStatsServiceTest
sail pint --test app/Services/MarketingCampaignStatsService.php
```

## Smoke test po wdrożeniu

Użyć testowej kampanii i nie składać realnej płatności:

1. W nowej sesji wybrać „Tylko niezbędne”.
2. Otworzyć pełny link UTM do formularza.
3. Sprawdzić w HTML, że hidden `fb_source` ma `campaign_code`.
4. Otworzyć `/l/{campaign_code}` i potwierdzić przekierowanie z UTM.
5. Wejść linkiem UTM na opis szkolenia, następnie przejść do formularza — `fb_source` ma pozostać.
6. Złożyć kontrolowane zamówienie odroczone i sprawdzić w `/form-orders`, czy widnieje „Pozyskano”.
7. Test PayU/Paynow wykonywać wyłącznie w sandboxie lub trybie deweloperskim.

## Monitoring

- porównać udział nowych `form_orders` z pustym `fb_source` przed i po wdrożeniu;
- dla aktywnych kampanii obserwować „Wejś.”, „Zam.” i CR;
- pamiętać, że „Zam.” nie oznacza płatności opłaconych ani liczby uczestników.

## Rollback

Wycofanie kodu `pnedu` przywraca wcześniejszą zależność od zgody analitycznej. Nie ma zmian schematu do cofnięcia. Rekordy `fb_source` zapisane po wdrożeniu pozostają poprawną historią zamówień.
