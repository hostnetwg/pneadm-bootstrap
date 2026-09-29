# PNE Growth OS — Architecture

Status: architektura koncepcyjna, bez migracji.

## Zasada Główna

`adm.pnedu.pl` pozostaje głównym źródłem prawdy dla PNE Growth OS. Systemy zewnętrzne, takie jak OpenAI, YouTube, Sendy, Meta, Canva lub Google Drive, są wykonawcami konkretnych zadań, ale nie przejmują kanonicznego stanu procesu.

## Przyszłe Obiekty Domenowe

- **Growth Campaign** — strategiczny projekt wokół tematu, eksperta, treści i celu.
- **Topic** — potrzeba, temat lub obszar zainteresowania rynku.
- **Expert** — profil ekspercki oparty o istniejącego prowadzącego / instruktora.
- **Artifact** — roboczy rezultat pracy: draft, brief, grafika, konspekt, prompt, materiał.
- **Task** — zadanie przygotowawcze lub operacyjne.
- **Decision / Approval** — decyzja właściciela lub osoby odpowiedzialnej.
- **Content** — opublikowana lub planowana treść.
- **Product** — powiązany produkt, szkolenie, e-book lub oferta.
- **Customer / Organization** — osoba, szkoła, JST, organizacja lub segment relacji.
- **Event / Activity** — zdarzenie, interakcja, publikacja, wysyłka lub aktywność.
- **Metrics** — metryki skuteczności dopasowane do celu.

## Ważne Rozróżnienia

### Growth Campaign ≠ MarketingCampaign

`MarketingCampaign` w istniejącym systemie dotyczy kampanii atrybucyjnej, linków, UTM i analityki marketingowej.

`Growth Campaign` to strategiczny projekt pracy: temat, ekspert, cel, treści, decyzje, zadania i wyniki.

### Course ≠ Growth Campaign

`Course` to konkretne szkolenie lub wydarzenie z terminem.

`Growth Campaign` może prowadzić do `Course`, może korzystać z istniejącego `Course`, albo w ogóle nie mieć produktu sprzedażowego.

## Obecny Etap 0.3.2

Obecnie nie ma jeszcze tabel domenowych Growth OS. Prototyp działa tylko w sesji HTTP i nie zapisuje stanu do bazy.

Nie tworzymy jeszcze migracji.

Jedyną rzeczywistą integracją zewnętrzną pilotażu jest opcjonalna rewizja koncepcji przez OpenAI:

```text
Etap Koncepcja
→ GrowthAiService
→ ConceptRevisionTask (allowlista danych, prompt i schema)
→ GrowthAiProvider
→ OpenAiProvider
→ OpenAI Responses API
→ parsowanie i walidacja
→ propozycja w sesji
→ jawne Zastosuj / Odrzuć
```

Logika etapu Koncepcja nie zależy bezpośrednio od endpointu ani SDK OpenAI. Provider i model są konfiguracją centralną. W pilotażu istnieje tylko implementacja OpenAI; nie ma automatycznego routingu ani fallbacku do innego dostawcy.

Stan projektu i pełna propozycja pozostają w sesji HTTP. Log plikowy przechowuje wyłącznie minimalne metadane techniczne wywołania, bez promptu, odpowiedzi i treści koncepcji.

## Granice

Growth OS nie może wprowadzać synchronicznych zależności do:

- zamówień,
- płatności,
- faktur,
- certyfikatów,
- provisioningu pnedu.pl.

Moduł pozostaje addytywny i odwracalny przez feature flag.
