# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-22)

**Core value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.
**Current focus:** Phase 2 - Campaign Push Sync

## Current Position

Phase: 2 of 6 (Campaign Push Sync)
Plan: 2 of 4 complete in current phase
Status: Executing phase 02
Last activity: 2026-01-23 — Plan 02-02 complete (campaign creation duplication workflow)

Progress: [███░░░░░░░] 30%

## Performance Metrics

**Velocity:**
- Total plans completed: 2
- Average duration: ~4 min (02-02)
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Status |
|-------|-------|--------|
| 01 - Configuration | 2 | Complete |
| 02 - Campaign Push Sync | 2/4 | In Progress |

**Recent Completions:**
- 02-02: Campaign creation duplication workflow (b099b71)
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

### Pending Todos

- Run staging test for 02-02 when SSH connectivity is restored

### Blockers/Concerns

**Environment concerns:**
- WP Engine staging SSH timeout (2026-01-23) - may be temporary connectivity issue

**Phase 2 concerns (from research):**
- Must validate which campaign fields can be updated post-duplication with Classy contact
- Template campaign must be Studio type (not legacy Classy Mode)

**Phase 4 concerns (from research):**
- WP-Cron unreliable on cached sites — must use server cron in production

**Phase 5 concerns (from research):**
- API rate limits unknown — must load test to determine safe throttling
- 758 funds will timeout without proper batching (50 per batch recommended)

## Session Continuity

Last session: 2026-01-23 (plan 02-02 execution)
Stopped at: Plan 02-02 complete (local verification only, SSH timeout), ready for plan 02-03 execution
Resume file: None
