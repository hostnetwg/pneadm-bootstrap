# Consultation Result — PNE Growth OS v0.1 Data Model

Date: 2026-09-29  
Status: accepted owner decision after external consultation  
Scope: documentation only, before migrations

## Accepted Direction

v0.1 stores the already verified Growth OS process with the smallest useful persistent model.

The first schema has 4 main tables:

- `growth_campaigns`,
- `growth_artifacts`,
- `growth_tasks`,
- `growth_decisions`.

## Explicitly Not In v0.1

The first schema does not include:

- `growth_topics`,
- `growth_experts`,
- `growth_campaign_topic`,
- `growth_campaign_expert`,
- `growth_artifact_versions`.

Topic remains part of campaign/concept data for now.

Expert is represented by an optional relation from campaign to existing `App\Models\Instructor`.

## Key Decisions

- `growth_decisions` remains a separate table because it is the canonical history of human approvals and rejections.
- `growth_artifacts` uses `key`, `type`, `schema_version` and `payload`.
- `type + schema_version` defines the allowed payload contract.
- `payload` is not a free-form dumping ground.
- `version` is only a current-version counter, not full version history.
- `published` is not an artifact status in v0.1. Publication belongs to a future Content / Distribution / Integration domain.
- Soft deletes are not a default rule for all v0.1 tables. Prefer explicit statuses such as `archived` and `cancelled`.

## Next Step

After owner acceptance of the updated documentation, prepare migrations for:

- `growth_campaigns`,
- `growth_artifacts`,
- `growth_tasks`,
- `growth_decisions`.

Do not rewrite the whole session prototype at once. Start with a vertical slice:

1. Campaign + Concept Artifact,
2. persistent project reload,
3. Decision,
4. Task,
5. further materials later.
