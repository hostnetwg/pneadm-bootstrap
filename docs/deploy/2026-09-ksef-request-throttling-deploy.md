# Deploy: ograniczenie requestów KSeF na szczegółach zamówienia

Data: 2026-09-30  
Projekt: `pneadm` / `adm.pnedu.pl`

## Zakres

- brak automatycznego pollingu KSeF po wejściu na oczekujące zamówienie;
- maksymalnie 3 odczyty lokalnego statusu po wystawieniu faktury;
- licznik nawigacji i preferencje iFirma bez osobnych requestów po załadowaniu;
- kolejka KSeF z backoffem: 1, 2, 5, 10, 15, 30 i 60 minut;
- jeden GET iFirma na próbę i widoczny czas następnej próby w raporcie automatu.

Migracja bazy danych nie jest wymagana.

## Opcjonalna konfiguracja

Domyślny harmonogram jest bezpieczny dla hostingu. Zmienną ustawiać tylko wtedy,
gdy potrzebny jest inny harmonogram:

```env
IFIRMA_KSEF_BACKGROUND_RETRY_DELAYS_SECONDS=60,120,300,600,900,1800,3600
```

## Wdrożenie produkcyjne

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```

Nie uruchamiać migracji — ten hotfix ich nie zawiera.

## Kontrola

1. Otworzyć zamówienie oczekujące na KSeF i sprawdzić w DevTools/Network, że
   samo wejście nie uruchamia cyklicznego `ifirma/ksef-status`.
2. Wystawić fakturę testową czerwonym przyciskiem. Strona może sprawdzić lokalny
   status najwyżej 3 razy; przejście do następnego zamówienia kończy te kontrole.
3. W **Admin → Raporty automatów** sprawdzić pozycję zamówienia i godzinę
   „Następna próba”.
4. Potwierdzić, że worker kolejki działa zgodnie z
   [`PRODUCTION_QUEUE_OPS.md`](./PRODUCTION_QUEUE_OPS.md).
5. Po pojawieniu się NumerKSeF w iFirma potwierdzić jego zapis w zamówieniu.

## Wycofanie

Wycofać commit hotfixu i ponownie wykonać:

```bash
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```
