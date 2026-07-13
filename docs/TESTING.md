# Testy — pneadm (Laravel Sail)

Data aktualizacji: 2026-07-13

## Cel

Opisuje jak uruchamiać testy lokalnie oraz konfigurację `phpunit.xml`, która izoluje bazę testową od współdzielonej bazy analityki deweloperskiej (`pne_analytics`).

## Szybki start

```bash
sail up -d
sail artisan migrate --env=testing   # opcjonalnie — RefreshDatabase robi to w testach Feature
sail test                            # pełny suite
sail test --filter=NazwaTestu        # pojedyncza klasa / metoda
sail pint                            # formatowanie przed commitem
```

**Oczekiwany stan (2026-07-13):** pełny `sail test` → **510 passed**, 0 failed, 3 skipped (rejestracja wyłączona), ewentualne ostrzeżenia „risky” PHPUnit (nie blokują).

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
| ClickMeeting / provision PNEDU | `--filter=ClickMeetingServiceTest`, `PneduProvisionEmailContextBuilderTest`, `ParticipantLiveAccessServiceTest` |
| KSeF / iFirma | `--filter=IfirmaFormOrderKsefSubmissionServiceTest` (jeśli istnieje) |
| Analityka lejka | `--filter=AnalyticsOrderFormFunnelAggregationTest` |
| Pełny suite | `sail test` |

Szczegóły provision PNEDU: [FORM_ORDERS_PNEDU_PROVISION.md](./FORM_ORDERS_PNEDU_PROVISION.md).

## Po zmianach w kodzie

1. `sail test` (lub `--filter=` dla dotkniętego modułu),
2. `sail pint` na zmienionych plikach PHP,
3. aktualizacja dokumentacji — patrz [AI_HUMAN_COMMUNICATION.md](./AI_HUMAN_COMMUNICATION.md) sekcja 14.
