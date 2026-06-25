# Forma komunikacji: AI (Cursor) ↔ Człowiek ↔ ChatGPT

Ten dokument definiuje OBOWIĄZUJĄCĄ formę komunikacji asystenta AI (Cursor) z właścicielem projektu (człowiekiem) oraz z głównym konsultantem AI (ChatGPT). Obowiązuje w obu projektach (`pnedu`, `pneadm`) i na każdym komputerze deweloperskim.

> Plik bliźniaczy: `pnedu/docs/AI_HUMAN_COMMUNICATION.md` (wskazuje na ten dokument). Zasada jest też skrótowo zapisana w `.cursorrules` obu projektów, aby asystent stosował ją automatycznie.

## Zasada główna

Po KAŻDEJ zakończonej akcji/kroku (np. wdrożenie etapu, naprawa, refaktor, analiza) asystent przygotowuje **dwa podsumowania**:

1. **Podsumowanie dla człowieka (proste)** — krótkie, prostym językiem, bez żargonu.
2. **Podsumowanie techniczne jako prompt dla ChatGPT** — szczegółowe, gotowe do skopiowania, w bloku kodu Markdown.

Dodatkowo, jeśli to istotne dla decyzji — asystent zadaje **kluczowe pytania bezpośrednio człowiekowi**.

## 1. Podsumowanie dla człowieka (proste)

- Pisane prostym, zrozumiałym językiem (po polsku).
- Krótkie: co zrobiono, co to oznacza w praktyce, co dalej.
- Bez wewnętrznych nazw klas/metod, chyba że konieczne.
- Może zawierać krótką listę „co działa” / „na co uważać”.
- Na końcu, jeśli trzeba: kluczowe pytania do człowieka (to właściciel podejmuje decyzje).

## 2. Podsumowanie techniczne (prompt do ChatGPT)

- Umieszczone w **bloku kodu Markdown** (```), aby można je było skopiować jednym ruchem.
- Samowystarczalne: zawiera kontekst potrzebny ChatGPT, bo on nie zna historii czatu w Cursorze.
- Zawiera: co zmieniono (pliki), gdzie podłączono logikę, payloady/kontrakty, decyzje, RODO/bezpieczeństwo, wyniki testów, czego nie ruszono, ryzyka.
- Na końcu, jeśli trzeba: konkretne pytania do ChatGPT lub prośba o feedback / weryfikację modelu.
- Nie zawiera sekretów (haseł, tokenów, kluczy, danych osobowych).

## 3. Pytania

- Pytania **kluczowe dla decyzji biznesowych** kieruj do człowieka — to on jest najważniejszy w podejmowaniu decyzji.
- Pytania **techniczne / dot. architektury / weryfikacji modelu** mogą iść do ChatGPT w prompcie.
- Pytania zadawaj na końcu, po podsumowaniach.

## Kolejność w odpowiedzi asystenta

1. (jeśli dotyczy) Krótka informacja o wykonanej akcji.
2. Podsumowanie dla człowieka (proste).
3. Podsumowanie techniczne jako prompt do ChatGPT (blok kodu do skopiowania).
4. **Następny rekomendowany krok** — jeden konkretny krok (nie lista wielu opcji), bez wdrażania go bez zgody człowieka.
5. Kluczowe pytania (do człowieka i/lub do ChatGPT).

## Kiedy stosować

- **Tylko po znaczących krokach** (wdrożenie etapu, większa zmiana, analiza wymagająca decyzji, commit/PR).
- Przy drobnych akcjach (jednolinijkowa poprawka, krótka odpowiedź informacyjna, samo wyjaśnienie) pełna struktura **nie jest wymagana**.
- Jeśli przy drobnej akcji pojawia się decyzja do podjęcia — wystarczy sekcja pytań (ew. krótkie podsumowanie dla człowieka).

## Dodatkowe zasady stałe (przypomnienie)

- Nie commituj zmian bez wyraźnej zgody człowieka.
- Wszystkie komendy Laravel/PHP uruchamiaj przez Sail.
- Zachowuj fail-silent i RODO w analityce (bez PII, bez raw payloadów).
