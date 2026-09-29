# Consultation Result — PNE Growth OS Direction

Date: 2026-09-29  
Status: accepted owner decision and implemented  
Scope: editable direction fields and a stored approval on the existing project screen

This file records the accepted result. It does not replace `CURRENT.md` or `DEC-022`. If they differ, code and `CURRENT.md` win.

## Accepted Direction

The direction block stores five fields as one `growth_artifacts` row: `key=direction`, `type=direction`, `schema_version=1`.

Payload fields:

- `why_now`
- `audience`
- `problem`
- `takeaway`
- `sell_later` (`nie`, `być może`, or `tak`)

„Zapisz kierunek” writes the artifact and does not create a decision. Opening the project does not insert a row.

## Approval

„Zatwierdź kierunek” writes a `growth_decisions` row with `type=direction_approval` and `status=approved`, and stores the current five fields. After login the approval returns, so the main next step does not fall back to direction.

„Cofnij zatwierdzenie” marks the previous approval `superseded` and adds `changes_requested`. Editing an approved direction also marks the approval `superseded` and does not add another decision.

Applying a concept AI proposal that changes `audience` stores the direction and supersedes its approval. The AI proposal itself stays in the browser session. The static direction hint is not stored.

## Explicitly Unchanged

The host name stays in the session and is empty after restore. Operational tasks do not drive the main next step. Topic remains on the campaign, not inside the direction artifact.
