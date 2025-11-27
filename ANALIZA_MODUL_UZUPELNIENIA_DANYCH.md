# Analiza i warianty architektury - Moduł uzupełniania danych uczestników

## 📋 Analiza istniejącego kodu

### Struktura danych

**Tabela `participants`:**
- `id` - klucz główny
- `course_id` - relacja do kursu
- `first_name`, `last_name` - imię i nazwisko
- `email` - adres e-mail (nullable)
- `birth_date` - data urodzenia (nullable, date)
- `birth_place` - miejsce urodzenia (nullable, string)
- **BRAK `user_id`** - uczestnicy nie są powiązani z tabelą users

**Tabela `courses`:**
- `id` - klucz główny
- `source_id_old` - źródło danych (wartość: `"certgen_Publigo"` dla szkoleń)
- `title` - nazwa kursu
- `start_date`, `end_date` - daty szkolenia
- `instructor_id` - relacja do instruktora

**Model `ParticipantEmail`:**
- Istnieje model, który grupuje uczestników po emailu
- Ma relację `participants()` zwracającą wszystkich uczestników z danym emailem
- **Można wykorzystać do identyfikacji "tej samej osoby"**

### Identyfikacja "tej samej osoby"

**Opcje identyfikacji:**
1. **Email** (najprostsze, ale może być problem z duplikatami emaili dla różnych osób)
2. **Email + imię + nazwisko** (bardziej precyzyjne, ale może być problem z różnymi zapisami)
3. **Model ParticipantEmail** (istniejący mechanizm grupowania)

**Rekomendacja:** Użycie **emaila jako głównego identyfikatora** z fallback na normalizację imię+nazwisko dla przypadków bez emaila (choć w wymaganiach jest, że email jest wymagany).

### Mechanizmy w projekcie

- **Menu:** `resources/views/layouts/navigation.blade.php` - struktura accordion z Bootstrap
- **Routing:** `routes/web.php` - standardowy Laravel routing
- **Maile:** Brak istniejących klas Mailable - trzeba utworzyć
- **Tokeny:** Brak mechanizmu tokenów - trzeba utworzyć
- **Walidacja:** Laravel Request classes

---

## 🏗️ Warianty architektury

### WARIANT 1: Prosty - Email jako klucz, jedna tabela tokenów

#### Architektura

**Nowe tabele:**
1. `data_completion_tokens` - tokeny do formularzy
   - `id`, `email`, `token`, `used_at`, `expires_at`, `created_at`, `updated_at`
   - Indeks na `token`, `email`

2. `data_completion_requests` - logi wysłanych próśb
   - `id`, `email`, `course_id` (nullable - dla logowania per kurs), `sent_at`, `completed_at`, `created_at`, `updated_at`
   - Indeks na `email`, `course_id`, `sent_at`

**Logika grupowania:**
- Uczestnicy grupowani po **emailu** (lowercase, trimmed)
- Jeden token na email
- Jeden mail na email (z listą wszystkich kursów certgen_Publigo)

**Przepływ:**
1. Kontroler znajduje uczestników z brakami dla kursów `certgen_Publigo`
2. Grupuje po emailu
3. Dla każdego emaila:
   - Sprawdza czy już wysłano prośbę (w `data_completion_requests`)
   - Generuje token (jeśli nie istnieje aktywny)
   - Wysyła mail z listą wszystkich kursów tej osoby
   - Loguje w `data_completion_requests`
4. Formularz przyjmuje token, aktualizuje wszystkie rekordy uczestnika z tym emailem

**Plusy:**
- ✅ Prosta struktura
- ✅ Szybka implementacja
- ✅ Łatwe zapytania (grupowanie po emailu)
- ✅ Jeden token = jedna osoba

**Minusy:**
- ⚠️ Problem jeśli ta sama osoba ma różne emaile (rzadkie, ale możliwe)
- ⚠️ Brak możliwości wysłania ponownej prośby bez ręcznego usunięcia rekordu

**Wpływ na istniejący kod:**
- Minimalny - nowe tabele, nowy kontroler, nowe widoki
- Wykorzystanie istniejącego modelu ParticipantEmail (opcjonalnie)

---

### WARIANT 2: Zaawansowany - Model ParticipantEmail + dedykowana tabela uczestników

#### Architektura

