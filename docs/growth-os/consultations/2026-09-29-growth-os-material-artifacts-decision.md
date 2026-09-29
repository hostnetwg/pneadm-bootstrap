# Consultation Result — PNE Growth OS Material Artifacts

Date: 2026-09-29  
Status: accepted owner decision and implemented  
Scope: ten working webinar materials on the existing material screen

This file records the accepted result. It does not replace `CURRENT.md` or `DEC-021`. If they differ, code and `CURRENT.md` win.

## Accepted Direction

All ten session materials are stored as `growth_artifacts` with `type=material`, `schema_version=1`, and a stable `key`. The same screen edits status and draft. There is no new module and no AI.

Rows are written only when the owner clicks „Zapisz materiał”. Opening the material page does not insert a row. A new campaign does not pre-create these artifacts.

## The Ten Keys

- `youtube-description`
- `main-graphic`
- `facebook-post`
- `main-mail`
- `reminder-mail`
- `landing`
- `host-script`
- `participant-material`
- `obs-intro`
- `follow-up`

`payload` holds the workspace status and the draft text. `version` is a counter. The artifact title and summary come from the existing material name and short description.

## Status Mapping

Workspace labels stay `NOT_STARTED`, `DRAFT`, `REVIEW`, `APPROVED`, and `PUBLISHED`. The database column uses the canonical artifact statuses. `PUBLISHED` is stored only in `payload.status` and the column becomes `approved`, because `published` is not an artifact status. Nothing is sent to Sendy, YouTube, or Meta.

## Explicitly Unchanged

Saving a material does not create a `growth_decisions` row and does not change the next-step rules. Direction, the AI proposal, and the host name remain in the browser session. Operational tasks stay unrelated to these artifacts.
