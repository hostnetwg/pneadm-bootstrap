# 🔧 Rozwiązanie problemu z uprawnieniami Vite

## Problem

Błąd: `EACCES: permission denied, open '/var/www/html/node_modules/.vite-temp/vite.config.js.timestamp-...'`

## Przyczyna

Katalog `node_modules` jest zamontowany jako volume z hosta do kontenera Docker. Pliki należą do użytkownika hosta (1000), a Vite w kontenerze działa jako użytkownik `sail` (1337), który nie ma uprawnień do zapisu.

## Rozwiązanie

### Opcja 1: Usuń katalog .vite-temp (ZALECANE)

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
rm -rf node_modules/.vite-temp
sail npm run dev
```

Vite automatycznie utworzy katalog z odpowiednimi uprawnieniami.

### Opcja 2: Zmień właściciela node_modules (jeśli problem się powtarza)

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sudo chown -R 1337:1000 node_modules
```

**UWAGA:** To może spowodować problemy z uprawnieniami na hoście.

### Opcja 3: Dodaj do .gitignore i usuń przed każdym uruchomieniem

Dodaj do `.gitignore`:
```
node_modules/.vite-temp
```

I przed uruchomieniem:
```bash
rm -rf node_modules/.vite-temp
sail npm run dev
```

## Zapobieganie problemowi

Możesz dodać skrypt do `package.json`:

```json
{
  "scripts": {
    "dev": "rm -rf node_modules/.vite-temp && vite",
    "build": "vite build"
  }
}
```

Lub utwórz alias w `.bashrc`:

```bash
alias sail-dev='cd /home/hostnet/WEB-APP/pneadm-bootstrap && rm -rf node_modules/.vite-temp && sail npm run dev'
```

## Status

✅ Katalog `.vite-temp` został usunięty  
✅ Vite powinien teraz działać poprawnie




