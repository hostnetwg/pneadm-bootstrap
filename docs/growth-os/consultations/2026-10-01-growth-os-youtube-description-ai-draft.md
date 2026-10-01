# Consultation Result — PNE Growth OS AI Draft For YouTube Description

Date: 2026-10-01<br>
Status: accepted owner specification, implemented locally, not committed<br>
Scope: first v0.2 slice — AI draft for the material `youtube-description` only

This file records the accepted result. It does not replace `CURRENT.md` or `DEC-024`. If they differ, code and `CURRENT.md` win.

## Accepted Direction

The material screen „Opis YouTube” has „Poproś AI o szkic”. It works only after both the direction and the concept are approved; otherwise the screen shows „Najpierw zatwierdź kierunek i koncepcję webinaru.” and no provider is called.

`GrowthAiService` now runs two tasks through a small `GrowthAiTask` contract: `concept_revision` (unchanged behaviour) and `material_draft`. The new task uses profile `youtube_description_v1`, prompt `material_youtube_description_v2` and schema `material_youtube_description_schema_v1`. Output is `draft` and `change_summary` only. Prompt v2 adds the owner's request for emojis: the checkbox „Dodaj emotikony do opisu” (checked by default) sends `style.emojis`; true asks for roughly 5–10 fitting emojis, false asks for none. Prompt v2 also accepts an optional owner instruction (`instruction`, max 1000 characters, same personal-data block); the prompt's rules take precedence over it.

Input allow-list: material key/name/type; campaign `working_topic`, goal label, `live_date`, `live_time`, app timezone; the five direction fields; concept `title`, `subtitle`, `promise`, `points`, `plan`, `cta`, `additional_material`; the current draft of this material; `style.emojis`; optional `instruction`. Other materials and any customer, order, payment or contact data are never sent.

Owner follow-up (DEC-025): the host's name is not secret, so `campaign.host_name` is now sent. The prompt copies it exactly and adds no titles or biography; an empty host or „—” is sent as empty and the AI must not invent one. The host is part of the fingerprint, so changing it makes an old proposal stale.

The proposal lives in the HTTP session with a sha256 fingerprint of the direction fields, the concept fields and the current draft. „Zastosuj” recomputes it and re-checks both approvals. A mismatch clears the proposal and shows „Kierunek, koncepcja lub materiał zmieniły się od czasu wygenerowania szkicu. Wygeneruj nową propozycję.” A match writes `payload.draft`, sets status `DRAFT`, saves the `material` artifact and records decision `material_ai_apply`. „Odrzuć” records `material_ai_reject` and changes nothing else. Decision `meta` holds only `material_key`, `prompt_version` and `source`.

With `GROWTH_AI_ENABLED=false` a local simulation builds the draft from the approved concept with the same UX. Provider errors change nothing and show a short message; manual editing keeps working.

## Explicitly Unchanged

No AI for the other nine materials. No publishing, sending or external integration. No new feature flag, no persisted proposal, no proposal table, no model routing or fallback. Material `schema_version` stays 1. Manual „Zapisz materiał” still creates no decision. The daily limit and circuit breaker are shared with `concept_revision`.

## Not Done

The optional warning on an already-saved material when the direction or concept changed after it was prepared.
