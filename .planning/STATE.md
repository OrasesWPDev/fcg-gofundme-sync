# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 4 Complete - Ready for Phase 5

## Current Position

Phase: 4 of 7 (Inbound Sync) - COMPLETE
Plan: 1 of 1 complete in current phase
Status: Phase 4 complete, all requirements verified
Last activity: 2026-01-26 — Inbound campaign sync (donation totals, status, progress)

Progress: [████████░░] 80%

## Performance Metrics

**Velocity:**
- Total plans completed: 9 (Phase 1: 3, Phase 2: 4, Phase 3: 1, Phase 4: 1)
- Average duration: N/A
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Status |
|-------|-------|--------|
| 01 - Configuration | 3/3 | Complete |
| 02 - Campaign Push Sync | 4/4 | Complete |
| 03 - Campaign Status Management | 1/1 | Complete |
| 04 - Inbound Sync | 1/1 | Complete |

**Recent Completions:**
- 04-01: Inbound campaign sync - donation totals, status, progress (2026-01-26)
- 03-01: Fix draft→unpublish bug + Campaign ID in meta box (2026-01-26)
- 02-04: E2E verification & deactivated campaign fix (2026-01-26)
- 02-03: Restore workflow & sync opt-out (b099b71, 41a4669)

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
- **Inbound sync uses set_syncing_flag() to prevent outbound sync loop** (2026-01-26)

### Pending Todos

- Optional: Add ACF field `disable_campaign_sync` to gofundme_settings field group (code handles it if present)
- Production: Enable Alternate Cron in WP Engine dashboard before go-live

### Blockers/Concerns

**RESOLVED - All Phase 4 issues:**
- Inbound sync implemented and verified

**Environment concerns:**
- WP Engine staging SSH timeout (2026-01-23) - connection intermittent, use rsync (not scp)

**Phase 5 concerns (from research):**
- API rate limits unknown — must load test to determine safe throttling
- 758 funds will timeout without proper batching (50 per batch recommended)

## Session Continuity

Last session: 2026-01-26 (Phase 4 COMPLETE)
Current: Phase 4 complete, ready for Phase 5

**Phase 4 Verification Results (2026-01-26):**

| Requirement | Check | Result |
|-------------|-------|--------|
| SYNC-01 | Donation totals fetched every 15 min | PASS |
| SYNC-02 | Campaign status stored in post meta | PASS |
| SYNC-03 | Goal progress calculated and stored | PASS |
| SYNC-04 | Inbound sync doesn't trigger outbound | PASS |

**New post meta keys added:**
- `_gofundme_donation_total` - Total gross donations
- `_gofundme_donor_count` - Number of unique donors
- `_gofundme_goal_progress` - Percentage toward goal
- `_gofundme_campaign_status` - active/unpublished/deactivated
- `_gofundme_last_inbound_sync` - Last inbound sync timestamp

**Plugin version:** 2.2.0 (deployed to staging)

Resume file: None
