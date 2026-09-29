# PNE Growth OS — architektura proponowana

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1.1
Status: Etap 0.1 wdrożony lokalnie; dalsza architektura pozostaje rekomendacją

## Stan faktyczny po Etapie 0.1

Szkielet Growth OS został osadzony addytywnie wyłącznie w aplikacji `pneadm`:

- konfiguracja: `config/growth_os.php`,
- zmienna środowiskowa: `PNE_GROWTH_OS_ENABLED=false`,
- middleware: `App\Http\Middleware\GrowthOS\EnsureGrowthOsAccess`,
- kontroler: `App\Http\Controllers\GrowthOS\DashboardController`,
- trasa: `GET /growth`, nazwa `growth.dashboard`,
- widok: `resources/views/growth-os/dashboard.blade.php`,
- menu renderowane tylko przy włączonej fladze i roli `super_admin`.

Middleware działa fail closed:

- `config('growth_os.enabled') !== true` → HTTP 404,
- funkcja włączona, ale brak roli `super_admin` → HTTP 403,
- gość → istniejący middleware `auth` kieruje do logowania.

Na tym etapie nie istnieją tabele, modele domenowe, joby, eventy, integracje ani odczyty danych biznesowych Growth OS.

## Decyzja kierunkowa

Growth OS powinien powstać jako wydzielony moduł istniejącego monolitu `pneadm`, dostępny wewnątrz `adm.pnedu.pl`.

Nie rekomenduje się:

- trzeciej aplikacji lub mikroserwisu na początku,
- przenoszenia istniejącej logiki do Growth OS,
- używania n8n, Sendy lub innego SaaS jako source of truth,
- wspólnego „wielkiego modelu” łączącego od razu klientów, zamówienia i treści.

## Układ logiczny

```text
Waldemar / uprawniony operator
            ↓
adm.pnedu.pl — moduł Growth OS
  ├─ Pulpit i Inbox decyzji
  ├─ Tematy i kampanie
  ├─ Eksperci i biblioteka treści
  ├─ AI Orchestrator
  ├─ Approval / Execution
  ├─ Adaptery integracji
  └─ Read models / metryki
       ↓                  ↓
 baza pneadm         baza pne_analytics
 stan operacyjny     eventy i agregaty bez PII
       ↓                  ↑
 istniejące moduły    pnedu.pl i procesy analityczne
       ↓
 wykonawcy zewnętrzni: OpenAI, Sendy, YouTube, Meta, Canva, Drive
```

## Warstwy modułu

### 1. Warstwa UI

Osobna, spokojna sekcja panelu:

- Pulpit,
- Do zatwierdzenia,
- Tematy,
- Kampanie,
- Eksperci,
- Biblioteka,
- później Radar, CRM szkół i Analityka Growth.

UI używa obecnego stosu Blade + Bootstrap. Nie ma potrzeby wprowadzania SPA ani zmiany frameworka.

### 2. Warstwa aplikacyjna

Serwisy przypadków użycia, np.:

- utworzenie kampanii,
- przygotowanie briefu,
- zlecenie analizy AI,
- utworzenie draftów,
- przesłanie do zatwierdzenia,
- wykonanie zatwierdzonej publikacji,
- odczyt metryk.

Kontrolery pozostają cienkie. Każda operacja wykonawcza sprawdza flagę, uprawnienie, status approval oraz idempotency key.

### 3. Domena Growth

Nowe pojęcia są izolowane nazwą i przestrzenią:

- Topic,
- Growth Campaign,
- Campaign Goal,
- Content Asset,
- Approval,
- Integration Operation,
- AI Run / Draft,
- Expert Profile.

Istniejące modele pozostają właścicielami swoich procesów. Growth OS przechowuje referencje, nie kopie całej domeny sprzedaży.

### 4. AI Orchestrator

Użytkownik uruchamia zamiar biznesowy, nie technicznego agenta. Orchestrator:

1. wybiera dozwolone źródła,
2. buduje zminimalizowany kontekst,
3. wybiera zadanie i model według konfiguracji,
4. zapisuje źródła, wersję promptu, model, koszt i wynik,
5. tworzy draft lub rekomendację,
6. nigdy sam nie publikuje operacji wysokiego ryzyka.

### 5. Approval i Execution

