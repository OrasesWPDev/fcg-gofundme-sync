# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 3 Complete - Ready for Phase 4

## Current Position

Phase: 3 of 7 (Campaign Status Management) - COMPLETE
Plan: 1 of 1 complete in current phase
Status: Phase 3 complete, all requirements verified
Last activity: 2026-01-26 — Draft→unpublish fix deployed, Campaign ID added to meta box

Progress: [███████░░░] 70%

## Performance Metrics

**Velocity:**
- Total plans completed: 8 (Phase 1: 3, Phase 2: 4, Phase 3: 1)
- Average duration: N/A
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Status |
|-------|-------|--------|
| 01 - Configuration | 3/3 | Complete |
| 02 - Campaign Push Sync | 4/4 | Complete |
| 03 - Campaign Status Management | 1/1 | Complete |

**Recent Completions:**
- 03-01: Fix draft→unpublish bug + Campaign ID in meta box (2026-01-26)
- 02-04: E2E verification & deactivated campaign fix (2026-01-26)
- 02-03: Restore workflow & sync opt-out (b099b71, 41a4669)
- 02-02: Campaign creation duplication workflow (b18c9ad)

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Campaign duplication approach: Must use duplicateCampaign endpoint (POST /campaigns returns 403)
- 1:1 fund-to-campaign relationship: Simplest model, matches business need
- WordPress wins on conflicts: Existing pattern, client is source of truth
- Use raw_goal (string) for duplication overrides, not goal (number)
- Update overview in separate API call (not available in duplication overrides)
- 60-second transient lock for race condition prevention
- Reactivate returns campaign to unpublished status, publish required afterward
- Campaign sync opt-out via ACF disable_campaign_sync field
- **API endpoints use /campaigns/{id}/action NOT /campaigns/{id}/actions/action** (fixed 2026-01-23)
- **Deactivated campaigns require reactivate→publish→update sequence** (fixed 2026-01-26)
- **Draft status calls unpublish_campaign(), not deactivate_campaign()** (fixed 2026-01-26)
- **Campaign ID displayed prominently in admin meta box** (user feedback, 2026-01-26)

### Pending Todos

- Optional: Add ACF field `disable_campaign_sync` to gofundme_settings field group (code handles it if present)
- Begin Phase 4 planning (Inbound Sync)

### Blockers/Concerns

**RESOLVED - All Phase 3 issues fixed:**
- ~~Draft status calling deactivate instead of unpublish~~ - Fixed in v2.1.4
- ~~Campaign ID not visible in admin~~ - Added in v2.1.5

**Environment concerns:**
- WP Engine staging SSH timeout (2026-01-23) - connection intermittent, use rsync (not scp)

**Phase 4 concerns (from research):**
- WP-Cron unreliable on cached sites — must use server cron in production

**Phase 5 concerns (from research):**
- API rate limits unknown — must load test to determine safe throttling
- 758 funds will timeout without proper batching (50 per batch recommended)

## Session Continuity

Last session: 2026-01-26 (Phase 3 COMPLETE)
Current: Phase 3 complete, ready for Phase 4

**Phase 3 E2E Test Results (2026-01-26):**

| Test | Action | Expected | Result |
|------|--------|----------|--------|
| STAT-01 | draft → unpublished | unpublished | PASS |
| STAT-02 | publish → active | active | PASS |
| STAT-03 | full cycle | active→unpublished→active | PASS |
| Trash regression | trash → deactivated | deactivated | PASS |

**Test artifacts in sandbox:**
- Campaign 763426: Updated_E2E_Fresh_Test (used for all status tests)
- Fund 13771: Test fund for status transitions

**Plugin version:** 2.1.5 (deployed to staging)

Resume file: None
