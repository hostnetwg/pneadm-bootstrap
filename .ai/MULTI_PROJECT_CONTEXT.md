# Multi-Project Context - adm.pnedu.pl

Ten projekt jest częścią większej platformy PNE (Platforma Nowoczesnej Edukacji).

## 📁 Powiązane projekty

### 1. **pnedu.pl** (`../pnedu/`)
- **Typ**: Publiczny serwis dla klientów
- **Baza danych**: Własna baza + dostęp do `pneadm` (read-write)
- **Funkcjonalność**: 
  - Przeglądanie ofert szkoleń
  - Rejestracja użytkowników
  - Dostęp do zaświadczeń (używa wspólnego pakietu)
- **Relacja**: Używa tego samego pakietu `certificate-generator` do generowania PDF

### 2. **certificate-generator** (planowany: `../pne-certificate-generator/`)
- **Typ**: Wspólny pakiet Composer
- **Zawartość**:
  - `CertificateGeneratorService` - główna logika generowania
  - `TemplateRenderer` - renderowanie szablonów blade
  - `PDFGenerator` - generowanie PDF przez DomPDF
- **Użycie**: Importowany przez oba projekty (adm.pnedu.pl i pnedu.pl)

## 🏗️ Struktura katalogów

```
/home/hostnet/WEB-APP/
├── pneadm-bootstrap/          ← JESTEŚ TUTAJ (adm.pnedu.pl) ✅ W WORKSPACE
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── CertificateController.php
│   │   │   └── CertificateTemplateController.php
│   │   └── Services/
│   │       └── (używa certificate-generator)
│   └── resources/views/certificates/
│
├── pnedu/                     (pnedu.pl - publiczny serwis) ✅ W WORKSPACE
│   ├── app/
│   │   └── Http/Controllers/
│   │       └── UserCertificateController.php
│   └── (używa certificate-generator)
│
├── pne-certificate-generator/ (wspólny pakiet - planowany) ⏳ BĘDZIE W WORKSPACE
│   ├── src/
│   │   ├── Services/
│   │   │   ├── CertificateGeneratorService.php
│   │   │   ├── TemplateRenderer.php
│   │   │   └── PDFGenerator.php
│   │   └── Models/
│   └── composer.json
│
├── karta-rowerowa/            ❌ NIEZALEŻNY PROJEKT - NIE W WORKSPACE
├── example-app/               ❌ NIEZALEŻNY PROJEKT - NIE W WORKSPACE
└── laravel-bootstrap/         ❌ NIEZALEŻNY PROJEKT - NIE W WORKSPACE
```

### ⚠️ Ważne: Niezależne projekty

W katalogu `/home/hostnet/WEB-APP/` są też inne, **niezależne projekty**:
- `karta-rowerowa/` - osobny projekt
- `example-app/` - osobny projekt  
- `laravel-bootstrap/` - osobny projekt

**Te projekty NIE są w workspace `pne-platform.code-workspace`**, więc:
- ✅ AI ich **nie widzi** podczas pracy nad projektami PNE
- ✅ Nie wprowadzają zamieszania w kontekście
- ✅ Możesz pracować nad nimi osobno (otwórz osobny folder/workspace)

**Jeśli chcesz pracować nad `karta-rowerowa`:**
- Otwórz osobny folder w Cursor: `File → Open Folder...` → wybierz `karta-rowerowa`
- Lub utwórz osobny workspace: `karta-rowerowa.code-workspace`

## 🔄 Workflow z wieloma projektami

### Gdy zmieniasz logikę generowania zaświadczeń:

1. **Zmiana w pakiecie** (`certificate-generator`):
   ```bash
   cd ../pne-certificate-generator
   # Zmień kod
   git add .
   git commit -m "feat: dodano cache PDF"
   git push
   ```

2. **Aktualizacja w adm.pnedu.pl**:
   ```bash
   cd ../pneadm-bootstrap
   composer update pne/certificate-generator
   git add composer.lock
   git commit -m "chore: aktualizacja certificate-generator"
   ```

3. **Aktualizacja w pnedu.pl**:
   ```bash
   cd ../pnedu
   composer update pne/certificate-generator
   git add composer.lock
   git commit -m "chore: aktualizacja certificate-generator"
   ```

### Gdy zmieniasz tylko w adm.pnedu.pl:

- Zmiany w kontrolerach, widokach, routach - tylko w tym projekcie
- Nie wpływają na pnedu.pl (chyba że zmieniasz wspólny pakiet)

## 🗄️ Baza danych

- **Wspólna baza**: `pneadm`
  - Tabela `certificates` - rekordy zaświadczeń
  - Tabela `certificate_templates` - szablony zaświadczeń
  - Tabela `participants` - uczestnicy szkoleń
  - Tabela `courses` - kursy

- **Dostęp**:
  - `adm.pnedu.pl`: Full access (read-write)
  - `pnedu.pl`: Read-write access (tylko do swoich danych przez email)

## 📝 Jak pracować z AI (Cursor)

### Gdy pracujesz nad zaświadczeniami:

**Pytaj AI konkretnie:**
- ✅ "W pakiecie certificate-generator dodaj funkcję cache PDF"
- ✅ "W projekcie pnedu.pl użyj certificate-generator do generowania zaświadczeń"
- ✅ "W obu projektach zaktualizuj użycie pakietu po zmianie X"

**Unikaj:**
- ❌ "Dodaj cache" (nie wiadomo gdzie)
- ❌ "Zmień generowanie" (nie wiadomo w którym projekcie)

### Workspace:

Otwórz workspace file: `/home/hostnet/WEB-APP/pne-platform.code-workspace`

To pozwoli AI widzieć wszystkie projekty jednocześnie.

## 🔗 Zależności Composer

W `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../pne-certificate-generator"
        }
    ],
    "require": {
        "pne/certificate-generator": "*"
    }
}
```

## ⚠️ Ważne uwagi

1. **Zmiany w pakiecie** wpływają na oba projekty
2. **Po zmianie pakietu** zawsze `composer update` w obu projektach
3. **Baza danych** jest wspólna - zmiany w jednym projekcie widoczne w drugim
4. **Szablony blade** są generowane z bazy przez `TemplateBuilderService`
5. **Cache PDF** - opcjonalnie, ale zalecane dla wydajności

