# Deploy: Import CSV wyciągu mBank (Windykacja)

Data: 2026-08-01  
Projekt: `pneadm`

## Cel

MVP importu wyciągu mBank CSV do Windykacji: upload → parsowanie → sugestie dopasowań → ręczne zatwierdzanie lokalnie (bez API wpłat iFirma).

## Migracje

```bash
sail artisan migrate --force
```

Migracja:

- `2026_08_01_000001_create_bank_statement_tables.php`
  - `bank_statement_imports`
  - `bank_transactions` (unique `fingerprint`)
  - `bank_transaction_matches`

## Hotfix (prod 500 na Accept)

Objaw: `POST .../matches/{id}/accept` → `500 | SERVER ERROR`.

Przyczyna: `debt_cases.form_order_id` jest UNIQUE. Przy akceptacji kod szukał tylko spraw `status != closed` i próbował utworzyć drugą sprawę, gdy istniała zamknięta / soft-deleted — MySQL Integrity constraint → 500.

Fix: reuse istniejącej sprawy (`withTrashed` + `restore` gdy trzeba), jak w `collectionsStore`. Dodatkowo wyjątki z rejestracji wpłaty iFirma nie psują już lokalnej akceptacji (warning flash).

Deploy: wypchnąć kod (bez nowej migracji) + `php artisan optimize:clear`.

## Po deployu

1. Sprawdź menu `Księgowość → Import wyciągu`.
2. Wgraj testowy / produkcyjny CSV mBank (`lista_operacji_*.csv`).
3. Zweryfikuj filtry high/medium/low i akceptację jednej sugestii.
4. Na karcie sprawy: sekcja „Wpłaty z wyciągu” + wpis historii `bank_match`.
5. Przy zgodnej kwocie: modal — lokalnie albo + wpłata iFirma; przy różnicy kwot — tylko lokalnie (ostrzeżenie).
6. Akceptacja na zamówieniu z już zamkniętą sprawą nie może dać 500 — wiąże do istniejącej.
7. Po **Akceptuj + wpłata w iFirma** (status `oplacone`) lub **Zaakceptuj jako opłacone w iFirma** — sprawa powinna zostać **zamknięta automatycznie** (wpis `close` w historii), chyba że była `disputed`.
8. Na karcie sprawy sprawdź wyszukiwarkę **Wpłaty z wyciągu**: znajdź niepowiązany wpływ po kwocie/frazie z opisu i powiąż lokalnie albo z rejestracją wpłaty iFirma.
9. W modalu przelewu bez sugestii: **Powiąż ręcznie ze sprawą** — wyszukaj niezamkniętą sprawę i powiąż.

## Auto-zamknięcie po Accept (2026-08)

Serwis: `DebtCaseAutoCloseService`. Bez migracji. Deploy kodu + `php artisan optimize:clear`.

## Ręczne powiązanie od strony sprawy (2026-08)

Bez migracji. Karta sprawy pokazuje kandydatów z `bank_transactions`, które są wpływami i nie mają zaakceptowanego/ignorowanego matcha. Powiązanie tworzy `bank_transaction_matches.match_reasons = manual_case_link` i używa istniejącej logiki akceptacji.

## Storage

Pliki CSV trafiają na dysk `local` (`storage/app/bank-statements/...`). Nie commituj plików produkcyjnych z PII.

## Rollback

```bash
sail artisan migrate:rollback --step=1
```

Uwaga: rollback usuwa tabele importów/transakcji/dopasowań. Wpisy `debt_case_actions` typu `bank_match` pozostaną w historii spraw.
