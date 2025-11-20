# Rozwiązanie problemu z git pull na produkcji

## Co oznacza komunikat?

```
error: Your local changes to the following files would be overwritten by merge:
        resources/views/certificates/default-kopia.blade.php
Please commit your changes or stash them before you merge.
```

**Znaczenie:**
- Na produkcji masz **lokalne, niezapisane zmiany** w pliku `default-kopia.blade.php`
- Git nie pozwala na `pull`, bo nadpisałby te lokalne zmiany zmianami z repozytorium
- Musisz najpierw zdecydować, co zrobić z lokalnymi zmianami

## Rozwiązania (3 opcje)

### Opcja 1: Sprawdź co się zmieniło (ZALECANE)

```bash
# Zobacz jakie są lokalne zmiany
git diff resources/views/certificates/default-kopia.blade.php

# Jeśli zmiany są ważne:
git add resources/views/certificates/default-kopia.blade.php
git commit -m "Zapisz lokalne zmiany przed pull"
git pull

# Jeśli zmiany NIE są ważne (np. przypadkowe):
git checkout -- resources/views/certificates/default-kopia.blade.php
git pull
```

### Opcja 2: Zapisz zmiany tymczasowo (stash)

```bash
# Zapisz lokalne zmiany tymczasowo
git stash

# Zrób pull
git pull

# Jeśli chcesz przywrócić lokalne zmiany:
git stash pop

# Jeśli NIE chcesz przywrócić lokalnych zmian:
git stash drop
```

### Opcja 3: Zignoruj lokalne zmiany (OSTROŻNIE)

```bash
# UWAGA: To usunie lokalne zmiany bezpowrotnie!
git checkout -- resources/views/certificates/default-kopia.blade.php
git pull
```

## Rekomendacja dla produkcji

**Krok po kroku:**

1. **Sprawdź zmiany:**
   ```bash
   git diff resources/views/certificates/default-kopia.blade.php
   ```

2. **Jeśli plik to tylko backup/kopia** (jak sugeruje nazwa `default-kopia.blade.php`), prawdopodobnie zmiany nie są ważne:
   ```bash
   git checkout -- resources/views/certificates/default-kopia.blade.php
   git pull
   ```

3. **Jeśli zmiany mogą być ważne**, zapisz je:
   ```bash
   git stash
   git pull
   git stash pop  # Przywróć zmiany i sprawdź czy nie ma konfliktów
   ```

## Ważne uwagi dla produkcji

- ⚠️ **Zawsze sprawdzaj `git diff` przed usunięciem zmian**
- ⚠️ Plik `default-kopia.blade.php` wygląda na backup (sufiks `-kopia`), więc prawdopodobnie lokalne zmiany nie są krytyczne
- ✅ Jeśli używasz stash, pamiętaj o `git stash pop` po pull, aby przywrócić zmiany
- ✅ Dla produkcji zalecane jest użycie stash, aby nie stracić potencjalnie ważnych zmian

## Dlaczego to się stało?

Plik `default-kopia.blade.php` prawdopodobnie został zmodyfikowany lokalnie na produkcji (może przez edycję ręczną lub przez skrypt), a teraz próbujesz zaktualizować kod z repozytorium.