**Nowe tabele:**
1. `data_completion_tokens` - jak w wariancie 1
2. `data_completion_requests` - jak w wariancie 1
3. `participant_data_completions` - dedykowana tabela dla procesu uzupełniania
   - `id`, `participant_email_id` (FK do participant_emails), `status` (pending/completed), `requested_at`, `completed_at`, `created_at`, `updated_at`
   - Indeks na `participant_email_id`, `status`

**Logika grupowania:**
- Wykorzystanie istniejącego modelu `ParticipantEmail`
- Jeden rekord w `participant_data_completions` reprezentuje "osobę" (grupę uczestników)
- Token powiązany z `participant_email_id`

**Przepływ:**
1. Kontroler znajduje uczestników z brakami dla kursów `certgen_Publigo`
2. Grupuje przez model `ParticipantEmail` (po emailu)
3. Dla każdego `ParticipantEmail`:
   - Tworzy/aktualizuje rekord w `participant_data_completions`
   - Generuje token powiązany z `participant_email_id`
   - Wysyła mail z listą wszystkich kursów z `participantEmail->participants()`
   - Loguje w `data_completion_requests`
4. Formularz przyjmuje token, aktualizuje wszystkie rekordy z `participantEmail->participants()`

**Plusy:**
- ✅ Wykorzystanie istniejącej infrastruktury (`ParticipantEmail`)
- ✅ Lepsze zarządzanie stanem (tabela `participant_data_completions`)
- ✅ Możliwość rozszerzenia o dodatkowe pola/metadane
- ✅ Czytelniejsza logika biznesowa

**Minusy:**
- ⚠️ Więcej tabel = więcej złożoności
- ⚠️ Wymaga synchronizacji z istniejącym modelem `ParticipantEmail`
- ⚠️ Jeśli `ParticipantEmail` nie jest w pełni wykorzystywany, może być overkill

**Wpływ na istniejący kod:**
- Średni - wykorzystanie istniejącego modelu `ParticipantEmail`
- Możliwość rozszerzenia modelu `ParticipantEmail` o relacje

---

### WARIANT 3: Hybrydowy - Email + normalizacja imię+nazwisko, cache wyników

#### Architektura

**Nowe tabele:**
1. `data_completion_tokens` - jak w wariancie 1
2. `data_completion_requests` - jak w wariancie 1
3. `participant_groups` - cache grupowania uczestników
   - `id`, `email` (nullable), `normalized_name` (hash z imię+nazwisko), `participant_ids` (JSON array), `created_at`, `updated_at`
   - Indeks na `email`, `normalized_name`
   - **Cel:** Cache wyników grupowania dla wydajności (262k rekordów)

**Logika grupowania:**
- **Priorytet 1:** Email (jeśli istnieje)
- **Priorytet 2:** Normalizacja imię+nazwisko (lowercase, trimmed, bez polskich znaków dla porównania)
- Grupowanie wykonywane raz, wyniki cache'owane w `participant_groups`
- Jeden token na grupę

**Przepływ:**
1. Kontroler znajduje uczestników z brakami dla kursów `certgen_Publigo`
2. Grupuje uczestników (email lub normalized_name)
3. Dla każdej grupy:
   - Sprawdza cache w `participant_groups` (lub tworzy)
   - Generuje token powiązany z grupą
   - Wysyła mail z listą wszystkich kursów grupy
   - Loguje w `data_completion_requests`
4. Formularz przyjmuje token, aktualizuje wszystkie rekordy z grupy

**Plusy:**
- ✅ Obsługa przypadków bez emaila (choć w wymaganiach email jest wymagany)
- ✅ Cache grupowania = lepsza wydajność przy 262k rekordów
- ✅ Elastyczność (można rozszerzyć o inne kryteria grupowania)

**Minusy:**
- ⚠️ Najbardziej złożony
- ⚠️ Wymaga synchronizacji cache przy zmianach danych
- ⚠️ Może być overkill jeśli email jest zawsze dostępny

**Wpływ na istniejący kod:**
- Średni - nowa logika grupowania, cache
- Możliwość wykorzystania w innych miejscach aplikacji

---

## 📊 Porównanie wariantów

| Kryterium | Wariant 1 | Wariant 2 | Wariant 3 |
|-----------|-----------|-----------|-----------|
| **Złożoność** | Niska | Średnia | Wysoka |
| **Wydajność** | Dobra | Dobra | Bardzo dobra (cache) |
| **Wykorzystanie istniejącego kodu** | Minimalne | Średnie (ParticipantEmail) | Minimalne |
| **Elastyczność** | Podstawowa | Średnia | Wysoka |
| **Czas implementacji** | Najkrótszy | Średni | Najdłuższy |
| **Obsługa edge cases** | Podstawowa | Dobra | Bardzo dobra |

