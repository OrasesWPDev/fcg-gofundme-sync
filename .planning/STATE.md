# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 7 (Frontend Embed) — Phase 5 blocked, skipping ahead

## Current Position

Phase: 5 of 7 (Bulk Migration) - ⚠️ BLOCKED
Plan: 1 plan ready but cannot execute
Status: Awaiting Classy support response on Studio campaign API limitation
Last activity: 2026-01-26 — Phase 5 blocked, email sent to Classy support

Progress: [████████░░] 80% (blocked on external dependency)

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
| 05 - Bulk Migration | 0/1 | ⚠️ Blocked |

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

**⚠️ PHASE 5 BLOCKED (2026-01-26):**
Classy's public API `duplicateCampaign` and `publishCampaign` endpoints do not support Studio campaign types. API-created campaigns appear "Published" in the dashboard but are broken:
- Design tab: "Oops! Something went wrong"
- Settings tab: "We can't seem to find that page"
- Confirmed by Classy support (Luke Dringoli, Jon Bierma)

**Action taken:** Email sent to Classy support (2026-01-26) asking for recommended path:
1. Classy runs bulk duplications using internal Studio endpoints
2. Provide fund list for Classy to batch-create
3. Alternative campaign type that works with public API

**Proceeding with:** Phase 6 (Admin UI) or Phase 7 (Frontend Embed) while waiting

**RESOLVED - All Phase 4 issues:**
- Inbound sync implemented and verified

**Environment concerns:**
- WP Engine staging SSH timeout (2026-01-23) - connection intermittent, use rsync (not scp)

## Session Continuity

Last session: 2026-01-26 (Phase 5 BLOCKED → Phase 7 queued)
Current: Phase 7 ready to plan

**Next session:** Run `/gsd:plan-phase 7` to create Phase 7 plans

**Context for Phase 7:**
- Replace legacy donation form with Classy embed on fund pages
- Can develop/test using template campaign (762966) which works
- Requirements: EMBD-01, EMBD-02, EMBD-03, EMBD-04

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
