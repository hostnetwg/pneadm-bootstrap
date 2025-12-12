# Naprawa uprawnień plików - Instrukcja

## Problem
Pliki tworzone przez kontener Docker (użytkownik 1337) mogą mieć niewłaściwe uprawnienia dla użytkownika hostnet (UID 1000).

## 🚀 Szybkie rozwiązanie (NAJPIERW TO)

### Napraw aktualny plik migracji:
```bash
sudo chown hostnet:hostnet database/migrations/2025_12_12_221458_add_performance_indexes_to_participants_and_courses_tables.php
sudo chmod 664 database/migrations/2025_12_12_221458_add_performance_indexes_to_participants_and_courses_tables.php
```

### Lub użyj skryptu:
```bash
# Szybka naprawa tylko dla aktualnego pliku:
sudo ./quick-fix-permissions.sh

# Lub napraw wszystkie pliki:
sudo ./fix-permissions.sh
```

## 📋 Rozwiązania na przyszłość

### Opcja 1: Ustaw WWWUSER w .env (NAJLEPSZE - trwałe rozwiązanie)

Dodaj do pliku `.env`:
```bash
WWWUSER=1000
WWWGROUP=1000
```

Następnie zrestartuj kontenery:
```bash
sail down
sail build --no-cache
sail up -d
```

**To sprawi, że kontener Docker będzie używał tego samego UID/GID (1000) co użytkownik hostnet, więc pliki będą tworzone z właściwymi uprawnieniami.**

### Opcja 2: Użyj skryptu automatycznie

Dodaj do `.git/hooks/post-merge`:
```bash
#!/bin/bash
cd "$(git rev-parse --show-toplevel)"
./fix-permissions.sh
```

I nadaj uprawnienia:
```bash
chmod +x .git/hooks/post-merge
```

### Opcja 3: Ręczna naprawa wszystkich plików
```bash
# Napraw uprawnienia dla katalogu migracji
sudo chown -R hostnet:hostnet database/migrations/
sudo chmod -R 775 database/migrations/

# Napraw uprawnienia dla storage i cache
sudo chown -R hostnet:hostnet storage/ bootstrap/cache/
sudo chmod -R 775 storage/ bootstrap/cache/
```

## 🔍 Sprawdzenie uprawnień
```bash
# Sprawdź właściciela pliku
ls -la database/migrations/2025_12_12_221458_add_performance_indexes_to_participants_and_courses_tables.php

# Sprawdź uprawnienia katalogu
ls -ld database/migrations/

# Sprawdź swój UID
id
```

## ⚙️ Co zostało zmienione

1. **docker-compose.yml** - dodano domyślne wartości dla WWWUSER i WWWGROUP (1000)
2. **fix-permissions.sh** - skrypt do automatycznej naprawy uprawnień
3. **FIX_PERMISSIONS.md** - ta dokumentacja

## 💡 Zapobieganie problemom

1. **Zawsze używaj Sail do tworzenia plików:**
   ```bash
   sail artisan make:migration nazwa_migracji
   ```

2. **Po git pull uruchom:**
   ```bash
   ./fix-permissions.sh
   ```

3. **Upewnij się, że .env ma:**
   ```bash
   WWWUSER=1000
   WWWGROUP=1000
   ```

## 🆘 Jeśli nadal masz problemy

1. Sprawdź czy `.env` ma `WWWUSER=1000` i `WWWGROUP=1000`
2. Zrestartuj kontenery: `sail down && sail up -d`
3. Uruchom skrypt: `sudo ./fix-permissions.sh`
4. Sprawdź uprawnienia: `ls -la database/migrations/`