---

## 🎯 Rekomendacja

**Rekomenduję WARIANT 1** z następujących powodów:

1. **Prostota** - zgodnie z wymaganiami, email jest wymagany, więc nie ma potrzeby obsługi przypadków bez emaila
2. **Wydajność** - grupowanie po emailu jest szybkie (indeks na email w participants)
3. **Czas implementacji** - najszybszy, co pozwala szybko dostarczyć funkcjonalność
4. **Łatwość utrzymania** - prosta struktura = łatwiejsze debugowanie i rozszerzanie

**Jeśli w przyszłości pojawi się potrzeba:**
- Obsługi przypadków bez emaila → można rozszerzyć o Wariant 3
- Wykorzystania ParticipantEmail → można zmigrować do Wariantu 2
- Cache'owania → można dodać warstwę cache bez zmiany struktury

---

## 🔧 Szczegóły techniczne (Wariant 1)

### Struktura tabel

```sql
-- Tabela tokenów
CREATE TABLE data_completion_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
);

-- Tabela logów próśb
CREATE TABLE data_completion_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    course_id BIGINT NULL, -- NULL = wysłano dla wszystkich kursów osoby
    sent_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_course (course_id),
    INDEX idx_sent (sent_at),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);
```

### Struktura plików

```
app/
  Http/
    Controllers/
      DataCompletionController.php      # Główny kontroler (Test/Zbierz)
      DataCompletionFormController.php   # Formularz publiczny (bez auth)
  Models/
    DataCompletionToken.php
    DataCompletionRequest.php
  Mail/
    DataCompletionRequestMail.php       # Klasa Mailable
  Requests/
    DataCompletionFormRequest.php       # Walidacja formularza
resources/
  views/
    data-completion/
      test.blade.php                    # Widok "Test"
      collect.blade.php                 # Widok "Zbierz"
      form.blade.php                    # Publiczny formularz
      email.blade.php                   # Szablon maila
database/
  migrations/
    YYYY_MM_DD_create_data_completion_tokens_table.php
    YYYY_MM_DD_create_data_completion_requests_table.php
routes/
  web.php                               # Nowe route'y
```

### Endpointy

```
GET  /data-completion/test              # Widok testowy
GET  /data-completion/collect           # Widok produkcyjny
POST /data-completion/send-test         # Symulacja dla testu
POST /data-completion/send/{courseId}  # Wysyłka dla kursu
GET  /uzupelnij-dane?token=XXX          # Publiczny formularz
POST /uzupelnij-dane                    # Zapis danych
```

### Zapytania SQL (wydajność)

```sql
-- Znajdź uczestników z brakami dla kursów certgen_Publigo
SELECT DISTINCT p.email, p.first_name, p.last_name
FROM participants p
INNER JOIN courses c ON p.course_id = c.id
WHERE c.source_id_old = 'certgen_Publigo'
  AND p.email IS NOT NULL
  AND p.email != ''
  AND (p.birth_date IS NULL OR p.birth_place IS NULL)
GROUP BY p.email, p.first_name, p.last_name;

-- Sprawdź czy już wysłano prośbę
SELECT COUNT(*) FROM data_completion_requests
WHERE email = ? AND completed_at IS NULL;
```

---

## ❓ Pytania do użytkownika

1. **Czy email jest zawsze dostępny dla uczestników kursów `certgen_Publigo`?** (Jeśli nie, trzeba rozważyć Wariant 3)

2. **Czy chcesz możliwość wysłania ponownej prośby do osoby, która nie uzupełniła danych?** (Jeśli tak, trzeba dodać mechanizm resetowania tokenu)

3. **Czy chcesz ograniczenie czasowe na token?** (np. 30 dni ważności)

4. **Czy chcesz możliwość wysłania prośby dla wszystkich kursów naraz, czy tylko per kurs?** (W wymaganiach jest "per kurs", ale można rozważyć opcję "wszystkie")

5. **Czy chcesz wykorzystać istniejący model `ParticipantEmail`?** (Jeśli tak, lepiej Wariant 2)

---

## 📝 Następne kroki

Po wyborze wariantu:
1. Utworzenie szczegółowego planu implementacji
2. Projekt struktur tabel/modeli
3. Projekt endpointów i widoków
4. Implementacja krok po kroku

