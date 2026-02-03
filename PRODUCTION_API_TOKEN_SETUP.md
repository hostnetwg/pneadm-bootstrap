# 🔑 Konfiguracja API Token na produkcji

## Problem
Błąd 401 "Invalid API token" oznacza, że token API w `pnedu.pl` nie pasuje do tokena w `adm.pnedu.pl`.

## ✅ Rozwiązanie

### Krok 1: Wygeneruj bezpieczny token API

Na produkcji wygeneruj bezpieczny token (32 znaki):

```bash
# Opcja 1: Użyj openssl
openssl rand -hex 32

# Opcja 2: Użyj Laravel tinker
php artisan tinker
\Illuminate\Support\Str::random(32);
```

**Przykładowy token:** `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6`

### Krok 2: Ustaw token w `adm.pnedu.pl`

Edytuj `.env` w `adm.pnedu.pl`:

```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap
nano .env
```

Dodaj/zmodyfikuj:
```env
PNEADM_API_TOKEN=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6
```

Zapisz: `Ctrl+O`, `Enter`, `Ctrl+X`

### Krok 3: Ustaw TEN SAM token w `pnedu.pl`

Edytuj `.env` w `pnedu.pl`:

```bash
cd /ścieżka/do/pnedu.pl/public_html/pnedu
nano .env
```

Dodaj/zmodyfikuj:
```env
PNEADM_API_URL=https://adm.pnedu.pl
PNEADM_API_TOKEN=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6
PNEADM_API_TIMEOUT=30
```

**WAŻNE:** Token musi być **IDENTYCZNY** w obu projektach!

Zapisz: `Ctrl+O`, `Enter`, `Ctrl+X`

### Krok 4: Wyczyść cache w obu projektach

**W `adm.pnedu.pl`:**
```bash
cd /ścieżka/do/adm.pnedu.pl/public_html/pneadm-bootstrap
php artisan config:clear
php artisan cache:clear
```

**W `pnedu.pl`:**
```bash
cd /ścieżka/do/pnedu.pl/public_html/pnedu
php artisan config:clear
php artisan cache:clear
```

### Krok 5: Sprawdź konfigurację

**W `adm.pnedu.pl`:**
```bash
php artisan tinker
config('services.pneadm.api_token');
# Powinno zwrócić: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6"
```

**W `pnedu.pl`:**
```bash
php artisan tinker
config('services.pneadm.api_url');
config('services.pneadm.api_token');
# Powinno zwrócić odpowiednie wartości
```

## 🔍 Diagnostyka

Jeśli nadal występuje błąd 401:

1. **Sprawdź czy tokeny są identyczne:**
   ```bash
   # W adm.pnedu.pl
   grep PNEADM_API_TOKEN .env
   
   # W pnedu.pl
   grep PNEADM_API_TOKEN .env
   ```

2. **Sprawdź czy cache został wyczyszczony:**
   ```bash
   # W obu projektach
   php artisan config:clear
   php artisan cache:clear
   ```

3. **Sprawdź czy middleware działa:**
   ```bash
   # W adm.pnedu.pl - test health check
   curl -H "Authorization: Bearer TWÓJ_TOKEN" https://adm.pnedu.pl/api/certificates/health
   ```

## 📝 Bezpieczeństwo

- **NIE** commituj `.env` do repozytorium
- Używaj **silnych tokenów** na produkcji (min. 32 znaki)
- **Różne tokeny** dla różnych środowisk (dev, staging, production)
- **Rotuj tokeny** okresowo (np. co 6 miesięcy)