Draft i wykonanie są osobnymi stanami.

```text
draft
→ ready_for_review
→ approved albo changes_requested
→ queued
→ executing
→ completed albo failed
```

Approval nie może być równoznaczny z wykonaniem, jeśli zewnętrzna operacja może się nie udać. Stan zewnętrznego wykonania musi być rejestrowany.

### 6. Adaptery integracji

Każdy dostawca ma własny adapter. Logika kampanii nie może znać szczegółów Sendy, YouTube czy OpenAI.

Wspólne wymagania:

- timeout,
- retry z backoff,
- idempotency,
- maskowanie sekretów,
- zapis bezpiecznego wyniku i błędu,
- możliwość ręcznego ponowienia,
- tryb testowy lub dry-run tam, gdzie dostawca go umożliwia.

### 7. Dane i analityka

- Stan operacyjny Growth OS: rekomendowane docelowo addytywne tabele w `pneadm`; decyzja fizyczna wymaga osobnej akceptacji.
- Eventy, sesje i agregaty: `pne_analytics`.
- Dane klientów, uczestników i faktur: pozostają w istniejących tabelach operacyjnych.
- Growth OS korzysta z read models i agregatów zamiast wykonywać ciężkie analizy na ścieżkach transakcyjnych.

## Granica ze starym systemem

### Growth OS może

- czytać zatwierdzone metadane kursów, produktów, artykułów i ekspertów,
- tworzyć własne tematy, kampanie, drafty i relacje,
- linkować do istniejących rekordów przez typ i ID,
- uruchamiać własne joby,
- konsumować agregaty `pne_analytics`,
- po osobnej zgodzie przekazywać zatwierdzone treści do istniejącego modułu publikacji.

### Growth OS nie może

- zmieniać statusu płatności,
- tworzyć lub korygować faktur,
- nadawać dostępu do produktu jako efekt rekomendacji AI,
- zmieniać cen bez osobnego workflow,
- publikować wypowiedzi eksperta bez approval,
- aktualizować istniejących modeli „przy okazji” implementacji,
- wymagać działania zewnętrznego API, aby checkout lub panel uczestnika działał.

## Feature flag i uprawnienia

Rekomendowany układ:

1. `PNE_GROWTH_OS_ENABLED=false` jako twardy kill switch w konfiguracji — wdrożone w Etapie 0.1.
2. Tymczasowo rola `super_admin`; osobne uprawnienie Growth powstanie dopiero w etapie, który świadomie zmienia dane autoryzacyjne.
3. Osobne uprawnienia przygotowania, zatwierdzania i wykonania.
4. Domyślnie brak dostępu dla istniejących użytkowników poza jawnie wskazaną rolą.
5. Ukryte menu i trasy przy wyłączonej fladze.

## Wzorzec zdarzeń

Growth OS nie powinien w pierwszych etapach podpinać się synchronicznie pod krytyczne operacje. Preferowane są:

- jawne akcje użytkownika,
- okresowe odczyty read-only,
- osobne joby uruchamiane po commit,
- w przyszłości outbox dla istotnych zdarzeń między modułami.

Observer istniejącego modelu jest dopuszczalny dopiero po analizie konkretnego przypadku i wyłącznie fail-silent, jeśli zdarzenie nie jest krytyczne dla starego procesu.

## Observability

Każde wykonanie automatyczne powinno mieć:

- biznesową nazwę zadania,
- status i czas,
- użytkownika inicjującego oraz zatwierdzającego,
- źródła,
- liczbę prób,
- bezpieczny opis błędu,
- identyfikator operacji u dostawcy, widoczny tylko w szczegółach technicznych,
- akcję „spróbuj ponownie” tam, gdzie jest bezpieczna.

## Rollback modułu

Po wyłączeniu flagi:

- menu i trasy Growth OS są niedostępne,
- nie uruchamiają się nowe joby ani publikacje,
- istniejące dane Growth pozostają bezpiecznie w bazie,
- stare moduły nie odczytują danych Growth jako wymaganych,
- `pnedu.pl`, checkout, płatności, certyfikaty i panel uczestnika działają bez zmiany.

Rollback wdrożenia nie oznacza automatycznego usuwania danych ani cofania już opublikowanych treści w zewnętrznych systemach. Takie działania wymagają osobnej procedury.
