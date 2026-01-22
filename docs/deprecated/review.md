# Deprecated Documentation

**Archived:** 2026-01-22
**Reason:** Superseded by `.planning/` GSD workflow

---

## Why These Files Are Deprecated

These documents were created during an intermediate campaign sync planning phase (Jan 14, 2026) before proper API research was completed. They contain:

1. **Incorrect API assumptions** — PRD-campaigns.md documents `POST /campaigns` for campaign creation, but this endpoint returns 403 Forbidden. Campaign creation MUST use `duplicateCampaign` from a template.

2. **Superseded by GSD workflow** — The project was re-initialized using the GSD (Get Shit Done) workflow on Jan 22, 2026, which created a proper research-backed roadmap in `.planning/`.

---

## Files in This Directory

| File | Original Purpose | Why Deprecated |
|------|------------------|----------------|
| `PRD-campaigns.md` | Campaign sync PRD | Wrong API info (POST /campaigns = 403) |
| `phase-C0-implementation-plan.md` | Fix designation sync | Work completed, merged to main |
| `phase-C1-implementation-plan.md` | Add campaign API methods | Work completed, merged to main |
| `phase-C2-implementation-plan.md` | Campaign push sync | Superseded by `.planning/ROADMAP.md` Phase 2 |
| `phase-C3-implementation-plan.md` | Campaign status management | Superseded by `.planning/ROADMAP.md` Phase 3 |
| `phase-C4-implementation-plan.md` | Bulk migration | Superseded by `.planning/ROADMAP.md` Phase 5 |
| `phase-C5-implementation-plan.md` | Inbound donation sync | Superseded by `.planning/ROADMAP.md` Phase 4 |

---

## What Was Salvaged

### Completed Work (C0, C1)

Phases C0 and C1 were **completed and merged** before deprecation:

- **C0** (`341dc63`): Fixed designation sync, added `wp fcg-sync push` command, synced 855 funds
- **C1** (`008aef9`): Added 5 campaign CRUD methods to API client, meta constants, bumped to v2.0.0

These implementation details are now documented in `docs/subagents/project-context.md` under "Phase History".

### Research Findings

Key API discoveries from this phase informed the new roadmap:

- POST /campaigns returns 403 (not a public endpoint)
- Must use `duplicateCampaign` from template campaign
- Campaign status lifecycle: active → unpublished → deactivated
- Reactivation requires two steps: reactivate → publish

These findings are documented in `.planning/research/SUMMARY.md`.

---

## Current Campaign Sync Planning

**Do not use these files for implementation.**

Campaign sync is now planned in `.planning/`:

```
.planning/
  PROJECT.md        # Project context and decisions
  REQUIREMENTS.md   # 24 v1 requirements with REQ-IDs
  ROADMAP.md        # 6-phase campaign sync roadmap
  STATE.md          # Current progress tracking
  research/         # API research (STACK, FEATURES, ARCHITECTURE, PITFALLS)
```

**To check current progress:** Run `/gsd:progress`

**To start Phase 1:** Run `/gsd:plan-phase 1`

---

## Historical Reference Only

These files are preserved for:

- Understanding the evolution of the project planning
- Referencing completed work (C0, C1 commit SHAs)
- Seeing what was originally planned vs what was actually implemented

**Do not modify these files.** They represent a historical snapshot.

---

*Archived by: Claude Code*
*Archive date: 2026-01-22*
