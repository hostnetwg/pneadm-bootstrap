# Consultation Result — PNE Growth OS Host Name

Date: 2026-09-29  
Status: accepted owner decision and implemented  
Scope: persist the free-text host entered for a webinar campaign

This file records the accepted result. It does not replace `CURRENT.md` or `DEC-023`. If they differ, code and `CURRENT.md` win.

## Accepted Direction

The host name is stored on `growth_campaigns.host_name` (nullable string, 120 characters). Creating a project writes it. The project screen can change it with „Zapisz prowadzącego”. After login the saved name returns.

`host_name` is not an instructor. `primary_instructor_id` stays empty unless a later decision links an existing `Instructor`.

## Explicitly Unchanged

The AI proposal stays in the browser session. Saving the host does not create a `growth_decisions` row and does not change the main next step. Operational tasks do not drive that step.

Campaigns created before this column have an empty `host_name` until the owner saves the name on the project screen.
