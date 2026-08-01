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

## Po deployu

1. Sprawdź menu `Księgowość → Import wyciągu`.
2. Wgraj testowy / produkcyjny CSV mBank (`lista_operacji_*.csv`).
3. Zweryfikuj filtry high/medium/low i akceptację jednej sugestii.
4. Na karcie sprawy: sekcja „Wpłaty z wyciągu” + wpis historii `bank_match`.
5. Przy zgodnej kwocie: modal — lokalnie albo + wpłata iFirma; przy różnicy kwot — tylko lokalnie (ostrzeżenie).

## Storage

Pliki CSV trafiają na dysk `local` (`storage/app/bank-statements/...`). Nie commituj plików produkcyjnych z PII.

## Rollback

```bash
sail artisan migrate:rollback --step=1
```

Uwaga: rollback usuwa tabele importów/transakcji/dopasowań. Wpisy `debt_case_actions` typu `bank_match` pozostaną w historii spraw.
