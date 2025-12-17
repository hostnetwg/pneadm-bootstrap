# 🔧 Tymczasowa naprawa - przywrócenie lokalnych szablonów

## ❌ Problem

Pakiet `pne-certificate-generator` nie może być zainstalowany w `pneadm-bootstrap` z powodu:
1. Volume nie jest zamontowany w kontenerze (`/var/www/pne-certificate-generator` nie istnieje)
2. `composer.json` nie jest zapisywalny w kontenerze
3. Problemy z uprawnieniami Git

## ✅ Tymczasowe rozwiązanie

Przywrócono lokalne szablony z backupu:
- ✅ `resources/views/certificates/default.blade.php`
- ✅ `resources/views/certificates/landscape.blade.php`
- ✅ `resources/views/certificates/minimal.blade.php`

## 🔄 Co należy zrobić

### Opcja 1: Zrestartować kontenery (zalecane)
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail down
sail up -d
```

Po restarcie volume powinien być zamontowany i pakiet powinien być dostępny.

### Opcja 2: Zainstalować pakiet ręcznie
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail composer require pne/certificate-generator
sail artisan package:discover
```

### Opcja 3: Użyć lokalnych szablonów (tymczasowo)
Lokalne szablony są już przywrócone i powinny działać. System będzie używał lokalnych szablonów jako fallback.

## 📝 Status

- ✅ Lokalne szablony przywrócone
- ⏳ Volume w docker-compose.yml dodany (wymaga restartu kontenerów)
- ⏳ Pakiet wymaga instalacji po restarcie kontenerów

## 🧪 Testowanie

Spróbuj teraz wygenerować certyfikat - powinno działać z lokalnymi szablonami.

Po restarcie kontenerów i instalacji pakietu, system automatycznie przełączy się na szablony z pakietu.












