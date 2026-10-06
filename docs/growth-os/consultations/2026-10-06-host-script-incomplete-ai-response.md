# Konsultacja: błąd „nie udało się przygotować poprawnej propozycji” przy scenariuszu

Data: 2026-10-06  
Kontekst: Growth OS, `host-script` na produkcji

## Objaw

Komunikat: „Nie udało się przygotować poprawnej propozycji AI. Obecny szkic nie został zmieniony.”  
UI: GPT-6.1 Sol · Zrównoważony, badge „Sieć”, czas 90 minut, pusty szkic.

## Diagnoza (kod)

Ten komunikat mapuje się na `GrowthAiException::INVALID_RESPONSE_MESSAGE`. Przy długim scenariuszu typowe przyczyny:

1. `incomplete_output` / `invalid_json` — budżet `max_output_tokens` (dla medium ok. 6000) dzieli się na reasoning + ewentualne web_search + JSON draftu; odpowiedź się urywa.
2. `unexpected_url` — przy włączonej „Sieci” model wrzuca URL do draftu, a walidacja odrzuca.
3. Draft > limitu znaków (było 12 000).

## Decyzja Waldemara

„Sieć” ma zostać — choć w ograniczonym zakresie (nie wyłączać domyślnie).

## Naprawa

1. Minimalny `max_output_tokens` dla host-script: 16 000 (miejsce na reasoning + krótki search + długi draft).
2. Limit draftu: 20 000 znaków.
3. Prompt `material_host_script_v4`: zwięzłe „Do powiedzenia”; przy web_search tylko 1–2 weryfikacje faktów o narzędziach z wejścia, bez budowania scenariusza ze stron i bez URL w draftcie.
4. Czytelniejsze komunikaty przy `incomplete_output` / `unexpected_url`.
5. „Sieć” domyślnie ON jak w innych materiałach (DEC-053).

Bez migracji.
