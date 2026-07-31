# Deploy / Changelog: Windykacja MVP

Data: 2026-07-31

## Zakres

MVP modułu `Księgowość → Windykacja` w `pneadm`.

Wdrożono:

- tabele `debt_cases`, `debt_case_actions`, `debt_case_contacts`,
- modele `DebtCase`, `DebtCaseAction`, `DebtCaseContact`,
- `DebtCustomerProfileService` z pierwszą segmentacją `risk_score` / `relationship_score`,
- dashboard spraw pod `accounting.collections.*`,
- karta sprawy z notatkami, działaniami, kontaktami i historią powiązanych zamówień,
- nawigacja **Poprzednia** / **Następna** na karcie sprawy,
- badge i skróty Windykacji na `/form-orders` i `/form-orders/{id}`,
- zachowanie obecnego `/accounting/debtors` jako lookupu faktury / KSeF.

Powiązane etapy (osobne deploy notes, ten sam release lokalny):

- `form_orders.ifirma_invoice_id` — `docs/deploy/2026-07-ifirma-invoice-id-deploy.md`,
- odczyt statusu płatności z iFirma — `docs/deploy/2026-07-ifirma-payment-status-sync-deploy.md`.

Poza zakresem MVP (nadal backlog):

- import CSV wyciągów bankowych,
- automatyczne rejestrowanie wpłat w iFirma,
- ograniczenia płatności na publicznym formularzu `pnedu`.

## Komendy Deploy

```bash
sail artisan migrate --force
sail artisan optimize:clear
```

Smoke test:

1. Wejdź w `Księgowość → Windykacja`.
2. Utwórz sprawę po istniejącym `form_orders.id`.
3. Otwórz kartę sprawy i dodaj notatkę / telefon.
4. Wejdź w `/form-orders/{id}` i sprawdź panel Windykacja.
5. Wejdź w `/accounting/debtors` i sprawdź lookup po numerze faktury / KSeF.

## Testy

```bash
sail artisan test --filter=AccountingCollectionsTest
sail artisan test --filter=AccountingDebtorsLookupKsefTest
```

Uwaga lokalna: PHPUnit zgłasza ostrzeżenie zapisu `.phpunit.result.cache` przy uprawnieniach kontenera, ale testy modułu przechodzą.
