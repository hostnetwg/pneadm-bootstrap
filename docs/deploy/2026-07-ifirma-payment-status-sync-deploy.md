# Deploy — sync statusu płatności iFirma (Windykacja)

## Co

Odczyt statusu płatności faktury z iFirma na karcie sprawy windykacyjnej (`Odśwież status z iFirma`). Cache: `debt_cases.ifirma_payment_status`, `ifirma_synced_at`. Historia: `debt_case_actions.action_type = ifirma_sync`.

**Bez nowej migracji** — kolumny były w MVP windykacji.

## Deploy

```bash
# brak migrate — tylko kod
git pull
# php-fpm / opcache reload według standardu serwera
```

## Smoke

1. Otwórz aktywną sprawę z `ifirma_invoice_id` lub numerem FV.
2. Kliknij „Odśwież status z iFirma”.
3. Sprawdź badge statusu i wpis w historii.
4. Sprawdź przyciski Poprzednia / Następna na karcie sprawy.

## Dokumentacja

`docs/WINDYKACJA.md` — sekcja „Synchronizacja statusu płatności z iFirma”.
