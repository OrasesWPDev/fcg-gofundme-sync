# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 2 Complete - Ready for Phase 3

## Current Position

Phase: 2 of 7 (Campaign Push Sync) - COMPLETE
Plan: 4 of 4 complete in current phase
Status: Phase 2 complete, all requirements verified
Last activity: 2026-01-26 — E2E verification complete, v2.1.3 deployed

Progress: [██████░░░░] 60%

## Performance Metrics

**Velocity:**
- Total plans completed: 7 (Phase 1: 3, Phase 2: 4)
- Average duration: N/A
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Status |
|-------|-------|--------|
| 01 - Configuration | 3/3 | Complete |
| 02 - Campaign Push Sync | 4/4 | Complete |

**Recent Completions:**
- 02-04: E2E verification & deactivated campaign fix (2026-01-26)
- 02-03: Restore workflow & sync opt-out (b099b71, 41a4669)
- 02-02: Campaign creation duplication workflow (b18c9ad)
- 02-01: Campaign lifecycle API methods (e38e439)

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

### Pending Todos

- Optional: Add ACF field `disable_campaign_sync` to gofundme_settings field group (code handles it if present)
- Commit v2.1.3 changes to git
- Begin Phase 3 or Phase 4 planning

### Blockers/Concerns

**RESOLVED - All Phase 2 issues fixed:**
- ~~API endpoint paths~~ - Fixed in v2.1.1 (removed /actions/ prefix)
- ~~Deactivated campaign not reactivated on restore~~ - Fixed in v2.1.3 (added ensure_campaign_active())

**Environment concerns:**
- WP Engine staging SSH timeout (2026-01-23) - connection intermittent, use zip upload as fallback

**Phase 4 concerns (from research):**
- WP-Cron unreliable on cached sites — must use server cron in production

**Phase 5 concerns (from research):**
- API rate limits unknown — must load test to determine safe throttling
- 758 funds will timeout without proper batching (50 per batch recommended)

## Session Continuity

Last session: 2026-01-26 (plan 02-04 E2E testing COMPLETE)
Current: Phase 2 complete, ready for next phase

**Phase 2 E2E Test Results (2026-01-26):**

| Test | Fund | Campaign | Result |
|------|------|----------|--------|
| Create | 13771 | 763426 | PASS - Campaign created, status=active |
| Update | 13771 | 763426 | PASS - Name updated correctly |
| Trash | 13771 | 763426 | PASS - Status changed to deactivated |
| Restore | 13771 | 763426 | PASS - Status changed back to active |

**Test artifacts in sandbox:**
- Campaign 763099: Updated_E2E_Test_Fund (recovered from deactivated)
- Campaign 763426: Updated_E2E_Fresh_Test (fresh E2E test)
- Campaign 763092: Theme_Test_Fund
- Campaign 763078: E2E_Test_Fund
- Campaign 762968: FCG Template Source
- Campaign 762966: FCG Template Campaign

**Plugin version:** 2.1.3 (deployed to staging)

Resume file: None
