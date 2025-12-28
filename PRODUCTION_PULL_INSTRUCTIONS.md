# Instrukcja git pull na produkcji

## Problem
Pliki untracked kolidują z nowymi plikami w commicie.

## Rozwiązanie - wykonaj na produkcji:

```bash
cd /ścieżka/do/pneadm-bootstrap

# 1. Usuń lokalne pliki untracked (które są już w commicie)
rm -f resources/views/certificates/default.blade.php
rm -f resources/views/certificates/landscape.blade.php
rm -f resources/views/certificates/minimal.blade.php

# 2. Wykonaj pull
git pull

# 3. Jeśli pliki nie zostały przywrócone automatycznie (sprawdź):
git status

# 4. Jeśli nadal brakuje, przywróć z repozytorium:
git checkout HEAD -- resources/views/certificates/default.blade.php resources/views/certificates/landscape.blade.php resources/views/certificates/minimal.blade.php
```

## Alternatywne rozwiązanie (bezpieczniejsze):

```bash
cd /ścieżka/do/pneadm-bootstrap

# 1. Przenieś pliki do backupu (na wypadek gdyby były potrzebne)
mkdir -p backup_certificates_$(date +%Y%m%d_%H%M%S)
mv resources/views/certificates/default.blade.php backup_certificates_*/ 2>/dev/null
mv resources/views/certificates/landscape.blade.php backup_certificates_*/ 2>/dev/null
mv resources/views/certificates/minimal.blade.php backup_certificates_*/ 2>/dev/null

# 2. Wykonaj pull
git pull

# 3. Sprawdź status
git status
```

## Pełna komenda (jedna linia):

```bash
cd /ścieżka/do/pneadm-bootstrap && rm -f resources/views/certificates/default.blade.php resources/views/certificates/landscape.blade.php resources/views/certificates/minimal.blade.php && git pull && git checkout HEAD -- resources/views/certificates/default.blade.php resources/views/certificates/landscape.blade.php resources/views/certificates/minimal.blade.php 2>/dev/null || true
```





