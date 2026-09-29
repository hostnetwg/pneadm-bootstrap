# Consultation Result — PNE Growth OS Operational Tasks

Date: 2026-09-29  
Status: accepted owner decision and implemented  
Scope: nine operational tasks on the existing webinar timeline

This file records the accepted result. It does not replace `CURRENT.md` or `DEC-020`. If they differ, code and `CURRENT.md` win.

## Accepted Direction

`growth_tasks` stores a small operational checklist for a Growth Campaign. The whole timeline is not converted into tasks.

Tasks are created when a campaign is created. Existing campaigns are filled by the explicit command `growth:seed-operational-tasks`. Opening the project page does not insert rows.

## The Nine Tasks

Stable `key` values:

- `youtube-live` — live_at minus 5 days
- `technical-test` — live_at minus 1 day
- `social-reminder` — live_at minus 3 hours
- `live-webinar` — live_at
- `certificate-form` — live_at
- `recording` — live_at plus 1 day
- `transcription` — live_at plus 1 day
- `certificate-topics` — live_at plus 1 day
- `content-repurposing` — live_at plus 3 days

If `live_at` is null, `due_at` stays null. Later edits of the live date do not rebuild these deadlines.

## Explicitly Not Tasks

Do not create tasks for topic, direction, concept, graphics, landing, main mailing, Facebook, host script, participant material, mail reminder, or follow-up. Those belong to the main flow or to session materials.

## UX Rules

The current screen shows only `todo` and `done`. The model still allows `in_progress`, `blocked`, and `cancelled`.

Checking a task does not create a `growth_decisions` row and does not change the main next-step call to action.

`assignee_user_id` and `growth_artifact_id` stay null for these nine tasks.

Direction, materials, the AI proposal, and the host name remain outside this slice.
